<?php
declare(strict_types=1);

namespace PulsePress\Http\Controllers;

use DateTimeImmutable;
use DateTimeZone;
use PulsePress\Analytics\MetricsCalculator;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class AnalyticsController
{
    /**
     * Performance ceiling for a single synchronous analytics request.
     *
     * This is NOT a Free/Pro gate. Same value for every install. A 2-year
     * window keeps a single REST request snappy on tables up to ~1M rows.
     * Sites that need longer windows in one shot can raise it via the
     * pulsepress_analytics_max_days filter. Pro adds pre-aggregated
     * weekly/monthly rollups for snappy multi-year queries by default.
     */
    public const MAX_WINDOW_DAYS = 730;

    /** Default window when the admin opens the dashboard with no args. */
    public const DEFAULT_WINDOW_DAYS = 30;

    public function __construct(private MetricsCalculator $calculator)
    {
    }

    public function summary(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $timezone = wp_timezone();
        $today    = new DateTimeImmutable('today', $timezone);

        $fromParam = (string) ($request->get_param('from') ?? '');
        $toParam   = (string) ($request->get_param('to') ?? '');

        $maxDays = (int) apply_filters('pulsepress_analytics_max_days', self::MAX_WINDOW_DAYS);
        if ($maxDays < 1) {
            $maxDays = self::MAX_WINDOW_DAYS;
        }

        $to   = $this->parseDate($toParam, $today, $timezone)->setTime(0, 0, 0)->modify('+1 day');
        $from = $this->parseDate($fromParam, $today->modify('-' . (self::DEFAULT_WINDOW_DAYS - 1) . ' days'), $timezone)->setTime(0, 0, 0);

        if ($from > $to) {
            $from = $to->modify('-1 day');
        }

        $diffDays = (int) $from->diff($to)->days;
        $clamped  = false;
        if ($diffDays > $maxDays) {
            $from    = $to->modify('-' . $maxDays . ' days');
            $clamped = true;
        }

        $utc      = new DateTimeZone('UTC');
        $fromUtc  = $from->setTimezone($utc);
        $toUtc    = $to->setTimezone($utc);

        $window = apply_filters('pulsepress_analytics_window', [
            'from'    => $fromUtc,
            'to'      => $toUtc,
            'clamped' => $clamped,
        ], $request);

        if (is_array($window)) {
            $fromUtc = $window['from'] instanceof DateTimeImmutable ? $window['from'] : $fromUtc;
            $toUtc   = $window['to'] instanceof DateTimeImmutable ? $window['to'] : $toUtc;
            $clamped = (bool) ($window['clamped'] ?? $clamped);
        }

        $envelope = $this->calculator->calculate($fromUtc, $toUtc, $clamped);

        $fromLocal = $envelope->fromUtc->setTimezone($timezone)->format('Y-m-d');
        $toLocal   = $envelope->toUtc->setTimezone($timezone)->modify('-1 second')->format('Y-m-d');

        return new WP_REST_Response([
            'from'              => $fromLocal,
            'to'                => $toLocal,
            'clamped'           => $envelope->clamped,
            'totalReactions'    => $envelope->totalReactions,
            'positiveReactions' => $envelope->positiveReactions,
            'totalCaptures'     => $envelope->totalCaptures,
            'sentimentRate'     => $envelope->sentimentRate,
            'captureRate'       => $envelope->captureRate,
            'dailySeries'       => (object) $envelope->dailySeries,
            'topPosts'          => $envelope->topPosts,
            'postTitles'        => (object) $envelope->postTitles,
            'positiveSet'       => $envelope->positiveSet,
        ], 200);
    }

    private function parseDate(string $raw, DateTimeImmutable $fallback, DateTimeZone $timezone): DateTimeImmutable
    {
        if ($raw === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return $fallback;
        }
        try {
            return (new DateTimeImmutable($raw, $timezone));
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
