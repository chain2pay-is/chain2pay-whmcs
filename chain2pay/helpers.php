<?php
/**
 * Chain2Pay V2 — Shared helpers for WHMCS payment gateway modules.
 *
 * Every gateway and callback file includes this once to access:
 *   - chain2pay_get_api_key()
 *   - chain2pay_is_sandbox()
 *   - chain2pay_api_base()
 *   - chain2pay_create_payment($params, $provider, $callbackUrl)
 *   - chain2pay_verify_signature($invoiceId, $sig)
 *   - chain2pay_process_callback()
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

define('CHAIN2PAY_API_BASE', 'https://chain2pay.is');
define('CHAIN2PAY_VERSION', '2.3.0');

/**
 * Read the API key for a specific Chain2Pay gateway. Each provider gateway
 * (chain2pay_paypal, chain2pay_cryptocom, ...) now stores its own
 * `chain2pay_api_key` in WHMCS. For backwards compatibility with merchants
 * upgrading from V2.1.1 - where the key only lived on chain2pay_multihosted -
 * we silently fall back to the multihosted key when the per-provider field
 * is empty. The same Chain2Pay key works for every provider, so the fallback
 * is always safe.
 */
function chain2pay_get_api_key($gatewaySlug = 'chain2pay_multihosted')
{
    $vars = getGatewayVariables($gatewaySlug);
    $key  = isset($vars['chain2pay_api_key']) ? trim($vars['chain2pay_api_key']) : '';
    if ($key === '' && $gatewaySlug !== 'chain2pay_multihosted') {
        $main = getGatewayVariables('chain2pay_multihosted');
        $key  = isset($main['chain2pay_api_key']) ? trim($main['chain2pay_api_key']) : '';
    }
    return $key;
}

function chain2pay_is_sandbox($gatewaySlug = 'chain2pay_multihosted')
{
    return strpos(chain2pay_get_api_key($gatewaySlug), 'c2p_sandbox_') === 0;
}

/**
 * Derive the HMAC secret from the per-provider API key (replaces V1
 * wallet-based salt). Each provider gateway has its own gateway slug
 * (chain2pay_<provider>), so the secret is naturally scoped per gateway.
 */
function chain2pay_hmac_secret($provider)
{
    return hash('sha256', 'chain2pay_v2_' . $provider . '_' . chain2pay_get_api_key('chain2pay_' . $provider));
}

function chain2pay_sign($invoiceId, $provider)
{
    return hash_hmac('sha256', (string)$invoiceId, chain2pay_hmac_secret($provider));
}

function chain2pay_verify_sig($invoiceId, $sig, $provider)
{
    return hash_equals(chain2pay_sign($invoiceId, $provider), (string)$sig);
}

/**
 * Create a payment via Chain2Pay API V2.
 *
 * Resolves the API key for the given provider gateway, then delegates to
 * chain2pay_create_payment_ex(). Use this for the standard per-provider
 * gateways (swapped, transfi, simplex, ...). The "custom" gateway, which
 * routes a merchant-typed slug, calls chain2pay_create_payment_ex() directly
 * with its own key.
 *
 * @return array{success: bool, checkout_url?: string, order_id?: string, error?: string}
 */
function chain2pay_create_payment($amount, $currency, $provider, $customerEmail, $callbackUrl, $invoiceId)
{
    $apiKey = chain2pay_get_api_key('chain2pay_' . $provider);
    if (empty($apiKey)) {
        return ['success' => false, 'error' => 'Chain2Pay API key not configured for the ' . $provider . ' gateway. Go to Setup > Payment Gateways > Chain2Pay ' . ucfirst($provider) . ' > Manage to enter your key.'];
    }
    return chain2pay_create_payment_ex($amount, $currency, $provider, $customerEmail, $callbackUrl, $invoiceId, $apiKey);
}

/**
 * Create a payment via Chain2Pay API V2 with an explicitly supplied API key.
 *
 * Robustness: transient transport failures (the WHMCS host briefly can't
 * reach the gateway) and gateway-side 5xx (e.g. during a deploy/restart)
 * were occasionally bubbling up to buyers as a hard "connection failed".
 * We now retry up to 3 times with a short linear backoff before giving up.
 * 2xx and 4xx responses are final and returned immediately.
 *
 * @param string $apiKey Chain2Pay API key (Bearer).
 * @return array{success: bool, checkout_url?: string, order_id?: string, error?: string}
 */
