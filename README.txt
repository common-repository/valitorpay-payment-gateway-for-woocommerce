===  Payment Gateway via ValitorPay for WooCommerce ===
Contributors: tacticaisdev
Tags: woocommerce, payment gateway, valitor, valitorpay, credit card
Requires at least: 5.5
Requires PHP: 7.0
Tested up to: 6.5.4
WC tested up to: 8.9.3
WC requires at least: 3.2.3
Stable tag: 1.2.19
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Take payments in your WooCommerce store using the ValitorPay Gateway

== Description ==

ValitorPay is an API driven payment gateway by payment processor Valitor.

Valitor is an international payment solutions company who helps partners, merchants and consumers to make and receive payments.

Taking card payments online on checkout page. You can handle with or without 3D secure verification.

This plugin is maintained and supported by Tactica

== Installation ==

Once you have installed WooCommerce on your Wordpress setup, follow these simple steps:

1. Upload the plugin files to the `/wp-content/plugins/valitorpay-payment-gateway-for-woocommerce` directory, or install the plugin through the WordPress plugins screen directly.
1. Activate the plugin through the 'Plugins' screen in WordPress.
1. Insert the Api key in the Checkout settings for the Valitorpay payment plugin and activate it.


== Screenshots ==

1. The settings panel for the Valitorpay gateway
2. Checkout screen


== Frequently Asked Questions ==

= Does the plugin support test mode? =

Yes, the plugin supports test mode.

= Does the plugin support <a href="https://woocommerce.com/products/woocommerce-subscriptions" target="_blank">WooCommerce Subscriptions</a>? =

Yes, the plugin supports <a href="https://woocommerce.com/products/woocommerce-subscriptions" target="_blank">WooCommerce Subscriptions</a>.


== Changelog ==

= 1.2.19 =
* Update demo cards list message in test mode
* Tested with WordPress 6.5.4 and WooCommerce 8.9.3

= 1.2.18 =
* Prevent subscriptions payments without 3dSecure card verification.
* Tested with WooCommerce 8.9.1

= 1.2.17 =
* Fixed refund
* Tested with WordPress 6.5.3 and WooCommerce 8.9.0

= 1.2.16 =
* Tested with WordPress 6.4.1 and WooCommerce 8.3.1
* Payment Method Integration for the Checkout Block

= 1.2.15 =
* Tested with WordPress 6.4.1 and WooCommerce 8.2.2
* Fixed Subscription payment

= 1.2.14 =
* Tested with WordPress 6.4 and WooCommerce 8.2.1
* Fixed 'dynamic property declaration' warnings(PHP 8.2+)

= 1.2.13 =
* Tested with Wordpress 6.3 and Woocommerce 7.9.0

= 1.2.12 =
* Tested with Wordpress 6.2.2 and Woocommerce 7.8.0
* Fixed PHP deprecated notice

= 1.2.11 =
* Tested with WordPress 6.2 and WooCommerce 7.6.0
* Improved refund process

= 1.2.10 =
* Fixed php8 warning
* Tested with WordPress 6.1.1 and WooCommerce 7.1.0

= 1.2.9 =
* Set subscription renewal payment retry limit

= 1.2.8 =
* Prevent create order when card verification failed

= 1.2.7 =
* Fixed redirect after canceling 3dsecure.
* Added notes for 3dsecure failed cases.
* Fixed order-pay payment after unsuccessful previous payment attempt with additional exponent request.

= 1.2.6 =
* Add checkout CC form fields validation
* Tested with  WordPress 6.0.1 and WooCommerce 6.7.0
* Updated scheduled subscription payments

= 1.2.5 =
* Add checkout CC form fields validation
* Tested with  WordPress 6.0 and WooCommerce 6.6.1

= 1.2.4 =
* Add support for old subscriptions with virtual cart stored in parent order

= 1.2.3 =
* Adjusted subscriptions failed renewal orders status for Recurring Payment Retry
* Fixed subscriptions manual renewal payments
* Tested with  WordPress 5.9.3 and WooCommerce 6.4.0

= 1.2.2 =
* Add actions for Valitorpay payment response

= 1.2.1 =
* Add support for multiple subscriptions

= 1.2 =
* Code refactoring
* Adjusted subscriptions payments due to changes in Valitorpay API
* Tested with WooCommerce 6.0.0

= 1.1.14 =
* Improved virtual card payments logic

= 1.1.13 =
* Improved virtual card payments

= 1.1.12 =
* Improved payments with verification
* Logs updated
* Tested with  WordPress 5.8.2 and WooCommerce 5.9

= 1.1.11 =
* Add 3dsecure processing nonce check setting
* Tested with WordPress 5.8 WooCommerce 5.6.0

= 1.1.10 =
* Improved Valitopay payment response processing

= 1.1.9 =
* Improved regular payments logic
* Used wc_get_price_decimals for amounts

= 1.1.7 =
* Adjusted CSRF verification
* Tested with  WooCommerce 5.5.1

= 1.1.6 =
* Adjusted merchantReferenceData param.

= 1.1.5 =
* Added dsTransId to the request(Required in EMV 3DS version 2.x transactions)

= 1.1.4 =
* Tested with  WooCommerce 5.3.0
* Updated Debug Mode file data

= 1.1.3 =
* Updated payment processing log messages
* Tested with WordPress 5.7.2 and WooCommerce 5.2.2

= 1.1.2 =
* Added exponent fixes for 3D Secure.

= 1.1.1 =
* Updated errors handling

= 1.1 =
* Valitorpay API changes(removed unsupported fields)
* Updated payment intent page functionality
* Updated Subscriptions payments with trial period
* Tested with WordPress 5.6 WooCommerce 4.9.2

= 1.0.6 =
* Tested with WordPress 5.6

= 1.0.5 =
* Moved unused options to "Advanced features" section

= 1.0.4 =
* Tested with new WP and WC version.

= 1.0.3 =
* Updated Valitorpay gateway settings

= 1.0.2 =
* Removed WooCommerce deprecated methods.
* Subscription payments use only 'Virtual card payment'.
* Tested with WordPress 5.5.1 and WooCommerce 4.5.2

= 1.0.1 =
* Updated description

= 1.0.0 =
* Initial release
