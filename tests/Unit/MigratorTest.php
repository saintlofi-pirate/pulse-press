<?php
declare(strict_types=1);

use PulsePress\Database\Migrator;
use PulsePress\Database\Schema;
use Tests\Stubs\DbDeltaSpy;
use Tests\Stubs\ErrorLogSpy;
use Tests\Stubs\OptionStore;
use Tests\Stubs\WpdbStub;

function pp_migrator_with_existing_tables(): Migrator
{
    $wpdb = new WpdbStub();
    $wpdb->existingTables = [
        'wp_pulsepress_reactions'  => null,
        'wp_pulsepress_captures'   => null,
        'wp_pulsepress_daily_agg'  => null,
    ];
    return new Migrator($wpdb, new Schema());
}

it('reports the current and latest version', function () {
    OptionStore::set(Migrator::VERSION_OPTION, '0');
    $migrator = pp_migrator_with_existing_tables();

    expect($migrator->currentVersion())->toBe(0);
    expect($migrator->latestVersion())->toBe(Schema::VERSION);
});

it('runs dbDelta for every declared table on a fresh install', function () {
    OptionStore::set(Migrator::VERSION_OPTION, '0');
    $migrator = pp_migrator_with_existing_tables();

    $result = $migrator->migrate();

    expect($result)->toBeTrue();
    expect(DbDeltaSpy::calls())->toHaveCount(3);
    expect(OptionStore::get(Migrator::VERSION_OPTION))->toBe((string) Schema::VERSION);
});

it('is a no-op when current version already matches latest', function () {
    OptionStore::set(Migrator::VERSION_OPTION, (string) Schema::VERSION);
    $migrator = pp_migrator_with_existing_tables();

    $result = $migrator->migrate();

    expect($result)->toBeFalse();
    expect(DbDeltaSpy::calls())->toBeEmpty();
});

it('does not bump the version when a declared table is missing post-flight', function () {
    OptionStore::set(Migrator::VERSION_OPTION, '0');

    $wpdb = new WpdbStub();
    $wpdb->existingTables = [
        'wp_pulsepress_reactions' => null,
        // captures intentionally missing
        'wp_pulsepress_daily_agg' => null,
    ];
    $migrator = new Migrator($wpdb, new Schema());

    $result = $migrator->migrate();

    expect($result)->toBeFalse();
    expect(OptionStore::get(Migrator::VERSION_OPTION))->toBe('0');
    expect(ErrorLogSpy::messages())
        ->toContain('[PulsePress] migration failed: missing table wp_pulsepress_captures');
});

it('does not run dbDelta twice across two invocations in the same release', function () {
    OptionStore::set(Migrator::VERSION_OPTION, '0');
    $migrator = pp_migrator_with_existing_tables();

    $migrator->migrate();
    $beforeSecond = count(DbDeltaSpy::calls());

    $migrator->migrate();

    expect(count(DbDeltaSpy::calls()))->toBe($beforeSecond);
});
