<?php
declare(strict_types=1);

use Moonfarmer\ReactionsLeadCapture\Settings\Settings;
use Moonfarmer\ReactionsLeadCapture\Settings\SettingsRepository;
use Moonfarmer\ReactionsLeadCapture\Visibility\VisibilityResolver;
use Tests\Stubs\FilterRegistry;
use Tests\Stubs\OptionStore;
use Tests\Stubs\PostMetaStore;
use Tests\Stubs\PostRegistry;

function pp_resolver(): VisibilityResolver
{
    return new VisibilityResolver(new SettingsRepository());
}

beforeEach(function () {
    PostMetaStore::reset();
    PostRegistry::reset();
    PostRegistry::register(42, 'publish', true);
    PostRegistry::setPostType(42, 'post');
});

it('returns auto when meta is missing', function () {
    expect(pp_resolver()->mode(42))->toBe('auto');
});

it('returns the stored meta verbatim when valid', function () {
    PostMetaStore::set(42, VisibilityResolver::META_KEY, 'off');
    expect(pp_resolver()->mode(42))->toBe('off');
});

it('coerces unknown meta values to auto', function () {
    PostMetaStore::set(42, VisibilityResolver::META_KEY, 'garbage');
    expect(pp_resolver()->mode(42))->toBe('auto');
});

it('explicit on beats hide list', function () {
    PostMetaStore::set(42, VisibilityResolver::META_KEY, 'on');
    OptionStore::set(Settings::OPTION_NAME, ['hide_on_post_types' => ['post']]);
    expect(pp_resolver()->shouldRender(42, 'auto'))->toBeTrue();
});

it('excluded post id beats explicit on', function () {
    PostMetaStore::set(42, VisibilityResolver::META_KEY, 'on');
    OptionStore::set(Settings::OPTION_NAME, ['hide_on_post_ids' => [42]]);
    expect(pp_resolver()->shouldRender(42, 'auto'))->toBeFalse();
});

it('explicit off beats auto-insert list', function () {
    PostMetaStore::set(42, VisibilityResolver::META_KEY, 'off');
    OptionStore::set(Settings::OPTION_NAME, ['auto_insert_post_types' => ['post']]);
    expect(pp_resolver()->shouldRender(42, 'auto'))->toBeFalse();
});

it('explicit off blocks block + shortcode too', function () {
    PostMetaStore::set(42, VisibilityResolver::META_KEY, 'off');
    expect(pp_resolver()->shouldRender(42, 'block'))->toBeFalse();
    expect(pp_resolver()->shouldRender(42, 'shortcode'))->toBeFalse();
});

it('hide list catches the auto context', function () {
    OptionStore::set(Settings::OPTION_NAME, [
        'hide_on_post_types'     => ['post'],
        'auto_insert_post_types' => ['post'],
    ]);
    expect(pp_resolver()->shouldRender(42, 'auto'))->toBeFalse();
});

it('hide list catches block + shortcode', function () {
    OptionStore::set(Settings::OPTION_NAME, ['hide_on_post_types' => ['post']]);
    expect(pp_resolver()->shouldRender(42, 'block'))->toBeFalse();
    expect(pp_resolver()->shouldRender(42, 'shortcode'))->toBeFalse();
});

it('excluded post id catches auto block and shortcode contexts', function () {
    OptionStore::set(Settings::OPTION_NAME, [
        'hide_on_post_ids'       => [42],
        'auto_insert_post_types' => ['post'],
    ]);
    expect(pp_resolver()->shouldRender(42, 'auto'))->toBeFalse();
    expect(pp_resolver()->shouldRender(42, 'block'))->toBeFalse();
    expect(pp_resolver()->shouldRender(42, 'shortcode'))->toBeFalse();
});

it('auto context honours auto-insert list', function () {
    OptionStore::set(Settings::OPTION_NAME, ['auto_insert_post_types' => ['page']]);
    expect(pp_resolver()->shouldRender(42, 'auto'))->toBeFalse();
});

it('block + shortcode render even when post type is not in auto-insert list', function () {
    OptionStore::set(Settings::OPTION_NAME, ['auto_insert_post_types' => []]);
    expect(pp_resolver()->shouldRender(42, 'block'))->toBeTrue();
    expect(pp_resolver()->shouldRender(42, 'shortcode'))->toBeTrue();
});

it('moonfarmer_reactions_lead_capture_visibility_mode filter can override the resolved mode', function () {
    PostMetaStore::set(42, VisibilityResolver::META_KEY, 'auto');
    FilterRegistry::addFilter('moonfarmer_reactions_lead_capture_visibility_mode', fn () => 'off');
    expect(pp_resolver()->shouldRender(42, 'block'))->toBeFalse();
});

it('returns false on invalid post id', function () {
    expect(pp_resolver()->shouldRender(0, 'auto'))->toBeFalse();
    expect(pp_resolver()->shouldRender(-1, 'block'))->toBeFalse();
});

it('sanitiseMode coerces values correctly', function () {
    expect(VisibilityResolver::sanitiseMode('on'))->toBe('on');
    expect(VisibilityResolver::sanitiseMode('off'))->toBe('off');
    expect(VisibilityResolver::sanitiseMode('auto'))->toBe('auto');
    expect(VisibilityResolver::sanitiseMode(''))->toBe('auto');
    expect(VisibilityResolver::sanitiseMode('garbage'))->toBe('auto');
    expect(VisibilityResolver::sanitiseMode(null))->toBe('auto');
    expect(VisibilityResolver::sanitiseMode(true))->toBe('auto');
});
