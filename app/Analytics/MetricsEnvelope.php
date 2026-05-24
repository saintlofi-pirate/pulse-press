<?php
declare(strict_types=1);

namespace Moonfarmer\ReactionsLeadCapture\Analytics;

use DateTimeImmutable;


if (!defined('ABSPATH')) {
    exit;
}

final class MetricsEnvelope
{
    public DateTimeImmutable $fromUtc;
    public DateTimeImmutable $toUtc;
    public bool $clamped;
    public int $totalReactions;
    public int $positiveReactions;
    public int $totalCaptures;
    public ?float $sentimentRate;
    public ?float $captureRate;
    public array $dailySeries;
    public array $topPosts;
    public array $postTitles;
    public array $positiveSet;

    /**
     * @param array<string, array<string, int>>             $dailySeries
     * @param list<array{post_id:int,total:int,positive:int,captures:int}> $topPosts
     * @param array<int, string>                            $postTitles
     * @param string[]                                      $positiveSet
     */
    public function __construct(DateTimeImmutable $fromUtc, DateTimeImmutable $toUtc, bool $clamped, int $totalReactions, int $positiveReactions, int $totalCaptures, ?float $sentimentRate, ?float $captureRate, array $dailySeries, array $topPosts, array $postTitles, array $positiveSet)
    {
        $this->fromUtc           = $fromUtc;
        $this->toUtc             = $toUtc;
        $this->clamped           = $clamped;
        $this->totalReactions    = $totalReactions;
        $this->positiveReactions = $positiveReactions;
        $this->totalCaptures     = $totalCaptures;
        $this->sentimentRate     = $sentimentRate;
        $this->captureRate       = $captureRate;
        $this->dailySeries       = $dailySeries;
        $this->topPosts          = $topPosts;
        $this->postTitles        = $postTitles;
        $this->positiveSet       = $positiveSet;
    }
}
