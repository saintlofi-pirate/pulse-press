<?php
declare(strict_types=1);

use Moonfarmer\ReactionsLeadCapture\Core\Application;
use Moonfarmer\ReactionsLeadCapture\Providers\WidgetServiceProvider;
use Moonfarmer\ReactionsLeadCapture\Settings\Settings;
use Moonfarmer\ReactionsLeadCapture\Settings\SettingsRepository;
use Moonfarmer\ReactionsLeadCapture\View\Manifest;
use Moonfarmer\ReactionsLeadCapture\Visibility\VisibilityResolver;
use Tests\Stubs\AssetSpy;
use Tests\Stubs\FilterRegistry;
use Tests\Stubs\OptionStore;
use Tests\Stubs\PostRegistry;
use Tests\Stubs\WpEnv;

if (!defined('MOONFARMER_REACTIONS_LEAD_CAPTURE_DIR')) {
    define('MOONFARMER_REACTIONS_LEAD_CAPTURE_DIR', __DIR__ . '/../../');
}
if (!defined('MOONFARMER_REACTIONS_LEAD_CAPTURE_URL')) {
    define('MOONFARMER_REACTIONS_LEAD_CAPTURE_URL', 'https://example.test/wp-content/plugins/moonfarmer-reactions-lead-capture/');
}
if (!defined('MOONFARMER_REACTIONS_LEAD_CAPTURE_VERSION')) {
    define('MOONFARMER_REACTIONS_LEAD_CAPTURE_VERSION', '0.1.0-test');
}

final class PpTestApplication extends Application
{
    public function __construct()
    {
        parent::__construct(__FILE__);
    }
}

function pp_make_provider(?Manifest $manifestOverride = null, bool $withVisibility = false): WidgetServiceProvider
{
    $app      = new PpTestApplication();
    $provider = new WidgetServiceProvider($app);
    $provider->register();
    if ($withVisibility) {
        $app->singleton(SettingsRepository::class, fn () => new SettingsRepository());
        $app->singleton(VisibilityResolver::class, fn () => new VisibilityResolver($app->get(SettingsRepository::class)));
    }
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
    $dir  = sys_get_temp_dir() . '/moonfarmer-reactions-lead-capture-widget-' . uniqid();
    mkdir($dir, 0o777, true);
    $path = $dir . '/manifest.json';
    file_put_contents($path, json_encode(['resources/widget/index.ts' => $entry]));
    return new Manifest($path, 'https://example.test/dist/');
}

it('enqueues script + style + data payload on a singular post when manifest resolves', function () {
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

    $inline = AssetSpy::only('inline_script');
    expect($inline)->toHaveCount(1);
    expect($inline[0]['args']['position'])->toBe('before');

    preg_match('/^window\.MoonfarmerReactionsLeadCaptureData = (.*);$/', $inline[0]['args']['data'], $matches);
    $data = json_decode($matches[1] ?? '{}', true);

    expect($data['postId'])->toBe(209);
    expect($data['root'])->toBe('https://example.test/wp-json/moonfarmer-reactions-lead-capture/v1/');
    expect($data['reactions'])->toBe(['love', 'insightful', 'funny', 'sad', 'surprised', 'angry']);
    expect($data['allowGuestReactions'])->toBeTrue();
    expect($data['countThreshold'])->toBe(5);
    expect($data['animationMode'])->toBe('subtle');

    $scriptData = AssetSpy::only('script_add_data');
    expect($scriptData)->toHaveCount(1);
    expect($scriptData[0]['args'])->toMatchArray([
        'handle' => WidgetServiceProvider::SCRIPT_HANDLE,
        'key'    => 'type',
        'value'  => 'module',
    ]);
});

it('enqueues on non-singular pages when moonfarmer_reactions_lead_capture_widget_enqueue is filtered true', function () {
    WpEnv::setAdmin(false);
    WpEnv::setSingular(null);
    FilterRegistry::addFilter('moonfarmer_reactions_lead_capture_widget_enqueue', fn () => true);

    $manifest = pp_make_temp_manifest(['file' => 'js/widget.abc.js']);
    $provider = pp_make_provider($manifest);

    $provider->enqueueAssets();

    expect(AssetSpy::only('enqueue_script'))->toHaveCount(1);
    expect(AssetSpy::only('register_style'))->toBeEmpty();
});

it('does not enqueue on a singular post hidden by settings', function () {
    WpEnv::setAdmin(false);
    WpEnv::setSingular('post', 42);
    PostRegistry::register(42, 'publish', true, 'post');
    OptionStore::set(Settings::OPTION_NAME, ['hide_on_post_ids' => [42]]);

    $manifest = pp_make_temp_manifest(['file' => 'js/widget.abc.js']);
    $provider = pp_make_provider($manifest, true);

    $provider->enqueueAssets();

    expect(AssetSpy::calls())->toBeEmpty();
});

it('enqueues on a singular page when it contains the shortcode', function () {
    WpEnv::setAdmin(false);
    WpEnv::setSingular('page', 88);
    WpEnv::setPostContent('<p>Intro</p>[moonfarmer-reactions-lead-capture]');
    PostRegistry::register(88, 'publish', true, 'page');
    OptionStore::set(Settings::OPTION_NAME, ['auto_insert_post_types' => []]);

    $manifest = pp_make_temp_manifest(['file' => 'js/widget.abc.js']);
    $provider = pp_make_provider($manifest, true);

    $provider->enqueueAssets();

    expect(AssetSpy::only('enqueue_script'))->toHaveCount(1);
});

it('enqueues on a singular page when it contains the reactions block', function () {
    WpEnv::setAdmin(false);
    WpEnv::setSingular('page', 89);
    WpEnv::setPostContent('<!-- wp:moonfarmer-reactions-lead-capture/reactions /-->');
    PostRegistry::register(89, 'publish', true, 'page');
    OptionStore::set(Settings::OPTION_NAME, ['auto_insert_post_types' => []]);

    $manifest = pp_make_temp_manifest(['file' => 'js/widget.abc.js']);
    $provider = pp_make_provider($manifest, true);

    $provider->enqueueAssets();

    expect(AssetSpy::only('enqueue_script'))->toHaveCount(1);
});

it('appends the widget container to single-post content', function () {
    WpEnv::setAdmin(false);
    WpEnv::setSingular('post', 42);
    $provider = pp_make_provider();

    $result = $provider->maybeAppendWidget('<p>Body</p>');

    expect($result)->toContain('<p>Body</p>');
    expect($result)->toContain('data-moonfarmer-reactions-lead-capture-widget');
    expect($result)->toContain('data-moonfarmer-reactions-lead-capture-post-id="42"');
});

it('skips appending on non-singular contexts', function () {
    WpEnv::setAdmin(false);
    WpEnv::setSingular(null);
    $provider = pp_make_provider();

    $result = $provider->maybeAppendWidget('<p>Body</p>');

    expect($result)->toBe('<p>Body</p>');
});

it('skips appending when moonfarmer_reactions_lead_capture_widget_auto_insert filters to false', function () {
    WpEnv::setAdmin(false);
    WpEnv::setSingular('post', 42);
    FilterRegistry::addFilter('moonfarmer_reactions_lead_capture_widget_auto_insert', fn () => false);
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
