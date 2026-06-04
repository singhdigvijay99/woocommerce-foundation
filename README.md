<div align="center">

# 🛒 WooCommerce Foundation

### A collection of reusable WooCommerce snippets, hooks, filters, and custom development utilities.

<p>
<img src="https://img.shields.io/badge/WordPress-6.x-21759B?logo=wordpress&logoColor=white" />
<img src="https://img.shields.io/badge/WooCommerce-Compatible-96588A?logo=woocommerce&logoColor=white" />
<img src="https://img.shields.io/badge/PHP-8+-777BB4?logo=php&logoColor=white" />
<img src="https://img.shields.io/github/stars/singhdigvijay99/woocommerce-foundation" />
<img src="https://img.shields.io/github/forks/singhdigvijay99/woocommerce-foundation" />
<img src="https://img.shields.io/github/issues/singhdigvijay99/woocommerce-foundation" />
<img src="https://img.shields.io/github/license/singhdigvijay99/woocommerce-foundation" />
</p>

**Production-ready WooCommerce code snippets collected from real-world client projects.**

</div>

---

## Overview

This repository contains a curated collection of WooCommerce customizations that I frequently use while building WordPress eCommerce websites.

The goal is to have a centralized place for commonly used hooks, filters, utility functions, and integration examples that can be quickly reused across projects.

Rather than searching through old projects or Stack Overflow answers, I can simply grab a tested snippet from here.

---

## Categories

### Product

* Customize Add to Cart button
* Change product tabs
* Modify related products
* Product badges
* Product gallery customizations
* Custom product fields

### Cart

* Auto apply coupons
* AJAX cart updates
* Cart item validation
* Custom cart notices
* Minimum order amount

### Checkout

* Add custom checkout fields
* Remove checkout fields
* Field validation
* Custom order meta
* Checkout UI improvements

### My Account

* Add custom endpoints
* Custom dashboard widgets
* Additional menu items
* User-specific content

### Orders

* Custom order statuses
* Order metadata
* Admin order actions
* Order automation

### Pricing

* Dynamic pricing examples
* Role-based pricing
* Quantity discounts
* Sale badge customization

### Emails

* Customize WooCommerce emails
* Add custom content
* Modify email templates

### Admin

* Admin column customization
* Bulk actions
* Product list enhancements
* Dashboard utilities

### Integrations

* Advanced Custom Fields (ACF)
* Custom Post Types
* REST API examples
* Third-party plugin compatibility

---

## Example

```php
add_filter( 'woocommerce_product_tabs', function( $tabs ) {
    unset( $tabs['reviews'] );
    return $tabs;
});
```

---

## Repository Structure

```
woocommerce-foundation/

├── product/
├── cart/
├── checkout/
├── account/
├── orders/
├── pricing/
├── emails/
├── admin/
├── integrations/
└── README.md
```

---

## Usage

Simply copy the required snippet and add it to:

* your theme's `functions.php`
* a custom functionality plugin
* a WordPress mu-plugin
* your custom WooCommerce extension

Always test snippets on a staging environment before deploying to production.

---

## Tech Stack

* PHP
* WordPress
* WooCommerce
* MySQL
* ACF
* REST API

---

## Contributing

Feel free to open an issue or submit a pull request if you'd like to improve an existing snippet or add a new one.

---

## Author

**Digvijay Singh**

GitHub: https://github.com/singhdigvijay99

Portfolio: https://singhdigvijay99.github.io/portfolio

---

## License

This repository is licensed under the MIT License.

---

<div align="center">

⭐ If you find these snippets useful, consider starring the repository.

</div>
