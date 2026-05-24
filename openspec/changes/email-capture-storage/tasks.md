## 1. Capture domain

- [ ] 1.1 Create `app/Captures/Captures.php` with `final class` in namespace `Moonfarmer\ReactionsLeadCapture\Captures`. Expose `public const SOURCES = ['inline', 'block', 'shortcode']`, `public const DEFAULT_VERSION = 'v1'`, `public const PURGE_DAYS = 30`. Add static `isValidSource(string $source): bool` (filtered via `moonfarmer_reactions_lead_capture_capture_sources`) and static `consentTextVersion(): string` (filtered via `moonfarmer_reactions_lead_capture_consent_text_version`).
- [ ] 1.2 Create `app/Captures/CaptureInput.php` as a `final readonly` DTO with: `int $postId`, `string $email`, `string $reactionType`, `string $source`, `string $consentTextVersion`, `\DateTimeImmutable $consentAt`, `string $ipHash`, `string $userAgentHash`, `\DateTimeImmutable $purgeAt`.
- [ ] 1.3 Create `app/Captures/CaptureRecord.php` as a `final readonly` DTO with: `int $id`, all CaptureInput fields, `string $status` ('inserted' | 'already_exists').

## 2. Repository

- [ ] 2.1 Create `app/Captures/CaptureRepository.php` (`final class`). Constructor `(\wpdb $wpdb)`. Method `store(CaptureInput $input): CaptureRecord` builds an INSERT against `Schema::tableName($wpdb, Schema::TABLE_CAPTURES)` with prepared values. Uses `$wpdb->last_error` to detect duplicate-key violations (or `$wpdb->insert_id === 0` plus a defensive SELECT) and returns `CaptureRecord` with `status: 'already_exists'` and the existing row's id when the unique key fires. Otherwise returns `status: 'inserted'` with `$wpdb->insert_id`.
- [ ] 2.2 Add `findByEmailAndPost(string $email, int $postId): ?array` private/protected helper for the duplicate path's existing-row lookup.

## 3. Controller and route

- [ ] 3.1 Create `app/Http/Controllers/CaptureController.php` (`final class`). Constructor `(CaptureRepository $repository)`. Single public method `capture(\WP_REST_Request $request): \WP_REST_Response | \WP_Error`.
- [ ] 3.2 Method body order: parse `post_id`, `email`, `reaction_type`, `consent`, `source` from JSON params. Defaults `source` to `'inline'` when missing. Lowercase + trim email; apply `moonfarmer_reactions_lead_capture_capture_email` filter; validate `is_email()` and `strlen <= 190`. Validate `consent === true` (strict). Validate `reaction_type` via `Reactions::isValid`. Validate `source` via `Captures::isValidSource`. Validate post via `postIsPublic` (copy or extract from ReactionController). Compute `ip_hash` + `user_agent_hash` via `UserHash::compute(ip, ua)` with a temporary scope swap — see 3.3.
- [ ] 3.3 The capture hash uses a distinct salt scope (`'moonfarmer_reactions_lead_capture_capture'`). Add a `UserHash::computeForCapture(string $ip, string $ua): string` helper that calls `hash_hmac('sha256', "$ip|$ua", wp_salt('auth') . 'moonfarmer_reactions_lead_capture_capture')`. Add `UserHash::fromRequestForCapture(\WP_REST_Request $request): array{ip: string, ua: string, ipHash: string, uaHash: string}` so the controller gets all four values in one call and the salt scope stays encapsulated.
- [ ] 3.4 Compute `consentAt = new DateTimeImmutable('now', new DateTimeZone('UTC'))` and `purgeAt = consentAt->modify('+30 days')`. Build `CaptureInput`. Fire `moonfarmer_reactions_lead_capture_before_capture` inside try/catch for `RestException`. Call `$repository->store($input)`. On `'already_exists'` return 409. On `'inserted'` fire `moonfarmer_reactions_lead_capture_after_capture` and return 200 with `{post_id, reaction_type, email, status, capture_id}`.
- [ ] 3.5 All error returns use distinct codes and full-sentence messages: `moonfarmer_reactions_lead_capture_post_not_found` (404), `moonfarmer_reactions_lead_capture_invalid_reaction_type` (422), `moonfarmer_reactions_lead_capture_invalid_email` (422), `moonfarmer_reactions_lead_capture_consent_required` (422), `moonfarmer_reactions_lead_capture_invalid_source` (422), `moonfarmer_reactions_lead_capture_capture_already_exists` (409).
- [ ] 3.6 Register the route in `RestServiceProvider::boot()` alongside the existing routes. `POST /capture`. Permission: nonce check (same as `/react`). Args: integer post_id ≥ 1, string email 1..190, string reaction_type 1..32, boolean consent, string source 1..32 (optional, default 'inline').

## 4. Fraud purger

- [ ] 4.1 Create `app/Captures/FraudPurger.php` (`final class`). Constructor `(\wpdb $wpdb)`. Method `run(): int` executes the documented UPDATE and returns the number of rows nulled.

## 5. Service provider

