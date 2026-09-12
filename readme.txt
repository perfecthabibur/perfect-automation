=== Perfect Automation ===
Contributors: habiburrahman
Tags: woocommerce, laravel, order-sync, api, automation
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
WC requires at least: 5.0
Stable tag: 1.1.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Synchronize WooCommerce orders to a Laravel e-commerce application via secure API.

== Description ==

Perfect Automation connects your WordPress WooCommerce store with a Laravel backend application. When customers place orders on your WooCommerce store, the order data is automatically synchronized to your Laravel application via a secure HMAC-authenticated API.

Features:

* Automatic order synchronization on checkout
* Status change synchronization
* Failed order retry mechanism
* Custom field mapping (Size, Color, etc.)
* HMAC-SHA256 API authentication
* Connection testing
* Sync statistics dashboard

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/perfect-automation/`, or install through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to WooCommerce > Perfect Automation to configure your API credentials.
4. Enter your Laravel application URL, API Key, and API Secret.
5. Test the connection.
6. Enable "Sync Orders" to start automatic synchronization.

== Frequently Asked Questions ==

= Does this plugin modify existing order data? =

No. The plugin only reads order data and sends it to your Laravel application. It adds metadata to orders (_wcls_synced, _wcls_laravel_order_id) but never modifies order content.

= What happens to my settings when I update the plugin? =

Your settings are stored in the WordPress database (wp_options table) and are never touched by plugin updates. Only the plugin code files are replaced during an update.

= Does this plugin create database tables? =

No. All data is stored in standard WordPress options (wp_options table) and WooCommerce order metadata.

== Changelog ==

= 1.1.3 =
* Added plugin image/icon for WordPress plugin details and update interface

= 1.1.2 =
* Fixed WordPress update detection for GitHub-hosted plugin updates
* WordPress Plugins page now correctly shows "Update available" for Perfect Automation
* Version extraction from GitHub tags improved

= 1.1.1 =
* WooCommerce standard attribute values now display underneath the product image in Laravel Order Index
* Plugin naming and file structure standardized to "Perfect Automation"
* Existing order synchronization functionality preserved

= 1.1.0 =
* Initial public release
* WooCommerce order synchronization to Laravel
* HMAC-SHA256 API authentication
* Custom field mapping
* Failed order retry mechanism
* GitHub-based automatic updates

== Upgrade Notice ==

= 1.1.0 =
First release with GitHub-based automatic updates.
