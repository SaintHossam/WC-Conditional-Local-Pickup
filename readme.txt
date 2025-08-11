=== WC Conditional Local Pickup (Custom City Exceptions) ===
Contributors: Saint Hossam
Tags: woocommerce, shipping, local pickup, saudi arabia, custom cities
Requires at least: 5.0
Tested up to: 6.6
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Shows WooCommerce local pickup only when the shipping/billing city is in your allowed cities list.

== Description ==
Hides `local_pickup` for all Saudi cities except the ones you specify in the allowed cities list inside the plugin code.  
You can easily add or remove cities (in English or Arabic) according to your needs. Works on cart/checkout.

== Installation ==
1. Upload `wc-conditional-local-pickup-sa` to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Edit the `$allowed_cities` array inside the plugin file to add your allowed cities.
4. Test it by setting the shipping city to one of the allowed cities.

== Changelog ==
= 1.0.0 =
* Initial release with customizable allowed cities.

== Frequently Asked Questions ==
= Can I add multiple cities? =
Yes, you can add as many cities as you want in the `$allowed_cities` array.

= Will this work for other countries? =
Currently coded for Saudi Arabia (SA), but can be adapted for other countries by changing the country code in the plugin code.
