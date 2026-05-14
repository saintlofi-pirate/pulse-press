<?php
declare(strict_types=1);

namespace PulsePress\Providers;

use DateTimeImmutable;
use PulsePress\Analytics\Aggregator;
use PulsePress\Analytics\QueueScheduler;
use PulsePress\Analytics\WpCronScheduler;
use PulsePress\Core\ServiceProvider;

final class AnalyticsServiceProvider extends ServiceProvider
{
    public const CRON_HOOK = 'pulsepress_aggregate_reactions';

    public function register(): void
    {
        $this->app->singleton(QueueScheduler::class, fn () => new WpCronScheduler());
        $this->app->singleton(Aggregator::class, function () {
            return new Aggregator($GLOBALS['wpdb']);
        });
    }

    public function boot(): void
    {
        add_action(self::CRON_HOOK, [$this, 'runScheduledAggregation']);

        // Default site-timezone resolution for the filter (priority 5 → site filters at 10 still win).
        add_filter('pulsepress_aggregation_timezone', static function ($value) {
            if ($value instanceof \DateTimeZone) {
                return $value;
            }
            return wp_timezone();
        }, 5);
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
    }
}
