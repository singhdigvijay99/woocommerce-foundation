<?php
/**
 * Plugin Name: WC Simple Debug
 */

if ( ! defined('ABSPATH') ) exit;

/**
 * Only log for admin
 */
function wc_simple_dbg($msg) {
    if ( current_user_can('manage_options') ) {
        error_log($msg);
    }
}

/**
 * 1. ADD TO CART
 */
add_filter('woocommerce_add_to_cart_validation',
function($passed, $product_id, $qty, $variation_id = 0, $variations = []) {

    $product = wc_get_product($product_id);

    wc_simple_dbg("=== ADD TO CART ===");
    wc_simple_dbg("Type: " . $product->get_type());
    wc_simple_dbg("Product ID: $product_id");
    wc_simple_dbg("Variation ID: $variation_id");
    wc_simple_dbg("Qty: $qty");

    if (!empty($variations)) {
        wc_simple_dbg("Attributes: " . print_r($variations, true));
    }

    return $passed;

}, 10, 5);


/**
 * 2. CART CONTENT
 */
add_action('woocommerce_before_calculate_totals', function($cart) {

    if ( ! current_user_can('manage_options') ) return;

    wc_simple_dbg("=== CART ===");

    foreach ($cart->get_cart() as $key => $item) {

        $product = $item['data'];

        wc_simple_dbg("Item:");
        wc_simple_dbg(" - Type: " . $product->get_type());
        wc_simple_dbg(" - Product ID: " . $product->get_id());
        wc_simple_dbg(" - Variation ID: " . ($item['variation_id'] ?? 0));
        wc_simple_dbg(" - Qty: " . $item['quantity']);

        if (!empty($item['variation'])) {
            wc_simple_dbg(" - Variation: " . print_r($item['variation'], true));
        }
    }

});


/**
 * 3. ORDER ITEMS (WORKS WITHOUT PAYMENT)
 */
add_action('woocommerce_checkout_create_order_line_item',
function($item, $cart_item_key, $values, $order) {

    $product = $values['data'];

    wc_simple_dbg("=== ORDER ITEM ===");
    wc_simple_dbg("Order ID: " . $order->get_id());
    wc_simple_dbg("Type: " . $product->get_type());
    wc_simple_dbg("Product ID: " . $product->get_id());
    wc_simple_dbg("Qty: " . $values['quantity']);

    if (!empty($values['variation'])) {
        wc_simple_dbg("Variation: " . print_r($values['variation'], true));
    }

}, 10, 4);


/**
 * 4. CHECKOUT TYPE
 */
add_action('init', function() {

    if ( ! current_user_can('manage_options') ) return;

    if ( strpos($_SERVER['REQUEST_URI'] ?? '', 'wc-ajax=checkout') !== false ) {
        wc_simple_dbg("CHECKOUT: shortcode");
    }

    if ( strpos($_SERVER['REQUEST_URI'] ?? '', '/wc/store/') !== false ) {
        wc_simple_dbg("CHECKOUT: block");
    }

});

// Grouped Product Page
//         ↓
// User selects quantities
//         ↓
// Loop over each child product
//         ↓
// Call add_to_cart() for EACH child 

// when click on external product it redirect to different site not on same no check out and no add to cart
?>
// Variable Product 
[21-Apr-2026 08:59:06 UTC] CHECKOUT: block
[21-Apr-2026 08:59:20 UTC] === ADD TO CART ===
[21-Apr-2026 08:59:20 UTC] Type: variable
[21-Apr-2026 08:59:20 UTC] Product ID: 49
[21-Apr-2026 08:59:20 UTC] Variation ID: 50
[21-Apr-2026 08:59:20 UTC] Qty: 1
[21-Apr-2026 08:59:20 UTC] Attributes: Array
(
    [attribute_size] => M
)

[21-Apr-2026 09:00:25 UTC] === CART ===
[21-Apr-2026 09:00:25 UTC] Item:
[21-Apr-2026 09:00:25 UTC]  - Type: variation
[21-Apr-2026 09:00:25 UTC]  - Product ID: 50
[21-Apr-2026 09:00:25 UTC]  - Variation ID: 50
[21-Apr-2026 09:00:25 UTC]  - Qty: 1
[21-Apr-2026 09:00:25 UTC]  - Variation: Array
(
    [attribute_size] => M
)

