<?php

/**
 * Detect if cart total is zero - if so, show only allowed gateways
 * If cart total is greater than zero, remove allowed gateways
 * @autor Sahanur
 */

function cart_gateway_removal_for_free_products($vars)
{
    if ($vars['templatefile'] == 'viewcart') {
        $gateways = $vars['gateways'];
        $total = $vars['total']->toNumeric();
        $allowed = ['mailin'];

        if ($total == 0) {
            // Keep only the allowed gateways
            foreach ($gateways as $k => $item) {
                if (!in_array($item['sysname'], $allowed)) {
                    unset($gateways[$k]);
                }
            }
        } else {
            // Remove the allowed gateways
            foreach ($gateways as $k => $item) {
                if (in_array($item['sysname'], $allowed)) {
                    unset($gateways[$k]);
                }
            }
        }

        return array("gateways" => $gateways);
    }
    return $vars; // Ensure you return the original data if the condition is not met
}

// Register the function to the appropriate hook
add_hook('ClientAreaPageCart', 1, 'cart_gateway_removal_for_free_products');
