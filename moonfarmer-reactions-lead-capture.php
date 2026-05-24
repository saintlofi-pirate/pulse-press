<?php
/**
 * Plugin Name:       Moonfarmer Reactions Lead Capture
 * Plugin URI:        https://saintlofi-pirate.github.io/moonfarmer-reactions-lead-capture/
 * Description:       Reactions, inline email capture, and analytics — privacy-first and built for WordPress.
 * Version:           0.1.0
 * Author:            Alp Arsalan
 * Author URI:        https://profiles.wordpress.org/saintlofi/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       moonfarmer-reactions-lead-capture
 * Domain Path:       /languages
 * Requires at least: 6.2
 * Requires PHP:      7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MOONFARMER_REACTIONS_LEAD_CAPTURE_VERSION', '0.1.0');
define('MOONFARMER_REACTIONS_LEAD_CAPTURE_FILE', __FILE__);
define('MOONFARMER_REACTIONS_LEAD_CAPTURE_DIR', plugin_dir_path(__FILE__));
define('MOONFARMER_REACTIONS_LEAD_CAPTURE_URL', plugin_dir_url(__FILE__));
define('MOONFARMER_REACTIONS_LEAD_CAPTURE_BASENAME', plugin_basename(__FILE__));
define('MOONFARMER_REACTIONS_LEAD_CAPTURE_MIN_PHP', '7.4');
define('MOONFARMER_REACTIONS_LEAD_CAPTURE_MIN_WP', '6.2');

if (PHP_VERSION_ID < 70400 || version_compare($GLOBALS['wp_version'] ?? '0', MOONFARMER_REACTIONS_LEAD_CAPTURE_MIN_WP, '<')) {
    add_action('admin_notices', static function () {
        $message = sprintf(
            /* translators: 1: required PHP version, 2: required WordPress version */
            esc_html__('Moonfarmer Reactions Lead Capture requires PHP %1$s or newer and WordPress %2$s or newer. The plugin is inactive until the host is upgraded.', 'moonfarmer-reactions-lead-capture'),
            MOONFARMER_REACTIONS_LEAD_CAPTURE_MIN_PHP,
            MOONFARMER_REACTIONS_LEAD_CAPTURE_MIN_WP
        );
        printf('<div class="notice notice-error"><p>%s</p></div>', wp_kses_post($message));
    });
    return;
}

if (file_exists(MOONFARMER_REACTIONS_LEAD_CAPTURE_DIR . 'vendor/autoload.php')) {
    require_once MOONFARMER_REACTIONS_LEAD_CAPTURE_DIR . 'vendor/autoload.php';
}

register_activation_hook(__FILE__, static function (): void {
    if (get_option('moonfarmer_reactions_lead_capture_db_version') === false) {
        update_option('moonfarmer_reactions_lead_capture_db_version', '0', false);
    }
    if (get_option('moonfarmer_reactions_lead_capture_delete_on_uninstall') === false) {
        update_option('moonfarmer_reactions_lead_capture_delete_on_uninstall', '0', false);
    }
    if (get_option('moonfarmer_reactions_lead_capture_retention_days') === false) {
        update_option('moonfarmer_reactions_lead_capture_retention_days', '0', false);
    }

    \Moonfarmer\ReactionsLeadCapture\Core\Application::boot(MOONFARMER_REACTIONS_LEAD_CAPTURE_FILE);

    if (!wp_next_scheduled(\Moonfarmer\ReactionsLeadCapture\Providers\CaptureServiceProvider::PURGE_HOOK)) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', \Moonfarmer\ReactionsLeadCapture\Providers\CaptureServiceProvider::PURGE_HOOK);
    }

    if (!wp_next_scheduled(\Moonfarmer\ReactionsLeadCapture\Providers\AnalyticsServiceProvider::CRON_HOOK)) {
        $firstRun = (new \DateTimeImmutable('today 02:00', wp_timezone()))->modify('+1 day');
        wp_schedule_event($firstRun->getTimestamp(), 'daily', \Moonfarmer\ReactionsLeadCapture\Providers\AnalyticsServiceProvider::CRON_HOOK);
    }
});

register_deactivation_hook(__FILE__, static function (): void {
    $next = wp_next_scheduled(\Moonfarmer\ReactionsLeadCapture\Providers\CaptureServiceProvider::PURGE_HOOK);
    if ($next !== false) {
        wp_unschedule_event($next, \Moonfarmer\ReactionsLeadCapture\Providers\CaptureServiceProvider::PURGE_HOOK);
    }

    $aggNext = wp_next_scheduled(\Moonfarmer\ReactionsLeadCapture\Providers\AnalyticsServiceProvider::CRON_HOOK);
    if ($aggNext !== false) {
        wp_unschedule_event($aggNext, \Moonfarmer\ReactionsLeadCapture\Providers\AnalyticsServiceProvider::CRON_HOOK);
    }
});

add_action('plugins_loaded', static function (): void {
    \Moonfarmer\ReactionsLeadCapture\Core\Application::boot(MOONFARMER_REACTIONS_LEAD_CAPTURE_FILE);
}, 5);
