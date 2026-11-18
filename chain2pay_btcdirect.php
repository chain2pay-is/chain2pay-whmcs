<?php
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once __DIR__ . '/chain2pay/helpers.php';

function chain2pay_btcdirect_MetaData()
{
    return array(
        'DisplayName' => 'Pay with BTC Direct',
        'DisableLocalCreditCardInput' => true,
        'APIVersion' => '2.0',
    );
}

function chain2pay_btcdirect_config()
{
    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Pay with BTC Direct',
        ),
        'description' => array(
            'FriendlyName' => 'Description',
            'Type' => 'textarea',
            'Rows' => '3',
            'Cols' => '25',
            'Default' => 'Pay with card or Apple Pay via BTC Direct',
            'Description' => 'The description shown to customers during checkout.',
        ),
        'chain2pay_api_key' => array(
            'FriendlyName' => 'Chain2Pay API Key',
            'Type'        => 'text',
            'Description' => 'Enter your Chain2Pay API key (c2p_sandbox_... or c2p_live_...). Same key works across all Chain2Pay gateways - paste it once per provider you activate.',
        ),
    );
}

function chain2pay_btcdirect_link($params)
{
    $amount    = $params['amount'];
    $invoiceId = $params['invoiceid'];
    $email     = $params['clientdetails']['email'];
    $systemUrl = rtrim($params['systemurl'], '/');
    $currency  = $params['currency'];

    if (floatval($amount) < 33) {
        return 'Error: Minimum payment amount is $33 USD. Your invoice total is ' . $amount . ' ' . $currency;
    }

    $sig = chain2pay_sign($invoiceId, 'btcdirect');
    $callbackUrl = $systemUrl . '/modules/gateways/callback/chain2pay_btcdirect.php'
        . '?invoice_id=' . urlencode($invoiceId)
        . '&sig=' . urlencode($sig);

    $result = chain2pay_create_payment($amount, $currency, 'btcdirect', $email, $callbackUrl, $invoiceId);

    if (!$result['success']) {
        return 'Error: ' . $result['error'];
    }

    $invoice = localAPI('GetInvoice', array('invoiceid' => $invoiceId), null);
    $invoice['notes'] = 'Chain2Pay Order: ' . $result['order_id'];
    localAPI('UpdateInvoice', $invoice);

    return '<a href="' . htmlspecialchars($result['checkout_url'], ENT_QUOTES) . '" class="btn btn-primary" rel="noreferrer">'
        . $params['langpaynow'] . '</a>';
}

function chain2pay_btcdirect_activate()
{
    return array('status' => 'success', 'description' => 'Pay with BTC Direct gateway activated.');
}

function chain2pay_btcdirect_deactivate()
{
    return array('status' => 'success', 'description' => 'Pay with BTC Direct gateway deactivated.');
}

function chain2pay_btcdirect_upgrade($vars) {}
function chain2pay_btcdirect_output($vars) {}
function chain2pay_btcdirect_error($vars) {}
