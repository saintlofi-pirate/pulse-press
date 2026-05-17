<?php
declare(strict_types=1);

namespace PulsePress\Analytics;


if (!defined('ABSPATH')) {
    exit;
}

final class WpCronScheduler implements QueueScheduler
{
    public function schedule(string $hook, string $recurrence, ?int $firstRunTime = null): void
    {
        if ($this->isScheduled($hook)) {
            return;
        }
        $when = $firstRunTime ?? (time() + HOUR_IN_SECONDS);
        wp_schedule_event($when, $recurrence, $hook);
    }

    public function unschedule(string $hook): void
    {
        $next = wp_next_scheduled($hook);
        if ($next !== false) {
            wp_unschedule_event($next, $hook);
        }
    }

    public function isScheduled(string $hook): bool
    {
        return wp_next_scheduled($hook) !== false;
    }
}
