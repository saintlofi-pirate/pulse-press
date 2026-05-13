<?php
declare(strict_types=1);

use PulsePress\Blocks\WidgetMarkup;
use Tests\Stubs\FilterRegistry;

it('renders the default container HTML', function () {
    $html = WidgetMarkup::container(42);
    expect($html)->toBe('<div class="pulsepress" data-pulsepress-widget data-pulsepress-post-id="42"></div>');
});

it('serialises empty-string attribute values as bare attributes', function () {
    $html = WidgetMarkup::container(42);
    expect($html)->toContain('data-pulsepress-widget');
    expect($html)->not->toContain('data-pulsepress-widget=""');
});

it('passes postId to the filter', function () {
    $captured = null;
    FilterRegistry::addFilter('pulsepress_widget_container_attrs', function (array $attrs, int $postId) use (&$captured) {
        $captured = $postId;
        return $attrs;
    });
    WidgetMarkup::container(99);
    expect($captured)->toBe(99);
});

it('lets a filter add an attribute', function () {
    FilterRegistry::addFilter('pulsepress_widget_container_attrs', function (array $attrs) {
        $attrs['data-pulsepress-variant'] = 'b';
        return $attrs;
    });
    $html = WidgetMarkup::container(7);
    expect($html)->toContain('data-pulsepress-variant="b"');
});

it('lets a filter replace the class attribute', function () {
    FilterRegistry::addFilter('pulsepress_widget_container_attrs', function (array $attrs) {
        $attrs['class'] = 'pulsepress custom';
        return $attrs;
    });
    $html = WidgetMarkup::container(1);
    expect($html)->toContain('class="pulsepress custom"');
});
