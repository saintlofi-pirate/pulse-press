<?php
declare(strict_types=1);

use Moonfarmer\ReactionsLeadCapture\Reactions\Reactions;
use Tests\Stubs\FilterRegistry;

it('exposes exactly six default reaction types', function () {
    expect(Reactions::TYPES)->toBe(['love', 'insightful', 'funny', 'sad', 'surprised', 'angry']);
});

it('accepts every type in the default allowlist', function () {
    foreach (Reactions::TYPES as $type) {
        expect(Reactions::isValid($type))->toBeTrue();
    }
});

it('rejects unknown reaction types', function () {
    expect(Reactions::isValid('applause'))->toBeFalse();
    expect(Reactions::isValid(''))->toBeFalse();
});

it('rejects near-matches because comparison is strict and case-sensitive', function () {
    expect(Reactions::isValid('Love'))->toBeFalse();
    expect(Reactions::isValid('LOVE'))->toBeFalse();
});

it('lets a filter override the allowlist', function () {
    FilterRegistry::addFilter('moonfarmer_reactions_lead_capture_reaction_types', fn () => ['celebrate']);
    expect(Reactions::isValid('celebrate'))->toBeTrue();
    expect(Reactions::isValid('love'))->toBeFalse();
});

it('falls back to the constant when the filter returns a non-array value', function () {
    FilterRegistry::addFilter('moonfarmer_reactions_lead_capture_reaction_types', fn () => 'not-an-array');
    expect(Reactions::isValid('love'))->toBeTrue();
});
