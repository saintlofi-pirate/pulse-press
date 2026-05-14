<?php
declare(strict_types=1);

use PulsePress\Captures\CaptureExporter;
use Tests\Stubs\FilterRegistry;
use Tests\Stubs\PostRegistry;
use Tests\Stubs\WpdbStub;

function pp_capture_rows(): array
{
    return [
        [
            'id' => '1', 'post_id' => '42', 'email' => 'reader@example.com',
            'reaction_type' => 'love', 'consent_text_version' => 'v1',
            'source' => 'inline', 'consent_at' => '2026-05-13 12:00:00',
            'created_at' => '2026-05-13 12:00:00',
        ],
        [
            'id' => '2', 'post_id' => '42', 'email' => 'comma,user@example.com',
            'reaction_type' => 'love', 'consent_text_version' => 'v1',
            'source' => 'inline', 'consent_at' => '2026-05-13 12:01:00',
            'created_at' => '2026-05-13 12:01:00',
        ],
        [
            'id' => '3', 'post_id' => '42', 'email' => 'quote@example.com',
            'reaction_type' => 'insightful', 'consent_text_version' => 'v1',
            'source' => 'inline', 'consent_at' => '2026-05-13 12:02:00',
            'created_at' => '2026-05-13 12:02:00',
        ],
    ];
}

function pp_exporter(?array $rows = null): array
{
    $wpdb = new WpdbStub();
    $wpdb->resultsByQuery['FROM wp_pulsepress_captures ORDER BY id ASC'] = $rows ?? pp_capture_rows();
    return [new CaptureExporter($wpdb), $wpdb];
}

beforeEach(function () {
    PostRegistry::register(42, 'publish', true);
    // Title fallback for the calculator: PostRegistry::title returns false if not registered with title.
});

it('emits a header line then one row per capture', function () {
    [$exporter] = pp_exporter();
    $lines = [];
    $count = $exporter->stream(function (string $line) use (&$lines) {
        $lines[] = $line;
    });

    expect($count)->toBe(3);
    expect($lines)->toHaveCount(4); // header + 3 rows
    expect($lines[0])->toContain('Consent timestamp');
    expect($lines[0])->toContain('Email');
    expect($lines[0])->toContain('Captured at');
});

it('escapes cells containing commas with quotes', function () {
    [$exporter] = pp_exporter();
    $lines = [];
    $exporter->stream(function (string $line) use (&$lines) { $lines[] = $line; });

    // Second data row has the comma email.
    expect($lines[2])->toContain('"comma,user@example.com"');
});

it('uses CRLF line endings between rows', function () {
    [$exporter] = pp_exporter();
    $lines = [];
    $exporter->stream(function (string $line) use (&$lines) { $lines[] = $line; });

    foreach ($lines as $line) {
        expect(str_ends_with($line, "\r\n"))->toBeTrue();
    }
});

it('lets a filter add a column', function () {
    FilterRegistry::addFilter('pulsepress_export_columns', function (array $columns): array {
        $columns['esp_sync_status'] = [
            'label'  => 'ESP',
            'render' => static fn () => 'synced',
        ];
        return $columns;
    });

    [$exporter] = pp_exporter();
    $lines = [];
    $exporter->stream(function (string $line) use (&$lines) { $lines[] = $line; });

    expect($lines[0])->toContain('ESP');
    expect($lines[1])->toContain('synced');
});

it('skips columns with an invalid render callable', function () {
    FilterRegistry::addFilter('pulsepress_export_columns', function (array $columns): array {
        $columns['bad_column'] = ['label' => 'Bad', 'render' => 'not-a-callable'];
        return $columns;
    });

    [$exporter] = pp_exporter();
    $lines = [];
    $exporter->stream(function (string $line) use (&$lines) { $lines[] = $line; });

    expect($lines[0])->not->toContain('Bad');
});

it('short-circuits via pulsepress_before_export RestException', function () {
    FilterRegistry::addAction('pulsepress_before_export', function () {
        throw new \PulsePress\Http\RestException(new \WP_Error('pulsepress_rate_limited', 'Too many', ['status' => 429]));
    });

    [$exporter] = pp_exporter();
    $lines = [];

    try {
        $exporter->stream(function (string $line) use (&$lines) { $lines[] = $line; });
        $threw = false;
    } catch (\PulsePress\Http\RestException $e) {
        $threw = true;
    }

    expect($threw)->toBeTrue();
    expect($lines)->toBeEmpty();
});

it('issues chunked SELECT with LIMIT/OFFSET', function () {
    $rows = [];
    for ($i = 1; $i <= 3; $i++) {
        $rows[] = [
            'id' => (string) $i, 'post_id' => '42', 'email' => "u{$i}@example.com",
            'reaction_type' => 'love', 'consent_text_version' => 'v1',
            'source' => 'inline', 'consent_at' => '2026-05-13 12:00:00',
            'created_at' => '2026-05-13 12:00:00',
        ];
    }

    $wpdb = new WpdbStub();
    $wpdb->resultsByQuery['FROM wp_pulsepress_captures ORDER BY id ASC'] = $rows;
    $exporter = new CaptureExporter($wpdb);
    $count = $exporter->stream(static fn () => null, ['chunk_size' => 50]);

    expect($count)->toBe(3);
    $selects = array_filter($wpdb->queries, fn ($q) => str_contains($q, 'FROM wp_pulsepress_captures ORDER BY id ASC LIMIT'));
    expect($selects)->not->toBeEmpty();
});
