<?php
/**
 * Plugin Name: Dev Product Seeder (CLI)
 */


// I have activated the plugin using this cli cmd
//wp plugin activate dev-product-seeder
// then i run this cmd to create variaty of products with different paramter to understand the topic in depth
//wp dev seed_products

if ( defined('WP_CLI') && WP_CLI ) {

    class Dev_Product_Seeder {

        public function seed( $args, $assoc_args ) {

            WP_CLI::log('Starting product seeding...');

            // 1. SIMPLE PRODUCTS
            for ($i = 1; $i <= 5; $i++) {
                $product = new WC_Product_Simple();
                $product->set_name("Simple Product $i");
                $product->set_regular_price(rand(50, 200));
                $product->set_stock_quantity(rand(1, 20));
                $product->set_manage_stock(true);
                $product->save();
            }

            WP_CLI::log('Simple products created');

            // 2. VARIABLE PRODUCT
            $parent = new WC_Product_Variable();
            $parent->set_name('Variable Product 1');
            $parent_id = $parent->save();

            $attribute = new WC_Product_Attribute();
            $attribute->set_name('Size');
            $attribute->set_options(['S', 'M', 'L']);
            $attribute->set_visible(true);
            $attribute->set_variation(true);

            $parent->set_attributes([$attribute]);
            $parent->save();

            foreach (['S', 'M', 'L'] as $size) {
                $variation = new WC_Product_Variation();
                $variation->set_parent_id($parent_id);
                $variation->set_regular_price(rand(100, 300));
                $variation->set_attributes(['Size' => $size]);
                $variation->set_stock_quantity(10);
                $variation->set_manage_stock(true);
                $variation->save();
            }

            WP_CLI::log('Variable product created');

            // 3. GROUPED PRODUCT
            $grouped = new WC_Product_Grouped();
            $grouped->set_name('Grouped Product 1');
            $grouped_id = $grouped->save();

            $child_ids = [];

            for ($i = 1; $i <= 2; $i++) {
                $child = new WC_Product_Simple();
                $child->set_name("Grouped Child $i");
                $child->set_regular_price(rand(30, 80));
                $child->set_stock_quantity(10);
                $child->set_manage_stock(true);
                $child_ids[] = $child->save();
            }

            $grouped->set_children($child_ids);
            $grouped->save();

            WP_CLI::log('Grouped product created');

            // 4. EXTERNAL PRODUCT
            $external = new WC_Product_External();
            $external->set_name('External Product 1');
            $external->set_product_url('https://example.com');
            $external->set_button_text('Buy External');
            $external->save();

            WP_CLI::log('External product created');

            // 5. EDGE CASE PRODUCTS

            // Out of stock
            $oos = new WC_Product_Simple();
            $oos->set_name('Out of Stock Product');
            $oos->set_regular_price(100);
            $oos->set_stock_quantity(0);
            $oos->set_manage_stock(true);
            $oos->set_stock_status('outofstock');
            $oos->save();

            // No price
            $noprice = new WC_Product_Simple();
            $noprice->set_name('No Price Product');
            $noprice->save();

            // High price
            $expensive = new WC_Product_Simple();
            $expensive->set_name('Expensive Product');
            $expensive->set_regular_price(9999);
            $expensive->save();

            WP_CLI::success('Seeding completed!');
        }
    }

    WP_CLI::add_command('dev seed_products', ['Dev_Product_Seeder', 'seed']);
}
