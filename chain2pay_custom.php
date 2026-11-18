<?php
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once __DIR__ . '/chain2pay/helpers.php';

function chain2pay_custom_MetaData()
{
    return array(
        'DisplayName' => 'Pay by Card (Chain2Pay)',
        'DisableLocalCreditCardInput' => true,
        'APIVersion' => '2.0',
    );
}

function chain2pay_custom_config()
{
    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Chain2Pay Custom Provider',
        ),
        'description' => array(
            'FriendlyName' => 'Description',
            'Type' => 'textarea',
            'Rows' => '3',
            'Cols' => '25',
            'Default' => 'Pay securely with your card',
            'Description' => 'The description shown to customers during checkout.',
        ),
        'provider' => array(
            'FriendlyName' => 'Provider slug',
            'Type'        => 'text',
            'Description' => 'The Chain2Pay provider this gateway routes to, all lowercase (e.g. swapped, simplex, transfi, finchpay, kryptonim, moonpay). Whatever you type here is sent to the Chain2Pay API as the provider.',
        ),
        'chain2pay_api_key' => array(
            'FriendlyName' => 'Chain2Pay API Key',
            'Type'        => 'text',
            'Description' => 'Enter your Chain2Pay API key (c2p_sandbox_... or c2p_live_...). Same key works across all Chain2Pay gateways - paste it once per provider you activate.',
        ),
    );
}

function chain2pay_custom_link($params)
{
    $amount    = $params['amount'];
    $invoiceId = $params['invoiceid'];
    $email     = $params['clientdetails']['email'];
    $systemUrl = rtrim($params['systemurl'], '/');
    $currency  = $params['currency'];

    $providerSlug = strtolower(trim(isset($params['provider']) ? $params['provider'] : ''));
    if ($providerSlug === '') {
        return 'Error: No payment provider configured. Set the "Provider slug" field under Setup > Payment Gateways > Chain2Pay Custom Provider.';
    }

    if (floatval($amount) < 5) {
        return 'Error: Minimum payment amount is $5 USD. Your invoice total is ' . $amount . ' ' . $currency;
    }

    $apiKey = chain2pay_get_api_key('chain2pay_custom');
    if (empty($apiKey)) {
        return 'Error: Chain2Pay API key not configured for the Custom Provider gateway.';
    }

    $sig = chain2pay_sign($invoiceId, 'custom');
    $callbackUrl = $systemUrl . '/modules/gateways/callback/chain2pay_custom.php'
        . '?invoice_id=' . urlencode($invoiceId)
        . '&sig=' . urlencode($sig);

    $result = chain2pay_create_payment_ex($amount, $currency, $providerSlug, $email, $callbackUrl, $invoiceId, $apiKey);

    if (!$result['success']) {
        return 'Error: ' . $result['error'];
    }

    $invoice = localAPI('GetInvoice', array('invoiceid' => $invoiceId), null);
    $invoice['notes'] = 'Chain2Pay Order: ' . $result['order_id'];
    localAPI('UpdateInvoice', $invoice);

    return '<a href="' . htmlspecialchars($result['checkout_url'], ENT_QUOTES) . '" class="btn btn-primary" rel="noreferrer">'
        . $params['langpaynow'] . '</a>';
}

function chain2pay_custom_activate()
{
    return array('status' => 'success', 'description' => 'Chain2Pay Custom Provider gateway activated.');
}

function chain2pay_custom_deactivate()
{
    return array('status' => 'success', 'description' => 'Chain2Pay Custom Provider gateway deactivated.');
}

function chain2pay_custom_upgrade($vars) {}
function chain2pay_custom_output($vars) {}
function chain2pay_custom_error($vars) {}
