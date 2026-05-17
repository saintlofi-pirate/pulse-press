<?php
declare(strict_types=1);

use PulsePress\Analytics\Aggregator;
use PulsePress\Analytics\AnalyticsRepository;
use PulsePress\Analytics\MetricsCalculator;
use PulsePress\Core\Application;
use PulsePress\Providers\AnalyticsServiceProvider;
use PulsePress\Reactions\ReactionRepository;
use PulsePress\Settings\Settings;
use PulsePress\Settings\SettingsRepository;
use Tests\Stubs\FilterRegistry;
use Tests\Stubs\OptionStore;
use Tests\Stubs\WpdbStub;

function pp_make_analytics_provider(WpdbStub $wpdb): AnalyticsServiceProvider
{
    $GLOBALS['wpdb'] = $wpdb;
    $app = new class(__FILE__) extends Application {
        public function __construct(string $pluginFile)
        {
            parent::__construct($pluginFile);
        }
    };

    $app->singleton(SettingsRepository::class, fn () => new SettingsRepository());
    $provider = new AnalyticsServiceProvider($app);
    $provider->register();

    return $provider;
}

it('keeps raw reactions when retention is disabled', function () {
    OptionStore::set(Settings::OPTION_NAME, ['retention_days' => 0, '_version' => 1]);
    $wpdb = new WpdbStub();
    $wpdb->resultsByQuery['GROUP BY post_id, reaction_type'] = [];
    $provider = pp_make_analytics_provider($wpdb);

    $provider->runScheduledAggregation();

    $selects = array_filter($wpdb->queries, fn (string $query): bool => str_contains($query, 'FROM wp_pulsepress_reactions'));
    $deletes = array_filter($wpdb->queries, fn (string $query): bool => str_contains($query, 'DELETE FROM wp_pulsepress_reactions'));

    expect($selects)->not->toBeEmpty();
    expect($deletes)->toBeEmpty();
    expect(FilterRegistry::actionCalls('pulsepress_reactions_retention_purged'))->toBeEmpty();
});

it('purges raw reactions older than the saved retention window after aggregation', function () {
    OptionStore::set(Settings::OPTION_NAME, ['retention_days' => 30, '_version' => 1]);
    $wpdb = new WpdbStub();
    $wpdb->rows_affected = 6;
    $wpdb->resultsByQuery['GROUP BY post_id, reaction_type'] = [];
    $provider = pp_make_analytics_provider($wpdb);

    $provider->runScheduledAggregation();

    $delete = null;
    foreach ($wpdb->queries as $query) {
        if (str_contains($query, 'DELETE FROM wp_pulsepress_reactions')) {
            $delete = $query;
            break;
        }
    }
    expect($delete)->not->toBeNull();
    expect($delete)->toContain('updated_at <');

    $calls = FilterRegistry::actionCalls('pulsepress_reactions_retention_purged');
    expect($calls)->toHaveCount(1);
    expect($calls[0][0])->toBe(6);
    expect($calls[0][1])->toBeInstanceOf(DateTimeImmutable::class);
    expect($calls[0][2])->toBe(30);
});