[21-Apr-2026 09:00:26 UTC] === ORDER ITEM ===
[21-Apr-2026 09:00:26 UTC] Order ID: 0
[21-Apr-2026 09:00:26 UTC] Type: variation
[21-Apr-2026 09:00:26 UTC] Product ID: 50
[21-Apr-2026 09:00:26 UTC] Qty: 1
[21-Apr-2026 09:00:26 UTC] Variation: Array
(
    [attribute_size] => M
)


// Group Product 
// A grouped product is never added to the cart.
// Only its child simple products are added.
[21-Apr-2026 09:03:14 UTC] CHECKOUT: block
[21-Apr-2026 09:03:19 UTC] CHECKOUT: block
[21-Apr-2026 09:03:19 UTC] === ADD TO CART ===
[21-Apr-2026 09:03:19 UTC] Type: simple
[21-Apr-2026 09:03:19 UTC] Product ID: 54
[21-Apr-2026 09:03:19 UTC] Variation ID: 0
[21-Apr-2026 09:03:19 UTC] Qty: 1
[21-Apr-2026 09:03:19 UTC] === CART ===
[21-Apr-2026 09:03:19 UTC] Item:
[21-Apr-2026 09:03:19 UTC]  - Type: simple
[21-Apr-2026 09:03:19 UTC]  - Product ID: 54
[21-Apr-2026 09:03:19 UTC]  - Variation ID: 0
[21-Apr-2026 09:03:19 UTC]  - Qty: 1
[21-Apr-2026 09:03:21 UTC] === CART ===
[21-Apr-2026 09:03:21 UTC] Item:
[21-Apr-2026 09:03:21 UTC]  - Type: simple
[21-Apr-2026 09:03:21 UTC]  - Product ID: 54
[21-Apr-2026 09:03:21 UTC]  - Variation ID: 0
[21-Apr-2026 09:03:21 UTC]  - Qty: 1
21-Apr-2026 09:04:49 UTC] === CART ===
[21-Apr-2026 09:04:49 UTC] Item:
[21-Apr-2026 09:04:49 UTC]  - Type: simple
[21-Apr-2026 09:04:49 UTC]  - Product ID: 54
[21-Apr-2026 09:04:49 UTC]  - Variation ID: 0
[21-Apr-2026 09:04:49 UTC]  - Qty: 1
[21-Apr-2026 09:04:49 UTC] === CART ===
[21-Apr-2026 09:04:49 UTC] Item:
[21-Apr-2026 09:04:49 UTC]  - Type: simple
[21-Apr-2026 09:04:49 UTC]  - Product ID: 54
[21-Apr-2026 09:04:49 UTC]  - Variation ID: 0
[21-Apr-2026 09:04:49 UTC]  - Qty: 1
[21-Apr-2026 09:04:49 UTC] === ORDER ITEM ===
[21-Apr-2026 09:04:49 UTC] Order ID: 0
[21-Apr-2026 09:04:49 UTC] Type: simple
[21-Apr-2026 09:04:49 UTC] Product ID: 54
[21-Apr-2026 09:04:49 UTC] Qty: 1
[21-Apr-2026 09:05:06 UTC] CHECKOUT: block
[21-Apr-2026 09:05:07 UTC] === CART ===
[21-Apr-2026 09:05:07 UTC] Item:
[21-Apr-2026 09:05:07 UTC]  - Type: simple
[21-Apr-2026 09:05:07 UTC]  - Product ID: 54
[21-Apr-2026 09:05:07 UTC]  - Variation ID: 0
[21-Apr-2026 09:05:07 UTC]  - Qty: 1
[21-Apr-2026 09:05:07 UTC] === CART ===
[21-Apr-2026 09:05:07 UTC] Item:
[21-Apr-2026 09:05:07 UTC]  - Type: simple
[21-Apr-2026 09:05:07 UTC]  - Product ID: 54
[21-Apr-2026 09:05:07 UTC]  - Variation ID: 0
[21-Apr-2026 09:05:07 UTC]  - Qty: 1