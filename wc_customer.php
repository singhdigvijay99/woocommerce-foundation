
In WooCommerce, a WC_Customer is a PHP class object (WC_Customer{}) used by developers to represent, retrieve, and 
update customer data—such as billing/shipping addresses, payment tokens, and historical order data—for both 
logged-in users and guest shoppers.
Key Aspects of WC_Customer:
Data Access: Allows accessing information through methods like $customer->get_billing_address()
or $customer->get_total_spent().
Context: It helps distinguish between logged-in users and guest customers by handling their data specifically.
Usage: It is commonly used in custom coding, theme development, and API interactions to manage user 
information effectively.
Integration: It interacts directly with WC_Customer_Data_Store for saving and retrieving customer details

Three Customer States
WooCommerce always has a "customer" — but who that customer is, and where their data lives, changes completely depending on 
whether they're logged in, browsing as a guest, or just placed an order without an account.
State 01
Logged-In Customer
Has a WordPress user account. Data persists in wp_users + wp_usermeta. Accessed via WC_Customer($user_id).
State 02
Session Guest
Browsing, cart active, not logged in. Data lives in WooCommerce session (DB or cookie). No user ID yet.
State 03
Order-Only Guest
Completed checkout without account. Data is frozen on the order itself. Session is gone after checkout.
Transition
Account Creation
Guest → registered user. WC migrates session data and links past orders to new account.
Where Data Lives
State	                Storage
Logged In	          wp_usermeta
Session Guest	      woocommerce_sessions table
Order Guest	Order     meta — frozen
Session Fallback	  Browser cookie (woocommerce_customer)
Customer Identifier
State	                ID Used
Logged In	       WordPress $user_id (integer)
Session Guest	   Random session key (string)
Order Guest	       Order billing email
Current User	   get_current_user_id() → 0 if guest

<?php 
// What happens to customer data from first visit to completed order
// php — lifecycle

// ── VISIT 1: Anonymous guest arrives ─────────────────────────────
// WC creates a session, assigns a random customer_id in the session
$session_id = WC()->session->get_customer_id(); // e.g. "t_abc123xyz"

// ── VISIT 2: Guest adds to cart ──────────────────────────────────
// Cart data saved to session; WC_Customer populated from session
$customer = WC()->customer; // WC_Customer — loaded from session

// ── CHECKOUT: Guest fills billing form ───────────────────────────
// Data available in session until order is placed
$email = WC()->customer->get_email();

// ── ORDER PLACED ─────────────────────────────────────────────────
// Session data written to order meta; session destroyed after
$order->get_billing_email(); // data now lives here permanently

// ── REGISTERS ACCOUNT LATER ──────────────────────────────────────
// woocommerce_created_customer fires, WC links orders by email
add_action( 'woocommerce_created_customer', function( $customer_id, $new_customer_data ) {
    // Past guest orders now tied to this user_id
    $customer = new WC_Customer( $customer_id );
}, 10, 2 ); 
?>

Three ways to get a WC_Customer — each for a different context
php — instantiation
<?php 
// 1. Current logged-in customer (most common)
$customer = WC()->customer;
//    → WC_Customer loaded from session if guest
//    → WC_Customer loaded from usermeta if logged in

// 2. Specific user by ID (backend, reports, admin)
$customer = new WC_Customer( $user_id );
//    → Always reads from usermeta
//    → Throws exception if user doesn't exist

// 3. Current user, no fallback to session
$user_id  = get_current_user_id(); // 0 if guest
if ( $user_id ) {
    $customer = new WC_Customer( $user_id );
} else {
    // Guest — use session customer
    $customer = WC()->customer;
} 
?>
All Key WC_Customer Methods
Getters, setters, and helpers
Identity
get_id() ->WordPress user ID. Returns 0 for guests.
get_email() -> Email address — from usermeta or session checkout fields.
get_username() -> WordPress username. Empty for guests.
get_first_name() -> First name from billing or account fields.
get_last_name() -> Last name.
get_display_name() -> Display name from WP profile.
Billing Address  
Billing first name. -> get_billing_first_name()
Billing last name.  -> get_billing_last_name()
get_billing_email() -> Billing email — may differ from account email.
get_billing_phone() -> Phone number.
get_billing_address_1() -> Street address line 1.
get_billing_city() -> City.
get_billing_state() -> State/Province code.
get_billing_postcode() -> Postcode / ZIP.
get_billing_country() -> 2-letter country code, e.g. IN, US.
Shipping Address
get_shipping_address_1() -> Separate shipping street address.
get_shipping_city() -> Shipping city.
get_shipping_country() -> Shipping country code.
get_shipping_postcode() -> Shipping ZIP.
Orders & Totals
get_order_count() -> Total number of orders placed. Returns 0 for guests.
get_total_spent() -> Lifetime spend (float). Returns 0 for guests.
get_last_order() -> Returns the most recent WC_Order or false.
get_date_created() -> Account creation WC_DateTime.
Account creation WC_DateTime.
get_is_paying_customer() -> Returns true if they've completed at least one paid order.
Write Methods
set_billing_*(value) -> Setter for each billing field. Always call save() after.
set_shipping_*(value) -> Setter for each shipping field.
save() -> Persists changes to wp_usermeta (logged-in) or session (guest).

