<?php
declare(strict_types=1);

use PulsePress\Database\Schema;
use Tests\Stubs\WpdbStub;

it('exposes a single integer schema version constant', function () {
    expect(Schema::VERSION)->toBe(1);
});

it('declares the three expected tables keyed by un-prefixed name', function () {
    $wpdb = new WpdbStub();
    $tables = Schema::tables($wpdb);

    expect($tables)
        ->toHaveKey(Schema::TABLE_REACTIONS)
        ->toHaveKey(Schema::TABLE_CAPTURES)
        ->toHaveKey(Schema::TABLE_DAILY_AGG)
        ->and($tables)->toHaveCount(3);
});

it('prefixes every CREATE statement with the wpdb prefix', function () {
    $wpdb = new WpdbStub();
    foreach (Schema::tables($wpdb) as $unprefixed => $sql) {
        expect($sql)->toContain("wp_{$unprefixed}");
    }
});

it('embeds the wpdb charset_collate at the end of every CREATE statement', function () {
    $wpdb = new WpdbStub();
    foreach (Schema::tables($wpdb) as $sql) {
        expect($sql)->toContain($wpdb->get_charset_collate());
    }
});

it('declares the replacement uniqueness on reactions', function () {
    $wpdb = new WpdbStub();
    $sql = Schema::tables($wpdb)[Schema::TABLE_REACTIONS];
    expect($sql)
        ->toContain('UNIQUE KEY uniq_post_user (post_id, user_hash)')
        ->toContain('KEY idx_post_reaction (post_id, reaction_type)');
});

it('declares the fraud-purge column and index on captures', function () {
    $wpdb = new WpdbStub();
    $sql = Schema::tables($wpdb)[Schema::TABLE_CAPTURES];
    expect($sql)
        ->toContain('fraud_metadata_purge_at DATETIME NOT NULL')
        ->toContain('KEY idx_purge (fraud_metadata_purge_at)')
        ->toContain('UNIQUE KEY uniq_email_post (email, post_id)');
});

it('declares the composite upsert key on daily aggregates', function () {
    $wpdb = new WpdbStub();
    $sql = Schema::tables($wpdb)[Schema::TABLE_DAILY_AGG];
    expect($sql)
        ->toContain('UNIQUE KEY uniq_date_post_reaction (agg_date, post_id, reaction_type)')
        ->toContain('KEY idx_post_date (post_id, agg_date)');
});

it('uses dbDelta conventions (two spaces after PRIMARY KEY, no backticks)', function () {
    $wpdb = new WpdbStub();
    foreach (Schema::tables($wpdb) as $sql) {
        expect($sql)
            ->toContain('PRIMARY KEY  (id)')
            ->not->toContain('`PRIMARY KEY`');
    }
});

it('computes the prefixed table name via tableName()', function () {
    $wpdb = new WpdbStub();
    expect(Schema::tableName($wpdb, Schema::TABLE_REACTIONS))->toBe('wp_pulsepress_reactions');
});
