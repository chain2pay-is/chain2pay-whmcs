<?php
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once __DIR__ . '/chain2pay/helpers.php';

function chain2pay_multihosted_MetaData()
{
    return array(
        'DisplayName' => 'Card Payment',
        'DisableLocalCreditCardInput' => true,
        'APIVersion' => '2.0',
    );
}

function chain2pay_multihosted_config()
{
    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Card Payment',
        ),
        'chain2pay_api_key' => array(
            'FriendlyName' => 'Chain2Pay API Key',
            'Type' => 'text',
            'Description' => 'Enter your Chain2Pay API key (c2p_sandbox_... or c2p_live_...). This key is shared by all Chain2Pay gateways.',
        ),
        'description' => array(
            'FriendlyName' => 'Description',
            'Type' => 'textarea',
            'Rows' => '3',
            'Cols' => '25',
            'Default' => 'Pay with Visa, Mastercard, Apple Pay and more via Chain2Pay',
            'Description' => 'The description shown to customers during checkout.',
        ),
    );
}

function chain2pay_multihosted_link($params)
{
    $amount    = $params['amount'];
    $invoiceId = $params['invoiceid'];
    $email     = $params['clientdetails']['email'];
    $systemUrl = rtrim($params['systemurl'], '/');
    $currency  = $params['currency'];

    if (floatval($amount) < 6) {
        return 'Error: Minimum payment amount is $6 USD. Your invoice total is ' . $amount . ' ' . $currency;
    }

    $sig = chain2pay_sign($invoiceId, 'multihosted');
    $callbackUrl = $systemUrl . '/modules/gateways/callback/chain2pay_multihosted.php'
        . '?invoice_id=' . urlencode($invoiceId)
        . '&sig=' . urlencode($sig);

    $result = chain2pay_create_payment($amount, $currency, 'multihosted', $email, $callbackUrl, $invoiceId);

    if (!$result['success']) {
        return 'Error: ' . $result['error'];
    }

    $invoice = localAPI('GetInvoice', array('invoiceid' => $invoiceId), null);
    $invoice['notes'] = 'Chain2Pay Order: ' . $result['order_id'];
    localAPI('UpdateInvoice', $invoice);

    return '<a href="' . htmlspecialchars($result['checkout_url'], ENT_QUOTES) . '" class="btn btn-primary" rel="noreferrer">'
        . $params['langpaynow'] . '</a>';
}

function chain2pay_multihosted_activate()
{
    return array('status' => 'success', 'description' => 'Card Payment gateway activated.');
}

function chain2pay_multihosted_deactivate()
{
    return array('status' => 'success', 'description' => 'Card Payment gateway deactivated.');
}

function chain2pay_multihosted_upgrade($vars) {}
function chain2pay_multihosted_output($vars) {}
function chain2pay_multihosted_error($vars) {}
