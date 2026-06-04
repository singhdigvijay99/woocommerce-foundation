Every product type is a class with overridden behavior
Type	       Class	              What changes
simple	   WC_Product_Simple	      baseline
variable   WC_Product_Variable  	variation selection logic
grouped	   WC_Product_Grouped	   multiple children
external	WC_Product_External	      no cart → redirects
subscription	(plugin)	          recurring logic


<?php
/**
 * Plugin Name: My WC Debug
 * Description: Learning WooCommerce internals
 */

// Hook into WordPress init so WC is loaded
add_action( 'wp', function() {
    if ( ! current_user_can('manage_options') ) return; // admin only
    
    // Get a product by ID — change 123 to a real product ID in your DB
    $product = wc_get_product( 123 );
    
    if ( ! $product ) return;
    
    // What type is it?
    error_log( 'Product type: ' . $product->get_type() );
    // Outputs: simple | variable | grouped | external | subscription
    
    // Type-specific checks
    error_log( 'Is purchasable: ' . var_export( $product->is_purchasable(), true ) );
    error_log( 'Is in stock: '    . var_export( $product->is_in_stock(), true ) );
    
    // For variable products — get all variation IDs
    if ( $product->is_type('variable') ) {
        $variation_ids = $product->get_children(); // array of variation post IDs
        error_log( 'Variations: ' . implode(', ', $variation_ids) );
        
        // Get a specific variation
        $variation = wc_get_product( $variation_ids[0] );
        error_log( 'Variation price: ' . $variation->get_price() );
        error_log( 'Variation attributes: ' . print_r( $variation->get_attributes(), true ) );
    }
    
    // For grouped products — get child product IDs
    if ( $product->is_type('grouped') ) {
        $children = $product->get_children();
        error_log( 'Grouped children: ' . print_r( $children, true ) );
    }
    
    // External/affiliate — has a URL, can't be added to cart normally
    if ( $product->is_type('external') ) {
        error_log( 'External URL: ' . $product->get_product_url() );
        error_log( 'Button text: ' . $product->get_button_text() );
    }
});
?>

Dedug log for simple product
[21-Apr-2026 07:50:31 UTC] Product type: simple
[21-Apr-2026 07:50:31 UTC] Is purchasable: true
[21-Apr-2026 07:50:31 UTC] Is in stock: true
[21-Apr-2026 08:02:36 UTC] Product type: simple
[21-Apr-2026 08:02:36 UTC] Is purchasable: true
[21-Apr-2026 08:02:36 UTC] Is in stock: true

debug log for variable product 
[21-Apr-2026 08:07:44 UTC] Product type: variable
[21-Apr-2026 08:07:44 UTC] Is purchasable: true
[21-Apr-2026 08:07:44 UTC] Is in stock: true
[21-Apr-2026 08:07:44 UTC] Variations: 50, 51, 52
[21-Apr-2026 08:07:44 UTC] Variation price: 119
[21-Apr-2026 08:07:44 UTC] Variation attributes: Array
(
    [size] => 
)

debug log for grouped product 
[21-Apr-2026 08:10:57 UTC] Product type: grouped
[21-Apr-2026 08:10:57 UTC] Is purchasable: false
[21-Apr-2026 08:10:57 UTC] Is in stock: true
[21-Apr-2026 08:10:57 UTC] Grouped children: Array
(
    [0] => 54
    [1] => 55
)


debug log for external product 
21-Apr-2026 08:16:08 UTC] Product type: external
[21-Apr-2026 08:16:08 UTC] Is purchasable: false
[21-Apr-2026 08:16:08 UTC] Is in stock: true
[21-Apr-2026 08:16:08 UTC] External URL: https://example.com
[21-Apr-2026 08:16:08 UTC] Button text: Buy External
[21-Apr-2026 08:16:09 UTC] Product type: external
[21-Apr-2026 08:16:09 UTC] Is purchasable: false
[21-Apr-2026 08:16:09 UTC] Is in stock: true
[21-Apr-2026 08:16:09 UTC] External URL: https://example.com
[21-Apr-2026 08:16:09 UTC] Button text: Buy External