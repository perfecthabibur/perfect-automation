<?php
defined('ABSPATH') || exit;

/**
 * GitHub-based plugin updater for Perfect Automation.
 *
 * Integrates with WordPress core update mechanism to detect
 * new releases from a GitHub repository and offer updates
 * through the standard WordPress Plugins update UI.
 *
 * Data-safe: Only replaces plugin files. Never touches wp_options,
 * post meta, user data, or any persistent WordPress data.
 */
class WCLS_Updater {

    private $github_owner;
    private $github_repo;
    private $api_url;
    private $installed_version;
    private $plugin_basename;

    public function __construct() {
        $this->github_owner       = 'perfecthabibur';
        $this->github_repo        = 'perfect-automation';
        $this->api_url            = 'https://api.github.com/repos/' . $this->github_owner . '/' . $this->github_repo;
        $this->installed_version  = WCLS_VERSION;
        $this->plugin_basename    = plugin_basename(WCLS_PLUGIN_DIR . 'wc-laravel-sync.php');

        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_update']);
        add_filter('plugins_api', [$this, 'plugins_api_info'], 10, 3);
    }

    /**
     * Inject update information into WordPress plugin update transient.
     */
    public function check_for_update($transient) {
        if (empty($transient->response) || !is_object($transient->response)) {
            return $transient;
        }

        $remote = $this->get_remote_release();

        if (!$remote || !isset($remote['version'])) {
            return $transient;
        }

        $remote_version = $remote['version'];

        if (version_compare($remote_version, $this->installed_version, '<=')) {
            return $transient;
        }

        $download_url = $this->get_download_url($remote);

        if (empty($download_url)) {
            return $transient;
        }

        if (isset($transient->response[$this->plugin_basename])) {
            $existing_version = $transient->response[$this->plugin_basename]->new_version ?? '0';
            if (version_compare($remote_version, $existing_version, '<=')) {
                return $transient;
            }
        }

        $obj = new stdClass();
        $obj->slug         = 'wc-laravel-sync';
        $obj->plugin       = $this->plugin_basename;
        $obj->new_version  = $remote_version;
        $obj->url          = $remote['html_url'] ?? '';
        $obj->package      = $download_url;
        $obj->requires     = '5.8';
        $obj->requires_php = '7.4';
        $obj->tested       = '6.4';
        $obj->name         = 'Perfect Automation';
        $obj->section      = $remote['body'] ?? '';
        $obj->changelog    = $remote['body'] ?? '';

        $transient->response[$this->plugin_basename] = $obj;

        return $transient;
    }

    /**
     * Provide plugin info to the WordPress Plugins API.
     */
    public function plugins_api_info($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (!isset($args->slug) || $args->slug !== 'wc-laravel-sync') {
            return $result;
        }

        $remote = $this->get_remote_release();

        if (!$remote) {
            return $result;
        }

        $obj = new stdClass();
        $obj->name         = 'Perfect Automation';
        $obj->slug         = 'wc-laravel-sync';
        $obj->version      = $remote['version'] ?? $this->installed_version;
        $obj->author       = 'Habibur Rahman';
        $obj->author_profile = 'https://perfectacademy.bd';
        $obj->homepage     = 'https://perfectacademy.bd';
        $obj->requires     = '5.8';
        $obj->requires_php = '7.4';
        $obj->tested       = '6.4';
        $obj->sections     = [
            'description' => $remote['body'] ?? 'Synchronize WooCommerce orders to a Laravel e-commerce application via secure API.',
            'changelog'   => $remote['body'] ?? '',
        ];
        $obj->download_link = $this->get_download_url($remote);
        $obj->banners       = [];

        return $obj;
    }

    /**
     * Get the remote release from GitHub API (cached for 12 hours).
     */
    private function get_remote_release() {
        $cache_key = 'wcls_github_release_' . md5($this->api_url);
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $response = wp_remote_get($this->api_url . '/releases/latest', [
            'timeout' => 15,
            'headers' => [
                'Accept' => 'application/vnd.github.v3+json',
            ],
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code !== 200) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || !is_array($data)) {
            return null;
        }

        set_transient($cache_key, $data, 12 * HOUR_IN_SECONDS);

        return $data;
    }

    /**
     * Get the download URL for the plugin ZIP from a GitHub release.
     */
    private function get_download_url($release) {
        if (!isset($release['assets']) || !is_array($release['assets'])) {
            return '';
        }

        foreach ($release['assets'] as $asset) {
            $name = $asset['name'] ?? '';
            if (preg_match('/\.zip$/i', $name)) {
                return $asset['browser_download_url'] ?? '';
            }
        }

        return '';
    }
}
