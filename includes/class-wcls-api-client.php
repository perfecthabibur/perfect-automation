<?php
defined('ABSPATH') || exit;

class WCLS_API_Client {

    private $api_url;
    private $api_key;
    private $api_secret;

    public function __construct() {
        $settings = WCLS_Settings::get_all();
        $this->api_url    = rtrim($settings['api_url'], '/');
        $this->api_key    = $settings['api_key'];
        $this->api_secret = $settings['api_secret'];
    }

    public function is_configured() {
        return !empty($this->api_url) && !empty($this->api_key) && !empty($this->api_secret);
    }

    public function test_connection() {
        if (!$this->is_configured()) {
            return [
                'success' => false,
                'message' => 'API credentials not configured.',
            ];
        }

        $url = $this->api_url . '/api/woocommerce/connection/test';
        $response = $this->make_request('GET', $url);

        return $response;
    }

    public function sync_order($payload) {
        if (!$this->is_configured()) {
            return [
                'success' => false,
                'message' => 'API credentials not configured.',
            ];
        }

        $url = $this->api_url . '/api/woocommerce/orders/sync';
        return $this->make_request('POST', $url, $payload);
    }

    private function make_request($method, $url, $data = null) {
        $timestamp = time();
        $body = $data ? wp_json_encode($data) : '';
        $signature = $this->generate_signature($body, $timestamp);

        $args = [
            'method'  => $method,
            'timeout' => 30,
            'headers' => [
                'Content-Type'     => 'application/json',
                'X-WC-API-Key'     => $this->api_key,
                'X-WC-Signature'   => $signature,
                'X-WC-Timestamp'   => $timestamp,
                'Accept'           => 'application/json',
            ],
        ];

        if ($method === 'POST' && $data) {
            $args['body'] = $body;
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }

        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if (!$decoded) {
            return [
                'success' => false,
                'message' => 'Invalid response from server.',
            ];
        }

        return $decoded;
    }

    private function generate_signature($payload, $timestamp) {
        $string_to_sign = $payload . $timestamp;
        return hash_hmac('sha256', $string_to_sign, $this->api_secret);
    }
}
