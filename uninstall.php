<?php
/**
 * PulsePress uninstall handler.
 *
 * Invoked by WordPress when the admin clicks "Delete" on the plugin row.
 * Non-destructive by default; only drops tables and removes options when
 * `pulsepress_delete_on_uninstall` is explicitly `'1'`.
 */

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

if (get_option('pulsepress_delete_on_uninstall') !== '1') {
    return;
}

/** @var wpdb $wpdb */
global $wpdb;

$tables = [
    $wpdb->prefix . 'pulsepress_reactions',
    $wpdb->prefix . 'pulsepress_captures',
    $wpdb->prefix . 'pulsepress_daily_agg',
];

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS `{$table}`"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
}

$optionKeys = $wpdb->get_col(
    $wpdb->prepare(
        "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
        $wpdb->esc_like('pulsepress_') . '%'
    )
);

foreach ($optionKeys as $key) {
    delete_option($key);
}
