<?php
// Never assume $order is a WP_Post. Code that does $order->post_title, 
// get_post_meta($order_id, ...), or queries orders via WP_Query will 
// silently fail or return wrong data when HPOS is active.
// Direct post access

global $post;
$order_id = $post->ID;
$status  = $post->post_status;
// Raw postmeta
get_post_meta( $order_id, '_billing_email', true );
// WP_Query on orders
new WP_Query([
  'post_type'   => 'shop_order',
  'post_status' => 'wc-completed',
]);
// instanceof check
if ( $order instanceof WP_Post ) { ... }

//Use WC abstractions only
// In hpos we always get order via WC
$order = wc_get_order( $order_id );

// We Use WC methods
$status = $order->get_status();
$email  = $order->get_billing_email();

// WC order query
wc_get_orders([
  'status' => 'completed',
  'limit'  => 20,
]);

//Correct instanceof check
if ( $order instanceof WC_Abstract_Order ) { ... }

// Legacy Mode
// wp_posts
// Order stored as shop_order post type. Meta in wp_postmeta.
// HPOS Mode
// wc_orders
// Dedicated table. Meta in wc_orders_meta. Posts table unused.
// Sync Mode
// Both Tables
// WC writes to both during transition. Allows gradual migration.
// Always Safe
// WC Abstraction
// wc_get_order() reads from the correct table regardless of mode

// These WC methods work in all modes, no need to check for HPOS:

$order = wc_get_order( $order_id );
$status = $order->get_status();
$email  = $order->get_billing_email();
wc_get_orders([
  'status' => 'completed',
  'limit'  => 20,
]);
///php — plugin compatibility declaration
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',  // feature slug
            __FILE__,               // your plugin main file
            true                    // compatible = true
        );
    }
} );

// ------ Order Meta -------
// Data attached to the whole order — not individual products. 
// Think: shipping method chosen, coupon applied, custom delivery note, affiliate referral ID.

// Order meta is stored in wc_orders_meta (HPOS) or wp_postmeta (legacy). 
// Always access it via $order->get_meta() — never get_post_meta().
// Writing Order Meta
// Save custom data to an order — must call save() at the end
// php — write order meta
// Hook into order creation
add_action( 'woocommerce_checkout_order_created', function( $order ) {

    // Store a single value
    $order->update_meta_data( '_affiliate_ref', sanitize_text_field( $_COOKIE['aff_ref'] ?? '' ) );

    // Store an array (serialized automatically)
    $order->update_meta_data( '_delivery_window', [
        'date' => sanitize_text_field( $_POST['delivery_date'] ?? '' ),
        'slot' => sanitize_text_field( $_POST['delivery_slot'] ?? '' ),
    ]);

    //MUST save — meta is only written to DB on save()
    $order->save();
} );

// Reading Order Meta
// All the ways to retrieve meta from an order object
// php — read order meta

$order = wc_get_order( $order_id );
if ( ! $order ) return;

// Single value — returns '' if missing
$ref = $order->get_meta( '_affiliate_ref' );

// Array value — auto-unserialized
$window = $order->get_meta( '_delivery_window' );
if ( is_array( $window ) ) {
    $date = $window['date'];
    $slot = $window['slot'];
}

// All meta at once (returns WC_Meta_Data[])
$all_meta = $order->get_meta_data();
foreach ( $all_meta as $meta ) {
    $meta->__get( 'key' );   // meta key
    $meta->__get( 'value' ); // meta value
}

// Delete meta
$order->delete_meta_data( '_affiliate_ref' );
$order->save();


// Querying Orders BY Meta Value
// Find orders that have specific meta — always use wc_get_orders()
// php — query by meta

// Find all orders with a specific affiliate ref
$orders = wc_get_orders([
    'limit'      => -1,
    'status'     => [ 'completed', 'processing' ],
    'meta_query' => [
        [
            'key'     => '_affiliate_ref',
            'value'   => 'SUMMER2024',
            'compare' => '=',
        ],
    ],
]);

foreach ( $orders as $order ) {
    error_log( 'Order #' . $order->get_id() );
}

// ------ Line Item Meta ---------
// Product variation attributes displayed to the customer in order emails and the My Account page.
//  Think: Color → Red, Size → XL. Stored on WC_Order_Item_Product
// Adding Line Item Meta at Checkout
// Attach custom data to a cart item that transfers to the order line item
//php — cart → order item meta

