<?php
declare(strict_types=1);

use PulsePress\Reactions\UserHash;
use Tests\Stubs\WpSaltStub;

it('produces a 64-char lowercase hex digest', function () {
    $hash = UserHash::compute('1.2.3.4', 'Mozilla/5.0');
    expect(strlen($hash))->toBe(64);
    expect($hash)->toMatch('/^[0-9a-f]{64}$/');
});

it('is deterministic for the same inputs', function () {
    $a = UserHash::compute('1.2.3.4', 'Mozilla/5.0');
    $b = UserHash::compute('1.2.3.4', 'Mozilla/5.0');
    expect($a)->toBe($b);
});

it('differs when the IP changes', function () {
    $a = UserHash::compute('1.2.3.4', 'Mozilla/5.0');
    $b = UserHash::compute('5.6.7.8', 'Mozilla/5.0');
    expect($a)->not->toBe($b);
});

it('differs when the UA changes', function () {
    $a = UserHash::compute('1.2.3.4', 'Mozilla/5.0');
    $b = UserHash::compute('1.2.3.4', 'Safari/17');
    expect($a)->not->toBe($b);
});

it('differs from a hash built from raw wp_salt without the dedup scope', function () {
    $raw = hash_hmac('sha256', '1.2.3.4|Mozilla/5.0', WpSaltStub::for('auth'));
    expect(UserHash::compute('1.2.3.4', 'Mozilla/5.0'))->not->toBe($raw);
});

it('changes when wp_salt rotates', function () {
    $before = UserHash::compute('1.2.3.4', 'Mozilla/5.0');
    WpSaltStub::set('auth', 'rotated-salt');
    $after = UserHash::compute('1.2.3.4', 'Mozilla/5.0');
    expect($before)->not->toBe($after);
});