Reading & Writing Customer Data
<?php
$customer = new WC_Customer( $user_id );

// ── READ ─────────────────────────────────────────────────────────
$email       = $customer->get_email();
$city        = $customer->get_billing_city();
$country     = $customer->get_billing_country(); // "IN"
$total_spent = $customer->get_total_spent();    // float
$order_count = $customer->get_order_count();
$is_paying   = $customer->get_is_paying_customer(); // bool

// Custom meta (stored in wp_usermeta)
$loyalty_pts = $customer->get_meta( 'loyalty_points' );

// ── WRITE ────────────────────────────────────────────────────────
$customer->set_billing_city( 'Mumbai' );
$customer->set_billing_country( 'IN' );
$customer->update_meta_data( 'loyalty_points', 250 );

// Must call save() or changes are lost
$customer->save();

// ── CHECK if customer exists before instantiating ─────────────────
if ( get_user_by( 'id', $user_id ) ) {
    $customer = new WC_Customer( $user_id );
}

//---- Custom Customer Meta — The Right Way /
// Storing your own data on a customer vs using raw get_user_meta
// php — custom meta via WC_Customer

// Always use WC_Customer API for WC context
$customer = new WC_Customer( $user_id );
$customer->update_meta_data( 'preferred_payment', 'upi' );
$customer->save();

// Read it back
$pref = $customer->get_meta( 'preferred_payment' );

// ── For non-WC meta, using WP directly is fine ───────────────────
// (e.g. plugin-specific data unrelated to commerce)
update_user_meta( $user_id, 'newsletter_subscribed', 'yes' );
$sub = get_user_meta( $user_id, 'newsletter_subscribed', true );

// ── Never do this for WC fields: ─────────────────────────────────
// get_user_meta( $user_id, 'billing_city', true );
// update_user_meta( $user_id, 'billing_email', $email );



// ---------  Customer Session Keys ---------------------------------
// WooCommerce maintains a session object for every visitor.
// It's a key-value store — think of it as a temporary database row per browser. 
// It powers the cart, checkout fields, notices, and any data you want to persist across page loads for a guest.
// The session is stored in wp_woocommerce_sessions (a custom table, not wp_options or PHP sessions). 
// The session key is stored in a cookie named woocommerce_session_{COOKIEHASH}. 
// It expires after 48 hours of inactivity by default.
// ----------  Reading & Writing Session Data ----------------------------

// ── WRITE to session ─────────────────────────────────────────────
WC()->session->set( 'my_plugin_flag', 'yes' );
WC()->session->set( 'selected_store', 42 );
WC()->session->set( 'custom_cart_data', [
    'promo'   => 'SUMMER10',
    'applied' => true,
]);

// ── READ from session ─────────────────────────────────────────────
$flag  = WC()->session->get( 'my_plugin_flag' );
$store = WC()->session->get( 'selected_store', 0 ); // 2nd param = default
$data  = WC()->session->get( 'custom_cart_data', [] );

// ── DELETE from session ───────────────────────────────────────────
WC()->session->__unset( 'my_plugin_flag' );

