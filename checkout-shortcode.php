### Differences Between Shortcode Checkout and Block Checkout in WooCommerce

WooCommerce offers two primary checkout implementations: **shortcode-based** (legacy) and **block-based** (modern, using Gutenberg). They differ in rendering, hooks, and submission mechanisms, which can impact customizations and integrations.

#### Key Differences
- **Rendering**:
  - **Shortcode Checkout**: Uses the `[woocommerce_checkout]` shortcode to display the checkout form. It's template-driven and relies on PHP hooks for customization.
  - **Block Checkout**: Uses WooCommerce Blocks (e.g., Checkout Block) in the editor. It's component-based, with JavaScript/React for dynamic rendering.

- **Submission Paths**:
  - **Shortcode Checkout**: Submits data via `admin-ajax.php` (WordPress AJAX endpoint). The process involves server-side validation and processing through PHP.
  - **Block Checkout**: Submits via the **Store API REST** endpoints (e.g., `/wp-json/wc/store/v1/checkout`). This is a RESTful API approach, handling submissions asynchronously with JavaScript.

- **Hooks**:
  - **Shortcode Checkout**: Relies on action/filter hooks like `woocommerce_checkout_process`, `woocommerce_checkout_order_processed`, and template hooks (e.g., `woocommerce_before_checkout_form`).
  - **Block Checkout**: Uses Store API hooks (e.g., `woocommerce_store_api_checkout_order_processed`) and JavaScript events. Fewer traditional PHP hooks; more emphasis on API extensions.

- **Customization**:
  - Shortcode: Easier for PHP developers; modify templates or use hooks directly.
  - Block: Requires JavaScript/React knowledge; customize via block attributes or API schemas.

[Page Load]
   ↓
[AJAX: update_order_review]
   ↓
[User clicks Place Order]
   ↓
[woocommerce_checkout_process] ← validation
   ↓
[create_order]
   ↓
[add line items]
   ↓
[process payment]
   ↓
[redirect]
<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 1. Shortcode Wrapper (CORRECT WAY)
 */
add_shortcode('custom_checkout', function () {

    if (!function_exists('WC')) {
        return '<p>WooCommerce is not active.</p>';
    }

    if (!is_user_logged_in()) {
        return '<p>Please log in to proceed to checkout.</p>';
    }

    ob_start();

    echo '<div class="my-custom-checkout-wrapper">';
    echo '<h2>My Custom Checkout UI</h2>';

    // IMPORTANT: Use real WooCommerce checkout
    echo do_shortcode('[woocommerce_checkout]');

    echo '</div>';
    
    echo '<pre>';
    echo 'Cart Count: ' . WC()->cart->get_cart_contents_count() . "\n";
    echo 'Cart Total: ' . WC()->cart->get_total() . "\n";
    echo '</pre>';

    return ob_get_clean();
});


/**
 * 2. Modify Checkout Fields (Learning Hook)
 */
add_filter('woocommerce_checkout_fields', function ($fields) {

    // Change label
    $fields['billing']['billing_first_name']['label'] = 'First Name (Modified)';

    // Make phone required
    $fields['billing']['billing_phone']['required'] = true;

    return $fields;
});


/**
 * 3. Add Custom Validation
 */
add_action('woocommerce_checkout_process', function () {

    if (!WC()->cart) return;

    if (WC()->cart->get_subtotal() < 50) {
        wc_add_notice('Minimum order amount is 50.', 'error');
    }
});


/**
 * 4. Add Custom Meta to Order Items
 */
add_action('woocommerce_checkout_create_order_line_item', function ($item, $cart_item_key, $values) {

    $item->add_meta_data('custom_note', 'Added via plugin', true);

}, 10, 3);


/**
 * 5. Order Processed Hook (Best for logging / API calls)
 */
add_action('woocommerce_checkout_order_processed', function ($order_id, $posted_data, $order) {

    error_log('Order processed: ' . $order_id);

}, 10, 3);


/**
 * 6. Debug: Log Cart + Session (Learning Purpose)
 */
add_action('woocommerce_before_calculate_totals', function ($cart) {

    if (is_admin() && !defined('DOING_AJAX')) return;

    error_log('=== CART DEBUG ===');
    error_log(print_r($cart->get_cart(), true));

    if (WC()->session) {
        error_log('=== SESSION DEBUG ===');
        error_log(print_r(WC()->session->get_session_data(), true));
    }

});

