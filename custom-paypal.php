<?php
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

function custompaypal_config()
{
    return [
        'FriendlyName' => [
            'Type' => 'System',
            'Value' => 'Custom PayPal Gateway'
        ],
        'paypalEmail' => [
            'FriendlyName' => 'PayPal Email',
            'Type' => 'text',
            'Size' => '50',
            'Description' => 'Your PayPal account email'
        ],
        'clientId' => [
            'FriendlyName' => 'Client ID',
            'Type' => 'text',
            'Size' => '50'
        ],
        'clientSecret' => [
            'FriendlyName' => 'Client Secret',
            'Type' => 'password',
            'Size' => '50'
        ],
        'sandboxMode' => [
            'FriendlyName' => 'Sandbox Mode',
            'Type' => 'yesno',
            'Description' => 'Enable for testing'
        ]
    ];
}

function custompaypal_link($params)
{
    try {
        // Configuration parameters
        $paypalEmail = $params['paypalEmail'];
        $invoiceId = $params['invoiceid'];
        $amount = number_format($params['amount'], 2, '.', '');
        $currency = $params['currency'];
        $clientEmail = $params['clientdetails']['email'];
        $clientId = $params['clientId'];
        $clientSecret = $params['clientSecret'];
        $sandboxMode = $params['sandboxMode'];

        // API endpoints
        $paypalApiUrl = $sandboxMode 
            ? 'https://api.sandbox.paypal.com' 
            : 'https://api.paypal.com';

        // Create invoice request payload
        $invoiceData = [
            'detail' => [
                'invoice_number' => (string) $invoiceId,
                'currency_code' => $currency,
                'invoice_date' => date('Y-m-d'),
                'payment_term' => [
                    'term_type' => 'DUE_ON_RECEIPT'
                ]
            ],
            'invoicer' => [
                'email_address' => $paypalEmail
            ],
            'primary_recipients' => [
                [
                    'billing_info' => [
                        'email_address' => $clientEmail
                    ]
                ]
            ],
            'items' => [
                [
                    'name' => 'Invoice #' . $invoiceId,
                    'quantity' => '1',
                    'unit_amount' => [
                        'currency_code' => $currency,
                        'value' => $amount
                    ]
                ]
            ],
            'configuration' => [
                'allow_tip' => false,
                'tax_calculated_after_discount' => false
            ]
        ];

        // Create PayPal invoice
        $invoice = createPayPalInvoice($invoiceData, $clientId, $clientSecret, $paypalApiUrl);

        if (!$invoice || !isset($invoice['id'])) {
            throw new Exception('Failed to create PayPal invoice');
        }

        // Get invoice URL
        $invoiceUrl = null;
        foreach ($invoice['links'] as $link) {
            if ($link['rel'] === 'self' && $link['method'] === 'GET') {
                $invoiceUrl = $link['href'];
                break;
            }
        }

        if (!$invoiceUrl) {
            throw new Exception('Invoice URL not found in PayPal response');
        }

        // Send invoice to client
        if (!sendPayPalInvoice($invoice['id'], $clientId, $clientSecret, $paypalApiUrl)) {
            throw new Exception('Failed to send invoice to client');
        }

        // Store invoice details
        Capsule::table('tblcustompaypal_invoices')->updateOrInsert(
            ['invoice_id' => $invoiceId],
            [
                'paypal_invoice_id' => $invoice['id'],
                'paypal_invoice_url' => $invoiceUrl,
                'created_at' => date('Y-m-d H:i:s')
            ]
        );

        // Redirect to PayPal invoice
        if (!headers_sent()) {
            header("Location: " . $invoiceUrl);
            exit;
        }

        return 'Please click <a href="' . $invoiceUrl . '" target="_blank">here</a> to pay with PayPal.';

    } catch (Exception $e) {
        logActivity("Custom PayPal Error: " . $e->getMessage());
        return 'Payment processing error. Please contact support.';
    }
}

function createPayPalInvoice($invoiceData, $clientId, $clientSecret, $baseUrl)
{
    $accessToken = getPayPalAccessToken($clientId, $clientSecret, $baseUrl);
    if (!$accessToken) return false;

    $ch = curl_init($baseUrl . '/v2/invoicing/invoices');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken
        ],
        CURLOPT_POSTFIELDS => json_encode($invoiceData)
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $responseData = json_decode($response, true);

    // Handle duplicate invoice numbers
    if ($httpCode === 422 && isset($responseData['details'][0]['issue']) 
        && $responseData['details'][0]['issue'] === 'DUPLICATE_INVOICE_NUMBER') {
        $invoiceData['detail']['invoice_number'] .= '-' . time();
        return createPayPalInvoice($invoiceData, $clientId, $clientSecret, $baseUrl);
    }

    if ($httpCode !== 201) {
        logActivity("PayPal Invoice Creation Failed: " . print_r($responseData, true));
        return false;
    }

    return $responseData;
}

function sendPayPalInvoice($invoiceId, $clientId, $clientSecret, $baseUrl)
{
    $accessToken = getPayPalAccessToken($clientId, $clientSecret, $baseUrl);
    if (!$accessToken) return false;

    $ch = curl_init($baseUrl . "/v2/invoicing/invoices/$invoiceId/send");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'send_to_invoicer' => true
        ])
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 202) {
        logActivity("PayPal Invoice Send Failed: " . $response);
        return false;
    }

    return true;
}

function getPayPalAccessToken($clientId, $clientSecret, $baseUrl)
{
    $ch = curl_init($baseUrl . '/v1/oauth2/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_USERPWD => "$clientId:$clientSecret",
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded'
        ]
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        logActivity("PayPal Auth Failed: " . $response);
        return false;
    }

    $data = json_decode($response, true);
    return $data['access_token'] ?? false;
}