// ── Always check session exists first ─────────────────────────────
if ( isset( WC()->session ) && WC()->session->has_session() ) {
    $value = WC()->session->get( 'my_key' );
}
// ----------  Core WooCommerce Session Keys ---------------------------------
// Keys WooCommerce itself stores in the session — knowing these prevents collisions and helps you read them
Key              Type          Description
cart             array        All cart items — cart item key → item data array.
cart_totals      array        Calculated totals: subtotal, tax, shipping, total.
applied_coupons  array        Array of coupon codes currently applied.
coupon_discount_totals array   Discount amounts per coupon code.
shipping_for_package_0 array    available shipping methods for the primary package.
chosen_shipping_methods array    Method IDs the customer selected.
chosen_payment_method string     Payment method ID e.g. razorpay, cod.
customer   array        Guest billing/shipping fields typed so far at checkout.
wc_notices array      Flash notices (error/success/info) shown on next page load.
order_awaiting_payment int Order ID pending payment — prevents duplicate orders.
reload_checkout bool Forces a full checkout page reload.

// ------- Session Lifecycle & Expiry -----

// Default expiry: 48 hours — customise it
add_filter( 'wc_session_expiring', function() {
    return 60 * 60 * 24; // 24 hours before expiry warning
} );
add_filter( 'wc_session_expiration', function() {
    return 60 * 60 * 72; // 72 hours total lifetime
} );

// Manually destroy a session (e.g. after fraud detection)
add_action( 'wp', function() {
    if ( some_fraud_condition() ) {
        WC()->session->destroy_session();
    }
} );

// Check if a session currently exists for this visitor
$has_session = WC()->session->has_session(); // bool

// Get the session customer ID (random string for guests)
$session_customer_id = WC()->session->get_customer_id();
// Returns: WP user ID for logged-in, "t_abc123" for guests

// ── When user LOGS IN ──────────────────────────────────────────────
// WC automatically migrates guest session data to the user account
// This hook fires just before the merge:
add_action( 'woocommerce_load_cart_from_session', function() {
    // Cart has just been loaded from session into WC()->cart
} );

// We should never use PHP's native $_SESSION in WooCommerce. 
// WC has its own session handler that stores in the database, not PHP sessions. 
// Using $_SESSION alongside WC causes data loss on page caches and load-balanced servers.


// -------- Guest Customer Handling -------------------
Guests are not second-class citizens in WooCommerce — they get full cart, checkout, and order functionality. 
The challenge is that without a user ID, you must identify them differently across three phases: browsing, 
checkout, and post-purchase.

/// ------- Detecting Guest vs Logged-In --------------

// Is anyone logged in?
if ( is_user_logged_in() ) {
    $user_id  = get_current_user_id();
    $customer = new WC_Customer( $user_id );
} else {
    // Guest — use session customer
    $customer = WC()->customer;
    $email    = $customer->get_email();
    // Empty until checkout fields filled
}

// On an order — works for both
$order    = wc_get_order( $order_id );
$user_id  = $order->get_user_id(); // 0 for guests
$is_guest = ! $order->get_user_id();
$email    = $order->get_billing_email(); // always set


// -------- Guest Data Sources by Phase -------
Phase	    How to get data
Browsing	WC()->customer->get_billing_country() — from session, could be empty
Checkout	Session customer key fills as form typed — JS updates via AJAX
Post-order	$order->get_billing_*() — data frozen on order
By email	wc_get_orders(['billing_email'=>$email])



Handling Guest Orders — Lookup Patterns
Common operations on orders placed by guests

// ── Find all orders by a guest email ──────────────────────────────
$guest_orders = wc_get_orders([
    'billing_email' => 'customer@example.com',
    'limit'         => -1,
    'status'        => 'any',
]);

// ── Get total guest spend by email ────────────────────────────────
$total = array_reduce( $guest_orders, function( $carry, $order ) {
    return $carry + $order->get_total();
}, 0 );

// ── Get guest identity from a specific order ───────────────────────
$order      = wc_get_order( $order_id );
$guest_name = $order->get_formatted_billing_full_name();
$guest_email= $order->get_billing_email();
$guest_phone= $order->get_billing_phone();

// ── Check if guest already has an account ─────────────────────────
$existing_user = email_exists( $guest_email );
if ( $existing_user ) {
    // User exists — offer to link orders to account
    $customer = new WC_Customer( $existing_user );
}


// -------- Linking Guest Orders to a New Account --------
When a guest registers, automatically claim their past orders

// WooCommerce does this automatically on registration — but here's
// how to trigger it manually or add custom logic:

add_action( 'woocommerce_created_customer', function( $customer_id ) {

    $email = get_user_meta( $customer_id, 'billing_email', true )
           ?: get_userdata( $customer_id )->user_email;

    // Find all guest orders matching this email
    $orders = wc_get_orders([
        'billing_email' => $email,
        'customer_id'   => 0,    // guests only (user_id = 0)
        'limit'         => -1,
    ]);

    foreach ( $orders as $order ) {
        $order->set_customer_id( $customer_id ); // link to new account
        $order->save();
    }

    // WC built-in helper does the same thing:
    wc_update_new_customer_past_orders( $customer_id );
} );

