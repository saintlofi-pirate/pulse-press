<?php
/**
 * Plugin Name:       PulsePress
 * Plugin URI:        https://pulsepress.dev
 * Description:       Reactions, inline email capture, and analytics — privacy-first and built for WordPress.
 * Version:           0.1.0
 * Author:            PulsePress
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pulsepress
 * Domain Path:       /languages
 * Requires at least: 6.2
 * Requires PHP:      8.1
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PULSEPRESS_VERSION', '0.1.0');
define('PULSEPRESS_FILE', __FILE__);
define('PULSEPRESS_DIR', plugin_dir_path(__FILE__));
define('PULSEPRESS_URL', plugin_dir_url(__FILE__));
define('PULSEPRESS_BASENAME', plugin_basename(__FILE__));
define('PULSEPRESS_MIN_PHP', '8.1');
define('PULSEPRESS_MIN_WP', '6.2');

if (PHP_VERSION_ID < 80100 || version_compare($GLOBALS['wp_version'] ?? '0', PULSEPRESS_MIN_WP, '<')) {
    add_action('admin_notices', static function () {
        $message = sprintf(
            /* translators: 1: required PHP version, 2: required WordPress version */
            esc_html__('PulsePress requires PHP %1$s or newer and WordPress %2$s or newer. The plugin is inactive until the host is upgraded.', 'pulsepress'),
            PULSEPRESS_MIN_PHP,
            PULSEPRESS_MIN_WP
        );
        printf('<div class="notice notice-error"><p>%s</p></div>', $message);
    });
    return;
}

if (file_exists(PULSEPRESS_DIR . 'vendor/autoload.php')) {
    require_once PULSEPRESS_DIR . 'vendor/autoload.php';
}

register_activation_hook(__FILE__, static function (): void {
    if (get_option('pulsepress_db_version') === false) {
        update_option('pulsepress_db_version', '0', false);
    }
    if (get_option('pulsepress_delete_on_uninstall') === false) {
        update_option('pulsepress_delete_on_uninstall', '0', false);
    }
    if (get_option('pulsepress_retention_days') === false) {
        update_option('pulsepress_retention_days', '0', false);
    }

    \PulsePress\Core\Application::boot(PULSEPRESS_FILE);
});

register_deactivation_hook(__FILE__, static function (): void {
    // Reserved for future cleanup. Intentionally empty in v0.1.
});

add_action('plugins_loaded', static function (): void {
    \PulsePress\Core\Application::boot(PULSEPRESS_FILE);
}, 5);