// Step 1: Store custom data in cart item
add_filter( 'woocommerce_add_cart_item_data', function( $cart_item_data, $product_id ) {
    if ( ! empty( $_POST['custom_engraving'] ) ) {
        $cart_item_data['custom_engraving'] = sanitize_text_field( $_POST['custom_engraving'] );
    }
    return $cart_item_data;
}, 10, 2 );

// Step 2: Transfer cart meta → order line item
add_action( 'woocommerce_checkout_create_order_line_item',
function( $item, $cart_item_key, $values, $order ) {
    if ( isset( $values['custom_engraving'] ) ) {
        // 3rd param = display label shown to customer
        $item->add_meta_data(
            'Engraving Text',          // displayed key
            $values['custom_engraving'], // value
            true                        // unique
        );
    }
}, 10, 4 );

// Reading Line Item Meta
// Iterating order items and pulling their attributes
// php — read line item meta

$order = wc_get_order( $order_id );

foreach ( $order->get_items() as $item_id => $item ) {

    // Always type-check before accessing product methods
    if ( ! $item instanceof WC_Order_Item_Product ) continue;

    $product_name = $item->get_name();
    $quantity     = $item->get_quantity();

    // Read a specific meta key
    $engraving = $item->get_meta( 'Engraving Text' );

    // Read variation attribute (taxonomy-based)
    $color = $item->get_meta( 'pa_color' );
    $size  = $item->get_meta( 'pa_size' );

    // ALL meta for this item (WC_Meta_Data[])
    $all = $item->get_all_formatted_meta_data();
    // Returns label => value pairs, respects hidden meta
}

// Controlling What's Displayed to Customers
// Hide internal keys, rename labels in emails and My Account
// php — hide/rename line item meta display

// // Hide specific keys from customer-facing display

add_filter( 'woocommerce_hidden_order_itemmeta', function( $hidden_keys ) {
    $hidden_keys[] = '_internal_cost_override';
    $hidden_keys[] = '_plugin_tracking_id';
    return $hidden_keys;
} );

// Rename / translate attribute labels in order display
add_filter( 'woocommerce_attribute_label', function( $label, $name ) {
    if ( $name === 'pa_color' ) return 'Colour';
    return $label;
}, 10, 2 );


// ------ Order Item Meta -------

// Internal/hidden data attached to any order item — including fee lines, shipping lines, and tax lines. 
// Stored in wp_woocommerce_order_itemmeta. Not shown to customers by default.

// Order item meta ≠ Line item meta even though both live on items. 
// Line item meta is customer-visible product attributes. 
// Order item meta is internal data on any item type (fees, shipping, taxes too).

// All Item Types & Their APIs
// WooCommerce order items come in 5 types — each uses the same get_meta() API
// php — iterating all item types


$order = wc_get_order( $order_id );

// Product line items
foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
    $cost_override = $item->get_meta( '_cost_override' );
}

// Shipping line items
foreach ( $order->get_items( 'shipping' ) as $item_id => $item ) {
    // WC_Order_Item_Shipping
    $carrier     = $item->get_method_title();
    $tracking_no = $item->get_meta( '_tracking_number' );
}

// Fee line items
foreach ( $order->get_items( 'fee' ) as $item_id => $item ) {
    // WC_Order_Item_Fee
    $fee_reason = $item->get_meta( '_fee_reason' );
}

// Coupon lines
foreach ( $order->get_items( 'coupon' ) as $item_id => $item ) {
    $coupon_code = $item->get_code();
    $discount    = $item->get_discount();
}


// Adding Custom Fee with Meta
// A real pattern: dynamic surcharge based on payment method, attached to the fee item
// php — fee + item meta

add_action( 'woocommerce_checkout_order_created', function( $order ) {

    $payment_method = $order->get_payment_method();

    if ( $payment_method === 'cod' ) {

        // Create fee item
        $fee = new WC_Order_Item_Fee();
        $fee->set_name( 'Cash on Delivery Surcharge' );
        $fee->set_amount( 50 );  // ₹50
        $fee->set_total( 50 );
        $fee->set_tax_class( '' );
        $fee->set_tax_status( 'none' );

        // Attach internal meta to the fee item
        $fee->add_meta_data( '_fee_type', 'payment_surcharge', true );
        $fee->add_meta_data( '_applied_at', current_time( 'mysql' ), true );

        $order->add_item( $fee );
        $order->calculate_totals();
        $order->save();
    }
} );

