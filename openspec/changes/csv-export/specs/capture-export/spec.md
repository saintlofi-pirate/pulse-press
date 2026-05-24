## ADDED Requirements

### Requirement: GET /moonfarmer-reactions-lead-capture/v1/captures.csv streams the captures table as CSV

The plugin SHALL register `GET /wp-json/moonfarmer-reactions-lead-capture/v1/captures.csv` requiring `current_user_can('manage_options')`. The endpoint SHALL respond with `Content-Type: text/csv; charset=utf-8` and `Content-Disposition: attachment; filename=moonfarmer-reactions-lead-capture-captures-{timestamp}.csv`. The body SHALL be a UTF-8 BOM followed by one header row and one row per capture in `moonfarmer_reactions_lead_capture_captures`.

#### Scenario: Admin downloads the CSV

- **WHEN** an admin sends `GET /wp-json/moonfarmer-reactions-lead-capture/v1/captures.csv` with a valid REST nonce
- **THEN** the response status is `200`, the `Content-Type` header is `text/csv; charset=utf-8`, the `Content-Disposition` header begins with `attachment; filename=moonfarmer-reactions-lead-capture-captures-`, and the body starts with the UTF-8 BOM `\xEF\xBB\xBF` followed by the CSV header line

#### Scenario: Non-admin is rejected

- **WHEN** an unauthenticated visitor or a non-`manage_options` user requests the endpoint
- **THEN** the response status is `401` or `403` and the body contains no capture data

#### Scenario: Empty table still returns a header line

- **WHEN** the captures table is empty
- **THEN** the response body contains the BOM + header row only (no data rows) and the status is `200`

### Requirement: Default column set

The default columns SHALL be, in order: `consent_at`, `email`, `post_id`, `post_title`, `reaction_type`, `consent_text_version`, `source`, `created_at`. Column labels SHALL be human-readable English strings.

#### Scenario: Default header row

- **WHEN** the CSV is rendered with no filter overrides
- **THEN** the header line equals `"Consent timestamp","Email","Post ID","Post title","Reaction","Consent version","Source","Captured at"` (RFC 4180 quoting)

### Requirement: moonfarmer_reactions_lead_capture_export_columns filter extends or overrides columns

The exporter SHALL apply `apply_filters('moonfarmer_reactions_lead_capture_export_columns', $columns)` to the column definition map before streaming. Each entry SHALL declare `label` (string) and `render` (callable receiving the raw row array). Invalid entries (non-string labels or non-callable renders) SHALL be skipped with a debug log line.

#### Scenario: Pro adds an ESP-sync column

- **WHEN** a Pro plugin registers `add_filter('moonfarmer_reactions_lead_capture_export_columns', fn($c) => $c + ['esp_sync_status' => ['label' => 'ESP', 'render' => fn($r) => 'synced']])`
- **THEN** the rendered CSV header contains an additional `"ESP"` column and every data row carries `"synced"` for that column

#### Scenario: Invalid column entry is skipped

- **WHEN** a hook returns an entry with `render: 'not-a-callable'`
- **THEN** the exporter skips that entry, the rest of the CSV renders normally, and an error_log entry names the offending key

### Requirement: moonfarmer_reactions_lead_capture_before_export action can short-circuit

The exporter SHALL fire `do_action('moonfarmer_reactions_lead_capture_before_export', \WP_REST_Request $request)` inside a try-block before streaming. A handler MAY throw `Moonfarmer\ReactionsLeadCapture\Http\RestException` to abort the export; the controller SHALL return the wrapped `WP_Error` instead of streaming.

#### Scenario: Rate-limit blocks the export

- **WHEN** a plugin registers `add_action('moonfarmer_reactions_lead_capture_before_export', function () { throw new \Moonfarmer\ReactionsLeadCapture\Http\RestException(new \WP_Error('moonfarmer_reactions_lead_capture_rate_limited', 'Too many exports.', ['status' => 429])); })`
- **THEN** the response is `429` with code `moonfarmer_reactions_lead_capture_rate_limited` and no CSV bytes are streamed

### Requirement: Rows are RFC 4180-escaped

Every cell value SHALL be CSV-escaped:

- Wrapped in double quotes when the value contains a comma, double quote, or newline.
- Embedded double quotes SHALL be doubled (`"` → `""`).
- Line endings SHALL be `\r\n` between rows (Windows line endings for maximum spreadsheet compatibility).

#### Scenario: Embedded comma is quoted

- **WHEN** a capture row's `email` is `"foo,bar@example.com"` (unusual but legal)
- **THEN** the rendered cell is `"foo,bar@example.com"` (surrounded by quotes)

#### Scenario: Embedded double quote is doubled

- **WHEN** a row's `post_title` is `Hello "world"`
- **THEN** the rendered cell is `"Hello ""world"""`

### Requirement: Memory-bounded chunked reads

The exporter SHALL fetch captures in batches of at most 500 rows (filterable via `$options['chunk_size']`). The streaming pattern SHALL emit each batch's rows before fetching the next batch.

#### Scenario: 1,500-row table streams in three batches

- **WHEN** the captures table contains 1,500 rows and the exporter is called with default options
- **THEN** the underlying SELECT runs three times with `LIMIT 500 OFFSET 0/500/1000` (verified via wpdb stub or query log)

### Requirement: Reads only from moonfarmer_reactions_lead_capture_captures

The exporter SHALL NOT issue queries against `moonfarmer_reactions_lead_capture_reactions` or `moonfarmer_reactions_lead_capture_daily_agg`. Title lookups via `get_the_title` are permitted (they read `wp_posts`).

#### Scenario: Codebase grep audit

- **WHEN** running `grep -rE "FROM .+moonfarmer_reactions_lead_capture_(reactions|daily_agg)" app/Captures/CaptureExporter.php`
- **THEN** the search returns no matches
