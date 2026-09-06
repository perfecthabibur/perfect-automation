<?php
defined('ABSPATH') || exit;

class WCLS_Order_Sync {

    public function __construct() {
        add_action('woocommerce_thankyou', [$this, 'on_thankyou_page'], 99, 1);
        add_action('woocommerce_checkout_order_processed', [$this, 'on_checkout_order_processed'], 99, 1);
        add_action('woocommerce_order_status_changed', [$this, 'on_status_changed'], 99, 4);
        add_action('wcls_retry_failed_orders', [$this, 'retry_failed_orders']);

        if (!wp_next_scheduled('wcls_retry_failed_orders')) {
            wp_schedule_event(time(), 'hourly', 'wcls_retry_failed_orders');
        }
    }

    private function log($message) {
        if (defined('WC_LOG_DIR') || function_exists('wc_get_logger')) {
            wc_get_logger()->info($message, ['source' => 'wc-laravel-sync']);
        }
    }

    public function on_status_changed($order_id, $old_status, $new_status, $order) {
        $this->log("on_status_changed: order #{$order_id} {$old_status} → {$new_status}");

        if ($old_status === $new_status) return;
        if (in_array($new_status, ['cancelled', 'refunded', 'failed'])) return;

        $this->try_sync($order_id, 'status_changed');
    }

    public function on_checkout_order_processed($order_id) {
        if (!$order_id) return;
        $this->log("on_checkout_order_processed: order #{$order_id}");
        $this->try_sync($order_id, 'checkout_order_processed');
    }

    public function on_thankyou_page($order_id) {
        if (!$order_id) return;
        $this->log("on_thankyou_page: order #{$order_id}");
        $this->try_sync($order_id, 'thankyou_page');
    }

    private function try_sync($order_id, $source) {
        $settings = WCLS_Settings::get_all();
        if (empty($settings['sync_enabled'])) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $already_synced = $order->get_meta('_wcls_synced');
        if ($already_synced) {
            return;
        }

        if (count($order->get_items()) === 0) {
            return;
        }

        $result = $this->sync_order($order);

        if ($result['success']) {
            $order->update_meta_data('_wcls_synced', 'yes');
            $order->update_meta_data('_wcls_laravel_order_id', $result['order_id'] ?? '');
            $order->save();
            WCLS_Settings::increment_synced();
            WCLS_Settings::remove_failed_order($order_id);
        } else {
            WCLS_Settings::increment_failed();
            WCLS_Settings::add_failed_order($order_id, $result['message'] ?? 'Unknown error');
        }
    }

    public function sync_order($order) {
        $payload = $this->normalize_order_data($order);

        $client = new WCLS_API_Client();
        return $client->sync_order($payload);
    }

    private function normalize_order_data($order) {
        $settings = WCLS_Settings::get_all();
        $field_mappings = $settings['field_mappings'] ?? [];

        $first_name = $order->get_billing_first_name();
        $phone = $order->get_billing_phone();
        $address_parts = [
            $order->get_billing_address_1(),
            $order->get_billing_address_2(),
            $order->get_billing_city(),
            $order->get_billing_state(),
            $order->get_billing_postcode(),
        ];
        $address = implode(', ', array_filter($address_parts));

        if (empty($address)) {
            $shipping_parts = [
                $order->get_shipping_address_1(),
                $order->get_shipping_address_2(),
                $order->get_shipping_city(),
                $order->get_shipping_state(),
                $order->get_shipping_postcode(),
            ];
            $address = implode(', ', array_filter($shipping_parts));
        }

        $order_meta = $this->get_order_meta($order);

        $items = [];
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $item_data = [
                'name'                => $item->get_name(),
                'quantity'            => $item->get_quantity(),
                'unit_price'          => (float) $item->get_total() / max(1, $item->get_quantity()),
                'total_price'         => (float) $item->get_total(),
                'external_product_id' => $product ? (string) $product->get_id() : '',
                'external_variation_id' => '',
                'image_url'           => '',
                'attributes'          => [],
            ];

            if ($product) {
                $image_id = $product->get_image_id();
                if ($image_id) {
                    $image_url = wp_get_attachment_image_url($image_id, 'full');
                    $item_data['image_url'] = $image_url ?: '';
                }

                $variation_id = $item->get_variation_id();
                if ($variation_id) {
                    $item_data['external_variation_id'] = (string) $variation_id;

                    $variation = wc_get_product($variation_id);
                    if ($variation) {
                        $var_image_id = $variation->get_image_id();
                        if ($var_image_id) {
                            $var_image_url = wp_get_attachment_image_url($var_image_id, 'full');
                            $item_data['image_url'] = $var_image_url ?: $item_data['image_url'];
                        }

                        $attributes = $variation->get_attributes();
                        foreach ($attributes as $key => $value) {
                            $clean_key = str_replace('attribute_', '', $key);
                            $item_data['attributes'][ucfirst($clean_key)] = $value;
                        }
                    }
                }

                if (empty($item_data['attributes'])) {
                    $product_attrs = $product->get_attributes();
                    foreach ($product_attrs as $key => $attr) {
                        if ($attr->is_taxonomy()) {
                            $terms = wp_get_post_terms($product->get_id(), $attr->get_name());
                            if (!is_wp_error($terms) && !empty($terms)) {
                                $item_data['attributes'][ucfirst($attr->get_name())] = $terms[0]->name;
                            }
                        } else {
                            $item_data['attributes'][ucfirst($key)] = $attr->get_options()[0] ?? '';
                        }
                    }
                }
            }

            $item_meta = $this->get_order_item_meta($item->get_id());
            foreach ($field_mappings as $mapping) {
                $meta_key = $mapping['meta_key'] ?? '';
                $type = $mapping['type'] ?? '';
                if (!empty($meta_key) && !empty($type)) {
                    $value = $item_meta[$meta_key]
                        ?? $item_meta['_' . $meta_key]
                        ?? $order_meta[$meta_key]
                        ?? $order_meta['_' . $meta_key]
                        ?? null;
                    if ($value !== null) {
                        $item_data['attributes'][ucfirst($type)] = $value;
                    }
                }
            }

            $items[] = $item_data;
        }

