## Context

The capture flow has three audiences: the site visitor who is asked to leave their email after a positive reaction (the form is Session 5's job); the site owner who wants the data for outreach (CSV export is Session 10's job); and any Pro/3rd-party integration that wants to sync captures to an ESP, fire a webhook, or trigger a double opt-in mail (everything attaches via `moonfarmer_reactions_lead_capture_after_capture`).

Session 1 already declared the schema with every column we need:

- `email` (VARCHAR(190))
- `consent` (TINYINT) + `consent_text_version` + `consent_at`
- `source` (e.g. `'inline'`, `'block'`)
- `ip_hash` / `user_agent_hash` (CHAR(64) NULL — nullable so the purge can wipe without dropping the row)
- `fraud_metadata_purge_at` (DATETIME indexed)
- UNIQUE KEY `uniq_email_post (email, post_id)`

Session 2 already wrote the HMAC helper (`UserHash::compute`) and the request-resolving helper (`UserHash::fromRequest`); Session 4 reuses both verbatim, just with a different scope string so the capture hash can't be cross-referenced with the reaction dedup hash (defense-in-depth — they should not look identical even though the inputs are the same).

Gap decisions that constrain this slice:

- **Gap 3**: captures kept until manual delete; fraud IP metadata purged after 30 days; uninstall opt-in for destructive delete.
- **Gap 4**: consent is required; store consent text version + timestamp + source; CSV export (Session 10) includes consent timestamp; ESP sync (Pro) respects provider double opt-in.

Two new questions answered here in addition to the above:

- **Duplicate (email, post)**: replace silently? Reject? Return existing? Decision in D4 below.
- **Consent text versioning**: how is the current version surfaced to the JS client and the admin? Decision in D5.

## Goals / Non-Goals

**Goals:**

- Anonymous visitors can capture (consent is the gate, login is not required) provided the request carries a fresh REST nonce.
- Consent is explicit (`consent === true`) and recorded with a versioned snapshot of the consent text, so we can prove what the visitor agreed to even if the copy changes.
- Email is normalised once at the application boundary so the unique key catches duplicates correctly regardless of case.
- The schema's UNIQUE KEY does the work for duplicate detection — no read-then-write race.
- Fraud metadata (IP/UA hash) is recorded for storage, but auto-purged after 30 days via WP-Cron so we can't accidentally retain it forever.
- Every failure mode has a unique WP_Error code and a full English sentence — Session 5's inline form pipes the message into `role="alert"` without translation.
- Pro can attach ESP sync / double opt-in / webhooks by hooking `moonfarmer_reactions_lead_capture_after_capture` without modifying Free.
- Every test runs without a live WordPress; existing stubs cover `wpdb`, options, transients, filters; we extend them with `is_email`, `wp_schedule_event`, `wp_next_scheduled`, and a deterministic clock.

**Non-Goals:**

