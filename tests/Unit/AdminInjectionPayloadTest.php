<?php
declare(strict_types=1);

use PulsePress\Core\Application;
use PulsePress\Providers\AdminServiceProvider;
use PulsePress\Settings\SettingsRepository;
use PulsePress\View\Manifest;
use Tests\Stubs\AssetSpy;
use Tests\Stubs\FilterRegistry;

if (!defined('PULSEPRESS_DIR')) {
    define('PULSEPRESS_DIR', __DIR__ . '/../../');
}
if (!defined('PULSEPRESS_URL')) {
    define('PULSEPRESS_URL', 'https://example.test/wp-content/plugins/pulse-press/');
}
if (!defined('PULSEPRESS_VERSION')) {
    define('PULSEPRESS_VERSION', '0.1.0-test');
}

if (!class_exists('PpAdminTestApplication')) {
    final class PpAdminTestApplication extends Application
    {
        public function __construct()
        {
            parent::__construct(__FILE__);
        }
    }
}

function pp_admin_manifest(): Manifest
{
    $dir = sys_get_temp_dir() . '/pulsepress-admin-' . uniqid('', true);
    mkdir($dir, 0o777, true);
    $path = $dir . '/manifest.json';
    file_put_contents($path, json_encode([
        'resources/admin/index.tsx' => [
            'file' => 'js/admin.abc.js',
            'css'  => ['assets/admin.css'],
        ],
    ]));
    return new Manifest($path, 'https://example.test/dist/');
}

function pp_make_admin_provider(): AdminServiceProvider
{
    $app = new PpAdminTestApplication();
    $app->singleton(SettingsRepository::class, fn () => new SettingsRepository());
    $app->singleton(Manifest::class, fn () => pp_admin_manifest());
    $provider = new AdminServiceProvider($app);
    $provider->register();
    return $provider;
}

function pp_admin_payload(AdminServiceProvider $provider): array
{
    AssetSpy::reset();
    $provider->maybeEnqueueAssets(AdminServiceProvider::PAGE_HOOK);
    $localize = AssetSpy::only('localize');
    expect($localize)->toHaveCount(1);
    return $localize[0]['args']['data'];
}

it('emits five Free tabs in order when no Pro filter attaches', function () {
    $provider = pp_make_admin_provider();

    $data = pp_admin_payload($provider);

    expect(array_column($data['tabs'], 'id'))
        ->toBe(['display', 'analytics', 'reactions', 'capture', 'privacy']);
    expect($data['metricCards'])->toBe([]);
    expect($data['analyticsPanels'])->toBe([]);
});

it('inserts a Pro tab at the requested order position', function () {
    FilterRegistry::addFilter('pulsepress_admin_tabs', fn () => [[
        'id'    => 'esp',
        'label' => 'ESP sync',
        'order' => 25,
    ]]);

    $data = pp_admin_payload(pp_make_admin_provider());

    expect(array_column($data['tabs'], 'id'))
        ->toBe(['display', 'analytics', 'esp', 'reactions', 'capture', 'privacy']);
});

it('refuses to let an extension hijack a built-in tab id', function () {
    FilterRegistry::addFilter('pulsepress_admin_tabs', fn () => [[
        'id'    => 'analytics',
        'label' => 'Hijacked',
        'order' => 1,
    ]]);

    $data = pp_admin_payload(pp_make_admin_provider());

    $analytics = current(array_filter($data['tabs'], fn ($t) => $t['id'] === 'analytics'));
    expect($analytics['label'])->not->toBe('Hijacked');
    expect($analytics['order'])->toBe(20);
});

it('passes metric-card and analytics-panel entries through verbatim', function () {
    FilterRegistry::addFilter('pulsepress_admin_metric_cards', fn () => [[
        'id'       => 'compare',
        'title'    => 'vs prior',
        'value'    => '+12%',
        'renderJs' => 'compare_card',
        'data'     => ['delta' => 0.12],
    ]]);
    FilterRegistry::addFilter('pulsepress_admin_analytics_panels', fn () => [[
        'id'       => 'compare_windows',
        'title'    => 'Window comparison',
        'data'     => ['previous' => 1234, 'current' => 1567],
        'renderJs' => 'compare_windows',
    ]]);

    $data = pp_admin_payload(pp_make_admin_provider());

    expect($data['metricCards'])->toHaveCount(1);
    expect($data['metricCards'][0])->toMatchArray([
        'id'       => 'compare',
        'title'    => 'vs prior',
        'value'    => '+12%',
        'renderJs' => 'compare_card',
    ]);
    expect($data['metricCards'][0]['data'])->toBe(['delta' => 0.12]);

    expect($data['analyticsPanels'])->toHaveCount(1);
    expect($data['analyticsPanels'][0]['id'])->toBe('compare_windows');
    expect($data['analyticsPanels'][0]['data'])->toBe(['previous' => 1234, 'current' => 1567]);
});
