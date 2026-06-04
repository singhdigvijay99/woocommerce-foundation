<?php 
// wc_get_logger() — Proper WC Logging with Source Channels
// WooCommerce has its own logging system. 
// Logs appear in WooCommerce → Status → Logs, filterable by the source you define.
$logger  = wc_get_logger();
$context = ['source' => 'my-plugin']; // this becomes the filename/filter in WC logs

$logger->debug('This is a debug message', $context);
$logger->info('Order processing started', $context);
$logger->warning('Coupon applied but cart total is zero', $context);
$logger->error('Payment gateway did not respond', $context);
$logger->critical('Database write failed for order meta', $context); 
?>
Method                      When to use
debug()          Development only — variable dumps, flowtraces
info()        Normal events — webhook received, order created
warning()    Something unexpected but recoverable
error()       Something failed — gateway timeout, missing data
critical()      System-level failure — DB error, fatal condition
<?php 
// examples 
add_action('woocommerce_api_my_gateway', function() {
    $logger  = wc_get_logger();
    $context = ['source' => 'my-gateway-webhook'];

    $payload = file_get_contents('php://input');
    $data    = json_decode($payload, true);

    $logger->info('Webhook received', $context);
    $logger->debug('Raw payload: ' . print_r($data, true), $context);

    if (empty($data['order_id'])) {
        $logger->error('Missing order_id in payload', $context);
        http_response_code(400);
        exit;
    }

    $order = wc_get_order($data['order_id']);

    if (!$order) {
        $logger->error('Order not found: ' . $data['order_id'], $context);
        http_response_code(404);
        exit;
    }

    $logger->info('Processing payment for order: ' . $order->get_id(), $context);
});


// Order Notes as a Live Debug Trail
// Order notes are the best way to attach a permanent, human-readable audit trail directly to an order.
// Visible in WP Admin on the order page, searchable, timestamped.
// Two Types of Notes

$order = wc_get_order($order_id);

// Customer-visible note (shows in My Account → Orders)
$order->add_order_note('Your order has been dispatched.', true);

// Internal-only note (only visible in WP Admin)
$order->add_order_note('Webhook received. Payload ID: ' . $payload_id);
// second param defaults to false = internal only

add_action('woocommerce_api_my_gateway', function() {
    $payload = json_decode(file_get_contents('php://input'), true);
    $order   = wc_get_order($payload['order_id'] ?? 0);

    if (!$order) return;

    // Log every step as an order note
    $order->add_order_note('Webhook received. Event: ' . $payload['event']);
    $order->add_order_note('Gateway transaction ID: ' . $payload['transaction_id']);

    if ($payload['status'] === 'paid') {
        $order->payment_complete($payload['transaction_id']);
        $order->add_order_note('Payment confirmed by gateway. Marking complete.');
    } else {
        $order->update_status('failed');
        $order->add_order_note(
            'Payment failed. Gateway reason: ' . $payload['failure_reason']
        );
    }
});
// Now when a customer reports "my payment didn't go through", you open the order and see the entire timeline without touching a log file.
// Pro Tip — Add Notes on Status Changes Too

phpadd_action('woocommerce_order_status_changed', function($order_id, $from, $to, $order) {
    $order->add_order_note(
        sprintf(
            'Status changed from %s → %s by %s',
            $from,
            $to,
            is_user_logged_in() ? wp_get_current_user()->user_login : 'system'
        )
    );
}, 10, 4);
?>
//WC System Status Report — Read This First
Before debugging ANY customer-reported issue, go to WooCommerce → Status → System Status. It shows you:

