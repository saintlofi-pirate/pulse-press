<?php
declare(strict_types=1);

namespace PulsePress\Captures;

use PulsePress\Database\Schema;
use wpdb;

final class FraudPurger
{
    public function __construct(private wpdb $wpdb)
    {
    }

    public function run(): int
    {
        $table   = Schema::tableName($this->wpdb, Schema::TABLE_CAPTURES);
        $sql = $this->wpdb->prepare(
            "UPDATE %i
             SET ip_hash = NULL, user_agent_hash = NULL
             WHERE fraud_metadata_purge_at <= NOW()
               AND (ip_hash IS NOT NULL OR user_agent_hash IS NOT NULL)",
            $table
        );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared above with a table identifier placeholder.
        $affected = $this->wpdb->query($sql);
        return is_int($affected) ? $affected : 0;
    }
}