- [ ] 5.1 Create `app/Providers/CaptureServiceProvider.php` (extends `ServiceProvider`). In `register()` bind `CaptureRepository`, `CaptureController`, and `FraudPurger` as singletons (each takes `$GLOBALS['wpdb']` or resolves from the container). In `boot()`: `add_action('moonfarmer_reactions_lead_capture_purge_fraud_metadata', fn() => $this->app->get(FraudPurger::class)->run())`.
- [ ] 5.2 Register `CaptureServiceProvider::class` in `app/bootstrap.php` after `RestServiceProvider::class` so the controller class is resolvable when REST routes register.
- [ ] 5.3 Extend `RestServiceProvider` to also bind/serve `CaptureController` — or move the capture route registration into `CaptureServiceProvider::boot()` instead, keeping route ownership next to the controller (preferred — small, explicit, decoupled from `RestServiceProvider`'s existing routes).

## 6. Activation + deactivation

- [ ] 6.1 Update `moonfarmer-reactions-lead-capture.php` activation closure: after `Application::boot()`, schedule `moonfarmer_reactions_lead_capture_purge_fraud_metadata` daily if `wp_next_scheduled` returns `false`. Use `time() + HOUR_IN_SECONDS` as the first-run timestamp.
- [ ] 6.2 Update `moonfarmer-reactions-lead-capture.php` deactivation closure: unschedule any existing `moonfarmer_reactions_lead_capture_purge_fraud_metadata` event. Leave option keys and table data intact.

## 7. Test stubs and tests

- [ ] 7.1 Extend `tests/Stubs/wp_functions.php` namespaced shims for `Moonfarmer\ReactionsLeadCapture\Captures` and `Moonfarmer\ReactionsLeadCapture\Http\Controllers`: `is_email`, `sanitize_email`, `wp_unslash`, `sanitize_text_field`, `wp_schedule_event`, `wp_unschedule_event`, `wp_next_scheduled`, `time`, `current_time` if needed.
- [ ] 7.2 Extend `Tests\Stubs\WpdbStub` with `insert_id` property + `insert($table, $data)` method that records the row and bumps `insert_id`. Add `simulateDuplicateKey: bool` flag so `query()` can pretend the unique key fired (sets `last_error`).
- [ ] 7.3 Add `Tests\Stubs\CronSpy` for `wp_schedule_event` / `wp_unschedule_event` / `wp_next_scheduled` assertions.
- [ ] 7.4 `tests/Unit/CapturesTest.php`: `isValidSource` default + filter; `consentTextVersion` default + filter.
- [ ] 7.5 `tests/Unit/CaptureRepositoryTest.php`: `store` builds expected INSERT with prepared values; returns `'inserted'` with the new id; returns `'already_exists'` with the existing id when duplicate key fires; never overwrites an existing row.
- [ ] 7.6 `tests/Unit/FraudPurgerTest.php`: `run()` issues the expected UPDATE; returns the affected-rows count; running a second time returns 0 when no rows match.
- [ ] 7.7 Update `tests/Unit/BootstrapTest.php` autoload assertions for `Captures`, `CaptureInput`, `CaptureRecord`, `CaptureRepository`, `CaptureController`, `FraudPurger`, `CaptureServiceProvider`.
- [ ] 7.8 Run `composer test`; confirm all green.

## 8. Manual REST verification

- [ ] 8.1 `wp db query "DELETE FROM wp_moonfarmer_reactions_lead_capture_captures"` for a clean slate.
- [ ] 8.2 Mint a nonce, POST a valid capture; confirm 200 + `status: 'inserted'`, row exists with all fields populated, `fraud_metadata_purge_at` ≈ now + 30 days.
- [ ] 8.3 POST the same `(email, post_id)` again; confirm 409 + `moonfarmer_reactions_lead_capture_capture_already_exists`.
- [ ] 8.4 POST with `consent: false`; confirm 422 + `moonfarmer_reactions_lead_capture_consent_required`.
- [ ] 8.5 POST with `email: "not-an-email"`; confirm 422 + `moonfarmer_reactions_lead_capture_invalid_email`.
- [ ] 8.6 POST with `reaction_type: "applause"`; confirm 422 + `moonfarmer_reactions_lead_capture_invalid_reaction_type`.
- [ ] 8.7 POST with `source: "telegram"`; confirm 422 + `moonfarmer_reactions_lead_capture_invalid_source`.
- [ ] 8.8 POST with mixed-case email; confirm the stored row's `email` is lowercase.
- [ ] 8.9 Manually trigger purge: `wp cron event run moonfarmer_reactions_lead_capture_purge_fraud_metadata`. On a fresh install it's a no-op (rows are < 30 days old). Insert a row with `fraud_metadata_purge_at` in the past via SQL; rerun the cron; confirm hashes are nulled.

## 9. Docs and final verification

- [ ] 9.1 Update `docs/hooks-and-filters.md`: promote `moonfarmer_reactions_lead_capture_before_capture` and `moonfarmer_reactions_lead_capture_after_capture` from "planned" to "shipped"; add new filters `moonfarmer_reactions_lead_capture_capture_sources`, `moonfarmer_reactions_lead_capture_consent_text_version`, `moonfarmer_reactions_lead_capture_capture_email`.
- [ ] 9.2 Run PHP lint across all touched files.
- [ ] 9.3 Run `openspec validate email-capture-storage --strict --no-interactive`.
- [ ] 9.4 Confirm `wp-content/debug.log` has no new Moonfarmer Reactions Lead Capture errors after manual verification.
- [ ] 9.5 Commit (no Co-Authored-By trailer; PR-style body per AGENTS.md).
