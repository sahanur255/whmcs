<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

add_hook('AfterCronJob', 1, function() {
    // Fetch unpaid invoices that are past due date
    $overdueInvoices = Capsule::table('tblinvoices')
                        ->where('status', 'Unpaid')
                        ->where('duedate', '<', date('Y-m-d')) // Compare with today's date
                        ->get();

    if (count($overdueInvoices) > 0) {
        // Prepare the message content
        $message = "The following invoices are overdue and unpaid:\n\n";
        foreach ($overdueInvoices as $invoice) {
            $message .= "Invoice ID: " . $invoice->id . " - Client ID: " . $invoice->userid . " - Due Date: " . $invoice->duedate . "\n";
        }

        // Get first overdue invoice ID for email subject
        $invoiceId = $overdueInvoices->first()->id;

        // Send email using WHMCS Mail system
        $adminEmail = 'support@webplanet.studio'; // Change this to the admin's email

        // Call SendAdminEmail API
        $result = localAPI('SendAdminEmail', [
            'messagename' => 'Overdue Invoices Notification', // Email template created for admin
            'custommessage' => $message,
            'customsubject' => 'Overdue Invoices Notification: #' . $invoiceId,
            'adminemail' => $adminEmail,
        ]);

        if ($result['result'] != 'success') {
            logActivity("Failed to send overdue invoice notification: " . $result['message']);
        }
    }
});
