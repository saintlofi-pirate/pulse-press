## ADDED Requirements

### Requirement: POST /moonfarmer-reactions-lead-capture/v1/capture stores a consented email tied to a post and reaction

The plugin SHALL register `POST /wp-json/moonfarmer-reactions-lead-capture/v1/capture` accepting a JSON body with `post_id` (positive integer), `email` (RFC-compliant per `is_email()` after normalisation, ≤ 190 chars), `reaction_type` (in the Reactions allowlist), `consent` (must be boolean `true`), and `source` (in `Captures::SOURCES`, default `'inline'`). The endpoint SHALL require a valid `X-WP-Nonce` header verified with `wp_verify_nonce(..., 'wp_rest')`. On successful insert it SHALL respond `200` with `{post_id, reaction_type, email, status: 'inserted', capture_id}`.

#### Scenario: First capture by a visitor

- **WHEN** an anonymous visitor with a valid nonce posts `{post_id: 42, email: "reader@example.com", reaction_type: "love", consent: true, source: "inline"}`
- **THEN** the response is `200` with `status: "inserted"` and a new row exists in `<prefix>moonfarmer_reactions_lead_capture_captures` with `email = 'reader@example.com'`, `post_id = 42`, `reaction_type = 'love'`, `consent = 1`, `consent_text_version = 'v1'`, populated `ip_hash` and `user_agent_hash`, `fraud_metadata_purge_at` approximately 30 days in the future, and `source = 'inline'`

#### Scenario: Same email on same post returns 409 with explicit code

- **WHEN** the visitor submits the same `(email, post_id)` pair a second time
- **THEN** the response is `409` with code `moonfarmer_reactions_lead_capture_capture_already_exists` and message "We already have your email saved for this post." and no new row is written

#### Scenario: Same email on a different post is a fresh capture

- **WHEN** the same email is captured against `post_id: 99` after having been captured against `post_id: 42`
- **THEN** a new row is inserted (the unique key is `(email, post_id)` not `(email)`)

#### Scenario: Missing nonce

- **WHEN** the request is made without `X-WP-Nonce`
- **THEN** WordPress returns `401` with code `rest_forbidden` and no row is written

### Requirement: Consent must be explicit boolean true

The controller SHALL reject any submission where the `consent` field is not strictly the boolean `true` (not `1`, not `"true"`, not `"on"`). Rejection returns `422` with code `moonfarmer_reactions_lead_capture_consent_required` and message "You must agree to the consent statement before submitting your email."

#### Scenario: Consent omitted

- **WHEN** the request body lacks a `consent` field
- **THEN** the response is `422` with code `moonfarmer_reactions_lead_capture_consent_required` and no row is written

#### Scenario: Consent is string "true"

- **WHEN** the request body has `"consent": "true"`
- **THEN** the response is `422` with code `moonfarmer_reactions_lead_capture_consent_required` — strict boolean check, no coercion

#### Scenario: Consent is boolean false

- **WHEN** the request body has `"consent": false`
- **THEN** the response is `422` with code `moonfarmer_reactions_lead_capture_consent_required`

### Requirement: Email is normalised, validated, and filterable

The controller SHALL lowercase and trim the submitted email before validation and storage. The normalised email SHALL pass `is_email()` and SHALL be ≤ 190 characters. Sites MAY register the `moonfarmer_reactions_lead_capture_capture_email` filter to apply additional transformations (e.g., strip `+tag` aliases) — the filter receives the already-lowercased, trimmed email.

#### Scenario: Mixed-case email is stored lowercase

- **WHEN** the visitor submits `"Reader@Example.COM"`
- **THEN** the stored row's `email` column equals `"reader@example.com"`

#### Scenario: Invalid email shape

- **WHEN** `"email": "not an email"` is submitted
- **THEN** the response is `422` with code `moonfarmer_reactions_lead_capture_invalid_email` and message "Please enter a valid email address."

#### Scenario: Email longer than 190 chars

