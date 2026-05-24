<?php
declare(strict_types=1);

use Moonfarmer\ReactionsLeadCapture\Analytics\AggregationResult;
use Moonfarmer\ReactionsLeadCapture\Analytics\Aggregator;
use Tests\Stubs\FilterRegistry;
use Tests\Stubs\WpdbStub;

it('runs a SELECT with UTC bounds converted from the site-local date', function () {
    $wpdb = new WpdbStub();
    $wpdb->resultsByQuery['GROUP BY post_id, reaction_type'] = [];
    $agg = new Aggregator($wpdb);

    $localDate = new DateTimeImmutable('2026-05-13 00:00:00', new DateTimeZone('UTC'));
    $agg->aggregate($localDate);

    $selectQuery = collect($wpdb->queries)->first(fn ($q) => str_contains($q, 'GROUP BY post_id, reaction_type'));
    expect($selectQuery)
        ->toContain('FROM wp_moonfarmer_reactions_lead_capture_reactions')
        ->toContain("'2026-05-13 00:00:00'")
        ->toContain("'2026-05-14 00:00:00'");
});

it('upserts one row per group into the daily_agg table', function () {
    $wpdb = new WpdbStub();
    $wpdb->resultsByQuery['GROUP BY post_id, reaction_type'] = [
        ['post_id' => 42, 'reaction_type' => 'love',  'c' => '3'],
        ['post_id' => 42, 'reaction_type' => 'angry', 'c' => '2'],
    ];
    $agg = new Aggregator($wpdb);

    $result = $agg->aggregate(new DateTimeImmutable('2026-05-13 00:00:00', new DateTimeZone('UTC')));

    expect($result)->toBeInstanceOf(AggregationResult::class);
    expect($result->rowsWritten)->toBe(2);
    expect($result->groupsProcessed)->toBe(2);

    $upserts = array_values(array_filter($wpdb->queries, fn ($q) => str_contains($q, 'INSERT INTO wp_moonfarmer_reactions_lead_capture_daily_agg')));
    expect($upserts)->toHaveCount(2);
    expect($upserts[0])->toContain('ON DUPLICATE KEY UPDATE')->toContain("'2026-05-13'")->toContain("'love'")->toContain('3');
    expect($upserts[1])->toContain("'angry'")->toContain('2');
});

it('fires moonfarmer_reactions_lead_capture_after_aggregate on success', function () {
    $wpdb = new WpdbStub();
    $wpdb->resultsByQuery['GROUP BY post_id, reaction_type'] = [
        ['post_id' => 7, 'reaction_type' => 'love', 'c' => '1'],
    ];
    $agg = new Aggregator($wpdb);

    $agg->aggregate(new DateTimeImmutable('2026-05-13 00:00:00', new DateTimeZone('UTC')));

    $calls = FilterRegistry::actionCalls('moonfarmer_reactions_lead_capture_after_aggregate');
    expect($calls)->toHaveCount(1);
    expect($calls[0][0])->toBeInstanceOf(AggregationResult::class);
});

it('fires the action even for an empty day with zero counts', function () {
    $wpdb = new WpdbStub();
    $wpdb->resultsByQuery['GROUP BY post_id, reaction_type'] = [];
    $agg = new Aggregator($wpdb);

    $result = $agg->aggregate(new DateTimeImmutable('2026-05-13 00:00:00', new DateTimeZone('UTC')));

    expect($result->groupsProcessed)->toBe(0);
    expect($result->rowsWritten)->toBe(0);
    expect(FilterRegistry::actionCalls('moonfarmer_reactions_lead_capture_after_aggregate'))->toHaveCount(1);
});

it('converts a PST local date to the correct UTC bounds (DST-aware)', function () {
    $wpdb = new WpdbStub();
    $wpdb->resultsByQuery['GROUP BY post_id, reaction_type'] = [];
    $agg = new Aggregator($wpdb);

    // PST during DST (PDT) is UTC-7 — May 13 is in DST.
    $localDate = new DateTimeImmutable('2026-05-13 00:00:00', new DateTimeZone('America/Los_Angeles'));
    $agg->aggregate($localDate);

    $select = collect($wpdb->queries)->first(fn ($q) => str_contains($q, 'GROUP BY post_id, reaction_type'));
    expect($select)
        ->toContain("'2026-05-13 07:00:00'")
        ->toContain("'2026-05-14 07:00:00'");
});

function collect(array $rows)
{
    return new class($rows) {
        public function __construct(private array $rows) {}
        public function first(callable $pred): ?string
        {
            foreach ($this->rows as $row) {
                if ($pred($row)) return $row;
            }
            return null;
        }
    };
}
