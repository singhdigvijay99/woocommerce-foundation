<?php 
//Checkout Session State
The session can be null early in the request lifecycle. Always guard before writing to it.
// crashes if session isn't initialized yet
WC()->session->set('my_key', 'my_value');

// we add the safe guard
add_action('woocommerce_init', function() {
    if (WC()->session && WC()->session->has_session()) {
        WC()->session->set('my_key', 'my_value');
    }
});

The thank-you page trap: Cart is cleared after order placement, so cart-based session data is gone. 
Store what you need on the ORDER itself, not the session.

// Save custom data to order meta instead of session
add_action('woocommerce_checkout_create_order', function($order, $data) {
    $my_val = WC()->session->get('my_key');
    if ($my_val) {
        $order->update_meta_data('_my_key', $my_val);
    }
}, 10, 2);



//---  Tax Calculation Timing ----
Taxes are recalculated multiple times in a single request. Never cache a tax value across hooks.

// See the rounding quirk in action — add this temporarily
add_action('woocommerce_calculate_totals', function($cart) {
    foreach ($cart->get_cart() as $item) {
        $price_incl = wc_get_price_including_tax($item['data']);
        $price_excl = wc_get_price_excluding_tax($item['data']);
        error_log("Price incl tax: $price_incl | excl: $price_excl");
    }
});
// output : 
// [24-Apr-2026 08:16:09 UTC] Price incl tax: 172.33 | excl: 172.33
//Never use wc_get_price_including_tax() as your source of truth for what the customer was charged — use $order->get_total() instead.

// ------ Coupon Application Order --------------
The woocommerce_calc_discounts_sequentially option changes whether percentage coupons run before or after fixed ones.

// programmatic way of coupon application
// Step 1: Suppress "does not exist" error for our virtual coupon
add_filter('woocommerce_coupon_error', function($err, $err_code, $coupon) {
    if ($err_code === 100 && strtolower($coupon->get_code()) === 'virtual10') {
        return '';
    }
    return $err;
}, 10, 3);

// Step 2: Tell WooCommerce the coupon is valid
add_filter('woocommerce_coupon_is_valid', function($valid, $coupon) {
    if (strtolower($coupon->get_code()) === 'virtual10') {
        return true;
    }
    return $valid;
}, 10, 2);

// Step 3: Provide the coupon data
add_filter('woocommerce_get_shop_coupon_data', function($data, $code, $coupon) {
    if (strtolower($code) !== 'virtual10') return $data;

    return [
        'discount_type'       => 'percent',
        'coupon_amount'       => 10,
        'individual_use'      => false,
        'product_ids'         => [],
        'exclude_product_ids' => [],
        'usage_limit'         => '',
        'usage_count'         => 0,
        'expiry_date'         => '',
        'free_shipping'       => false,
        'minimum_amount'      => '',
    ];
}, 10, 3);

// --- Cart Item Meta Persistence — The Classic Merge Bug ----
This is one of the most common WooCommerce bugs in custom development.

// if user adds same product twice, items MERGE and data is lost
add_filter('woocommerce_add_cart_item_data', function($data, $product_id) {
    $data['custom_note'] = 'My special note';
    return $data;
}, 10, 2);

// Correct way of doing is to add a unique key or unique hash that prevents merging
add_filter('woocommerce_add_cart_item_data', function($data, $product_id) {
    $data['custom_note'] = 'My special note';
    $data['unique_key'] = md5(microtime() . $product_id . 'custom_note');
    return $data;
}, 10, 2);

// Display it in cart
add_filter('woocommerce_get_item_data', function($item_data, $cart_item) {
    if (!empty($cart_item['custom_note'])) {
        $item_data[] = [
            'name'  => 'Note',
            'value' => $cart_item['custom_note'],
        ];
    }
    return $item_data;
}, 10, 2);

// Save it to order item meta
add_action('woocommerce_checkout_create_order_line_item', function($item, $cart_item_key, $values, $order) {
    if (!empty($values['custom_note'])) {
        $item->add_meta_data('Custom Note', $values['custom_note'], true);
    }
}, 10, 4);


//---- Order Status Transitions — Three Hooks Per Change ---
Every status change fires three hooks. If you're not aware, you'll run code three times.
// Hook 1: Generic — fires for every transition
add_action('woocommerce_order_status_changed', function($order_id, $from, $to, $order) {
    error_log("Hook 1 fired: Order $order_id changed from $from to $to");
}, 10, 4);

// Hook 2: Specific FROM→TO pair
add_action('woocommerce_order_status_processing_to_completed', function($order_id, $order) {
    error_log("Hook 2 fired: Order $order_id is now completed");
}, 10, 2);

// Hook 3: Just the TO status
add_action('woocommerce_order_status_completed', function($order_id) {
    error_log("Hook 3 fired: Order $order_id reached completed status");
});

Idempotency example — protect against double-fire:

