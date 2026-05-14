<?php
declare(strict_types=1);

use PulsePress\Analytics\WpCronScheduler;
use Tests\Stubs\CronSpy;

it('schedules an event via wp_schedule_event', function () {
    $sched = new WpCronScheduler();
    $sched->schedule('demo_hook', 'daily', 1700000000);

    expect(CronSpy::next('demo_hook'))->toBe(1700000000);
});

it('does not double-schedule when already scheduled', function () {
    $sched = new WpCronScheduler();
    $sched->schedule('demo_hook', 'daily', 1700000000);
    $sched->schedule('demo_hook', 'daily', 1800000000);

    // Stub only stores last schedule; production WpCronScheduler short-circuits.
    expect(CronSpy::next('demo_hook'))->toBe(1700000000);
});

it('unschedules cleanly', function () {
    $sched = new WpCronScheduler();
    $sched->schedule('demo_hook', 'daily', 1700000000);

    $sched->unschedule('demo_hook');

    expect(CronSpy::next('demo_hook'))->toBeFalse();
});

it('reports isScheduled correctly', function () {
    $sched = new WpCronScheduler();
    expect($sched->isScheduled('demo_hook'))->toBeFalse();
    $sched->schedule('demo_hook', 'daily', 1700000000);
    expect($sched->isScheduled('demo_hook'))->toBeTrue();
});
