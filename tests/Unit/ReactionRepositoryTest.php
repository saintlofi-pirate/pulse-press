<?php
declare(strict_types=1);

use PulsePress\Reactions\ReactionRepository;
use Tests\Stubs\TransientStore;
use Tests\Stubs\WpdbStub;

it('runs an INSERT ... ON DUPLICATE KEY UPDATE with prepared values on replace()', function () {
    $wpdb = new WpdbStub();
    $wpdb->rows_affected = 1;
    $repo = new ReactionRepository($wpdb);

    $now = new DateTimeImmutable('2026-05-13 12:34:56', new DateTimeZone('UTC'));
    $status = $repo->replace(42, 'love', 'abcdef', $now);

    expect($status)->toBe('inserted');
    expect($wpdb->last_query)
        ->toContain('INSERT INTO wp_pulsepress_reactions')
        ->toContain('ON DUPLICATE KEY UPDATE')
        ->toContain('42')
        ->toContain("'love'")
        ->toContain("'abcdef'")
        ->toContain("'2026-05-13 12:34:56'");
});

it('returns "updated" when rows_affected reports the ON DUPLICATE UPDATE path', function () {
    $wpdb = new WpdbStub();
    $wpdb->rows_affected = 2;
    $repo = new ReactionRepository($wpdb);

    $status = $repo->replace(42, 'angry', 'abcdef', new DateTimeImmutable('now', new DateTimeZone('UTC')));

    expect($status)->toBe('updated');
});

it('serves countsForPost from the transient when present', function () {
    TransientStore::set('pulsepress_counts_42', ['love' => 3, 'angry' => 1], 300);

    $wpdb = new WpdbStub();
    $repo = new ReactionRepository($wpdb);

    $counts = $repo->countsForPost(42);

    expect($counts)->toBe(['love' => 3, 'angry' => 1]);
    expect($repo->lastReadWasCached())->toBeTrue();
    expect($wpdb->queries)->toBeEmpty();
});

it('queries on cache miss and primes the transient', function () {
    $wpdb = new WpdbStub();
    $wpdb->resultsByQuery['GROUP BY reaction_type'] = [
        ['reaction_type' => 'love',  'c' => '5'],
        ['reaction_type' => 'angry', 'c' => '2'],
    ];
    $repo = new ReactionRepository($wpdb);

    $counts = $repo->countsForPost(42);

    expect($counts)->toBe(['love' => 5, 'angry' => 2]);
    expect($repo->lastReadWasCached())->toBeFalse();
    expect(TransientStore::get('pulsepress_counts_42'))->toBe(['love' => 5, 'angry' => 2]);
    expect(TransientStore::ttl('pulsepress_counts_42'))->toBe(ReactionRepository::COUNT_CACHE_TTL);
});

it('returns an empty array (and caches it) when a post has no reactions', function () {
    $wpdb = new WpdbStub();
    $repo = new ReactionRepository($wpdb);

    $counts = $repo->countsForPost(42);

    expect($counts)->toBe([]);
    expect(TransientStore::exists('pulsepress_counts_42'))->toBeTrue();
});

it('invalidates the counts transient on invalidateCounts()', function () {
    TransientStore::set('pulsepress_counts_42', ['love' => 1], 300);
    $repo = new ReactionRepository(new WpdbStub());

    $repo->invalidateCounts(42);

    expect(TransientStore::exists('pulsepress_counts_42'))->toBeFalse();
});

it('purges reaction rows older than the cutoff', function () {
    $wpdb = new WpdbStub();
    $wpdb->rows_affected = 4;
    $repo = new ReactionRepository($wpdb);

    $deleted = $repo->purgeOlderThan(new DateTimeImmutable('2026-05-01 00:00:00', new DateTimeZone('UTC')));

    expect($deleted)->toBe(4);
    expect($wpdb->last_query)
        ->toContain('DELETE FROM wp_pulsepress_reactions')
        ->toContain("updated_at < '2026-05-01 00:00:00'");
});