function chain2pay_create_payment_ex($amount, $currency, $provider, $customerEmail, $callbackUrl, $invoiceId, $apiKey)
{
    if (empty($apiKey)) {
        return ['success' => false, 'error' => 'Chain2Pay API key not configured.'];
    }
    if (empty($provider)) {
        return ['success' => false, 'error' => 'No payment provider configured.'];
    }

    $payload = json_encode([
        'amount'         => (float)$amount,
        'currency'       => strtoupper($currency),
        'provider'       => $provider,
        'customer_email'  => $customerEmail,
        'callback_url'   => $callbackUrl,
        'metadata'       => ['whmcs_invoice_id' => (string)$invoiceId],
    ]);

    $attempts = 3;
    $response = false;
    $httpCode = 0;
    $curlErr  = '';

    for ($i = 1; $i <= $attempts; $i++) {
        $ch = curl_init(CHAIN2PAY_API_BASE . '/api/v2/payments');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        // Final on a hard 2xx/4xx. Only a transport error or a 5xx is worth
        // retrying — those are the transient cases that used to surface as
        // "connection failed" to the buyer.
        if (!$curlErr && $httpCode > 0 && $httpCode < 500) {
            break;
        }
        if ($i < $attempts) {
            usleep(500000 * $i);
        }
    }

    if ($curlErr) {
        return ['success' => false, 'error' => 'Connection failed: ' . $curlErr];
    }

    $data = json_decode($response, true);

    if ($httpCode !== 200 && $httpCode !== 201) {
        $msg = 'API error (HTTP ' . $httpCode . ')';
        if (isset($data['error']['message'])) {
            $msg .= ': ' . $data['error']['message'];
        } elseif (isset($data['error'])) {
            $msg .= ': ' . (is_string($data['error']) ? $data['error'] : json_encode($data['error']));
        }
        return ['success' => false, 'error' => $msg];
    }

    $checkoutUrl = isset($data['checkout_url']) ? $data['checkout_url'] : null;
    $orderId     = isset($data['id']) ? $data['id'] : '';

    if (empty($checkoutUrl)) {
        return ['success' => false, 'error' => 'Invalid API response — missing checkout URL'];
    }

    return ['success' => true, 'checkout_url' => $checkoutUrl, 'order_id' => $orderId];
}

/**
 * Generic callback processor (called from each callback file).
 */
function chain2pay_process_callback($provider)
{
    $invoiceId = isset($_GET['invoice_id']) ? $_GET['invoice_id'] : null;
    $valueCoin = isset($_GET['value_coin']) ? $_GET['value_coin'] : null;
    $txidOut   = isset($_GET['txid_out'])   ? $_GET['txid_out']   : null;
    $sig       = isset($_GET['sig'])        ? $_GET['sig']        : '';

    if (empty($invoiceId) || empty($valueCoin)) {
        die('Invalid request parameters');
    }

    if (!chain2pay_verify_sig($invoiceId, $sig, $provider)) {
        die('Invalid callback signature');
    }

    $invoice = localAPI('GetInvoice', ['invoiceid' => $invoiceId]);
    if ($invoice['result'] !== 'success') {
        die('Invoice not found');
    }

    if ($invoice['status'] === 'Paid') {
        header('HTTP/1.1 200 OK');
        header('Content-Type: text/plain');
        echo '*ok*';
        exit;
    }

    // Compare in the invoice's native currency. V2 always echoes
    // value_coin in the merchant's original currency (the WooCommerce
    // 60% threshold check relies on the same invariant), so no fiat
    // conversion is needed here.
    $invoiceTotal = (float)$invoice['total'];
    $threshold = 0.60 * $invoiceTotal;
    $received  = (float)$valueCoin;

    if ($received < $threshold) {
        die('Payment below threshold');
    }

    $transactionId = !empty($txidOut) ? $txidOut : $provider . '_payment_' . time();

    $result = localAPI('AddInvoicePayment', [
        'invoiceid' => $invoiceId,
        'transid'   => $transactionId,
        'date'      => date('Y-m-d H:i:s'),
    ]);

    if ($result['result'] === 'success') {
        header('HTTP/1.1 200 OK');
        header('Content-Type: text/plain');
        echo '*ok*';
        exit;
    }

    die('Payment recording failed');
}
