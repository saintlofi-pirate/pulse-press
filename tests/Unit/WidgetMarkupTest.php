<?php
declare(strict_types=1);

use Moonfarmer\ReactionsLeadCapture\Blocks\WidgetMarkup;
use Tests\Stubs\FilterRegistry;

it('renders the default container HTML', function () {
    $html = WidgetMarkup::container(42);
    expect($html)->toBe('<div class="moonfarmer-reactions-lead-capture" data-moonfarmer-reactions-lead-capture-widget data-moonfarmer-reactions-lead-capture-post-id="42"></div>');
});

it('serialises empty-string attribute values as bare attributes', function () {
    $html = WidgetMarkup::container(42);
    expect($html)->toContain('data-moonfarmer-reactions-lead-capture-widget');
    expect($html)->not->toContain('data-moonfarmer-reactions-lead-capture-widget=""');
});

it('passes postId to the filter', function () {
    $captured = null;
    FilterRegistry::addFilter('moonfarmer_reactions_lead_capture_widget_container_attrs', function (array $attrs, int $postId) use (&$captured) {
        $captured = $postId;
        return $attrs;
    });
    WidgetMarkup::container(99);
    expect($captured)->toBe(99);
});

it('lets a filter add an attribute', function () {
    FilterRegistry::addFilter('moonfarmer_reactions_lead_capture_widget_container_attrs', function (array $attrs) {
        $attrs['data-moonfarmer-reactions-lead-capture-variant'] = 'b';
        return $attrs;
    });
    $html = WidgetMarkup::container(7);
    expect($html)->toContain('data-moonfarmer-reactions-lead-capture-variant="b"');
});

it('lets a filter replace the class attribute', function () {
    FilterRegistry::addFilter('moonfarmer_reactions_lead_capture_widget_container_attrs', function (array $attrs) {
        $attrs['class'] = 'moonfarmer-reactions-lead-capture custom';
        return $attrs;
    });
    $html = WidgetMarkup::container(1);
    expect($html)->toContain('class="moonfarmer-reactions-lead-capture custom"');
});