- **WHEN** the submitted email exceeds 190 characters after normalisation
- **THEN** the response is `422` with code `moonfarmer_reactions_lead_capture_invalid_email` and no row is written

#### Scenario: Filter strips Gmail-style aliases

- **WHEN** `moonfarmer_reactions_lead_capture_capture_email` is registered to strip `+tag` and the visitor submits `"reader+vip@gmail.com"`
- **THEN** the stored row's `email` column equals `"reader@gmail.com"`

### Requirement: Reaction type and source validated against allowlists

The controller SHALL reject submissions whose `reaction_type` is not in the filtered `Reactions::TYPES` allowlist (422 `moonfarmer_reactions_lead_capture_invalid_reaction_type`) and whose `source` is not in the filtered `Captures::SOURCES` allowlist (422 `moonfarmer_reactions_lead_capture_invalid_source`).

#### Scenario: Bad reaction type

- **WHEN** `"reaction_type": "applause"` is submitted
- **THEN** the response is `422` with code `moonfarmer_reactions_lead_capture_invalid_reaction_type` and message "That reaction type is not available on this site."

#### Scenario: Bad source

- **WHEN** `"source": "telegram"` is submitted
- **THEN** the response is `422` with code `moonfarmer_reactions_lead_capture_invalid_source` and message "That capture source is not allowed."

#### Scenario: Source defaults when omitted

- **WHEN** the request body omits `source`
- **THEN** the controller defaults to `'inline'` and the stored row's `source` column equals `'inline'`

### Requirement: Fraud-review IP and UA stored as HMAC hash with a capture-scoped salt

The controller SHALL compute `ip_hash` and `user_agent_hash` via the same HMAC-SHA256 construction as `UserHash::compute`, but with the salt scope `'moonfarmer_reactions_lead_capture_capture'` (not `'moonfarmer_reactions_lead_capture_dedup'`) so the capture hash is not bytewise equal to the reaction hash for the same visitor. Each hash SHALL be exactly 64 lowercase hex characters and stored in the CHAR(64) NULL columns.

#### Scenario: Hashes are produced for every successful capture

- **WHEN** a capture succeeds
- **THEN** the stored row has both `ip_hash` and `user_agent_hash` populated with 64-char lowercase hex strings

#### Scenario: Capture hash differs from reaction hash for the same visitor

- **WHEN** the same `(ip, ua)` pair produces both a reaction hash and a capture hash via the respective code paths
- **THEN** the two hashes differ because the salt scope differs

### Requirement: Fraud-metadata purge runs daily via WP-Cron and nulls expired hashes

The plugin SHALL register the WP-Cron event `moonfarmer_reactions_lead_capture_purge_fraud_metadata` to run daily. The event handler `FraudPurger::run()` SHALL execute `UPDATE <prefix>moonfarmer_reactions_lead_capture_captures SET ip_hash = NULL, user_agent_hash = NULL WHERE fraud_metadata_purge_at <= NOW() AND (ip_hash IS NOT NULL OR user_agent_hash IS NOT NULL)`. The event SHALL be scheduled at activation if not already scheduled and unscheduled at deactivation.

#### Scenario: Expired rows are nulled

- **WHEN** a capture row has `fraud_metadata_purge_at = '2026-04-01 00:00:00'` and today is later than that
- **THEN** after the cron runs, the row's `ip_hash` and `user_agent_hash` are both `NULL` while `email`, `consent`, and other fields are unchanged

#### Scenario: Active rows are untouched

- **WHEN** a capture row's `fraud_metadata_purge_at` is in the future
- **THEN** the cron leaves the row's hashes intact

#### Scenario: Re-run is a no-op for already-purged rows

- **WHEN** the cron runs a second time after rows have already been nulled
- **THEN** the UPDATE matches zero rows for those previously-nulled entries (because of the `IS NOT NULL` guard) and the query completes in approximately constant time

#### Scenario: Activation schedules the event idempotently

- **WHEN** the plugin is activated for the first time
- **THEN** `wp_next_scheduled('moonfarmer_reactions_lead_capture_purge_fraud_metadata')` returns a timestamp

