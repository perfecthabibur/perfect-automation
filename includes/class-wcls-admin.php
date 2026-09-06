<?php
defined('ABSPATH') || exit;

class WCLS_Admin {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_wcls_test_connection', [$this, 'ajax_test_connection']);
        add_action('wp_ajax_wcls_retry_order', [$this, 'ajax_retry_order']);
        add_action('wp_ajax_wcls_sync_now', [$this, 'ajax_sync_now']);
    }

    public function add_menu() {
        add_submenu_page(
            'woocommerce',
            'Perfect Automation',
            'Perfect Automation',
            'manage_woocommerce',
            'wc-laravel-sync',
            [$this, 'settings_page']
        );
    }

    public function enqueue_assets($hook) {
        if ($hook !== 'woocommerce_page_wc-laravel-sync') {
            return;
        }
        wp_enqueue_style('wcls-admin', WCLS_PLUGIN_URL . 'assets/admin.css', [], WCLS_VERSION);
        wp_enqueue_script('wcls-admin', WCLS_PLUGIN_URL . 'assets/admin.js', ['jquery'], WCLS_VERSION, true);
        wp_localize_script('wcls-admin', 'wclsAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('wcls_admin_nonce'),
        ]);
    }

    public function register_settings() {
        register_setting('wcls_settings_group', 'wcls_settings', [$this, 'sanitize_settings']);
    }

    public function sanitize_settings($input) {
        $sanitized = [];
        $sanitized['api_url'] = esc_url_raw($input['api_url'] ?? '');
        $sanitized['api_key'] = sanitize_text_field($input['api_key'] ?? '');
        $sanitized['api_secret'] = sanitize_text_field($input['api_secret'] ?? '');
        $sanitized['sync_enabled'] = !empty($input['sync_enabled']) ? 1 : 0;
        $sanitized['last_sync'] = $input['last_sync'] ?? '';
        $sanitized['total_synced'] = intval($input['total_synced'] ?? 0);
        $sanitized['failed_orders'] = intval($input['failed_orders'] ?? 0);

        $sanitized['field_mappings'] = [];
        if (!empty($input['field_mappings']) && is_array($input['field_mappings'])) {
            foreach ($input['field_mappings'] as $mapping) {
                $meta_key = sanitize_text_field($mapping['meta_key'] ?? '');
                $type = sanitize_text_field($mapping['type'] ?? '');
                if (!empty($meta_key) && !empty($type)) {
                    $sanitized['field_mappings'][] = [
                        'meta_key' => $meta_key,
                        'type'     => $type,
                    ];
                }
            }
        }

        return $sanitized;
    }

    public function settings_page() {
        $settings = WCLS_Settings::get_all();
        $failed_log = WCLS_Settings::get_failed_log();
        include WCLS_PLUGIN_DIR . 'templates/settings-page.php';
    }

    public function ajax_test_connection() {
        check_ajax_referer('wcls_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $client = new WCLS_API_Client();
        $result = $client->test_connection();
        wp_send_json($result);
    }

    public function ajax_retry_order() {
        $this->handle_sync_request();
    }

    public function ajax_sync_now() {
        $this->handle_sync_request();
    }

    private function handle_sync_request() {
        check_ajax_referer('wcls_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $order_id = intval($_POST['order_id'] ?? 0);
        if (!$order_id) {
            wp_send_json_error(['message' => 'Invalid order ID']);
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(['message' => 'Order not found']);
        }

        $sync = new WCLS_Order_Sync();
        $result = $sync->sync_order($order);

        if ($result['success']) {
            update_post_meta($order_id, '_wcls_synced', 'yes');
            update_post_meta($order_id, '_wcls_laravel_order_id', $result['order_id'] ?? '');
            WCLS_Settings::increment_synced();
            WCLS_Settings::remove_failed_order($order_id);
        }

        wp_send_json($result);
    }
}
