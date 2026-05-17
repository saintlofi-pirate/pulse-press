<?php
declare(strict_types=1);

namespace PulsePress\Database;

use wpdb;


if (!defined('ABSPATH')) {
    exit;
}

final class Migrator
{
    public const VERSION_OPTION = 'pulsepress_db_version';

    private wpdb $wpdb;
    private Schema $schema;

    public function __construct(wpdb $wpdb, Schema $schema)
    {
        $this->wpdb   = $wpdb;
        $this->schema = $schema;
    }

    public function migrate(): bool
    {
        $current = $this->currentVersion();
        $latest  = $this->latestVersion();

        if ($current >= $latest) {
            return false;
        }

        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        foreach ($this->schema::tables($this->wpdb) as $statement) {
            dbDelta($statement);
        }

        foreach (array_keys($this->schema::tables($this->wpdb)) as $unprefixed) {
            $tableName = Schema::tableName($this->wpdb, $unprefixed);
            $sql       = $this->wpdb->prepare('SHOW TABLES LIKE %s', $tableName);
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately above.
            $exists    = $this->wpdb->get_var($sql);

            if ($exists !== $tableName) {
                return false;
            }
        }

        update_option(self::VERSION_OPTION, (string) $latest, false);

        return true;
    }

    public function currentVersion(): int
    {
        return (int) get_option(self::VERSION_OPTION, '0');
    }

    public function latestVersion(): int
    {
        return Schema::VERSION;
    }
}