/// -------- Guest Checkout: Saving Extra Data During Checkout --------
Persist custom checkout fields for both guest and logged-in customers identically

// Step 1: Add field to checkout form
add_action( 'woocommerce_after_order_notes', function( $checkout ) {
    woocommerce_form_field( 'company_gst', [
        'type'        => 'text',
        'label'       => 'GST Number',
        'placeholder' => '22AAAAA0000A1Z5',
        'required'    => false,
        'class'       => [ 'form-row-wide' ],
    ], $checkout->get_value( 'company_gst' ) );
} );

// Step 2: Validate it
add_action( 'woocommerce_checkout_process', function() {
    if ( ! empty( $_POST['company_gst'] ) ) {
        if ( ! preg_match( '/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/', $_POST['company_gst'] ) ) {
            wc_add_notice( 'Invalid GST number format.', 'error' );
        }
    }
} );

// Step 3: Save to order meta (works for guest AND logged-in)
add_action( 'woocommerce_checkout_order_created', function( $order ) {
    if ( ! empty( $_POST['company_gst'] ) ) {
        $order->update_meta_data( '_company_gst', sanitize_text_field( $_POST['company_gst'] ) );
        $order->save();
    }
    // Also save to customer meta if logged in
    if ( $order->get_user_id() ) {
        $customer = new WC_Customer( $order->get_user_id() );
        $customer->update_meta_data( 'company_gst', sanitize_text_field( $_POST['company_gst'] ) );
        $customer->save();
    }
} );



/// --------- Key Hooks & Patterns -------------
The most important action and filter hooks for intercepting customer data at the right moment.
Customer Lifecycle Hooks
Fires in order from registration to account updates
Hook	                            Type	             When It Fires
woocommerce_created_customer	    action	          New WC account registered. Args: $customer_id, $new_customer_data, $password_generated
woocommerce_save_account_details	action	      Customer saves My Account → Account Details. Args: $user_id
woocommerce_customer_save_address	action	       Billing or shipping address saved. Args: $user_id, $load_address
woocommerce_checkout_order_created	action	      Order just created at checkout — both guest and logged-in. Args: $order
woocommerce_load_cart_from_session	action	       Cart loaded from session — fires on every request where cart is needed.
woocommerce_cart_emptied	        action	       Cart cleared. Good place to clean up session data.
woocommerce_update_order_review_fragments	filter	AJAX cart/review update — add your own fragments.
woocommerce_customer_get_*	filter	Filter         any getter return value, e.g. woocommerce_customer_get_billing_country.
woocommerce_customer_meta_fields	filter	        Add custom fields to customer profile in WP Admin.

/// --------- Adding Custom Fields to Admin Customer Profile ---------

add_filter( 'woocommerce_customer_meta_fields', function( $fields ) {
    $fields['billing']['fields']['company_gst'] = [
        'label'       => 'GST Number',
        'description' => 'Customer GST registration number',
    ];
    $fields['billing']['fields']['loyalty_tier'] = [
        'label'       => 'Loyalty Tier',
        'description' => 'silver / gold / platinum',
    ];
    return $fields;
} );

// These fields are saved automatically by WC when admin hits Update User
// Read them back with:
$gst   = $customer->get_meta( 'company_gst' );
$tier  = $customer->get_meta( 'loyalty_tier' );
Modifying Customer Data on the Fly with Filters
php — filter customer getters
copy
// Override customer country for tax calculation (e.g. B2B override)
add_filter( 'woocommerce_customer_get_billing_country', function( $country, $customer ) {
    if ( $customer->get_meta( 'force_tax_country' ) ) {
        return $customer->get_meta( 'force_tax_country' );
    }
    return $country;
}, 10, 2 );

// Pre-fill checkout fields for logged-in users from custom meta
add_filter( 'woocommerce_checkout_get_value', function( $value, $input ) {
    if ( ! is_user_logged_in() ) return $value;
    if ( $input === 'company_gst' ) {
        $c = new WC_Customer( get_current_user_id() );
        return $c->get_meta( 'company_gst' ) ?: $value;
    }
    return $value;
}, 10, 2 );