<?php
declare(strict_types=1);

namespace Moonfarmer\ReactionsLeadCapture\Database;

use wpdb;


if (!defined('ABSPATH')) {
    exit;
}

final class Schema
{
    public const VERSION = 1;

    public const TABLE_REACTIONS  = 'moonfarmer_reactions_lead_capture_reactions';
    public const TABLE_CAPTURES   = 'moonfarmer_reactions_lead_capture_captures';
    public const TABLE_DAILY_AGG  = 'moonfarmer_reactions_lead_capture_daily_agg';

    /**
     * Map of un-prefixed table name → CREATE TABLE SQL ready for dbDelta().
     *
     * dbDelta conventions are non-negotiable here: two spaces after PRIMARY KEY,
     * KEY name on every index, trailing semicolon, no backticks on PRIMARY KEY.
     *
     * @return array<string, string>
     */
    public static function tables(wpdb $wpdb): array
    {
        $charset = $wpdb->get_charset_collate();
        $prefix  = $wpdb->prefix;

        $reactions = "CREATE TABLE {$prefix}" . self::TABLE_REACTIONS . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            reaction_type VARCHAR(32) NOT NULL,
            user_hash CHAR(64) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_post_user (post_id, user_hash),
            KEY idx_post_reaction (post_id, reaction_type),
            KEY idx_updated (updated_at)
        ) {$charset};";

        $captures = "CREATE TABLE {$prefix}" . self::TABLE_CAPTURES . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            email VARCHAR(190) NOT NULL,
            reaction_type VARCHAR(32) NOT NULL,
            consent TINYINT(1) NOT NULL DEFAULT 0,
            consent_text_version VARCHAR(32) NOT NULL,
            consent_at DATETIME NOT NULL,
            source VARCHAR(32) NOT NULL,
            ip_hash CHAR(64) NULL,
            user_agent_hash CHAR(64) NULL,
            fraud_metadata_purge_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_email_post (email, post_id),
            KEY idx_purge (fraud_metadata_purge_at),
            KEY idx_post (post_id)
        ) {$charset};";

        $dailyAgg = "CREATE TABLE {$prefix}" . self::TABLE_DAILY_AGG . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            agg_date DATE NOT NULL,
            post_id BIGINT UNSIGNED NOT NULL,
            reaction_type VARCHAR(32) NOT NULL,
            count INT UNSIGNED NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_date_post_reaction (agg_date, post_id, reaction_type),
            KEY idx_post_date (post_id, agg_date)
        ) {$charset};";

        return [
            self::TABLE_REACTIONS => $reactions,
            self::TABLE_CAPTURES  => $captures,
            self::TABLE_DAILY_AGG => $dailyAgg,
        ];
    }

    public static function tableName(wpdb $wpdb, string $unprefixed): string
    {
        if (!in_array($unprefixed, [self::TABLE_REACTIONS, self::TABLE_CAPTURES, self::TABLE_DAILY_AGG], true)) {
            throw new \InvalidArgumentException('Unknown Moonfarmer Reactions Lead Capture table.');
        }

        $table = $wpdb->prefix . $unprefixed;
        return function_exists('esc_sql') ? esc_sql($table) : str_replace('`', '', $table);
    }
}
