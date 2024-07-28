<?php

/**
 * Detect if cart total is zero and if specific product ID is in the cart
 * - If cart total is zero, show only allowed gateways
 * - If cart total is greater than zero, remove allowed gateways
 * @author Sahanur
 */

function cart_gateway_removal_for_free_products($vars)
{
    $specific_product_id = 123; // Replace with your specific product ID

    if ($vars['templatefile'] == 'viewcart') {
        $gateways = $vars['gateways'];
        $total = $vars['total']->toNumeric();
        $allowed = ['mailin', 'paypal'];

        $product_in_cart = false;

        // Check if the specific product is in the cart
        foreach ($vars['products'] as $product) {
            if ($product['pid'] == $specific_product_id) {
                $product_in_cart = true;
                break;
            }
        }

        if ($product_in_cart) {
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
        }

        return array("gateways" => $gateways);
    }

    return $vars; // Ensure you return the original data if the condition is not met
}

// Register the function to the appropriate hook
add_hook('ClientAreaPageCart', 1, 'cart_gateway_removal_for_free_products');
