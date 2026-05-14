<?php
declare(strict_types=1);

namespace PulsePress\Analytics;

interface QueueScheduler
{
    public function schedule(string $hook, string $recurrence, ?int $firstRunTime = null): void;

    public function unschedule(string $hook): void;

    public function isScheduled(string $hook): bool;
}
