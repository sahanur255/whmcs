<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

add_hook('AfterCronJob', 1, function() {
    // Get the current date
    $currentDate = date('Y-m-d');

    // Check the last execution date from a temporary file
    $lastRunFile = '/home/webplanet/whmcsdata/custom_script/lastrun.txt'; // Specify a path to store the last run date

    // Initialize the last run date
    $lastRunDate = file_exists($lastRunFile) ? file_get_contents($lastRunFile) : '';

    // If the script has not run today, proceed
    if ($lastRunDate !== $currentDate) {
        // Update the last run date
        file_put_contents($lastRunFile, $currentDate);

        // Fetch unpaid invoices that are past due date
        $overdueInvoices = Capsule::table('tblinvoices')
            ->where('status', 'Unpaid')
            ->where('duedate', '<', $currentDate) // Compare with today's date
            ->get();

        if ($overdueInvoices->isNotEmpty()) { // Check if there are overdue invoices
            // Prepare the message content
            $message = "<h2 style='color: #d9534f;'>Overdue Invoices Notification</h2>";
            $message .= "<p>The following invoices are overdue and unpaid:</p>";
            $message .= "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse:collapse; width: 100%;'>";
            $message .= "<tr style='background-color: #f2f2f2;'><th>Invoice ID</th><th>Client</th><th>Due Date</th><th>Items</th><th>Total Amount</th></tr>";

            foreach ($overdueInvoices as $invoice) {
                // Fetch the client information
                $client = Capsule::table('tblclients')->where('id', $invoice->userid)->first();
                if (!$client) {
                    logActivity("Client not found for invoice ID: " . $invoice->id);
                    continue; // Skip to the next invoice if the client is not found
                }

                // Fetch the invoice items
                $invoiceItems = Capsule::table('tblinvoiceitems')->where('invoiceid', $invoice->id)->get();
                if ($invoiceItems->isEmpty()) {
                    logActivity("No items found for invoice ID: " . $invoice->id);
                    continue; // Skip to the next invoice if no items are found
                }

                // Prepare the item details
                $itemsDetails = '';
                $totalAmount = 0;

                foreach ($invoiceItems as $item) {
                    $itemsDetails .= "<li>Description: " . htmlspecialchars($item->description) . " - Amount: " . formatCurrency($item->amount) . "</li>";
                    $totalAmount += $item->amount; // Sum total amount
                }

                // Append the invoice information to the message
                $message .= "<tr>";
                $message .= "<td>" . htmlspecialchars($invoice->id) . "</td>";
                $message .= "<td>" . htmlspecialchars($client->companyname ?? 'N/A') . "</td>";
                $message .= "<td>" . htmlspecialchars($invoice->duedate) . "</td>";
                $message .= "<td><ul>" . $itemsDetails . "</ul></td>";
                $message .= "<td>" . formatCurrency($totalAmount) . "</td>";
                $message .= "</tr>";

                // Add a separator
                $message .= "<tr><td colspan='5' style='border-top: 2px solid #000; height: 10px;'></td></tr>";
            }

            $message .= "</table>";

            // Send email using WHMCS Mail system
            $adminEmail = 'support@webplanet.studio'; // Change this to the admin's email

            // Call SendAdminEmail API
            $result = localAPI('SendAdminEmail', [
                'messagename' => 'Overdue Invoices Notification',
                'custommessage' => $message,
                'customsubject' => 'Overdue Invoices Notification',
                'adminemail' => $adminEmail,
            ]);

            // Check the result and log any issues
            if ($result['result'] != 'success') {
                logActivity("Failed to send overdue invoice notification: " . $result['message']);
            } else {
                logActivity("Sent overdue invoice notification for " . count($overdueInvoices) . " invoices.");
            }
        } else {
            logActivity("No overdue invoices found.");
        }
    }
});
