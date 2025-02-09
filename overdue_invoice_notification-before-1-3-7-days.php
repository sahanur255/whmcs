<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

add_hook('AfterCronJob', 1, function() {
    // Set timezone to America/New_York (Eastern Time Zone for North Carolina)
    $timezone = new DateTimeZone('America/New_York');
    $currentDate = new DateTime('now', $timezone);
    $currentDateString = $currentDate->format('Y-m-d');

    $lastRunFile = '/home/webplanet/whmcsdata/custom_script/lastrun.txt'; 
    $lastRunDate = file_exists($lastRunFile) ? file_get_contents($lastRunFile) : '';

    // Only proceed if the script hasn't run today
    if ($lastRunDate !== $currentDateString) {
        file_put_contents($lastRunFile, $currentDateString);

        // Fetch invoices overdue by 1, 3, and 7 days
        $overdueInvoices = Capsule::table('tblinvoices')
            ->where('status', 'Unpaid')
            ->where(function ($query) use ($currentDateString) {
                $query->where('duedate', '=', date('Y-m-d', strtotime('-1 days', strtotime($currentDateString))))
                      ->orWhere('duedate', '=', date('Y-m-d', strtotime('-3 days', strtotime($currentDateString))))
                      ->orWhere('duedate', '=', date('Y-m-d', strtotime('-7 days', strtotime($currentDateString))));
            })
            ->get();

        if ($overdueInvoices->isNotEmpty()) {
            $notifications = [
                '1' => ['title' => 'Overdue Invoices Notification (1 Day Overdue)', 'message' => "<p>The following invoices are overdue by 1 day:</p>", 'hasInvoices' => false],
                '3' => ['title' => 'Overdue Invoices Notification (3 Days Overdue)', 'message' => "<p>The following invoices are overdue by 3 days:</p>", 'hasInvoices' => false],
                '7' => ['title' => 'Overdue Invoices Notification (7 Days Overdue)', 'message' => "<p>The following invoices are overdue by 7 days:</p>", 'hasInvoices' => false]
            ];

            $tableHeader = "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse:collapse; width: 100%;'>";
            $tableHeader .= "<tr style='background-color: #f2f2f2;'><th>Invoice ID</th><th>Client Name</th><th>Company Name</th><th>Due Date</th><th>Items</th><th>Total Amount</th></tr>";

            foreach ($overdueInvoices as $invoice) {
                $client = Capsule::table('tblclients')->where('id', $invoice->userid)->first();
                if (!$client) {
                    logActivity("Client not found for invoice ID: " . $invoice->id);
                    continue;
                }

                $invoiceItems = Capsule::table('tblinvoiceitems')->where('invoiceid', $invoice->id)->get();
                if ($invoiceItems->isEmpty()) {
                    logActivity("No items found for invoice ID: " . $invoice->id);
                    continue;
                }

                $itemsDetails = '';
                $totalAmount = 0;
                foreach ($invoiceItems as $item) {
                    $itemsDetails .= "<li>Description: " . htmlspecialchars($item->description) . " - Amount: " . formatCurrency($item->amount) . "</li>";
                    $totalAmount += $item->amount;
                }

                $clientName = htmlspecialchars($client->firstname . " " . $client->lastname);
                $companyName = htmlspecialchars($client->companyname ?? 'N/A');
                $dueDays = (int)(($currentDate->getTimestamp() - strtotime($invoice->duedate)) / 86400);

                if (isset($notifications[$dueDays])) {
                    $notifications[$dueDays]['message'] .= "<tr><td>" . htmlspecialchars($invoice->id) . "</td>";
                    $notifications[$dueDays]['message'] .= "<td>" . $clientName . "</td>";
                    $notifications[$dueDays]['message'] .= "<td>" . $companyName . "</td>";
                    $notifications[$dueDays]['message'] .= "<td>" . htmlspecialchars($invoice->duedate) . "</td>";
                    $notifications[$dueDays]['message'] .= "<td><ul>" . $itemsDetails . "</ul></td>";
                    $notifications[$dueDays]['message'] .= "<td>" . formatCurrency($totalAmount) . "</td></tr>";
                    $notifications[$dueDays]['hasInvoices'] = true;
                }
            }

            $adminEmail = 'support@webplanet.studio';
            foreach ($notifications as $days => $data) {
                if ($data['hasInvoices']) {
                    $data['message'] = "<h2 style='color: #d9534f;'>" . $data['title'] . "</h2>" . $data['message'] . "</table>";
                    $result = localAPI('SendAdminEmail', [
                        'messagename' => $data['title'],
                        'custommessage' => $data['message'],
                        'customsubject' => $data['title'],
                        'adminemail' => $adminEmail,
                    ]);
                    if ($result['result'] != 'success') {
                        logActivity("Failed to send $days-day overdue invoice notification: " . $result['message']);
                    } else {
                        logActivity("Sent $days-day overdue invoice notification.");
                    }
                }
            }
        } else {
            logActivity("No overdue invoices found for 1-day, 3-day, or 7-day notifications.");
        }
    }
});
