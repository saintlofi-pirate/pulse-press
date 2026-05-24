<?php
/**
 * Moonfarmer Reactions Lead Capture uninstall handler.
 *
 * Invoked by WordPress when the admin clicks "Delete" on the plugin row.
 * Non-destructive by default; only drops tables and removes options when the
 * saved privacy setting explicitly enables full cleanup.
 */

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$moonfarmerSettings = get_option('moonfarmer_reactions_lead_capture_settings', []);
$moonfarmerDeleteOnUninstall = is_array($moonfarmerSettings)
    ? (bool) ($moonfarmerSettings['delete_on_uninstall'] ?? false)
    : get_option('moonfarmer_reactions_lead_capture_delete_on_uninstall') === '1';

if (!$moonfarmerDeleteOnUninstall) {
    return;
}

/** @var wpdb $wpdb */
global $wpdb;

$moonfarmerTables = [
    $wpdb->prefix . 'moonfarmer_reactions_lead_capture_reactions',
    $wpdb->prefix . 'moonfarmer_reactions_lead_capture_captures',
    $wpdb->prefix . 'moonfarmer_reactions_lead_capture_daily_agg',
];

foreach ($moonfarmerTables as $moonfarmerTable) {
    $wpdb->query("DROP TABLE IF EXISTS `{$moonfarmerTable}`"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Uninstall cleanup intentionally scans only namespaced Moonfarmer Reactions Lead Capture options.
$moonfarmerOptionKeys = $wpdb->get_col(
    $wpdb->prepare(
        "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
        $wpdb->esc_like('moonfarmer_reactions_lead_capture_') . '%'
    )
);

foreach ($moonfarmerOptionKeys as $moonfarmerKey) {
    delete_option($moonfarmerKey);
}
