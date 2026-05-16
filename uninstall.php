<?php
/**
 * PulsePress uninstall handler.
 *
 * Invoked by WordPress when the admin clicks "Delete" on the plugin row.
 * Non-destructive by default; only drops tables and removes options when the
 * saved privacy setting explicitly enables full cleanup.
 */

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$pulsepressSettings = get_option('pulsepress_settings', []);
$pulsepressDeleteOnUninstall = is_array($pulsepressSettings)
    ? (bool) ($pulsepressSettings['delete_on_uninstall'] ?? false)
    : get_option('pulsepress_delete_on_uninstall') === '1';

if (!$pulsepressDeleteOnUninstall) {
    return;
}

/** @var wpdb $wpdb */
global $wpdb;

$pulsepressTables = [
    $wpdb->prefix . 'pulsepress_reactions',
    $wpdb->prefix . 'pulsepress_captures',
    $wpdb->prefix . 'pulsepress_daily_agg',
];

foreach ($pulsepressTables as $pulsepressTable) {
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall intentionally drops plugin tables.
    $wpdb->query(
        $wpdb->prepare('DROP TABLE IF EXISTS %i', $pulsepressTable)
    );
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall must discover plugin-owned options.
$pulsepressOptionKeys = $wpdb->get_col(
    $wpdb->prepare(
        'SELECT option_name FROM %i WHERE option_name LIKE %s',
        $wpdb->options,
        $wpdb->esc_like('pulsepress_') . '%'
    )
);
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

foreach ($pulsepressOptionKeys as $pulsepressKey) {
    delete_option($pulsepressKey);
}
