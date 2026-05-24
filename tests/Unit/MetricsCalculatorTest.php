<?php
declare(strict_types=1);

use Moonfarmer\ReactionsLeadCapture\Analytics\AnalyticsRepository;
use Moonfarmer\ReactionsLeadCapture\Analytics\MetricsCalculator;
use Moonfarmer\ReactionsLeadCapture\Analytics\MetricsEnvelope;
use Moonfarmer\ReactionsLeadCapture\Settings\Settings;
use Moonfarmer\ReactionsLeadCapture\Settings\SettingsRepository;
use Tests\Stubs\OptionStore;
use Tests\Stubs\WpdbStub;

function pp_calculator(array $dailyRows = [], array $capRows = [], array $topRows = []): MetricsCalculator
{
    $wpdb = new WpdbStub();
    $wpdb->resultsByQuery['GROUP BY agg_date, reaction_type'] = $dailyRows;
    $wpdb->resultsByQuery['GROUP BY agg.post_id, caps.captures'] = $topRows;
    $wpdb->resultsByQuery['FROM wp_moonfarmer_reactions_lead_capture_captures'] = $capRows;
    $repo = new AnalyticsRepository($wpdb);
    return new MetricsCalculator($repo, new SettingsRepository());
}

it('produces an empty envelope when there is no data', function () {
    $cal = pp_calculator();
    $env = $cal->calculate(new DateTimeImmutable('2026-05-01', new DateTimeZone('UTC')), new DateTimeImmutable('2026-05-15', new DateTimeZone('UTC')), false);

    expect($env)->toBeInstanceOf(MetricsEnvelope::class);
    expect($env->totalReactions)->toBe(0);
    expect($env->totalCaptures)->toBe(0);
    expect($env->sentimentRate)->toBeNull();
    expect($env->captureRate)->toBeNull();
    expect($env->dailySeries)->toBe([]);
    expect($env->topPosts)->toBe([]);
});

it('computes total reactions from the daily series', function () {
    $cal = pp_calculator([
        ['agg_date' => '2026-05-13', 'reaction_type' => 'love',  'c' => '4'],
        ['agg_date' => '2026-05-13', 'reaction_type' => 'angry', 'c' => '1'],
        ['agg_date' => '2026-05-14', 'reaction_type' => 'love',  'c' => '2'],
    ]);
    $env = $cal->calculate(new DateTimeImmutable('2026-05-13', new DateTimeZone('UTC')), new DateTimeImmutable('2026-05-15', new DateTimeZone('UTC')), false);

    expect($env->totalReactions)->toBe(7);
});

it('computes positive reactions from the saved positive set', function () {
    OptionStore::set(Settings::OPTION_NAME, ['positive_reactions' => ['love']]);
    $cal = pp_calculator([
        ['agg_date' => '2026-05-13', 'reaction_type' => 'love',  'c' => '6'],
        ['agg_date' => '2026-05-13', 'reaction_type' => 'angry', 'c' => '4'],
    ]);
    $env = $cal->calculate(new DateTimeImmutable('2026-05-13', new DateTimeZone('UTC')), new DateTimeImmutable('2026-05-14', new DateTimeZone('UTC')), false);

    expect($env->positiveReactions)->toBe(6);
    expect($env->sentimentRate)->toBe(0.6);
});

it('returns null sentiment rate when totalReactions is zero', function () {
    $cal = pp_calculator();
    $env = $cal->calculate(new DateTimeImmutable('2026-05-13', new DateTimeZone('UTC')), new DateTimeImmutable('2026-05-14', new DateTimeZone('UTC')), false);
    expect($env->sentimentRate)->toBeNull();
});

it('computes capture rate as captures over positive reactions', function () {
    $cal = pp_calculator(
        [['agg_date' => '2026-05-13', 'reaction_type' => 'love', 'c' => '6']],
        [['post_id' => 1, 'c' => '3']],
    );
    $env = $cal->calculate(new DateTimeImmutable('2026-05-13', new DateTimeZone('UTC')), new DateTimeImmutable('2026-05-14', new DateTimeZone('UTC')), false);

    expect($env->totalCaptures)->toBe(3);
    expect($env->captureRate)->toBe(0.5);
});

it('returns null capture rate when positive reactions are zero', function () {
    $cal = pp_calculator(
        [['agg_date' => '2026-05-13', 'reaction_type' => 'angry', 'c' => '5']],
    );
    $env = $cal->calculate(new DateTimeImmutable('2026-05-13', new DateTimeZone('UTC')), new DateTimeImmutable('2026-05-14', new DateTimeZone('UTC')), false);

    expect($env->positiveReactions)->toBe(0);
    expect($env->captureRate)->toBeNull();
});

it('keeps clamped flag in the envelope', function () {
    $cal = pp_calculator();
    $env = $cal->calculate(new DateTimeImmutable('2026-05-01', new DateTimeZone('UTC')), new DateTimeImmutable('2026-05-15', new DateTimeZone('UTC')), true);
    expect($env->clamped)->toBeTrue();
});
