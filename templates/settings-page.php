<?php defined('ABSPATH') || exit; ?>
<div class="wrap wcls-wrap">
    <h1>Perfect Automation</h1>

    <div class="wcls-stats">
        <div class="wcls-stat">
            <div class="value"><?php echo intval($settings['total_synced'] ?? 0); ?></div>
            <div class="label">Total Synced</div>
        </div>
        <div class="wcls-stat">
            <div class="value"><?php echo intval($settings['failed_orders'] ?? 0); ?></div>
            <div class="label">Failed</div>
        </div>
        <div class="wcls-stat">
            <div class="value">                <?php echo $settings['last_sync'] ? esc_html(human_time_diff(strtotime($settings['last_sync'])) . ' ago') : 'Never'; ?></div>
            <div class="label">Last Sync</div>
        </div>
        <div class="wcls-stat">
            <div class="value">
                <?php if (!empty($settings['sync_enabled'])): ?>
                    <span class="wcls-status wcls-status-active">Active</span>
                <?php else: ?>
                    <span class="wcls-status wcls-status-inactive">Inactive</span>
                <?php endif; ?>
            </div>
            <div class="label">Status</div>
        </div>
    </div>

    <form method="post" action="options.php" class="wcls-card">
        <?php settings_fields('wcls_settings_group'); ?>
        <h2>API Configuration</h2>
        <table class="wcls-form-table">
            <tr>
                <td><label for="api_url">API URL</label></td>
                <td>
                    <input type="url" id="api_url" name="wcls_settings[api_url]" value="<?php echo esc_attr($settings['api_url'] ?? ''); ?>" placeholder="https://your-laravel-app.com">
                    <div class="description">The URL of your Laravel application (e.g., https://yourstore.com)</div>
                </td>
            </tr>
            <tr>
                <td><label for="api_key">API Key</label></td>
                <td>
                    <input type="text" id="api_key" name="wcls_settings[api_key]" value="<?php echo esc_attr($settings['api_key'] ?? ''); ?>" placeholder="wc_xxxxxxxxxxxxxxxx">
                    <div class="description">Get this from your Laravel admin panel under WooCommerce connections.</div>
                </td>
            </tr>
            <tr>
                <td><label for="api_secret">API Secret</label></td>
                <td>
                    <input type="password" id="api_secret" name="wcls_settings[api_secret]" value="<?php echo esc_attr($settings['api_secret'] ?? ''); ?>" placeholder="Enter API Secret">
                    <div class="description">The API secret for HMAC signature verification.</div>
                </td>
            </tr>
            <tr>
                <td><label for="sync_enabled">Sync Orders</label></td>
                <td>
                    <label class="wcls-toggle">
                        <input type="checkbox" id="sync_enabled" name="wcls_settings[sync_enabled]" value="1" <?php checked($settings['sync_enabled'] ?? 0, 1); ?>>
                        <span class="slider"></span>
                    </label>
                    <div class="description">Enable automatic order synchronization when orders are placed.</div>
                </td>
            </tr>
        </table>

        <h2>Custom Field Mapping</h2>
        <p>Map WordPress checkout field meta keys to attribute types (e.g., CartFlows custom fields, WooCommerce variation attributes).</p>

        <div id="wcls-field-mappings">
            <?php
            $mappings = $settings['field_mappings'] ?? [];
            $fieldIndex = 0;
            foreach ($mappings as $mapping):
            ?>
            <div class="wcls-mapping-row" data-index="<?php echo $fieldIndex; ?>">
                <input type="text" name="wcls_settings[field_mappings][<?php echo $fieldIndex; ?>][meta_key]" value="<?php echo esc_attr($mapping['meta_key'] ?? ''); ?>" placeholder="Meta Key (e.g. cartflows_size, Size, Color)">
                <select name="wcls_settings[field_mappings][<?php echo $fieldIndex; ?>][type]">
                    <option value="size" <?php selected($mapping['type'] ?? '', 'size'); ?>>Size</option>
                    <option value="color" <?php selected($mapping['type'] ?? '', 'color'); ?>>Color</option>
                    <option value="other" <?php selected($mapping['type'] ?? '', 'other'); ?>>Other</option>
                </select>
                <button type="button" class="wcls-btn wcls-btn-danger wcls-remove-field">Remove</button>
            </div>
            <?php
                $fieldIndex++;
            endforeach;
            ?>
        </div>

        <button type="button" id="wcls-add-field" class="wcls-btn wcls-btn-secondary" style="margin-top:8px;">+ Add Field</button>
        <p class="description" style="margin-top:8px;">Map the meta keys used by your checkout plugin (e.g., CartFlows, WooCommerce Checkout Extensions) to Size/Color attribute types. For native WooCommerce variation attributes, use the attribute name as the meta key (e.g., Size, Color).</p>

        <p class="submit">
            <button type="button" id="wcls-test-connection" class="wcls-btn wcls-btn-secondary">Test Connection</button>
            <input type="submit" class="wcls-btn wcls-btn-primary" value="Save Changes">
        </p>
        <div id="wcls-test-result" class="wcls-result" style="display:none;"></div>
    </form>

    <?php if (!empty($failed_log)): ?>
    <div class="wcls-card">
        <h2>Failed Orders (<?php echo count($failed_log); ?>)</h2>
        <p>These orders failed to sync. You can retry them manually.</p>
        <table class="wcls-log-table">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Error</th>
                    <th>Time</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($failed_log as $entry): ?>
                <tr>
                    <td><strong>#<?php echo intval($entry['order_id']); ?></strong></td>
                    <td><?php echo esc_html($entry['error'] ?? ''); ?></td>
                    <td><?php echo esc_html($entry['time'] ?? ''); ?></td>
                    <td class="wcls-status-cell">
                        <button type="button" class="wcls-btn wcls-btn-secondary wcls-retry-order" data-order-id="<?php echo intval($entry['order_id']); ?>">Retry</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <div class="wcls-card">
        <h2>How it works</h2>
        <ol style="color:#475569; font-size:13px; line-height:1.8; padding-left:20px;">
            <li>Enter your Laravel app URL and API credentials above.</li>
            <li>Test the connection to verify it works.</li>
            <li>Enable "Sync Orders" to automatically send new WooCommerce orders to Laravel.</li>
            <li>Map any custom checkout fields (Size, Color) using the field mapping above.</li>
            <li>Orders will appear in your Laravel admin panel under the normal Orders list.</li>
        </ol>
    </div>
</div>
<script>var wclsAdmin = wclsAdmin || {}; wclsAdmin.fieldCount = <?php echo intval($fieldIndex); ?>;</script>