// Shipment Tracking on Shipping Item
// Attach tracking number directly to the shipping line item — not the order
// php — shipping item meta for tracking

function save_tracking_to_shipping_item( $order_id, $tracking_number ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) return;

    foreach ( $order->get_items( 'shipping' ) as $item_id => $item ) {
        $item->update_meta_data( '_tracking_number', $tracking_number );
        $item->update_meta_data( '_tracking_carrier', 'BlueDart' );
        $item->update_meta_data( '_shipped_at', current_time( 'mysql' ) );
        $item->save(); // item has its own save()
        break; // usually only one shipping item
    }
}

// Read it back later
function get_tracking_from_order( $order_id ) {
    $order = wc_get_order( $order_id );
    foreach ( $order->get_items( 'shipping' ) as $item ) {
        return [
            'number'  => $item->get_meta( '_tracking_number' ),
            'carrier' => $item->get_meta( '_tracking_carrier' ),
        ];
    }
    return null;
}



// Quick Reference: Key → Where to Store It
// Meta Key                      Use Case.                                                       Location
// _affiliate_ref        Affiliate/referral code tracked from cookie at checkout                Order Meta
// _delivery_date       Customer-chosen delivery date from checkout field                       Order Meta
// _po_number            B2B purchase order number entered at checkout                          Order Meta
// _gift_message          Gift wrapping message for the whole order                             Order Meta
// _source_channel       UTM source / marketing channel recorded at order time                  Order Meta
// pa_color              Product color variation attribute (taxonomy)                           Line Item Meta
// pa_size                Product size variation attribute                                      Line Item Meta
// Engraving Text         Custom text engraved on product — shown in emails                     Line Item Meta
// Personalisation         Print-on-demand custom text visible to customer                      Line Item Meta
// _tracking_number        Courier tracking ID on the shipping line item                        Order Item Meta
// _fee_reason            Internal reason code attached to a fee item                           Order Item Meta
// _cost_override         Manual cost override on a product line item (admin only)              Order Item Meta

// Full Real Example: Gift Message + Wrapping Fee
// Order meta for the message + fee item meta for the fee type — combined
// php — complete gift order example

// 1. Capture checkout field → order meta
add_action( 'woocommerce_checkout_order_created', function( $order ) {

    if ( ! empty( $_POST['gift_message'] ) ) {
        $order->update_meta_data( '_gift_message', sanitize_textarea_field( $_POST['gift_message'] ) );
        $order->update_meta_data( '_is_gift', 'yes' );
    }

    if ( ! empty( $_POST['gift_wrap'] ) ) {

        // Add gift wrap fee item
        $fee = new WC_Order_Item_Fee();
        $fee->set_name( 'Gift Wrapping' );
        $fee->set_total( 99 );
        $fee->set_tax_status( 'none' );

        // Internal meta on the fee item itself
        $fee->add_meta_data( '_fee_type', 'gift_wrap', true );

        $order->add_item( $fee );
        $order->calculate_totals();
    }

    $order->save();
} );

// 2. Show gift message in admin order page
add_action( 'woocommerce_admin_order_data_after_billing_address', function( $order ) {
    $msg = $order->get_meta( '_gift_message' );
    if ( $msg ) {
        echo '<p><strong>Gift Message:</strong> ' . esc_html( $msg ) . '</p>';
    }
} );

// 3. Include gift message in order confirmation email
add_action( 'woocommerce_email_order_meta', function( $order, $sent_to_admin ) {
    if ( $sent_to_admin ) return;
    $msg = $order->get_meta( '_gift_message' );
    if ( $msg ) {
        echo '<p><em>Your gift message: </em>' . esc_html( $msg ) . '</p>';
    }
}, 10, 2 );

// Decision Tree
// Is this about the whole order?
// → Order Meta
// Is this a product attribute shown to customer?
// → Line Item Meta
// Is this internal data on a fee/shipping/coupon item?
// → Order Item Meta
// Is this internal data on a product item (not for customers)?
// → Order Item Meta (hidden)
// 
// Golden Rules
// Always use wc_get_order()
// Always call ->save() after writes
// Use instanceof WC_Abstract_Order
// Declare HPOS compatibility
// Sanitize all input before saving
// Never get_post_meta() on orders
// Never WP_Query for orders
// Never $order->post_* properties