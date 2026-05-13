<?php
declare(strict_types=1);

use PulsePress\Core\Application;
use PulsePress\Providers\WidgetServiceProvider;
use PulsePress\View\Manifest;
use Tests\Stubs\AssetSpy;
use Tests\Stubs\FilterRegistry;
use Tests\Stubs\WpEnv;

if (!defined('PULSEPRESS_DIR')) {
    define('PULSEPRESS_DIR', __DIR__ . '/../../');
}
if (!defined('PULSEPRESS_URL')) {
    define('PULSEPRESS_URL', 'https://example.test/wp-content/plugins/pulse-press/');
}
if (!defined('PULSEPRESS_VERSION')) {
    define('PULSEPRESS_VERSION', '0.1.0-test');
}

final class PpTestApplication extends Application
{
    public function __construct()
    {
        parent::__construct(__FILE__);
    }
}

function pp_make_provider(?Manifest $manifestOverride = null): WidgetServiceProvider
{
    $app      = new PpTestApplication();
    $provider = new WidgetServiceProvider($app);
    $provider->register();
    if ($manifestOverride !== null) {
        $app->instance(Manifest::class, $manifestOverride);
    }
    return $provider;
}

it('does not enqueue assets in the admin', function () {
    WpEnv::setAdmin(true);
    $provider = pp_make_provider();

    $provider->enqueueAssets();

    expect(AssetSpy::calls())->toBeEmpty();
});

it('does not enqueue on non-singular pages by default', function () {
    WpEnv::setAdmin(false);
    WpEnv::setSingular(null);
    $provider = pp_make_provider();

    $provider->enqueueAssets();

    expect(AssetSpy::calls())->toBeEmpty();
});

function pp_make_temp_manifest(array $entry): Manifest
{
    $dir  = sys_get_temp_dir() . '/pulsepress-widget-' . uniqid();
    mkdir($dir, 0o777, true);
    $path = $dir . '/manifest.json';
    file_put_contents($path, json_encode(['resources/widget/index.ts' => $entry]));
    return new Manifest($path, 'https://example.test/dist/');
}

it('enqueues script + style + localize on a singular post when manifest resolves', function () {
    WpEnv::setAdmin(false);
    WpEnv::setSingular('post', 209);

    $manifest = pp_make_temp_manifest([
        'file' => 'js/widget.abc.js',
        'css'  => ['assets/widget.def.css'],
    ]);
    $provider = pp_make_provider($manifest);

    $provider->enqueueAssets();

    expect(AssetSpy::only('register_script'))->toHaveCount(1);
    expect(AssetSpy::only('enqueue_script'))->toHaveCount(1);
    expect(AssetSpy::only('register_style'))->toHaveCount(1);
    expect(AssetSpy::only('enqueue_style'))->toHaveCount(1);

    $localize = AssetSpy::only('localize');
    expect($localize)->toHaveCount(1);
    expect($localize[0]['args']['objectName'])->toBe('PulsePressData');
    expect($localize[0]['args']['data']['postId'])->toBe(209);
    expect($localize[0]['args']['data']['root'])->toBe('https://example.test/wp-json/pulsepress/v1/');
    expect($localize[0]['args']['data']['reactions'])->toBe(['love', 'insightful', 'funny', 'sad', 'surprised', 'angry']);
});

it('enqueues on non-singular pages when pulsepress_widget_enqueue is filtered true', function () {
    WpEnv::setAdmin(false);
    WpEnv::setSingular(null);
    FilterRegistry::addFilter('pulsepress_widget_enqueue', fn () => true);

    $manifest = pp_make_temp_manifest(['file' => 'js/widget.abc.js']);
    $provider = pp_make_provider($manifest);

    $provider->enqueueAssets();

    expect(AssetSpy::only('enqueue_script'))->toHaveCount(1);
    expect(AssetSpy::only('register_style'))->toBeEmpty();
});

it('appends the widget container to single-post content', function () {
    WpEnv::setAdmin(false);
    WpEnv::setSingular('post', 42);
    $provider = pp_make_provider();

    $result = $provider->maybeAppendWidget('<p>Body</p>');

    expect($result)->toContain('<p>Body</p>');
    expect($result)->toContain('data-pulsepress-widget');
    expect($result)->toContain('data-pulsepress-post-id="42"');
});

it('skips appending on non-singular contexts', function () {
    WpEnv::setAdmin(false);
    WpEnv::setSingular(null);
    $provider = pp_make_provider();

    $result = $provider->maybeAppendWidget('<p>Body</p>');

    expect($result)->toBe('<p>Body</p>');
});

it('skips appending when pulsepress_widget_auto_insert filters to false', function () {
    WpEnv::setAdmin(false);
    WpEnv::setSingular('post', 42);
    FilterRegistry::addFilter('pulsepress_widget_auto_insert', fn () => false);
    $provider = pp_make_provider();

    $result = $provider->maybeAppendWidget('<p>Body</p>');

    expect($result)->toBe('<p>Body</p>');
});

it('skips appending on a non-post singular by default', function () {
    WpEnv::setAdmin(false);
    WpEnv::setSingular('page', 12);
    $provider = pp_make_provider();

    $result = $provider->maybeAppendWidget('<p>Body</p>');

    expect($result)->toBe('<p>Body</p>');
});
