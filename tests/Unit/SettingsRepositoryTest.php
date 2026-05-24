<?php
declare(strict_types=1);

use Moonfarmer\ReactionsLeadCapture\Settings\Settings;
use Moonfarmer\ReactionsLeadCapture\Settings\SettingsRepository;
use Tests\Stubs\FilterRegistry;
use Tests\Stubs\OptionStore;

it('returns defaults when no option is stored', function () {
    $repo = new SettingsRepository();
    $settings = $repo->get();

    foreach (Settings::DEFAULTS as $key => $value) {
        expect($settings)->toHaveKey($key);
    }
    expect($settings['icon_style'])->toBe('classic');
});

it('merges stored option over defaults', function () {
    OptionStore::set(Settings::OPTION_NAME, ['icon_style' => 'emoji', '_version' => 1]);
    $repo = new SettingsRepository();

    $settings = $repo->get();
    expect($settings['icon_style'])->toBe('emoji');
    expect($settings['widget_design'])->toBe(Settings::DEFAULTS['widget_design']);
});

it('passes through moonfarmer_reactions_lead_capture_settings filter after merge', function () {
    OptionStore::set(Settings::OPTION_NAME, ['positive_reactions' => ['love'], '_version' => 1]);
    FilterRegistry::addFilter('moonfarmer_reactions_lead_capture_settings', function (array $s) {
        $s['positive_reactions'] = ['angry'];
        return $s;
    });
    $repo = new SettingsRepository();

    expect($repo->get()['positive_reactions'])->toBe(['angry']);
});

it('memoises within a request', function () {
    OptionStore::set(Settings::OPTION_NAME, ['icon_style' => 'emoji', '_version' => 1]);
    $repo = new SettingsRepository();

    $first  = $repo->get();
    OptionStore::set(Settings::OPTION_NAME, ['icon_style' => 'classic', '_version' => 1]);
    $second = $repo->get();

    expect($first['icon_style'])->toBe('emoji');
    expect($second['icon_style'])->toBe('emoji');
});

it('save sanitises, persists, and fires action', function () {
    $repo = new SettingsRepository();
    $actionFired = [];
    FilterRegistry::addAction('moonfarmer_reactions_lead_capture_settings_saved', function ($new, $prev) use (&$actionFired) {
        $actionFired[] = ['new' => $new, 'prev' => $prev];
    });

    $merged = $repo->save(['icon_style' => 'emoji', 'unknown_field' => 'evil']);

    expect($merged['icon_style'])->toBe('emoji');
    expect($merged)->not->toHaveKey('unknown_field');

    $stored = OptionStore::get(Settings::OPTION_NAME);
    expect($stored['icon_style'])->toBe('emoji');
    expect($stored['_version'])->toBe(Settings::SCHEMA_VERSION);

    expect($actionFired)->toHaveCount(1);
    expect($actionFired[0]['new']['icon_style'])->toBe('emoji');
});

it('save updates the in-request memo so subsequent get sees the new value', function () {
    $repo = new SettingsRepository();
    $repo->get();
    $repo->save(['icon_style' => 'emoji']);

    expect($repo->get()['icon_style'])->toBe('emoji');
});