        $data = [
            'external_order_id' => (string) $order->get_id(),
            'customer' => [
                'first_name' => $first_name ?: $order->get_shipping_first_name(),
                'phone'      => $phone ?: $order->get_shipping_phone(),
                'address'    => $address,
            ],
            'items'            => $items,
            'delivery_charge'  => (float) $order->get_shipping_total(),
            'discount'         => (float) abs($order->get_discount_total()),
            'payment_method'   => $order->get_payment_method_title() ?: 'Cash On Delivery',
        ];

        return $data;
    }

    private function get_order_item_meta($item_id) {
        $meta = [];
        $meta_values = wc_get_order_item_meta($item_id, '', false);
        if (is_array($meta_values)) {
            foreach ($meta_values as $key => $value) {
                if (is_array($value) && isset($value[0])) {
                    $meta[$key] = $value[0];
                } elseif (is_string($value)) {
                    $meta[$key] = $value;
                }
            }
        }
        return $meta;
    }

    private function get_order_meta($order) {
        $meta = [];
        if (!is_a($order, 'WC_Order')) {
            return $meta;
        }
        $all_meta = $order->get_meta_data();
        foreach ($all_meta as $meta_data) {
            $key = $meta_data->key;
            $value = $meta_data->value;
            if (is_array($value) && isset($value[0])) {
                $meta[$key] = $value[0];
            } elseif (is_string($value)) {
                $meta[$key] = $value;
            } elseif (!is_object($value) && !is_array($value)) {
                $meta[$key] = (string) $value;
            }
        }
        return $meta;
    }

    public function retry_failed_orders() {
        $settings = WCLS_Settings::get_all();
        if (empty($settings['sync_enabled'])) {
            return;
        }

        $failed_log = WCLS_Settings::get_failed_log();
        if (empty($failed_log)) {
            return;
        }

        $client = new WCLS_API_Client();
        if (!$client->is_configured()) {
            return;
        }

        $retried = 0;
        $max_retries_per_run = 10;

        foreach ($failed_log as $entry) {
            if ($retried >= $max_retries_per_run) {
                break;
            }

            $order_id = $entry['order_id'] ?? 0;
            if (!$order_id) {
                continue;
            }

            try {
                $order = wc_get_order($order_id);
                if (!$order) {
                    WCLS_Settings::remove_failed_order($order_id);
                    continue;
                }

                $already_synced = $order->get_meta('_wcls_synced');
                if ($already_synced) {
                    WCLS_Settings::remove_failed_order($order_id);
                    continue;
                }

                $result = $this->sync_order($order);
                $retried++;

                if ($result['success']) {
                    $order->update_meta_data('_wcls_synced', 'yes');
                    $order->update_meta_data('_wcls_laravel_order_id', $result['order_id'] ?? '');
                    $order->save();
                    WCLS_Settings::increment_synced();
                    WCLS_Settings::remove_failed_order($order_id);
                }
            } catch (\Throwable $e) {
                continue;
            }
        }
    }
}
