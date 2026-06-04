WC()->cart
Runtime object (in memory)
Rebuilt on every request
Holds:
cart items

WC()->session
Persistent storage (per user)
Backed by:
DB (wp_woocommerce_sessions)
cookie
Session (stored) → Cart (rebuilt every request)

Checkout Flows (2 Systems):

Shortcode Checkout (Old)
Form submit → admin-ajax.php → WC_AJAX::checkout
Hooks:
woocommerce_checkout_process
woocommerce_checkout_create_order

Block Checkout (New)
React UI → REST API → /wp-json/wc/store/checkout
Hooks:
woocommerce_store_api_checkout_update_order_meta

<?php 

// ─────────────────────────────────────────────────────────────
//load on the frontend only 
// ─────────────────────────────────────────────────────────────
function my_is_real_frontend_request(): bool {
    // Skip WP cron (the source of your repeating empty logs)
    if ( defined('DOING_CRON') && DOING_CRON ) return false;
    
    // Skip admin-ajax.php requests
    if ( defined('DOING_AJAX') && DOING_AJAX ) return false;
    
    // Skip REST API requests
    if ( defined('REST_REQUEST') && REST_REQUEST ) return false;
    
    // Skip wp-admin pages
    if ( is_admin() ) return false;
    
    return true;
}


// ─────────────────────────────────────────────────────────────
// woocommerce_cart_loaded_from_session = cart is FULLY ready
// wp_loaded = cart might not have totals calculated yet
// ─────────────────────────────────────────────────────────────
add_action( 'woocommerce_cart_loaded_from_session', function() {

    if ( ! my_is_real_frontend_request() ) return;
    if ( ! current_user_can('manage_options') ) return;

    $session = WC()->session;
    $cart    = WC()->cart;

    // ── SESSION: decoded, human-readable ─────────────────────
    error_log( "\n" . str_repeat('=', 60) );
    error_log( '  SESSION  [' . date('H:i:s') . '] URL: ' . $_SERVER['REQUEST_URI'] );
    error_log( str_repeat('=', 60) );

    // Customer ID — this tells you guest vs logged-in
    $customer_id = $session->get_customer_id();
    $is_guest    = ! is_numeric( $customer_id ) || (int) $customer_id === 0;
    error_log( 'Customer ID : ' . $customer_id );
    error_log( 'User type   : ' . ( $is_guest ? 'GUEST (hash key)' : 'LOGGED IN (WP user ID)' ) );

    // Has session cookie?
    $cookie = $session->get_session_cookie();
    if ( $cookie ) {
        error_log( 'Cookie set  : YES' );
        error_log( 'Expires at  : ' . date('Y-m-d H:i:s', $cookie[1]) );
    } else {
        error_log( 'Cookie set  : NO (no cart interaction yet)' );
    }

    // Chosen payment + shipping — decoded from raw session
    error_log( 'Payment     : ' . ( $session->get('chosen_payment_method') ?: 'not chosen' ) );
    
    $shipping = $session->get('chosen_shipping_methods');
    error_log( 'Shipping    : ' . ( $shipping ? implode(', ', $shipping) : 'not chosen' ) );
    
    $coupons = $session->get('applied_coupons');
    error_log( 'Coupons     : ' . ( $coupons ? implode(', ', $coupons) : 'none' ) );
    
    // Customer address from session (decoded — not the raw serialised string)
    $customer = WC()->customer;
    error_log( 'Billing     : ' . $customer->get_billing_first_name() . ' ' . $customer->get_billing_last_name() );
    error_log( 'B. Email    : ' . $customer->get_billing_email() );
    error_log( 'B. Country  : ' . $customer->get_billing_country() );
    error_log( 'S. Country  : ' . $customer->get_shipping_country() );


    // ── CART ─────────────────────────────────────────────────
    error_log( "\n" . str_repeat('-', 60) );
    error_log( '  CART' );
    error_log( str_repeat('-', 60) );

    if ( $cart->is_empty() ) {
        error_log( '[CART EMPTY]' );
    } else {
        error_log( 'Item count  : ' . $cart->get_cart_contents_count() . ' units' );
        error_log( 'Subtotal    : ' . $cart->get_subtotal() );
        error_log( 'Tax         : ' . $cart->get_cart_tax() );
        error_log( 'Shipping    : ' . $cart->get_shipping_total() );
        error_log( 'Total       : ' . $cart->get_total('edit') );  // float, not string
        error_log( 'Needs ship  : ' . ( $cart->needs_shipping() ? 'yes' : 'no' ) );
        error_log( 'Needs pay   : ' . ( $cart->needs_payment() ? 'yes' : 'no' ) );
        
        error_log( '' );
        foreach ( $cart->get_cart() as $key => $item ) {
            /** @var WC_Product $product */
            $product = $item['data'];
            
            error_log( '  ── Item: ' . $product->get_name() );
            error_log( '     cart_key     : ' . $key );
            error_log( '     product_id   : ' . $item['product_id'] );
            error_log( '     variation_id : ' . ( $item['variation_id'] ?: 'n/a (simple)' ) );
            error_log( '     quantity     : ' . $item['quantity'] );
            error_log( '     product type : ' . $product->get_type() );
            error_log( '     product sku  : ' . ( $product->get_sku() ?: 'no sku' ) );
            error_log( '     stock status : ' . $product->get_stock_status() );
            error_log( '     stock qty    : ' . $product->get_stock_quantity() );
            error_log( '     price each   : ' . $product->get_price() );
            error_log( '     line_subtotal: ' . $item['line_subtotal'] . ' (before discount)' );
            error_log( '     line_total   : ' . $item['line_total']    . ' (after discount)' );
            error_log( '     line_tax     : ' . $item['line_tax'] );
            
            // Variation attributes (only for variable products)
            if ( ! empty( $item['variation'] ) ) {
                foreach ( $item['variation'] as $attr => $val ) {
                    error_log( '     attr         : ' . $attr . ' = ' . $val );
                }
            }
            
            // Any custom keys your plugin attached (gift_message etc.)
            $known_keys = ['key','product_id','variation_id','variation','quantity',
                           'data_hash','line_tax_data','line_subtotal','line_subtotal_tax',
                           'line_total','line_tax','data'];
            $custom_keys = array_diff_key( $item, array_flip($known_keys) );
            if ( $custom_keys ) {
                foreach ( $custom_keys as $k => $v ) {
                    error_log( '     [CUSTOM] ' . $k . ' : ' . print_r($v, true) );
                }
            }
        }
    }

    error_log( str_repeat('=', 60) . "\n" );

} );


