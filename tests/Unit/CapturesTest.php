<?php
declare(strict_types=1);

use PulsePress\Captures\Captures;
use Tests\Stubs\FilterRegistry;

it('exposes the three default capture sources', function () {
    expect(Captures::SOURCES)->toBe(['inline', 'block', 'shortcode']);
});

it('accepts every default source', function () {
    foreach (Captures::SOURCES as $source) {
        expect(Captures::isValidSource($source))->toBeTrue();
    }
});

it('rejects unknown sources', function () {
    expect(Captures::isValidSource('telegram'))->toBeFalse();
    expect(Captures::isValidSource(''))->toBeFalse();
});

it('lets a filter extend the allowed sources', function () {
    FilterRegistry::addFilter('pulsepress_capture_sources', fn () => ['inline', 'sms']);
    expect(Captures::isValidSource('sms'))->toBeTrue();
    expect(Captures::isValidSource('block'))->toBeFalse();
});

it('returns v1 as the default consent text version', function () {
    expect(Captures::consentTextVersion())->toBe('v1');
});

it('lets a filter set a custom consent text version', function () {
    FilterRegistry::addFilter('pulsepress_consent_text_version', fn () => '2026-06-policy');
    expect(Captures::consentTextVersion())->toBe('2026-06-policy');
});

it('falls back to default version when filter returns an empty value', function () {
    FilterRegistry::addFilter('pulsepress_consent_text_version', fn () => '');
    expect(Captures::consentTextVersion())->toBe('v1');
});
