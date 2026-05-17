<?php
declare(strict_types=1);

namespace PulsePress\Captures;

use PulsePress\Database\Schema;
use wpdb;


if (!defined('ABSPATH')) {
    exit;
}

final class CaptureRepository
{
    private wpdb $wpdb;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function store(CaptureInput $input): CaptureRecord
    {
        $existing = $this->findByEmailAndPost($input->email, $input->postId);
        if ($existing !== null) {
            return new CaptureRecord((int) $existing['id'], $input, CaptureRecord::STATUS_ALREADY_EXISTS);
        }

        $table = Schema::tableName($this->wpdb, Schema::TABLE_CAPTURES);
        $now   = $input->consentAt->format('Y-m-d H:i:s');

        $inserted = $this->wpdb->insert(
            $table,
            [
                'post_id'                 => $input->postId,
                'email'                   => $input->email,
                'reaction_type'           => $input->reactionType,
                'consent'                 => 1,
                'consent_text_version'    => $input->consentTextVersion,
                'consent_at'              => $now,
                'source'                  => $input->source,
                'ip_hash'                 => $input->ipHash,
                'user_agent_hash'         => $input->userAgentHash,
                'fraud_metadata_purge_at' => $input->purgeAt->format('Y-m-d H:i:s'),
                'created_at'              => $now,
                'updated_at'              => $now,
            ],
            ['%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        if ($inserted === false || (int) $this->wpdb->insert_id === 0) {
            // Race: another request inserted between the SELECT and our INSERT.
            $existing = $this->findByEmailAndPost($input->email, $input->postId);
            if ($existing !== null) {
                return new CaptureRecord((int) $existing['id'], $input, CaptureRecord::STATUS_ALREADY_EXISTS);
            }
        }

        return new CaptureRecord((int) $this->wpdb->insert_id, $input, CaptureRecord::STATUS_INSERTED);
    }

    /** @return array<string, mixed>|null */
    private function findByEmailAndPost(string $email, int $postId): ?array
    {
        $table = Schema::tableName($this->wpdb, Schema::TABLE_CAPTURES);
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is selected from Schema allowlist.
        $sql   = $this->wpdb->prepare(
            "SELECT id FROM {$table} WHERE email = %s AND post_id = %d LIMIT 1",
            $email,
            $postId
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared above with an allowlisted table name.
        $rows = $this->wpdb->get_results($sql, ARRAY_A);
        return is_array($rows) && isset($rows[0]) ? $rows[0] : null;
    }
}
