<?php
declare(strict_types=1);

namespace PulsePress\Providers;

use DateTimeImmutable;
use PulsePress\Analytics\Aggregator;
use PulsePress\Analytics\AnalyticsRepository;
use PulsePress\Analytics\MetricsCalculator;
use PulsePress\Analytics\QueueScheduler;
use PulsePress\Analytics\WpCronScheduler;
use PulsePress\Core\ServiceProvider;
use PulsePress\Http\Controllers\AnalyticsController;
use PulsePress\Reactions\ReactionRepository;
use PulsePress\Settings\SettingsRepository;

final class AnalyticsServiceProvider extends ServiceProvider
{
    public const CRON_HOOK = 'pulsepress_aggregate_reactions';

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
        add_filter('pulsepress_aggregation_timezone', static function ($value) {
            if ($value instanceof \DateTimeZone) {
                return $value;
            }
            return wp_timezone();
        }, 5);
    }

    public function registerRestRoutes(): void
    {
        $controller = $this->app->get(AnalyticsController::class);
        register_rest_route('pulsepress/v1', '/analytics/summary', [
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
        $timezone = apply_filters('pulsepress_aggregation_timezone', wp_timezone());
        if (!$timezone instanceof \DateTimeZone) {
            $timezone = wp_timezone();
        }

        $yesterday = (new DateTimeImmutable('yesterday', $timezone))->setTime(0, 0, 0);
        $date      = apply_filters('pulsepress_aggregation_date', $yesterday);

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

        do_action('pulsepress_reactions_retention_purged', $deleted, $cutoff, $days);
    }
}
