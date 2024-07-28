<?php

/**
* Detect if cart total zero - if so, remove other gateways
* @author Sahanur
*/
 
function cart_gateway_removal_for_free_products($vars)
{
	if ($vars['templatefile']=='viewcart'){
		$gateways = $vars['gateways'];
		$total = $vars['total']->toNumeric();
		$allowed = ['mailin','paypal'];
		foreach ($gateways as $k => $item) {
			if (!in_array($item['sysname'],$allowed) and $total == '0') {
				unset($gateways[$k]);
			}
		} 
		return array("gateways" => $gateways);
	}
}
add_hook("ClientAreaPageCart", 1, "cart_gateway_removal_for_free_products");
