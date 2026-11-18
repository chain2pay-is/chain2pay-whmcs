<?php
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once __DIR__ . '/chain2pay/helpers.php';

function chain2pay_blockchain_MetaData()
{
    return array(
        'DisplayName' => 'Pay with Blockchain.com',
        'DisableLocalCreditCardInput' => true,
        'APIVersion' => '2.0',
    );
}

function chain2pay_blockchain_config()
{
    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Pay with Blockchain.com',
        ),
        'description' => array(
            'FriendlyName' => 'Description',
            'Type' => 'textarea',
            'Rows' => '3',
            'Cols' => '25',
            'Default' => 'Pay via Blockchain.com',
            'Description' => 'The description shown to customers during checkout.',
        ),
        'chain2pay_api_key' => array(
            'FriendlyName' => 'Chain2Pay API Key',
            'Type'        => 'text',
            'Description' => 'Enter your Chain2Pay API key (c2p_sandbox_... or c2p_live_...). Same key works across all Chain2Pay gateways - paste it once per provider you activate.',
        ),
    );
}

function chain2pay_blockchain_link($params)
{
    $amount    = $params['amount'];
    $invoiceId = $params['invoiceid'];
    $email     = $params['clientdetails']['email'];
    $systemUrl = rtrim($params['systemurl'], '/');
    $currency  = $params['currency'];

    if (floatval($amount) < 15) {
        return 'Error: Minimum payment amount is $15 USD. Your invoice total is ' . $amount . ' ' . $currency;
    }

    $sig = chain2pay_sign($invoiceId, 'blockchain');
    $callbackUrl = $systemUrl . '/modules/gateways/callback/chain2pay_blockchain.php'
        . '?invoice_id=' . urlencode($invoiceId)
        . '&sig=' . urlencode($sig);

    $result = chain2pay_create_payment($amount, $currency, 'blockchain', $email, $callbackUrl, $invoiceId);

    if (!$result['success']) {
        return 'Error: ' . $result['error'];
    }

    $invoice = localAPI('GetInvoice', array('invoiceid' => $invoiceId), null);
    $invoice['notes'] = 'Chain2Pay Order: ' . $result['order_id'];
    localAPI('UpdateInvoice', $invoice);

    return '<a href="' . htmlspecialchars($result['checkout_url'], ENT_QUOTES) . '" class="btn btn-primary" rel="noreferrer">'
        . $params['langpaynow'] . '</a>';
}

function chain2pay_blockchain_activate()
{
    return array('status' => 'success', 'description' => 'Pay with Blockchain.com gateway activated.');
}

function chain2pay_blockchain_deactivate()
{
    return array('status' => 'success', 'description' => 'Pay with Blockchain.com gateway deactivated.');
}

function chain2pay_blockchain_upgrade($vars) {}
function chain2pay_blockchain_output($vars) {}
function chain2pay_blockchain_error($vars) {}
