<?php
declare(strict_types=1);

namespace Moonfarmer\ReactionsLeadCapture\Analytics;

use DateTimeImmutable;
use DateTimeZone;
use Moonfarmer\ReactionsLeadCapture\Database\Schema;
use wpdb;


if (!defined('ABSPATH')) {
    exit;
}

final class Aggregator
{
    private wpdb $wpdb;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function aggregate(DateTimeImmutable $localDate): AggregationResult
    {
        $start = microtime(true);

        $localStart = $localDate->setTime(0, 0, 0);
        $localEnd   = $localStart->modify('+1 day');
        $utc        = new DateTimeZone('UTC');
        $utcStart   = $localStart->setTimezone($utc)->format('Y-m-d H:i:s');
        $utcEnd     = $localEnd->setTimezone($utc)->format('Y-m-d H:i:s');

        $reactionsTable = Schema::tableName($this->wpdb, Schema::TABLE_REACTIONS);
        $aggTable       = Schema::tableName($this->wpdb, Schema::TABLE_DAILY_AGG);

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is selected from Schema allowlist.
        $selectSql = $this->wpdb->prepare(
            "SELECT post_id, reaction_type, COUNT(*) AS c
             FROM {$reactionsTable}
             WHERE updated_at >= %s AND updated_at < %s
             GROUP BY post_id, reaction_type",
            $utcStart,
            $utcEnd
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared above with an allowlisted table name.
        $rows = $this->wpdb->get_results($selectSql, ARRAY_A);
        if (!is_array($rows)) {
            return new AggregationResult($localStart, 0, 0, (int) ((microtime(true) - $start) * 1_000_000));
        }

        $now      = (new DateTimeImmutable('now', $utc))->format('Y-m-d H:i:s');
        $aggDate  = $localStart->format('Y-m-d');
        $written  = 0;
        $groups   = count($rows);

        foreach ($rows as $row) {
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is selected from Schema allowlist.
            $sql = $this->wpdb->prepare(
                "INSERT INTO {$aggTable}
                  (agg_date, post_id, reaction_type, count, updated_at)
                 VALUES (%s, %d, %s, %d, %s)
                 ON DUPLICATE KEY UPDATE
                   count      = VALUES(count),
                   updated_at = VALUES(updated_at)",
                $aggDate,
                (int) $row['post_id'],
                (string) $row['reaction_type'],
                (int) $row['c'],
                $now
            );
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared above with an allowlisted table name.
            $this->wpdb->query($sql);
            $written++;
        }

        $result = new AggregationResult(
            $localStart,
            $written,
            $groups,
            (int) ((microtime(true) - $start) * 1_000_000)
        );

        do_action('moonfarmer_reactions_lead_capture_after_aggregate', $result);

        return $result;
    }
}
