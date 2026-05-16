<?php
declare(strict_types=1);

use PulsePress\Settings\Settings;

it('exposes a complete defaults map', function () {
    foreach ([
        'count_visibility', 'count_threshold', 'widget_design', 'icon_style',
        'theme_mode', 'animation_mode', 'auto_insert_post_types', 'auto_insert_position',
        'positive_reactions', 'allow_guest_reactions', 'consent_text',
        'consent_text_version', 'delete_on_uninstall', 'retention_days',
        'hide_on_post_types', 'hide_on_post_ids',
    ] as $key) {
        expect(Settings::DEFAULTS)->toHaveKey($key);
    }
});

it('drops unknown keys in sanitise', function () {
    $clean = Settings::sanitise(['unknown_key' => 'evil', 'icon_style' => 'emoji']);
    expect($clean)->not->toHaveKey('unknown_key');
    expect($clean['icon_style'])->toBe('emoji');
});

it('clamps count_threshold to max', function () {
    $clean = Settings::sanitise(['count_threshold' => 99999]);
    expect($clean['count_threshold'])->toBe(1000);
});

it('falls back to default when count_threshold is negative', function () {
    $clean = Settings::sanitise(['count_threshold' => -5]);
    expect($clean['count_threshold'])->toBe(Settings::DEFAULTS['count_threshold']);
});

it('falls back to default for unknown enum value', function () {
    $clean = Settings::sanitise(['icon_style' => 'flat']);
    expect($clean['icon_style'])->toBe(Settings::DEFAULTS['icon_style']);
});

it('accepts free widget design and animation choices', function () {
    $clean = Settings::sanitise([
        'widget_design'  => 'progress_split',
        'animation_mode' => 'spring',
    ]);

    expect($clean['widget_design'])->toBe('progress_split');
    expect($clean['animation_mode'])->toBe('spring');
});

it('falls back for unknown widget design and animation choices', function () {
    $clean = Settings::sanitise([
        'widget_design'  => 'glassmorphic_pills',
        'animation_mode' => 'confetti',
    ]);

    expect($clean['widget_design'])->toBe(Settings::DEFAULTS['widget_design']);
    expect($clean['animation_mode'])->toBe(Settings::DEFAULTS['animation_mode']);
});

it('filters positive_reactions to the Reactions allowlist', function () {
    $clean = Settings::sanitise(['positive_reactions' => ['love', 'celebrate', 'angry', 'foo']]);
    expect($clean['positive_reactions'])->toBe(['love', 'angry']);
});

it('falls back when positive_reactions ends up empty', function () {
    $clean = Settings::sanitise(['positive_reactions' => ['celebrate']]);
    expect($clean['positive_reactions'])->toBe(Settings::DEFAULTS['positive_reactions']);
});

it('coerces string booleans for allow_guest_reactions', function () {
    expect(Settings::sanitise(['allow_guest_reactions' => 'true'])['allow_guest_reactions'])->toBeTrue();
    expect(Settings::sanitise(['allow_guest_reactions' => 'no'])['allow_guest_reactions'])->toBeFalse();
    expect(Settings::sanitise(['allow_guest_reactions' => 1])['allow_guest_reactions'])->toBeTrue();
});

it('trims and bounds consent_text length', function () {
    $long = str_repeat('a', 5000);
    $clean = Settings::sanitise(['consent_text' => '   ' . $long . '   ']);
    expect(strlen($clean['consent_text']))->toBe(2000);
});

it('returns default consent_text when value is empty after trim', function () {
    $clean = Settings::sanitise(['consent_text' => '   ']);
    expect($clean['consent_text'])->toBe(Settings::DEFAULTS['consent_text']);
});

it('sanitises excluded post ids from arrays and comma separated strings', function () {
    expect(Settings::sanitise(['hide_on_post_ids' => [42, '42', 'abc', -5, 9]])['hide_on_post_ids'])->toBe([42, 9]);
    expect(Settings::sanitise(['hide_on_post_ids' => '12, 48 103, nope'])['hide_on_post_ids'])->toBe([12, 48, 103]);
});
