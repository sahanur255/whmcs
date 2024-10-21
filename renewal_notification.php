<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

add_hook('InvoiceCreationPreEmail', 1, function($vars) {
    // Fetch invoice details
    $invoiceId = $vars['invoiceid'];
    $invoice = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();

    // Check if the invoice has any line items (any product)
    $invoiceItems = Capsule::table('tblinvoiceitems')
                    ->where('invoiceid', $invoiceId)
                    ->get();

    if (count($invoiceItems) > 0) {
        // Email content
        $adminEmail = 'support@webplanet.studio'; // Change this to the admin's email
        $message = "A new renewal invoice has been generated for client #" . $invoice->userid . " with invoice ID #" . $invoiceId;

        // Call SendAdminEmail API
        $result = localAPI('SendAdminEmail', [
            'messagename' => 'Admin Renewal Notification', // Email template created for admin
            'custommessage' => $message,
            'customsubject' => 'New Renewal Invoice: #' . $invoiceId,
            'adminemail' => $adminEmail,
        ]);

        if ($result['result'] != 'success') {
            logActivity("Failed to send renewal notification: " . $result['message']);
        }
    }
});