// ─────────────────────────────────────────────────────────────
// Dedicated hook to watch session merge
// Only fires when someone actually logs in
// ─────────────────────────────────────────────────────────────
add_action( 'woocommerce_load_cart_from_session', function() {

    if ( ! my_is_real_frontend_request() ) return;
    if ( ! current_user_can('manage_options') ) return;

    error_log( '[MERGE HOOK] woocommerce_load_cart_from_session fired' );
    error_log( '[MERGE HOOK] customer_id = ' . WC()->session->get_customer_id() );
    error_log( '[MERGE HOOK] is_user_logged_in = ' . var_export(is_user_logged_in(), true) );

} );


// ─────────────────────────────────────────────────────────────
//  Watch when cart actually changes
// ─────────────────────────────────────────────────────────────

// Fires when something is added to cart
add_action( 'woocommerce_add_to_cart', function( $cart_item_key, $product_id, $quantity ) {
    error_log( '[ADD TO CART] product_id=' . $product_id . ' qty=' . $quantity . ' key=' . $cart_item_key );
}, 10, 3 );

// Fires when something is removed
add_action( 'woocommerce_remove_cart_item', function( $cart_item_key, $cart ) {
    error_log( '[REMOVE FROM CART] key=' . $cart_item_key );
}, 10, 2 );

// Fires when quantity is changed
add_action( 'woocommerce_after_cart_item_quantity_update', function( $cart_item_key, $quantity ) {
    error_log( '[QTY UPDATE] key=' . $cart_item_key . ' new_qty=' . $quantity );
}, 10, 2 );

// Fires when cart is cleared entirely
add_action( 'woocommerce_cart_emptied', function() {
    error_log( '[CART EMPTIED] cart was cleared' );
} );