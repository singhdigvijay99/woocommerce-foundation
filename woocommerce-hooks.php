<?php 
//woocommerce_checkout_process
//Runs before order is created
//Use: validation
add_action( 'woocommerce_checkout_process', function() {
    if ( empty($_POST['custom_field']) ) {
        wc_add_notice( 'Custom field is required', 'error' );
    }
});
//key concept: we are blocking checkout here

//woocommerce_checkout_update_order_meta
//Runs after order is created
//Use: save custom data
add_action( 'woocommerce_checkout_update_order_meta', function( $order_id ) {
    if ( ! empty($_POST['custom_field']) ) {
        update_post_meta( $order_id, '_custom_field', sanitize_text_field($_POST['custom_field']) );
    }
});
//Important: In HPOS, we better use $order->update_meta_data()

// woocommerce_checkout_fields
// Filter to modify checkout fields UI
add_filter( 'woocommerce_checkout_fields', function( $fields ) {
    $fields['billing']['billing_custom'] = [
        'type' => 'text',
        'label' => 'Custom Field',
        'required' => true,
    ];
    return $fields;
});


// woocommerce_order_status_changed
// Fires whenever status changes
add_action( 'woocommerce_order_status_changed', function( $order_id, $old, $new ) {
    if ( $new === 'completed' ) {
        // Trigger logic
    }
}, 10, 3 );

//Use cases: CRM sync ,Notifications , Analytics

//woocommerce_payment_complete
add_action( 'woocommerce_payment_complete', function( $order_id ) {
    // Payment successful
});

//Difference: Payment success ≠ order completed

//woocommerce_add_to_cart_validation
add_filter( 'woocommerce_add_to_cart_validation', function( $passed, $product_id ) {
    if ( $product_id == 123 ) {
        wc_add_notice( 'This product cannot be added', 'error' );
        return false;
    }
    return $passed;
}, 10, 2 );

//woocommerce_cart_item_price
add_filter( 'woocommerce_cart_item_price', function( $price, $cart_item ) {
    return $price . ' (customized)';
}, 10, 2 );


Cart & Pricing
//Modify price dynamically
add_action( 'woocommerce_before_calculate_totals', function( $cart ) {
    foreach ( $cart->get_cart() as $item ) {
        $item['data']->set_price( 50 );
    }
});

//Add custom data to cart item
add_filter( 'woocommerce_add_cart_item_data', function( $cart_item_data, $product_id ) {
    $cart_item_data['custom'] = 'value';
    return $cart_item_data;
}, 10, 2 );

//Restore cart item data
add_filter( 'woocommerce_get_cart_item_from_session', function( $item, $values ) {
    if ( isset($values['custom']) ) {
        $item['custom'] = $values['custom'];
    }
    return $item;
}, 10, 2 );
//--- Order & Metadata ----

//Add meta to order items (line items)
add_action( 'woocommerce_checkout_create_order_line_item', function( $item, $cart_item_key, $values, $order ) {
    if ( isset($values['custom']) ) {
        $item->add_meta_data( 'Custom', $values['custom'] );
    }
}, 10, 4 );

// Few Other VERY important hook and filters that are helpful in real projects

// --- Payment & Thank You Page ----
/// Thank you page
add_action( 'woocommerce_thankyou', function( $order_id ) {
    echo "Thanks for your order!";
});

//Product Page Hooks
//Before add to cart button
add_action( 'woocommerce_before_add_to_cart_button', function() {
    echo '<p>Extra info</p>';
});

//Account / My Account
add_filter( 'woocommerce_account_menu_items', function( $items ) {
    $items['custom'] = 'My Custom Tab';
    return $items;
});

//Priority & Execution Order
add_action( 'hook', 'func1', 5 );
add_action( 'hook', 'func2', 10 );

// func1 runs first

// Common Mistakes (must be avoid)
// Not returning value in filter
add_filter( 'woocommerce_checkout_fields', function( $fields ) {
    // forgot return → breaks checkout
});

// Using wrong hook timing
// Trying to access order before it's created
// Modifying cart after totals calculated
// Mixing meta APIs
// update_post_meta vs $order->update_meta_data()
