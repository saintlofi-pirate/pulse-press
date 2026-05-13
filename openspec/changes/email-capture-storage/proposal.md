## Why

Sessions 0–3 give us reactions and a working widget. The plan's revenue thesis is "turn a positive reaction into an email list." Session 4 is the smallest write-path slice of that thesis: a public REST endpoint that accepts an explicitly consented email tied to a specific post and reaction, stores it in `pulsepress_captures` (schema from Session 1), and records the fraud-review IP/UA hashes that will be purged after 30 days per the gap decisions in `docs/gap-questions-and-session-tasks.md`. Session 5 puts the inline UI on top of this endpoint; Session 10 exports CSVs from it; Pro layers ESP sync over the same `pulsepress_after_capture` action hook.

Free remains generous: the endpoint is open to anonymous visitors (consent is the gate, not authentication), email validation is lenient by default but filterable, and no PII leaves the site without admin action. Privacy stays first-class: storage requires `consent === true`, every capture records the consent text version + timestamp + source, IP/UA are stored as HMAC hashes (never raw), and `fraud_metadata_purge_at` is set to `now + 30 days` so that a daily WP-Cron job can null the hashes after the abuse-review window closes. Accessibility lives in the error responses: each failure mode has a distinct code and a full-sentence message that Session 5's UI can hand straight to `role="alert"`.

## What Changes

- Add `PulsePress\Captures\Captures` final class with `public const SOURCES = ['inline', 'block', 'shortcode']`, `isValidSource(string $source): bool` (filtered via `pulsepress_capture_sources`), `consentTextVersion(): string` (filterable via `pulsepress_consent_text_version`, default `'v1'`).
- Add `PulsePress\Captures\CaptureRepository` whose `store(CaptureInput $input): CaptureRecord` runs `INSERT INTO <prefix>pulsepress_captures` with prepared values. The schema's `UNIQUE KEY (email, post_id)` raises a duplicate-key error; the repository catches it and returns a `CaptureRecord` with `status: 'already_exists'` rather than overwriting. Successful inserts return `status: 'inserted'`.
- Add `PulsePress\Captures\CaptureInput` readonly DTO carrying `postId`, `email` (already normalised lowercase), `reactionType`, `source`, `consentTextVersion`, `consentAt` (DateTimeImmutable UTC), `ipHash`, `userAgentHash`, `purgeAt`.
- Add `PulsePress\Captures\CaptureRecord` readonly DTO carrying `id`, the persisted fields, and `status`.
- Add `PulsePress\Captures\FraudPurger::run()` that runs `UPDATE <prefix>pulsepress_captures SET ip_hash = NULL, user_agent_hash = NULL WHERE fraud_metadata_purge_at <= NOW() AND (ip_hash IS NOT NULL OR user_agent_hash IS NOT NULL)`.
- Add `PulsePress\Http\Controllers\CaptureController` with one method `capture(\WP_REST_Request $request)`. Validates: `post_id` is published+publicly-viewable; `email` passes `is_email()` and post-normalisation length ≤ 190; `reaction_type` in the Reactions allowlist; `consent === true`; `source` in `Captures::SOURCES`. Fires `pulsepress_before_capture` (rate-limit/abuse can short-circuit by throwing `RestException`); calls `CaptureRepository::store`; fires `pulsepress_after_capture` only on `'inserted'`. Returns `{post_id, reaction_type, email, status, capture_id}` on success.
- Add `PulsePress\Providers\CaptureServiceProvider` registered after `RestServiceProvider` in `app/bootstrap.php`. In `register()` binds the repository/purger; in `boot()` adds the REST route on `rest_api_init`, registers the daily WP-Cron event `pulsepress_purge_fraud_metadata`, and hooks `FraudPurger::run()` to that event.
- Update `pulsepress.php` activation/deactivation hooks: activation schedules the daily cron if not present; deactivation unschedules it (data stays).
- Add `pulsepress_capture_email` filter so site admins can normalise/transform emails before storage (e.g., strip `+tag` aliases).
- All error responses use full-sentence messages and distinct WP_Error codes for assistive-tech-friendly UI consumption: `pulsepress_post_not_found` (404), `pulsepress_invalid_reaction_type` (422), `pulsepress_invalid_email` (422), `pulsepress_consent_required` (422), `pulsepress_invalid_source` (422), `pulsepress_capture_already_exists` (409).
- **BREAKING**: none — this is the first capture surface.

## Capabilities

### New Capabilities

- `capture-api`: defines the public capture REST contract — endpoint path, request/response shape, validation rules, permission/consent gating, replacement-vs-409 semantics, fraud-metadata fields and their purge contract, action hooks fired before and after storage, WP-Cron event name and cadence, accessibility-friendly error response shape.

### Modified Capabilities

None — schema is unchanged from Session 1; reaction API is untouched; widget API is untouched.

## Impact

- **New files**: `app/Captures/Captures.php`, `app/Captures/CaptureInput.php`, `app/Captures/CaptureRecord.php`, `app/Captures/CaptureRepository.php`, `app/Captures/FraudPurger.php`, `app/Http/Controllers/CaptureController.php`, `app/Providers/CaptureServiceProvider.php`, `tests/Unit/CaptureRepositoryTest.php`, `tests/Unit/FraudPurgerTest.php`, `tests/Unit/CapturesTest.php`.
- **Modified files**: `pulsepress.php` (activation schedules cron; deactivation unschedules), `app/bootstrap.php` (registers `CaptureServiceProvider`), `tests/Stubs/wp_functions.php` (adds shims for `is_email`, `sanitize_email`, `wp_schedule_event`, `wp_unschedule_event`, `wp_next_scheduled`, time helpers), `docs/hooks-and-filters.md` (promotes `pulsepress_before_capture` / `pulsepress_after_capture` to shipped; documents new `pulsepress_consent_text_version`, `pulsepress_capture_sources`, `pulsepress_capture_email` filters).
- **REST API**: one new endpoint — `POST /wp-json/pulsepress/v1/capture` (nonce-protected, anonymous OK if nonce present, consent must be `true`).
- **Database changes**: none. Schema from Session 1 has every column needed.
- **WP-Cron events introduced**: `pulsepress_purge_fraud_metadata` (daily). Idempotent — purges only rows that still have hashes.
- **Filters introduced**: `pulsepress_capture_sources`, `pulsepress_consent_text_version`, `pulsepress_capture_email`.
- **Actions introduced**: `pulsepress_before_capture`, `pulsepress_after_capture`. Both promoted from "planned" to "shipped" in `docs/hooks-and-filters.md`.
- **Privacy**: explicit consent gate; HMAC-hashed IP/UA; 30-day purge ensures even hashes don't persist beyond the abuse review window. Capture rows themselves are kept until admin deletion (gap 3).
- **Performance**: writes are a single prepared INSERT. Cron job runs once daily and touches only rows where the purge condition is met. No transient cache for captures (writes only).
- **Accessibility**: every error code has a distinct, full-sentence message Session 5's inline form will surface via `role="alert"`. No HTML, no codes-only messages.
- **Free/Pro boundary**: untouched. Pro hooks `pulsepress_after_capture` for ESP sync and double opt-in — it does not modify Free's storage path.