phpadd_action('woocommerce_order_status_completed', function($order_id) {
    $order = wc_get_order($order_id);

    // Guard: already processed?
    if ($order->get_meta('_reward_points_granted')) return;

    // Do your one-time action
    grant_reward_points($order_id);

    // Mark as done
    $order->update_meta_data('_reward_points_granted', true);
    $order->save();
});


///---- Stock Management — Oversell Risk -----

// Simulate what happens during concurrent checkout

// WooCommerce holds stock via woocommerce_checkout_order_created

// Check stock hold status manually:
add_action('woocommerce_checkout_order_created', function($order) {
    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        error_log("Stock after hold: " . $product->get_stock_quantity());
    }
});

/// ------- Refund stock quirk: ----------------
// Full refund → stock auto-restored (if "Return to stock" is checked)
// Partial refund → you must handle it manually

add_action('woocommerce_order_partially_refunded', function($order_id, $refund_id) {
    $refund = wc_get_order($refund_id);

    foreach ($refund->get_items() as $item) {
        $product_id = $item->get_product_id();
        $qty        = $item->get_quantity();

        // Manually restore stock
        wc_update_product_stock($product_id, $qty, 'increase');
        error_log("Manually restored $qty units for product $product_id");
    }
}, 10, 2);

/// ------- Refunds — Two Different Data Models ----------------
// Full refund creates a WC_Order_Refund object
$refund = wc_create_refund([
    'order_id'   => $order_id,
    'amount'     => $order->get_total(),
    'reason'     => 'Customer request',
    'line_items' => [],
]);

// Partial refund — only refund one line item
$items = $order->get_items();
$first_item = reset($items);

$refund = wc_create_refund([
    'order_id'   => $order_id,
    'amount'     => 50.00,
    'line_items' => [
        $first_item->get_id() => [
            'qty'          => 1,
            'refund_total' => 50.00,
        ]
    ],
]);

// Reconciliation check: compare gateway state vs WC state
add_action('woocommerce_order_refunded', function($order_id, $refund_id) {
    $order  = wc_get_order($order_id);
    $refund = wc_get_order($refund_id);

    error_log("WC refund amount: " . $refund->get_amount());
    error_log("Order total remaining: " . $order->get_remaining_refund_amount());
    // If these don't match your gateway's record → you have a reconciliation problem
}, 10, 2);

/// ------- HPOS Compatibility — The Silent Killer -------
This is the most dangerous one. On HPOS-enabled sites, WP_Query on orders silently returns zero results with no error.
//BROKEN on HPOS — returns nothing, no error
$orders = new WP_Query([
    'post_type'   => 'shop_order',
    'post_status' => 'wc-completed',
    'numberposts' => 10,
]);

// CORRECT — works on both classic and HPOS
$orders = wc_get_orders([
    'status' => 'completed',
    'limit'  => 10,
]);

// BROKEN — direct post meta access
update_post_meta($order_id, '_my_key', 'value');
$val = get_post_meta($order_id, '_my_key', true);

// CORRECT — use the order object API
$order = wc_get_order($order_id);
$order->update_meta_data('_my_key', 'value');
$order->save();
$val = $order->get_meta('_my_key');



// ----- Block Checkout vs Shortcode Checkout ----------
Block checkout uses the Store API, not admin-ajax. Validation hooks are completely different.
// This works ONLY on shortcode checkout — silently ignored on blocks
add_action('woocommerce_checkout_process', function() {
    if (empty($_POST['billing_company'])) {
        wc_add_notice('Company name is required.', 'error');
    }
});

// For BLOCK checkout — use the Store API extension
// Requires registering an IntegrationRegistry script
// Validation is done via woocommerce_store_api_checkout_update_order_from_request

add_action('woocommerce_store_api_checkout_update_order_from_request', function($order, $request) {
    $data = $request->get_param('extensions');

    if (empty($data['my-plugin']['company'])) {
        throw new \Exception('Company name is required.');
    }

    $order->update_meta_data('_company', sanitize_text_field($data['my-plugin']['company']));
}, 10, 2);

 To check which checkout your store is using:
phpadd_action('wp_footer', function() {
    if (!is_checkout()) return;

    $using_blocks = has_block('woocommerce/checkout');
    error_log($using_blocks ? 'Block checkout active' : 'Shortcode checkout active');
});

//----- Email Deduplication -----------
Multiple hooks fire for the same transition → you can end up sending 3 emails for 1 event.
/// calling remove_action on internal handlers is fragile
// (function references are hard to get and break across WC versions)

// disable via filter cleanly
add_filter('woocommerce_email_enabled_customer_processing_order', '__return_false');

// Disable multiple:
$emails_to_disable = [
    'customer_processing_order',
    'new_order',
];

foreach ($emails_to_disable as $email_id) {
    add_filter("woocommerce_email_enabled_{$email_id}", '__return_false');
}

// If you're sending a custom email instead, guard with a flag
add_action('woocommerce_order_status_processing', function($order_id) {
    $order = wc_get_order($order_id);

    if ($order->get_meta('_custom_email_sent')) return;

    // send your custom email here...

    $order->update_meta_data('_custom_email_sent', '1');
    $order->save();
});
