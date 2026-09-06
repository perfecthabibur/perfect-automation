<?php
defined('ABSPATH') || exit;

class WCLS_Settings {

    const OPTION_KEY = 'wcls_settings';

    public static function get_all() {
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
        $settings = get_option(self::OPTION_KEY, []);
        return wp_parse_args($settings, $defaults);
    }

    public static function get($key, $default = '') {
        $settings = self::get_all();
        return $settings[$key] ?? $default;
    }

    public static function update($data) {
        $settings = self::get_all();
        $settings = array_merge($settings, $data);
        update_option(self::OPTION_KEY, $settings);
    }

    public static function increment_synced() {
        $settings = self::get_all();
        $settings['total_synced'] = ($settings['total_synced'] ?? 0) + 1;
        $settings['last_sync'] = current_time('mysql');
        update_option(self::OPTION_KEY, $settings);
    }

    public static function increment_failed() {
        $settings = self::get_all();
        $settings['failed_orders'] = ($settings['failed_orders'] ?? 0) + 1;
        update_option(self::OPTION_KEY, $settings);
    }

    public static function get_field_mappings() {
        return self::get('field_mappings', []);
    }

    public static function add_failed_order($order_id, $error) {
        $log = get_option('wcls_failed_orders_log', []);
        $log[] = [
            'order_id' => $order_id,
            'error'    => $error,
            'time'     => current_time('mysql'),
        ];
        // Keep only last 100 entries
        if (count($log) > 100) {
            $log = array_slice($log, -100);
        }
        update_option('wcls_failed_orders_log', $log);
    }

    public static function get_failed_log() {
        return get_option('wcls_failed_orders_log', []);
    }

    public static function clear_failed_log() {
        update_option('wcls_failed_orders_log', []);
    }

    public static function remove_failed_order($order_id) {
        $log = get_option('wcls_failed_orders_log', []);
        $log = array_filter($log, function ($entry) use ($order_id) {
            return $entry['order_id'] != $order_id;
        });
        update_option('wcls_failed_orders_log', array_values($log));
    }
}
