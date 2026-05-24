<?php
declare(strict_types=1);

namespace Moonfarmer\ReactionsLeadCapture\Captures;

use Moonfarmer\ReactionsLeadCapture\Database\Schema;
use wpdb;


if (!defined('ABSPATH')) {
    exit;
}

final class FraudPurger
{
    private wpdb $wpdb;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function run(): int
    {
        $table   = Schema::tableName($this->wpdb, Schema::TABLE_CAPTURES);
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is selected from Schema allowlist and no user input is interpolated.
        $affected = $this->wpdb->query(
            "UPDATE {$table}
             SET ip_hash = NULL, user_agent_hash = NULL
             WHERE fraud_metadata_purge_at <= NOW()
               AND (ip_hash IS NOT NULL OR user_agent_hash IS NOT NULL)"
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
        return is_int($affected) ? $affected : 0;
    }
}
