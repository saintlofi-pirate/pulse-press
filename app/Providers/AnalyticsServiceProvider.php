<?php
declare(strict_types=1);

namespace Moonfarmer\ReactionsLeadCapture\Providers;

use DateTimeImmutable;
use Moonfarmer\ReactionsLeadCapture\Analytics\Aggregator;
use Moonfarmer\ReactionsLeadCapture\Analytics\AnalyticsRepository;
use Moonfarmer\ReactionsLeadCapture\Analytics\MetricsCalculator;
use Moonfarmer\ReactionsLeadCapture\Analytics\QueueScheduler;
use Moonfarmer\ReactionsLeadCapture\Analytics\WpCronScheduler;
use Moonfarmer\ReactionsLeadCapture\Core\ServiceProvider;
use Moonfarmer\ReactionsLeadCapture\Http\Controllers\AnalyticsController;
use Moonfarmer\ReactionsLeadCapture\Reactions\ReactionRepository;
use Moonfarmer\ReactionsLeadCapture\Settings\SettingsRepository;


if (!defined('ABSPATH')) {
    exit;
}

final class AnalyticsServiceProvider extends ServiceProvider
{
    public const CRON_HOOK = 'moonfarmer_reactions_lead_capture_aggregate_reactions';

    public function register(): void
    {
        $this->app->singleton(QueueScheduler::class, fn () => new WpCronScheduler());
        $this->app->singleton(Aggregator::class, function () {
            return new Aggregator($GLOBALS['wpdb']);
        });
        $this->app->singleton(AnalyticsRepository::class, function () {
            return new AnalyticsRepository($GLOBALS['wpdb']);
        });
        if (!$this->app->has(ReactionRepository::class)) {
            $this->app->singleton(ReactionRepository::class, function () {
                return new ReactionRepository($GLOBALS['wpdb']);
            });
        }
        $this->app->singleton(MetricsCalculator::class, function () {
            return new MetricsCalculator(
                $this->app->get(AnalyticsRepository::class),
                $this->app->get(SettingsRepository::class),
            );
        });
        $this->app->singleton(AnalyticsController::class, function () {
            return new AnalyticsController($this->app->get(MetricsCalculator::class));
        });
    }

    public function boot(): void
    {
        add_action(self::CRON_HOOK, [$this, 'runScheduledAggregation']);
        add_action('rest_api_init', [$this, 'registerRestRoutes']);

        // Default site-timezone resolution for the filter (priority 5 → site filters at 10 still win).
        add_filter('moonfarmer_reactions_lead_capture_aggregation_timezone', static function ($value) {
            if ($value instanceof \DateTimeZone) {
                return $value;
            }
            return wp_timezone();
        }, 5);
    }

    public function registerRestRoutes(): void
    {
        $controller = $this->app->get(AnalyticsController::class);
        register_rest_route('moonfarmer-reactions-lead-capture/v1', '/analytics/summary', [
            'methods'             => 'GET',
            'callback'            => [$controller, 'summary'],
            'permission_callback' => static fn () => current_user_can('manage_options'),
            'args'                => [
                'from' => ['type' => 'string', 'required' => false],
                'to'   => ['type' => 'string', 'required' => false],
            ],
        ]);
    }

    public function runScheduledAggregation(): void
    {
        $timezone = apply_filters('moonfarmer_reactions_lead_capture_aggregation_timezone', wp_timezone());
        if (!$timezone instanceof \DateTimeZone) {
            $timezone = wp_timezone();
        }

        $yesterday = (new DateTimeImmutable('yesterday', $timezone))->setTime(0, 0, 0);
        $date      = apply_filters('moonfarmer_reactions_lead_capture_aggregation_date', $yesterday);

        if (!$date instanceof DateTimeImmutable) {
            $date = $yesterday;
        }

        $this->app->get(Aggregator::class)->aggregate($date);
        $this->purgeExpiredReactions();
    }

    private function purgeExpiredReactions(): void
    {
        $settings = $this->app->get(SettingsRepository::class)->get();
        $days     = (int) ($settings['retention_days'] ?? 0);

        if ($days <= 0) {
            return;
        }

        $cutoff  = new DateTimeImmutable('-' . $days . ' days', new \DateTimeZone('UTC'));
        $deleted = $this->app->get(ReactionRepository::class)->purgeOlderThan($cutoff);

        do_action('moonfarmer_reactions_lead_capture_reactions_retention_purged', $deleted, $cutoff, $days);
    }
}
