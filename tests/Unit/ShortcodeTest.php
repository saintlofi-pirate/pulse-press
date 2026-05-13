<?php
declare(strict_types=1);

use PulsePress\Blocks\Shortcode;
use Tests\Stubs\PostRegistry;
use Tests\Stubs\WpEnv;

it('returns empty when no current post and no post_id attribute', function () {
    expect(Shortcode::render([]))->toBe('');
});

it('uses the explicit post_id when provided', function () {
    PostRegistry::register(123, 'publish', true);
    WpEnv::setSingular('post', 50);
    expect(Shortcode::render(['post_id' => '123']))->toContain('data-pulsepress-post-id="123"');
});

it('returns empty when the explicit post id is non-public', function () {
    PostRegistry::register(123, 'draft', false);
    expect(Shortcode::render(['post_id' => '123']))->toBe('');
});

it('falls back to the current post id when no attribute', function () {
    PostRegistry::register(50, 'publish', true);
    WpEnv::setSingular('post', 50);
    expect(Shortcode::render([]))->toContain('data-pulsepress-post-id="50"');
});