PHP version, memory limit, max execution time
Active theme + whether it overrides WC templates
All active plugins + versions
WC database version vs code version (mismatch = update didn't run)
Cron status — if cron is broken, order emails won't send

What to look for
# Red flags in System Status:

WooCommerce database version: 7.0.0 / WooCommerce version: 8.5.0
   → DB update didn't run. Go to WC → Status → Tools → Update database

Template overrides detected: checkout/form-checkout.php (outdated)
   → Your theme's template is older than the current WC version
   → This causes mysterious checkout bugs

PHP max_input_vars: 100
   → Large orders/variable products will silently truncate POST data

wp_cron: disabled (DISABLE_WP_CRON = true)
   → If you disabled cron without a real cron job, scheduled emails won't send

SECURE_AUTH_KEY: not set
   → Site wasn't properly configured
Programmatically Access System Status (useful for debug plugins)
<?php 
// Get WC environment data programmatically
add_action('admin_init', function() {
    if (!current_user_can('manage_woocommerce')) return;

    $status = new WC_REST_System_Status_Controller();
    // Or just grab individual environment values:

    $environment = [
        'php_version'     => phpversion(),
        'wp_version'      => get_bloginfo('version'),
        'wc_version'      => WC()->version,
        'wc_db_version'   => get_option('woocommerce_db_version'),
        'memory_limit'    => WP_MEMORY_LIMIT,
        'max_input_vars'  => ini_get('max_input_vars'),
    ];

    if ($environment['wc_version'] !== $environment['wc_db_version']) {
        wc_get_logger()->warning(
            'WC version mismatch: code=' . $environment['wc_version'] .
            ' db=' . $environment['wc_db_version'],
            ['source' => 'system-check']
        );
    }
});

//Tracing a Full Checkout — Three Hook Points
This is the most useful debugging pattern. You instrument three hooks to get a complete picture of the checkout lifecycle.
woocommerce_checkout_process
        ↓  (POST data arrives, validation runs)
woocommerce_checkout_order_processed
        ↓  (order object created, items added)
woocommerce_payment_complete
        ↓  (payment confirmed, order marked processing/complete)
Complete Checkout Trace
php$logger  = wc_get_logger();
$context = ['source' => 'checkout-trace'];

// --- HOOK 1: Raw POST data ---
add_action('woocommerce_checkout_process', function() use ($logger, $context) {
    $logger->debug('--- CHECKOUT PROCESS START ---', $context);
    $logger->debug('POST data: ' . print_r($_POST, true), $context);

    // Spot what fields are missing or malformed
    $required = ['billing_first_name', 'billing_email', 'billing_phone'];
    foreach ($required as $field) {
        $value = isset($_POST[$field]) ? $_POST[$field] : 'MISSING';
        $logger->debug("$field = $value", $context);
    }
});

// --- HOOK 2: Order object created ---
add_action('woocommerce_checkout_order_processed', function($order_id, $posted_data, $order) use ($logger, $context) {
    $logger->debug('--- ORDER CREATED: #' . $order_id . ' ---', $context);
    $logger->debug('Order status: ' . $order->get_status(), $context);
    $logger->debug('Order total: ' . $order->get_total(), $context);
    $logger->debug('Payment method: ' . $order->get_payment_method(), $context);

    // Log each line item
    foreach ($order->get_items() as $item) {
        $logger->debug(
            'Item: ' . $item->get_name() .
            ' x' . $item->get_quantity() .
            ' = ' . $item->get_total(),
            $context
        );
    }

    // Log applied coupons
    foreach ($order->get_coupon_codes() as $coupon) {
        $logger->debug('Coupon applied: ' . $coupon, $context);
    }
}, 10, 3);

// --- HOOK 3: Payment confirmed ---
add_action('woocommerce_payment_complete', function($order_id) use ($logger, $context) {
    $order = wc_get_order($order_id);
    $logger->debug('--- PAYMENT COMPLETE: #' . $order_id . ' ---', $context);
    $logger->debug('Final status: ' . $order->get_status(), $context);
    $logger->debug('Transaction ID: ' . $order->get_transaction_id(), $context);
    $logger->debug('Date paid: ' . $order->get_date_paid(), $context);

    $order->add_order_note(
        'Debug trace: payment complete. Transaction ID: ' . $order->get_transaction_id()
    );
});

///how to test it :
///Now place a test order. Go to WC → Status → Logs → filter by checkout-trace. You'll see the entire lifecycle in sequence.

// -----  Testing Payment Gateways Safely. ----------
//BACS (Bank Transfer) for Offline Testing
// BACS puts order in "on-hold" — useful for testing post-payment hooks
// No credentials needed, works out of the box

add_action('woocommerce_order_status_on-hold', function($order_id) {
    $logger = wc_get_logger();
    $logger->info(
        'Order ' . $order_id . ' is on-hold (BACS). Awaiting payment confirmation.',
        ['source' => 'bacs-test']
    );
});

// Manually trigger payment_complete to simulate bank confirmation:
add_action('admin_init', function() {
    if (!isset($_GET['test_payment_complete'])) return;

    $order_id = absint($_GET['test_payment_complete']);
    $order    = wc_get_order($order_id);

    if ($order && $order->get_status() === 'on-hold') {
        $order->payment_complete('FAKE-TXN-' . time());
        $order->add_order_note('Manually triggered payment_complete for testing.');
        wp_die('Done — order ' . $order_id . ' marked complete.');
    }
});

// Usage: /wp-admin/?test_payment_complete=123

//Stripe Test Mode Verification
// Before running any Stripe test, verify you're in test mode
add_action('admin_notices', function() {
    if (!current_user_can('manage_woocommerce')) return;

    $stripe_settings = get_option('woocommerce_stripe_settings', []);

    if (
        isset($stripe_settings['enabled'], $stripe_settings['testmode']) &&
        $stripe_settings['enabled'] === 'yes' &&
        $stripe_settings['testmode'] !== 'yes'
    ) {
        echo '<div class="notice notice-error">
            <p><strong>⚠️ Stripe is in LIVE mode.</strong> Switch to test mode before testing.</p>
        </div>';
    }
});

Never Log Real Card Data — Sanitize Before Logging


add_action('woocommerce_checkout_process', function() {
    $logger  = wc_get_logger();
    $context = ['source' => 'checkout-safe-trace'];

    //NEVER do this — logs card data
    // $logger->debug(print_r($_POST, true), $context);

    // Whitelist only safe fields
    $safe_fields = [
        'billing_first_name',
        'billing_last_name',
        'billing_email',
        'billing_country',
        'payment_method',
        'order_comments',
    ];

    $safe_post = array_intersect_key($_POST, array_flip($safe_fields));
    $logger->debug('Safe POST: ' . print_r($safe_post, true), $context);
});

///Reproducing a Customer's Order Locally

Step 1 — Export the order via WC REST API

bash# Get a specific order from production
curl -u consumer_key:consumer_secret \
  https://yoursite.com/wp-json/wc/v3/orders/1234 \
  > order_1234.json

Step 2 — Import on local
// Read the exported JSON and recreate order locally
add_action('admin_init', function() {
    if (!isset($_GET['import_test_order'])) return;

    $json  = file_get_contents(get_template_directory() . '/order_1234.json');
    $data  = json_decode($json, true);

    $order = wc_create_order();

    // Billing address
    $order->set_address([
        'first_name' => $data['billing']['first_name'],
        'last_name'  => $data['billing']['last_name'],
        'email'      => $data['billing']['email'],
        'phone'      => $data['billing']['phone'],
        'address_1'  => $data['billing']['address_1'],
        'city'       => $data['billing']['city'],
        'postcode'   => $data['billing']['postcode'],
        'country'    => $data['billing']['country'],
    ], 'billing');

    // Line items
    foreach ($data['line_items'] as $item) {
        $product = wc_get_product($item['product_id']);
        if ($product) {
            $order->add_product($product, $item['quantity']);
        }
    }

    $order->calculate_totals();
    $order->add_order_note('Imported from production for local debugging.');
    $order->save();

    wp_die('Test order created: #' . $order->get_id());
});

// Trigger: /wp-admin/?import_test_order=1
//Step 3 — Replay a Webhook Locally
// Simulate a gateway webhook hitting your local site
// Run this from command line or a test script

add_action('admin_init', function() {
    if (!isset($_GET['replay_webhook'])) return;

    $order_id = absint($_GET['order_id'] ?? 0);
    $order    = wc_get_order($order_id);

    if (!$order) wp_die('Order not found');

    // Build a fake payload matching your gateway's format
    $fake_payload = json_encode([
        'event'          => 'payment.success',
        'order_id'       => $order_id,
        'transaction_id' => 'TEST-' . time(),
        'status'         => 'paid',
        'amount'         => $order->get_total(),
    ]);

    // Send it to your own webhook endpoint
    $response = wp_remote_post(home_url('/wc-api/my_gateway/'), [
        'body'    => $fake_payload,
        'headers' => ['Content-Type' => 'application/json'],
        'timeout' => 15,
    ]);

    $logger = wc_get_logger();
    $logger->debug(
        'Webhook replay response: ' . print_r(wp_remote_retrieve_body($response), true),
        ['source' => 'webhook-replay']
    );

    wp_die('Webhook replayed. Check logs and order #' . $order_id);
});

// Trigger: /wp-admin/?replay_webhook=1&order_id=123

The Full Debugging Workflow in One Picture
Customer reports issue
        ↓
1. WC → Status → System Status    ← versions, templates, PHP, cron
        ↓
2. WC → Status → Logs             ← filter by your source channel
        ↓
3. Open the order in WP Admin     ← read order notes timeline
        ↓
4. Reproduce locally               ← import order, replay webhook
        ↓
5. Add checkout trace hooks        ← instrument the three hook points
        ↓
6. Place test order with BACS      ← safe, no credentials needed
        ↓
7. Read logs + order notes         ← find exact failure point

The key discipline is: order notes for what happened, WC logger for why it happened. 
Together they give you a complete picture without needing to reproduce the issue live.