<?php
/**
 * Plugin Name: Perfect Automation
 * Description: Synchronize WooCommerce orders to a Laravel e-commerce application via secure API.
 * Version: 1.1.2
 * Author: Habibur Rahman
 * Plugin URI: https://perfectacademy.bd
 * Text Domain: perfect-automation
 * Requires PHP: 7.4
 * Requires at least: 5.8
 * WC requires at least: 5.0
 */

defined('ABSPATH') || exit;

define('WCLS_VERSION', '1.1.2');
define('WCLS_DB_VERSION', '1.1.2');
define('WCLS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WCLS_PLUGIN_URL', plugin_dir_url(__FILE__));

final class WC_Laravel_Sync {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    private function includes() {
        require_once WCLS_PLUGIN_DIR . 'includes/class-wcls-settings.php';
        require_once WCLS_PLUGIN_DIR . 'includes/class-wcls-order-sync.php';
        require_once WCLS_PLUGIN_DIR . 'includes/class-wcls-api-client.php';
        require_once WCLS_PLUGIN_DIR . 'includes/class-wcls-admin.php';
        require_once WCLS_PLUGIN_DIR . 'includes/class-wcls-updater.php';
    }

    private function init_hooks() {
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        add_action('init', [$this, 'init']);

        // Initialize GitHub updater on plugins page
        if (is_admin()) {
            new WCLS_Updater();
        }
    }

    public function activate() {
        $defaults = [
            'api_url'       => '',
            'api_key'       => '',
            'api_secret'    => '',
            'sync_enabled'  => 0,
            'last_sync'     => '',
            'total_synced'  => 0,
            'failed_orders' => 0,
            'field_mappings' => [],
        ];
        if (!get_option('wcls_settings')) {
            add_option('wcls_settings', $defaults);
        }

        if (!get_option('wcls_failed_orders_log')) {
            add_option('wcls_failed_orders_log', []);
        }

        $this->maybe_upgrade();
    }

    public function deactivate() {
        // Cleanup if needed
    }

    public function init() {
        load_plugin_textdomain('perfect-automation', false, dirname(plugin_basename(__FILE__)) . '/languages');

        if (is_admin()) {
            new WCLS_Admin();
        }

        new WCLS_Order_Sync();
    }

    /**
     * Safe version-based upgrade mechanism.
     *
     * Compares the stored DB version against the current plugin version.
     * Runs only the required migrations. Never deletes existing data.
     */
    private function maybe_upgrade() {
        $installed_version = get_option('wcls_db_version', '0.0.0');

        if (version_compare($installed_version, WCLS_DB_VERSION, '>=')) {
            return;
        }

        // Future migrations go here:
        // if (version_compare($installed_version, '1.2.0', '<')) {
        //     $this->upgrade_to_1_2_0();
        // }

        update_option('wcls_db_version', WCLS_DB_VERSION);
    }
}

WC_Laravel_Sync::instance();
