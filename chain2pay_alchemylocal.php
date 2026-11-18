<?php
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once __DIR__ . '/chain2pay/helpers.php';

function chain2pay_alchemylocal_MetaData()
{
    return array(
        'DisplayName' => 'Local payment methods',
        'DisableLocalCreditCardInput' => true,
        'APIVersion' => '2.0',
    );
}

function chain2pay_alchemylocal_config()
{
    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Local payment methods',
        ),
        'description' => array(
            'FriendlyName' => 'Description',
            'Type' => 'textarea',
            'Rows' => '3',
            'Cols' => '25',
            'Default' => 'Pay with local payment methods via Alchemy Pay (Local)',
            'Description' => 'The description shown to customers during checkout.',
        ),
        'chain2pay_api_key' => array(
            'FriendlyName' => 'Chain2Pay API Key',
            'Type'        => 'text',
            'Description' => 'Enter your Chain2Pay API key (c2p_sandbox_... or c2p_live_...). Same key works across all Chain2Pay gateways - paste it once per provider you activate.',
        ),
    );
}

function chain2pay_alchemylocal_link($params)
{
    $amount    = $params['amount'];
    $invoiceId = $params['invoiceid'];
    $email     = $params['clientdetails']['email'];
    $systemUrl = rtrim($params['systemurl'], '/');
    $currency  = $params['currency'];

    if (floatval($amount) < 15) {
        return 'Error: Minimum payment amount is $15 USD. Your invoice total is ' . $amount . ' ' . $currency;
    }

    $sig = chain2pay_sign($invoiceId, 'alchemylocal');
    $callbackUrl = $systemUrl . '/modules/gateways/callback/chain2pay_alchemylocal.php'
        . '?invoice_id=' . urlencode($invoiceId)
        . '&sig=' . urlencode($sig);

    $result = chain2pay_create_payment($amount, $currency, 'alchemylocal', $email, $callbackUrl, $invoiceId);

    if (!$result['success']) {
        return 'Error: ' . $result['error'];
    }

    $invoice = localAPI('GetInvoice', array('invoiceid' => $invoiceId), null);
    $invoice['notes'] = 'Chain2Pay Order: ' . $result['order_id'];
    localAPI('UpdateInvoice', $invoice);

    return '<a href="' . htmlspecialchars($result['checkout_url'], ENT_QUOTES) . '" class="btn btn-primary" rel="noreferrer">'
        . $params['langpaynow'] . '</a>';
}

function chain2pay_alchemylocal_activate()
{
    return array('status' => 'success', 'description' => 'Local payment methods gateway activated.');
}

function chain2pay_alchemylocal_deactivate()
{
    return array('status' => 'success', 'description' => 'Local payment methods gateway deactivated.');
}

function chain2pay_alchemylocal_upgrade($vars) {}
function chain2pay_alchemylocal_output($vars) {}
function chain2pay_alchemylocal_error($vars) {}