#### Scenario: Reactivation does not double-schedule

- **WHEN** the plugin is deactivated and reactivated
- **THEN** `wp_next_scheduled('moonfarmer_reactions_lead_capture_purge_fraud_metadata')` returns exactly one timestamp; no duplicate events are registered

#### Scenario: Deactivation unschedules

- **WHEN** the plugin is deactivated
- **THEN** `wp_next_scheduled('moonfarmer_reactions_lead_capture_purge_fraud_metadata')` returns `false`

### Requirement: Action hooks expose extension points around the capture write

The controller SHALL fire `do_action('moonfarmer_reactions_lead_capture_before_capture', int $postId, string $email, string $reactionType, \WP_REST_Request $request)` before invoking the repository, inside a try/catch for `\Moonfarmer\ReactionsLeadCapture\Http\RestException`. After a successful insert (and only `'inserted'` — not `'already_exists'`) the controller SHALL fire `do_action('moonfarmer_reactions_lead_capture_after_capture', int $captureId, int $postId, string $email, string $reactionType, string $consentTextVersion)`.

#### Scenario: Before hook can short-circuit

- **WHEN** a plugin registers `add_action('moonfarmer_reactions_lead_capture_before_capture', $cb, 10, 4)` whose callback throws `new RestException(new WP_Error('moonfarmer_reactions_lead_capture_rate_limited', 'Too many requests.', ['status' => 429]))`
- **THEN** the response is `429` with code `moonfarmer_reactions_lead_capture_rate_limited` and no row is written and the after-hook does not fire

#### Scenario: After hook fires only on insert

- **WHEN** a duplicate submission returns 409 `moonfarmer_reactions_lead_capture_capture_already_exists`
- **THEN** no `moonfarmer_reactions_lead_capture_after_capture` action is dispatched for that request

#### Scenario: After hook receives full context

- **WHEN** a fresh capture succeeds
- **THEN** `moonfarmer_reactions_lead_capture_after_capture` is invoked once with arguments `($captureId, $postId, $email, $reactionType, $consentTextVersion)`

### Requirement: Consent text version is exposed via filter

`Captures::consentTextVersion(): string` SHALL return `apply_filters('moonfarmer_reactions_lead_capture_consent_text_version', 'v1')`. The returned string SHALL be stored verbatim in the `consent_text_version` column on every new capture. Existing rows SHALL NOT be modified when the filter changes the version — old captures keep their original version.

#### Scenario: Default version

- **WHEN** no filter is registered
- **THEN** `Captures::consentTextVersion()` returns `'v1'` and new rows store `'v1'`

#### Scenario: Filter returns a custom version

- **WHEN** `add_filter('moonfarmer_reactions_lead_capture_consent_text_version', fn() => '2026-06-policy')` is registered and a new capture lands
- **THEN** the new row's `consent_text_version` column equals `'2026-06-policy'` and rows captured before the filter was added still hold `'v1'`

### Requirement: Error responses are full sentences with distinct codes for assistive tech

Every error response from the capture endpoint SHALL use a unique `code` and a `message` that is a complete, user-facing English sentence (no HTML, no placeholder codes, no concatenated fragments). The message field SHALL be safe to pipe directly into a `role="alert"` element by Session 5's inline form.

#### Scenario: Error message is a full sentence

- **WHEN** any error response is generated
- **THEN** `message` is a complete sentence ending in a period; the message is descriptive of the cause; the message is not "Failed." or "Error" or a code

#### Scenario: Codes are distinct

- **WHEN** comparing every error code produced by the capture endpoint
- **THEN** `moonfarmer_reactions_lead_capture_post_not_found`, `moonfarmer_reactions_lead_capture_invalid_reaction_type`, `moonfarmer_reactions_lead_capture_invalid_email`, `moonfarmer_reactions_lead_capture_consent_required`, `moonfarmer_reactions_lead_capture_invalid_source`, and `moonfarmer_reactions_lead_capture_capture_already_exists` are all distinct and stable