- No inline UI. That's Session 5.
- No CSV export. Session 10.
- No ESP sync, no webhook delivery, no double opt-in mail. Pro.
- No "unsubscribe" or "request deletion" endpoint. Email handling under the GDPR right-to-erasure is a separate slice (likely Session 12 packaging).
- No rate-limiting at the application layer. The `moonfarmer_reactions_lead_capture_before_capture` hook plus the unique key cover the obvious abuse cases; serious rate-limiting is a follow-up module.
- No support for arbitrary post types in Free. Only published, publicly-viewable posts can capture (matching the reaction endpoint's contract).
- No Action Scheduler. WP-Cron is fine for a once-per-day purge with no fan-out. Action Scheduler may swap in via the QueueScheduler abstraction in Session 8.

## Decisions

### D1. One endpoint, no split read/write

Captures are write-only on the front end. There is no public "list my captures" endpoint — that would be a privacy hole. Reads come from the admin (CSV export in Session 10, dashboard in Session 9). So `POST /moonfarmer-reactions-lead-capture/v1/capture` is the entire surface.

### D2. Consent gate, not auth gate

`permission_callback` checks the REST nonce only. The `consent === true` requirement is validated in the controller body, not in `permission_callback`, because a missing consent should produce a 422 with a helpful message — not a 401 ("you are not allowed to do that").

This means a logged-in admin still has to consent the same way an anonymous visitor does. The endpoint never special-cases logged-in users in Free.

### D3. Email normalisation: lowercase + trim + filterable

The controller does `strtolower(trim($input))` before calling `is_email()`. Some admins want to also strip Gmail-style `+tag` aliases to dedupe more aggressively; the `moonfarmer_reactions_lead_capture_capture_email` filter lets them. The default is plain lowercase+trim — the most defensible behaviour without surprising anyone.

`VARCHAR(190)` means emails longer than that are rejected with `moonfarmer_reactions_lead_capture_invalid_email`. RFC 5321's 254-char limit is theoretical; 190 covers every real email I've seen in five years of WordPress logs.

### D4. Duplicate (email, post): return 409 `moonfarmer_reactions_lead_capture_capture_already_exists`, not replace

When the unique key fires:

- We do not overwrite. The first consent is the authoritative one — a second submission shouldn't quietly reset the timestamp or change the consent text version.
- We return HTTP 409 with `status: 'already_exists'` in the response body so the UI can show a friendly "We already have your email for this post" message.
- We do not leak whether the email was ever captured on a different post — only this `(email, post)` pair is implied by the 409.

**Alternative considered**: silent success (return 200 with the existing record). Rejected — it's confusing to a client that thinks it just wrote new data, and Session 10's CSV export should never show "Captured: yes" for a visitor who clicked twice. A 409 is correct REST.

**Alternative considered**: update the `updated_at` column. Rejected — `updated_at` is reserved for cases where the row's substantive fields change, not for click counters.

### D5. Consent text version: filterable string, default `'v1'`

`Captures::consentTextVersion()` returns `apply_filters('moonfarmer_reactions_lead_capture_consent_text_version', 'v1')`. Session 5's inline form reads this string from the JS payload (via `moonfarmer_reactions_lead_capture_widget_data`) and shows the matching consent copy from `i18n.consent.<version>`. Session 6's settings page lets admins write their own consent text per version; bumping the version creates a fresh snapshot for every new capture without touching old rows.

The schema column is VARCHAR(32), so version strings stay short — `'v1'`, `'2025-06-policy'`, etc.

### D6. IP/UA hash uses the same HMAC scheme as reactions, with a different scope

```php
hash_hmac('sha256', $ip . '|' . $userAgent, wp_salt('auth') . 'moonfarmer_reactions_lead_capture_capture')
```

The `'moonfarmer_reactions_lead_capture_capture'` scope (vs. `'moonfarmer_reactions_lead_capture_dedup'` from Session 2) ensures the same visitor's reaction hash and capture hash *don't match*, so a leaked capture table can't be joined to the reactions table to deanonymise visitors. Defense-in-depth.

### D7. Fraud purge nulls the hashes, doesn't delete rows

When `fraud_metadata_purge_at <= NOW()`, the cron job runs:

```sql
UPDATE <prefix>moonfarmer_reactions_lead_capture_captures
SET ip_hash = NULL, user_agent_hash = NULL
WHERE fraud_metadata_purge_at <= NOW()
  AND (ip_hash IS NOT NULL OR user_agent_hash IS NOT NULL)
```

The row itself stays. The visitor's email + consent record is the long-lived data; the IP/UA hashes are auxiliary and time-bounded. Once nulled, the row is permanently fingerprint-free.

The `AND (ip_hash IS NOT NULL OR ...)` guard makes the query a no-op on rows already purged, so re-running the cron costs almost nothing.

### D8. WP-Cron, scheduled at activation, unscheduled at deactivation

`moonfarmer_reactions_lead_capture_purge_fraud_metadata` is registered as a daily WP-Cron event:

- **Activation**: if `wp_next_scheduled('moonfarmer_reactions_lead_capture_purge_fraud_metadata') === false`, call `wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'moonfarmer_reactions_lead_capture_purge_fraud_metadata')`. The +1 hour offset avoids racing the activation hook itself.
- **Deactivation**: `wp_unschedule_event(wp_next_scheduled(...), 'moonfarmer_reactions_lead_capture_purge_fraud_metadata')`. Data stays in tables; only the cron is removed.
- **Uninstall**: handled by `uninstall.php` indirectly — once the plugin's gone, the cron event is orphaned and WordPress garbage-collects it after one round-trip.

**Alternative considered**: Action Scheduler. Rejected for v1 per gap 2; once Session 8 introduces the QueueScheduler abstraction, this cron migrates behind it without changing the consumer.

### D9. Single full-sentence error message per failure mode

Every error response is shaped:

```json
{
  "code": "moonfarmer_reactions_lead_capture_consent_required",
  "message": "You must agree to the consent statement before submitting your email.",
  "data": { "status": 422 }
}
```

Sentences are imperative, second-person, no codes-as-fallback, no concatenated fragments. Session 5's inline form pipes `message` directly into a `<p role="alert">`; assistive tech reads exactly what the message says.

### D10. before_capture can short-circuit; after_capture is a notification

```php
do_action('moonfarmer_reactions_lead_capture_before_capture', $postId, $normalisedEmail, $reactionType, $request);
// store
do_action('moonfarmer_reactions_lead_capture_after_capture', $captureId, $postId, $normalisedEmail, $reactionType, $consentTextVersion);
```

`before_capture` is called inside a try/catch for `RestException` so a rate-limit module can throw a `WP_Error('moonfarmer_reactions_lead_capture_rate_limited', '…', ['status' => 429])` and the controller will return it. `after_capture` fires only on `'inserted'` — a 409 duplicate skips it, so ESP sync doesn't double-subscribe.

## Risks / Trade-offs

- **Risk**: a visitor enters the same email on the same post via two different reaction types. → Mitigation: the unique key is `(email, post)`, so the second submission returns 409. The original capture retains its original `reaction_type`. Trade-off: we lose the information that they reacted with two different types, but the email list is what matters and gap 4 specifies "one consent per post".
- **Risk**: WP-Cron is unreliable on low-traffic sites (it only fires when someone visits). → Mitigation: the 30-day window is generous; a site with one weekly visitor still purges within a week of expiry. For high-stakes purges, a future "Set up real cron" hint in Session 12 packaging suggests `wp cron event run --due-now` via system cron.
- **Risk**: `is_email()` is permissive (allows some technically-invalid addresses). → Mitigation: we accept WP's definition as canonical. The `moonfarmer_reactions_lead_capture_capture_email` filter lets stricter sites layer extra validation.
- **Risk**: a Pro `moonfarmer_reactions_lead_capture_after_capture` handler throws an exception, leaving Free with a stored row but no ESP sync. → Mitigation: the controller does not propagate `after_capture` exceptions — Free's perspective is the store succeeded. Pro handlers should log and retry on their own (Pro will land Action Scheduler retry for ESP sync in its own session).
- **Risk**: a visitor's email contains uppercase that survives normalisation in some lib upstream. → Mitigation: we lowercase explicitly in the controller before validation and storage; the unique key matches whatever the column collation does (utf8mb4_unicode_520_ci is case-insensitive, so `Foo@bar.com` and `foo@bar.com` would still collide even without our normalisation — belt + braces).
- **Risk**: a malicious actor sends `consent: false` and gets a 422, then notes the existence of the endpoint. → Mitigation: this is fine — the endpoint's existence isn't a secret, and returning a clean 422 is more honest than a silent 200.
- **Trade-off**: no "soft replacement" on consent text version changes. If an admin bumps `consent_text_version` from `'v1'` to `'v2'`, existing rows keep `'v1'` and new rows get `'v2'`. We do not silently re-sign old captures. Acceptable — that's the entire point of versioned consent.
- **Trade-off**: the endpoint accepts anonymous captures. This is the WordPress.org-distributable default; sites that want admin-only capture can hook `permission_callback` via... well, they can't right now — `permission_callback` is wired into the route registration. If needed, Session 6 settings will expose an "Require login to capture" option that the permission callback consults.

## Migration Plan

No data migration. Endpoint becomes available the moment `CaptureServiceProvider` is registered. Rollback is `git revert`.

For deployment safety:

1. Land the change.
2. Hit `POST /wp-json/moonfarmer-reactions-lead-capture/v1/capture` with `consent: false`. Confirm 422 + `moonfarmer_reactions_lead_capture_consent_required`.
3. Hit with valid `consent: true`, valid email, valid post, valid reaction. Confirm 200 + a row in `wp_moonfarmer_reactions_lead_capture_captures` with `consent=1`, `consent_text_version='v1'`, populated `ip_hash` and `user_agent_hash`, `fraud_metadata_purge_at` ≈ now + 30 days.
4. Re-submit the same email on the same post. Confirm 409 + `moonfarmer_reactions_lead_capture_capture_already_exists` and no new row.
5. Re-submit the same email on a *different* post. Confirm 200 + new row.
6. Run `wp cron event run moonfarmer_reactions_lead_capture_purge_fraud_metadata` manually. Confirm rows older than 30 days have their hashes nulled and newer rows are untouched. (Will be a no-op on a fresh install — assert via SQL.)

## Open Questions

- **Q1**: Should the response include the consent text version that was recorded, so the UI can show "We saved your consent (v1)" on success? → **Decided no for v1.** Surfacing the version in success UI feels admin-y, not user-facing; clutters the success state. The data is in the DB for legal purposes, which is sufficient.
- **Q2**: Should we send a confirmation email (double opt-in) in Free, or leave that to Pro/ESP? → **Decided defer to Pro.** Confirmation email requires SMTP/mail infrastructure we don't want to commit Free to operating. The capture is stored either way; admins who want double opt-in flip to Pro.
- **Q3**: Should we accept multiple emails per request (CSV-style)? → **Decided no.** One capture per request keeps the audit/rate-limit story simple. Bulk import is an admin feature for a later session.
