<?php
declare(strict_types=1);

namespace PulsePress\Analytics;

use DateTimeImmutable;
use PulsePress\Database\Schema;
use wpdb;

final class AnalyticsRepository
{
    public function __construct(private wpdb $wpdb)
    {
    }

    /** @return array<string, array<string, int>> */
    public function dailySeries(DateTimeImmutable $fromUtc, DateTimeImmutable $toUtc): array
    {
        $table = Schema::tableName($this->wpdb, Schema::TABLE_DAILY_AGG);
        $sql   = $this->wpdb->prepare(
            "SELECT agg_date, reaction_type, SUM(count) AS c
             FROM {$table}
             WHERE agg_date >= %s AND agg_date < %s
             GROUP BY agg_date, reaction_type
             ORDER BY agg_date ASC",
            $fromUtc->format('Y-m-d'),
            $toUtc->format('Y-m-d')
        );
        $rows = $this->wpdb->get_results($sql, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        $series = [];
        foreach ($rows as $row) {
            $date = (string) $row['agg_date'];
            $type = (string) $row['reaction_type'];
            $count = (int) $row['c'];
            $series[$date] ??= [];
            $series[$date][$type] = $count;
        }
        return $series;
    }

    /**
     * @return list<array{post_id:int,total:int,positive:int,captures:int}>
     */
    public function topPosts(DateTimeImmutable $fromUtc, DateTimeImmutable $toUtc, array $positiveSet, int $limit = 10): array
    {
        $aggTable = Schema::tableName($this->wpdb, Schema::TABLE_DAILY_AGG);
        $capTable = Schema::tableName($this->wpdb, Schema::TABLE_CAPTURES);

        $positiveSet = array_values(array_filter($positiveSet, 'is_string'));
        if ($positiveSet === []) {
            $positiveSet = ['love'];
        }

        $placeholders = implode(',', array_fill(0, count($positiveSet), '%s'));

        $args   = array_merge(
            [$fromUtc->format('Y-m-d'), $toUtc->format('Y-m-d')],
            $positiveSet,
            [$fromUtc->format('Y-m-d H:i:s'), $toUtc->format('Y-m-d H:i:s'), $limit]
        );

        $sql = $this->wpdb->prepare(
            "SELECT agg.post_id,
                    SUM(agg.count) AS total,
                    SUM(CASE WHEN agg.reaction_type IN ({$placeholders}) THEN agg.count ELSE 0 END) AS positive,
                    COALESCE(caps.captures, 0) AS captures
             FROM {$aggTable} AS agg
             LEFT JOIN (
                 SELECT post_id, COUNT(*) AS captures
                 FROM {$capTable}
                 WHERE consent_at >= %s AND consent_at < %s
                 GROUP BY post_id
             ) AS caps ON caps.post_id = agg.post_id
             WHERE agg.agg_date >= %s AND agg.agg_date < %s
             GROUP BY agg.post_id, caps.captures
             ORDER BY total DESC
             LIMIT %d",
            ...$this->reorderTopPostArgs($fromUtc, $toUtc, $positiveSet, $limit)
        );

        $rows = $this->wpdb->get_results($sql, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'post_id'  => (int) $row['post_id'],
                'total'    => (int) $row['total'],
                'positive' => (int) $row['positive'],
                'captures' => (int) $row['captures'],
            ];
        }
        return $out;
    }

    /** @return array<int, int> */
    public function captureRollup(DateTimeImmutable $fromUtc, DateTimeImmutable $toUtc): array
    {
        $table = Schema::tableName($this->wpdb, Schema::TABLE_CAPTURES);
        $sql   = $this->wpdb->prepare(
            "SELECT post_id, COUNT(*) AS c
             FROM {$table}
             WHERE consent_at >= %s AND consent_at < %s
             GROUP BY post_id",
            $fromUtc->format('Y-m-d H:i:s'),
            $toUtc->format('Y-m-d H:i:s')
        );
        $rows = $this->wpdb->get_results($sql, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['post_id']] = (int) $row['c'];
        }
        return $out;
    }

    private function reorderTopPostArgs(DateTimeImmutable $fromUtc, DateTimeImmutable $toUtc, array $positiveSet, int $limit): array
    {
        // SQL placeholder order: positive IN(...), captures join (from, to), main WHERE (from, to), LIMIT
        return array_merge(
            $positiveSet,
            [$fromUtc->format('Y-m-d H:i:s'), $toUtc->format('Y-m-d H:i:s')],
            [$fromUtc->format('Y-m-d'), $toUtc->format('Y-m-d')],
            [$limit]
        );
    }
}
