<?php
declare(strict_types=1);

namespace PulsePress\Analytics;

use DateTimeImmutable;
use DateTimeZone;
use PulsePress\Database\Schema;
use wpdb;

final class Aggregator
{
    public function __construct(private wpdb $wpdb)
    {
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

        $selectSql = $this->wpdb->prepare(
            "SELECT post_id, reaction_type, COUNT(*) AS c
             FROM %i
             WHERE updated_at >= %s AND updated_at < %s
             GROUP BY post_id, reaction_type",
            $reactionsTable,
            $utcStart,
            $utcEnd
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared above with a table identifier placeholder.
        $rows = $this->wpdb->get_results($selectSql, ARRAY_A);
        if (!is_array($rows)) {
            do_action('pulsepress_aggregation_failed', (string) ($this->wpdb->last_error ?? 'unknown error'));
            return new AggregationResult($localStart, 0, 0, (int) ((microtime(true) - $start) * 1_000_000));
        }

        $now      = (new DateTimeImmutable('now', $utc))->format('Y-m-d H:i:s');
        $aggDate  = $localStart->format('Y-m-d');
        $written  = 0;
        $groups   = count($rows);

        foreach ($rows as $row) {
            $sql = $this->wpdb->prepare(
                "INSERT INTO %i
                  (agg_date, post_id, reaction_type, count, updated_at)
                 VALUES (%s, %d, %s, %d, %s)
                 ON DUPLICATE KEY UPDATE
                   count      = VALUES(count),
                   updated_at = VALUES(updated_at)",
                $aggTable,
                $aggDate,
                (int) $row['post_id'],
                (string) $row['reaction_type'],
                (int) $row['c'],
                $now
            );
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared above with a table identifier placeholder.
            $this->wpdb->query($sql);
            $written++;
        }

        $result = new AggregationResult(
            $localStart,
            $written,
            $groups,
            (int) ((microtime(true) - $start) * 1_000_000)
        );

        do_action('pulsepress_after_aggregate', $result);

        return $result;
    }
}
