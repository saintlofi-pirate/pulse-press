<?php
declare(strict_types=1);

namespace PulsePress\Analytics;

use DateTimeImmutable;
use PulsePress\Settings\SettingsRepository;


if (!defined('ABSPATH')) {
    exit;
}

final class MetricsCalculator
{
    private AnalyticsRepository $repository;
    private SettingsRepository $settings;

    public function __construct(AnalyticsRepository $repository, SettingsRepository $settings)
    {
        $this->repository = $repository;
        $this->settings   = $settings;
    }

    public function calculate(DateTimeImmutable $fromUtc, DateTimeImmutable $toUtc, bool $clamped): MetricsEnvelope
    {
        $settings    = $this->settings->get();
        $positiveSet = is_array($settings['positive_reactions'] ?? null)
            ? array_values(array_filter($settings['positive_reactions'], 'is_string'))
            : [];

        $series   = $this->repository->dailySeries($fromUtc, $toUtc);
        $captures = $this->repository->captureRollup($fromUtc, $toUtc);

        $totalReactions    = 0;
        $positiveReactions = 0;
        foreach ($series as $day) {
            foreach ($day as $type => $count) {
                $totalReactions += $count;
                if (in_array($type, $positiveSet, true)) {
                    $positiveReactions += $count;
                }
            }
        }

        $totalCaptures = 0;
        foreach ($captures as $c) {
            $totalCaptures += $c;
        }

        $sentimentRate = $totalReactions > 0
            ? (float) round($positiveReactions / $totalReactions, 4)
            : null;
        $captureRate = $positiveReactions > 0
            ? (float) round($totalCaptures / $positiveReactions, 4)
            : null;

        $topPosts   = $this->repository->topPosts($fromUtc, $toUtc, $positiveSet);
        $postTitles = $this->resolveTitles(array_map(static fn ($row) => (int) $row['post_id'], $topPosts));

        return new MetricsEnvelope(
            $fromUtc,
            $toUtc,
            $clamped,
            $totalReactions,
            $positiveReactions,
            $totalCaptures,
            $sentimentRate,
            $captureRate,
            $series,
            $topPosts,
            $postTitles,
            $positiveSet
        );
    }

    /**
     * @param list<int> $postIds
     * @return array<int, string>
     */
    private function resolveTitles(array $postIds): array
    {
        $out = [];
        foreach ($postIds as $id) {
            $title = get_the_title($id);
            if (!is_string($title) || $title === '') {
                $out[$id] = __('(deleted post)', 'pulse-press');
            } else {
                $out[$id] = $title;
            }
        }
        return $out;
    }
}
