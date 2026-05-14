<?php
declare(strict_types=1);

namespace PulsePress\Analytics;

use DateTimeImmutable;

final readonly class MetricsEnvelope
{
    /**
     * @param array<string, array<string, int>>             $dailySeries
     * @param list<array{post_id:int,total:int,positive:int,captures:int}> $topPosts
     * @param array<int, string>                            $postTitles
     * @param string[]                                      $positiveSet
     */
    public function __construct(
        public DateTimeImmutable $fromUtc,
        public DateTimeImmutable $toUtc,
        public bool $clamped,
        public int $totalReactions,
        public int $positiveReactions,
        public int $totalCaptures,
        public ?float $sentimentRate,
        public ?float $captureRate,
        public array $dailySeries,
        public array $topPosts,
        public array $postTitles,
        public array $positiveSet,
    ) {
    }
}
