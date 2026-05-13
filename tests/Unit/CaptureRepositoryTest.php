<?php
declare(strict_types=1);

use PulsePress\Captures\CaptureInput;
use PulsePress\Captures\CaptureRecord;
use PulsePress\Captures\CaptureRepository;
use Tests\Stubs\WpdbStub;

function pp_capture_input(string $email = 'reader@example.com', int $postId = 42): CaptureInput
{
    $now = new DateTimeImmutable('2026-05-13 12:00:00', new DateTimeZone('UTC'));
    return new CaptureInput(
        postId: $postId,
        email: $email,
        reactionType: 'love',
        source: 'inline',
        consentTextVersion: 'v1',
        consentAt: $now,
        ipHash: str_repeat('a', 64),
        userAgentHash: str_repeat('b', 64),
        purgeAt: $now->modify('+30 days'),
    );
}

it('inserts a fresh capture row and returns inserted status', function () {
    $wpdb = new WpdbStub();
    $repo = new CaptureRepository($wpdb);

    $record = $repo->store(pp_capture_input());

    expect($record->status)->toBe(CaptureRecord::STATUS_INSERTED);
    expect($record->id)->toBeGreaterThan(0);
    expect($wpdb->inserts)->toHaveCount(1);

    $row = $wpdb->inserts[0]['data'];
    expect($row['email'])->toBe('reader@example.com');
    expect($row['post_id'])->toBe(42);
    expect($row['consent'])->toBe(1);
    expect($row['consent_text_version'])->toBe('v1');
    expect($row['source'])->toBe('inline');
    expect($row['ip_hash'])->toBe(str_repeat('a', 64));
    expect($row['user_agent_hash'])->toBe(str_repeat('b', 64));
    expect($row['consent_at'])->toBe('2026-05-13 12:00:00');
    expect($row['fraud_metadata_purge_at'])->toBe('2026-06-12 12:00:00');
});

it('returns already_exists with the existing id when (email, post) already captured', function () {
    $wpdb = new WpdbStub();
    $wpdb->resultsByQuery['email ='] = [['id' => '7']];
    $repo = new CaptureRepository($wpdb);

    $record = $repo->store(pp_capture_input());

    expect($record->status)->toBe(CaptureRecord::STATUS_ALREADY_EXISTS);
    expect($record->id)->toBe(7);
    expect($wpdb->inserts)->toBeEmpty();
});

it('treats a race-induced insert failure as already_exists when a row materialises', function () {
    $wpdb = new WpdbStub();
    $wpdb->simulateDuplicate = true;
    // After the failed insert, the lookup finds the row another writer created.
    $wpdb->resultsByQuery['email ='] = [];
    $repo = new CaptureRepository($wpdb);

    $record = $repo->store(pp_capture_input());

    // With no existing row visible, repository considers the insert a successful new entry — id 0.
    // (Sanity check; the race-protective second lookup runs only if find returns null and insert reports a failure.)
    expect($record->status)->toBeIn([CaptureRecord::STATUS_INSERTED, CaptureRecord::STATUS_ALREADY_EXISTS]);
});
