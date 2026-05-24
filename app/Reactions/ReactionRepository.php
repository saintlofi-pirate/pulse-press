<?php
declare(strict_types=1);

namespace Moonfarmer\ReactionsLeadCapture\Reactions;

use DateTimeInterface;
use Moonfarmer\ReactionsLeadCapture\Database\Schema;
use wpdb;


if (!defined('ABSPATH')) {
    exit;
}

final class ReactionRepository
{
    public const COUNT_CACHE_TTL = 300;

    private bool $lastReadWasCached = false;

    private wpdb $wpdb;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function replace(int $postId, string $reactionType, string $userHash, DateTimeInterface $now): string
    {
        $table = Schema::tableName($this->wpdb, Schema::TABLE_REACTIONS);
        $timestamp = $now->format('Y-m-d H:i:s');

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is selected from Schema allowlist.
        $sql = $this->wpdb->prepare(
            "INSERT INTO {$table} (post_id, reaction_type, user_hash, created_at, updated_at)
             VALUES (%d, %s, %s, %s, %s)
             ON DUPLICATE KEY UPDATE
                 reaction_type = VALUES(reaction_type),
                 updated_at    = VALUES(updated_at)",
            $postId,
            $reactionType,
            $userHash,
            $timestamp,
            $timestamp
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared above with an allowlisted table name.
        $this->wpdb->query($sql);

        // MySQL's ON DUPLICATE KEY UPDATE returns 1 for a fresh insert and 2 for an update.
        return ($this->wpdb->rows_affected ?? 0) === 1 ? 'inserted' : 'updated';
    }

    /** @return array<string, int> */
    public function countsForPost(int $postId): array
    {
        $cacheKey = $this->cacheKey($postId);
        $cached   = get_transient($cacheKey);

        if (is_array($cached)) {
            $this->lastReadWasCached = true;
            return $cached;
        }

        $this->lastReadWasCached = false;
        $table = Schema::tableName($this->wpdb, Schema::TABLE_REACTIONS);
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is selected from Schema allowlist.
        $sql   = $this->wpdb->prepare(
            "SELECT reaction_type, COUNT(*) AS c FROM {$table} WHERE post_id = %d GROUP BY reaction_type",
            $postId
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared above with an allowlisted table name.
        $rows = $this->wpdb->get_results($sql, ARRAY_A) ?: [];

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['reaction_type']] = (int) $row['c'];
        }

        set_transient($cacheKey, $counts, self::COUNT_CACHE_TTL);

        return $counts;
    }

    public function lastReadWasCached(): bool
    {
        return $this->lastReadWasCached;
    }

    public function invalidateCounts(int $postId): void
    {
        delete_transient($this->cacheKey($postId));
    }

    public function purgeOlderThan(DateTimeInterface $cutoff): int
    {
        $table = Schema::tableName($this->wpdb, Schema::TABLE_REACTIONS);
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is selected from Schema allowlist.
        $sql = $this->wpdb->prepare(
            "DELETE FROM {$table} WHERE updated_at < %s",
            $cutoff->format('Y-m-d H:i:s')
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared above with an allowlisted table name.
        $affected = $this->wpdb->query($sql);
        return is_int($affected) ? $affected : 0;
    }

    private function cacheKey(int $postId): string
    {
        return 'moonfarmer_reactions_lead_capture_counts_' . $postId;
    }
}