//  Block Approach better then Shortcode Approach for React UI (Checkout Block) customization, but here’s the flow:
// React UI (Checkout Block)
//    ↓
// POST /wp-json/wc/store/v1/checkout
//    ↓
// Store API Controller
//    ↓
// Order created

// Rest API approach means we need to capture data early (before order is created) 
// and store it in order meta, then use it in final processing. 
// We can also add validation in the validation hook, but we can’t rely on 
// request data in the final order hook, so we must capture it early.

/**
 * Logger
 */
function bcl_log( $message ) {
    if ( is_array( $message ) || is_object( $message ) ) {
        error_log( print_r( $message, true ) );
    } else {
        error_log( $message );
    }
}

/**
 * 1. VALIDATION (always has request)
 */
add_action(
    'woocommerce_store_api_checkout_validate_order',
    function ( $request ) {

        $data = $request->get_params();
        $payment = $data['payment_method'] ?? '';

        if ( $payment === 'cod' ) {
            throw new \WC_REST_Exception(
                'cod_disabled',
                'COD disabled (REST)',
                400
            );
        }

    },
    10,
    1
);


/**
 * 2. CAPTURE REQUEST DATA EARLY (IMPORTANT)
 */
add_action(
    'woocommerce_store_api_checkout_update_order_meta',
    function ( $order, $request = null ) {

        // Always log once for debugging
        bcl_log('=== META HOOK ===');

        // Set source only once
        if ( ! $order->get_meta('_custom_source') ) {
            $order->update_meta_data('_custom_source', 'block_checkout_rest');
        }

        // Capture request data WHEN AVAILABLE (even in draft)
        if ( $request && ! $order->get_meta('_captured_payment_method') ) {

            $data = $request->get_params();

            $payment = $data['extensions'] ?? '';

            bcl_log('Captured from request: ' . $payment);

            $order->update_meta_data('_captured_payment_method', $payment);
        }

    },
    10,
    2
);


/**
 * 3. FINAL ORDER LOGIC (NO REQUEST RELIANCE)
 */
add_action(
    'woocommerce_store_api_checkout_order_processed',
    function ( $order ) {

        // Avoid running twice
        if ( $order->get_meta('_bcl_processed') ) {
            return;
        }

        // Mark as processed
        $order->update_meta_data('_bcl_processed', 'yes');
        $order->save();

        bcl_log('=== FINAL ORDER ONLY ===');
        bcl_log('Order ID: ' . $order->get_id());
        bcl_log('Status: ' . $order->get_status());

        // Use captured data instead of request
        $payment = $order->get_meta('_captured_payment_method');

        bcl_log('Captured Payment: ' . $payment);

    },
    10,
    1
);


/**
 * 4. GLOBAL FALLBACK (optional)
 */
add_action(
    'woocommerce_new_order',
    function ( $order_id ) {

        bcl_log('=== ORDER CREATED (GLOBAL) === ID: ' . $order_id);

    }
);


/**
 * 5. CART DEBUG (safe)
 */
add_action(
    'woocommerce_store_api_cart_update_customer_from_request',
    function ( $customer, $request = null ) {

        if ( ! $request ) return;

        bcl_log('=== CART UPDATED ===');
        bcl_log( $request->get_params() );

    },
    10,
    2
);


/**
 * 6. EXTEND SCHEMA
 */
add_filter(
    'woocommerce_store_api_checkout_schema',
    function ( $schema ) {

        $schema['properties']['extensions']['properties']['bcl'] = [
            'type'       => 'object',
            'properties' => [
                'custom_note' => [
                    'type' => 'string',
                ],
            ],
        ];

        return $schema;
    }
);

// [22-Apr-2026 07:11:44 UTC] === META HOOK ===
// [22-Apr-2026 07:11:51 UTC] === META HOOK ===
// [22-Apr-2026 07:12:04 UTC] === META HOOK ===
// [22-Apr-2026 07:12:04 UTC] === ORDER CREATED (GLOBAL) === ID: 82
// [22-Apr-2026 07:12:04 UTC] === FINAL ORDER ONLY ===
// [22-Apr-2026 07:12:04 UTC] Order ID: 82
// [22-Apr-2026 07:12:04 UTC] Status: pending
// [22-Apr-2026 07:12:04 UTC] Captured Payment: COD