# Moonfarmer Reactions Lead Capture Pro Addon Implementation Plan

This is the build plan for `moonfarmer-reactions-lead-capture-pro`, a separate paid addon that extends the Free plugin without modifying Free internals.

Free remains the complete WordPress.org product. Pro adds automation, scale, deeper analysis, and agency controls by consuming the public Free hook/JS surface documented in `docs/hooks-and-filters.md`.

## 1. Product Boundary

### Free Owns

- Reaction widget and reaction storage.
- Inline email capture and consent storage.
- CSV export.
- 30-day default analytics and a 730-day synchronous performance ceiling.
- Gutenberg block, shortcode, auto-insert, per-post visibility.
- Settings, privacy, uninstall behavior.
- Public hook contract and admin JS extension registry.

### Pro Adds

- ESP/provider sync for captured emails.
- Weekly/monthly rollups and fast long-window comparisons.
- A/B testing for widget/capture variants.
- Segments by taxonomy, author, post type, date range, and logged-in state.
- Webhooks.
- Async report generation.
- Scheduled email digests/exports.
- White-label branding controls.
- License, updates, and support tooling.

### Pro Must Not

- Fork, patch, or replace files inside `moonfarmer-reactions-lead-capture/`.
- Depend on Free private implementation classes unless Free explicitly promotes them as stable public API.
- Store duplicate copies of Free reaction/capture rows.
- Gate Free features behind a Pro license.
- Break when license lapses. Pro should no-op and preserve data.

## 2. Repository Shape

Private repo: `moonfarmer-reactions-lead-capture-pro`

```text
moonfarmer-reactions-lead-capture-pro/
├── moonfarmer-reactions-lead-capture-pro.php
├── composer.json
├── package.json
├── vite.config.js
├── app/
│   ├── Core/
│   ├── Providers/
│   ├── Licensing/
│   ├── Database/
│   ├── Esp/
│   ├── Analytics/
│   ├── AbTesting/
│   ├── Segments/
│   ├── Webhooks/
│   ├── Reports/
│   └── WhiteLabel/
├── resources/
│   ├── admin/
│   └── widget/
├── tests/
├── docs/
└── openspec/
```

Namespace: `Moonfarmer Reactions Lead CapturePro\`.

Runtime PHP support: PHP 7.4 through 8.4, matching Free. Avoid PHP 8-only runtime syntax.

## 3. Bootstrap Contract

### Plugin Header

`moonfarmer-reactions-lead-capture-pro.php`:

```php
/**
 * Plugin Name:       Moonfarmer Reactions Lead Capture Pro
 * Description:       Provider sync, advanced analytics, A/B testing, and agency controls for Moonfarmer Reactions Lead Capture.
 * Version:           0.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Requires Plugins:  moonfarmer-reactions-lead-capture
 * Text Domain:       moonfarmer-reactions-lead-capture-pro
 */
```

### Load Sequence

1. Exit if `ABSPATH` is not defined.
2. Define Pro constants: `MOONFARMER_REACTIONS_LEAD_CAPTURE_PRO_VERSION`, `MOONFARMER_REACTIONS_LEAD_CAPTURE_PRO_FILE`, `MOONFARMER_REACTIONS_LEAD_CAPTURE_PRO_DIR`, `MOONFARMER_REACTIONS_LEAD_CAPTURE_PRO_URL`, `MOONFARMER_REACTIONS_LEAD_CAPTURE_PRO_BASENAME`.
3. Check PHP/WP floors.
4. Check Free is active by verifying `defined('MOONFARMER_REACTIONS_LEAD_CAPTURE_VERSION')`.
5. Check minimum Free version.
6. Load Composer autoload.
7. Boot Pro on `plugins_loaded` priority `20`, after Free boots at priority `5`.

### Minimum Free Version

Initial Pro requires the first Free version that contains:

- `moonfarmer_reactions_lead_capture_admin_tabs`
- `moonfarmer_reactions_lead_capture_admin_metric_cards`
- `moonfarmer_reactions_lead_capture_admin_analytics_panels`
- `window.MoonfarmerReactionsLeadCaptureAdmin.registerTabRenderer`
- `window.MoonfarmerReactionsLeadCaptureAdmin.registerCardRenderer`
- `window.MoonfarmerReactionsLeadCaptureAdmin.registerPanelRenderer`
- `moonfarmer_reactions_lead_capture_after_capture`
- `moonfarmer_reactions_lead_capture_after_aggregate`
- `moonfarmer_reactions_lead_capture_export_columns`
- `moonfarmer_reactions_lead_capture_analytics_max_days`

Until Free has a stable tagged release, Pro should use a constant like:

```php
const REQUIRED_FREE_VERSION = '0.1.0';
```

Update this before paid distribution.

## 4. Reuse Map

| Pro Capability | Free Surface To Reuse | Pro-Owned Work |
| --- | --- | --- |
| Admin tabs | `moonfarmer_reactions_lead_capture_admin_tabs`, JS tab renderer registry | Pro tab renderers and Pro REST endpoints |
| Metric cards | `moonfarmer_reactions_lead_capture_admin_metric_cards`, JS card renderer registry | Comparison cards, license cards, sync health |
| Analytics panels | `moonfarmer_reactions_lead_capture_admin_analytics_panels`, JS panel renderer registry | Segments, A/B result panels, rollup panels |
| Widget variant data | `moonfarmer_reactions_lead_capture_widget_data`, `moonfarmer_reactions_lead_capture_widget_container_attrs` | Variant assignment and Pro widget payload |
| ESP sync | `moonfarmer_reactions_lead_capture_after_capture`, `moonfarmer_reactions_lead_capture_export_columns` | Provider adapters, job queue, sync status |
| Long analytics | `moonfarmer_reactions_lead_capture_after_aggregate`, `moonfarmer_reactions_lead_capture_analytics_max_days`, `moonfarmer_reactions_lead_capture_analytics_window` | Weekly/monthly rollups and Pro analytics API |
| A/B testing | `moonfarmer_reactions_lead_capture_widget_data`, `moonfarmer_reactions_lead_capture_after_react`, `moonfarmer_reactions_lead_capture_after_capture` | Assignment, test config, result calculator |
| Segments | Pro REST routes and Free post/reaction/capture data | Segment query layer and saved segment definitions |
| Webhooks | `moonfarmer_reactions_lead_capture_after_react`, `moonfarmer_reactions_lead_capture_after_capture`, `moonfarmer_reactions_lead_capture_after_aggregate`, `moonfarmer_reactions_lead_capture_settings_saved` | Event formatter, signing, delivery jobs |
| White-label | `moonfarmer_reactions_lead_capture_admin_data`, `moonfarmer_reactions_lead_capture_widget_data` | Brand settings, CSS sanitization, scoped injection |

If a Pro feature cannot be built with this map, add a new Free hook first. Do not reach around the contract.

## 5. Pro Service Providers

Register these providers from Pro's `app/bootstrap.php`:

- `AppServiceProvider`
- `DatabaseServiceProvider`
- `LicenseServiceProvider`
- `AdminExtensionServiceProvider`
- `AssetServiceProvider`
- `EspServiceProvider`
- `AnalyticsRollupServiceProvider`
- `AbTestingServiceProvider`
- `SegmentServiceProvider`
- `WebhookServiceProvider`
- `ReportServiceProvider`
- `WhiteLabelServiceProvider`

Each provider owns one vertical slice. Providers attach to Free hooks in `boot()` and bind Pro services in `register()`.

## 6. Storage Plan

All Pro tables are prefixed through `$wpdb->prefix` and namespaced with `moonfarmer_reactions_lead_capture_pro_`.

### `moonfarmer_reactions_lead_capture_pro_esp_connections`

- `id`
- `provider`
- `name`
- `encrypted_credentials`
- `status`
- `created_at`
- `updated_at`

### `moonfarmer_reactions_lead_capture_pro_esp_jobs`

- `id`
- `capture_id`
- `connection_id`
- `email`
- `payload_json`
- `status` (`queued|running|synced|failed|dead`)
- `attempts`
- `last_error`
- `available_at`
- `created_at`
- `updated_at`

### `moonfarmer_reactions_lead_capture_pro_ab_tests`

- `id`
- `name`
- `status` (`draft|running|paused|ended`)
- `traffic_split`
- `variants_json`
- `starts_at`
- `ends_at`
- `created_at`
- `updated_at`

### `moonfarmer_reactions_lead_capture_pro_ab_assignments`

- `id`
- `test_id`
- `post_id`
- `user_hash`
- `variant_key`
- `created_at`

Unique key: `(test_id, post_id, user_hash)`.

### `moonfarmer_reactions_lead_capture_pro_weekly_agg`

- `week_start`
- `post_id`
- `reaction_type`
- `count`
- `captures`
- `updated_at`

Unique key: `(week_start, post_id, reaction_type)`.

### `moonfarmer_reactions_lead_capture_pro_monthly_agg`

- `month_start`
- `post_id`
- `reaction_type`
- `count`
- `captures`
- `updated_at`

Unique key: `(month_start, post_id, reaction_type)`.

### `moonfarmer_reactions_lead_capture_pro_segments`

- `id`
- `name`
- `definition_json`
- `created_by`
- `created_at`
- `updated_at`

### `moonfarmer_reactions_lead_capture_pro_webhooks`

- `id`
- `url`
- `secret`
- `events_json`
- `active`
- `last_status`
- `last_attempted_at`
- `created_at`
- `updated_at`

### `moonfarmer_reactions_lead_capture_pro_webhook_deliveries`

- `id`
- `webhook_id`
- `event`
- `payload_json`
- `status`
- `attempts`
- `response_code`
- `last_error`
- `available_at`
- `created_at`
- `updated_at`

### `moonfarmer_reactions_lead_capture_pro_reports`

- `id`
- `type`
- `params_json`
- `status`
- `file_path`
- `error`
- `expires_at`
- `created_by`
- `created_at`
- `updated_at`

## 7. Licensing And Updates

### Options

- `moonfarmer_reactions_lead_capture_pro_license_key`
- `moonfarmer_reactions_lead_capture_pro_license_status`
- `moonfarmer_reactions_lead_capture_pro_license_features`
- `moonfarmer_reactions_lead_capture_pro_license_expires_at`
- `moonfarmer_reactions_lead_capture_pro_license_checked_at`
- `moonfarmer_reactions_lead_capture_pro_license_grace_until`

### Resolution Order

1. `MOONFARMER_REACTIONS_LEAD_CAPTURE_PRO_LICENSE_KEY` constant.
2. `moonfarmer_reactions_lead_capture_pro/license_key` filter.
3. Stored option.

### Behavior

- Active or grace: Pro features run.
- Invalid/expired after grace: Pro hooks stay registered but return defaults or stub UI.
- License failure never deletes Pro data.
- License check runs on activation, weekly cron, and explicit admin refresh.
- Pro REST endpoints require both `manage_options` and active/grace license unless the endpoint is for license activation/status.

### Update Channel

Pro uses a private update server. Update checks hook into WordPress update transients and return a package URL only when the license is active or in grace.

## 8. Admin UI Integration

Pro ships its own Vite admin bundle and registers renderers into Free's admin app:

```js
window.MoonfarmerReactionsLeadCaptureAdmin.registerTabRenderer('pro-sync', renderSyncTab);
window.MoonfarmerReactionsLeadCaptureAdmin.registerCardRenderer('pro-compare-30', renderCompareCard);
window.MoonfarmerReactionsLeadCaptureAdmin.registerPanelRenderer('pro-segments', renderSegmentsPanel);
```

PHP declares the slots:

- Pro top-level tab: `moonfarmer_reactions_lead_capture_admin_tabs`
- Comparison cards: `moonfarmer_reactions_lead_capture_admin_metric_cards`
- Segments/A-B panels: `moonfarmer_reactions_lead_capture_admin_analytics_panels`

Pro UI states:

- Free missing: Pro shows admin notice, no boot.
- Free too old: admin notice names minimum Free version.
- License missing: render license activation tab only.
- License active/grace: render all enabled Pro modules.
- License expired after grace: render read-only data plus renewal prompt; jobs stop.

## 9. REST API

Namespace: `moonfarmer-reactions-lead-capture/v1/pro`.

Routes:

- `GET /license`
- `POST /license/activate`
- `POST /license/deactivate`
- `GET /esp/connections`
- `POST /esp/connections`
- `POST /esp/connections/{id}/test`
- `GET /esp/jobs`
- `POST /esp/jobs/{id}/retry`
- `GET /analytics/summary`
- `GET /analytics/compare`
- `GET /segments`
- `POST /segments`
- `GET /ab-tests`
- `POST /ab-tests`
- `POST /ab-tests/{id}/start`
- `POST /ab-tests/{id}/stop`
- `GET /webhooks`
- `POST /webhooks`
- `POST /webhooks/{id}/test`
- `GET /reports`
- `POST /reports`
- `GET /reports/{id}`
- `GET /reports/{id}/download`

Every route must have:

- nonce validation through WordPress REST
- capability check
- license check where relevant
- schema validation for params/body
- tests for unauthorized, unlicensed, and happy paths

## 10. Feature Sessions

### Session P0: Pro Bootstrap And Compatibility Gate

Goal: installable Pro skeleton that safely no-ops without Free.

Deliverables:

- `moonfarmer-reactions-lead-capture-pro.php`
- Composer autoload
- Pro service container
- Free presence/version check
- admin notice when Free missing/too old
- PHP 7.4-8.4 lint matrix
- OpenSpec change for Pro bootstrap

Verification:

- Activate Pro without Free: no fatal, clear notice.
- Activate Free + Pro: Pro boots.
- Free old-version simulation: Pro no-ops.

### Session P1: License Shell

Goal: license activation/status/deactivation without feature gating yet.

Deliverables:

- license options
- license REST endpoints
- license admin tab registered through Free admin renderer
- weekly license cron
- update-server interface stub

Verification:

- missing license shows activation tab only.
- active license unlocks a test-only Pro panel.
- server unreachable enters grace.

### Session P2: Admin Extension Runtime

Goal: Pro can mount real admin tabs/cards/panels into Free.

Deliverables:

- Pro Vite admin bundle
- tab/card/panel renderer registration
- PHP filter payload declarations
- extension fallback handling

Verification:

- Free admin renders unchanged without Pro.
- Pro tabs/cards/panels appear when active.
- Missing Pro JS shows Free fallback copy instead of a broken panel.

### Session P3: ESP Sync

Goal: captures sync to one ESP provider, with queue/retry/dead-letter.

First provider: Mailchimp or ConvertKit. Pick one before implementation.

Deliverables:

- connection storage
- encrypted credentials
- `moonfarmer_reactions_lead_capture_after_capture` job enqueue
- provider adapter interface
- queue worker
- sync status column via `moonfarmer_reactions_lead_capture_export_columns`

Verification:

- capture inserts one queued job.
- worker syncs a successful job.
- API error retries and eventually dead-letters.
- unlicensed Pro stops enqueueing new jobs but keeps old job records.

### Session P4: Rollups And Comparison Analytics

Goal: fast long-window analytics and previous-period comparison.

Deliverables:

- weekly/monthly tables
- hook into `moonfarmer_reactions_lead_capture_after_aggregate`
- Pro analytics summary endpoint
- metric cards through `moonfarmer_reactions_lead_capture_admin_metric_cards`
- raise `moonfarmer_reactions_lead_capture_analytics_max_days` only when rollups are available

Verification:

- daily aggregation upserts weekly/monthly rollups.
- comparison card renders current vs previous window.
- Free endpoint remains unchanged.

### Session P5: A/B Testing

Goal: run two-variant tests for widget/capture settings.

Deliverables:

- test config storage
- deterministic assignment
- widget payload injection
- result calculator
- Pro A/B admin tab

Verification:

- same visitor/post gets same variant.
- assignment records on first qualifying event.
- results update after reactions/captures.

### Session P6: Segments

Goal: saved analytics filters.

Deliverables:

- segment definitions
- Pro segmented analytics endpoint
- segment panel in Free analytics tab
- taxonomy/author/post-type/date filters

Verification:

- saved segment affects Pro endpoint only.
- Free analytics remains unsegmented.

### Session P7: Webhooks

Goal: signed outbound event delivery.

Deliverables:

- webhook config storage
- event mapper for Free actions
- HMAC signature header
- delivery queue and retry
- webhook test button

Verification:

- capture webhook posts signed payload.
- failed endpoint retries.
- inactive webhook does nothing.

### Session P8: Async Reports And Scheduled Exports

Goal: large reports without blocking REST workers.

Deliverables:

- report request endpoint
- background report builder
- temporary CSV storage
- status polling
- download endpoint
- scheduled digest/export jobs

Verification:

- report creates queued row.
- worker produces file.
- file expires after retention window.

### Session P9: White-Label

Goal: agency branding and safe scoped CSS.

Deliverables:

- brand settings
- CSS sanitizer
- admin payload override
- widget payload/style override

Verification:

- brand label changes in Pro-controlled surfaces.
- unsafe CSS is stripped.
- Free still works when Pro disabled.

## 11. Testing Matrix

Every Pro session must run:

- PHP lint on 7.4, 8.0, 8.1, 8.2, 8.3, 8.4.
- Pro Pest suite.
- Free Pest suite when Free hook contracts are touched.
- `npm run build` for Pro admin/widget assets.
- Free + Pro activation smoke test.
- Pro deactivation smoke test.
- License inactive, active, grace, expired paths.

Contract-changing Free PRs must also run:

- new Free + old Pro compatibility test when possible
- old Free + new Pro graceful no-op test
- hook signature grep against Pro

## 12. Release Strategy

Free releases through WordPress.org.

Pro releases through the private update server.

Compatibility rules:

- Additive Free hooks: Free can release first, Pro consumes later.
- Backward-compatible hook changes: keep old hook for at least one minor release.
- Breaking hook changes: paired Free and Pro release, same day, both changelogs explicit.

Pro plugin header should eventually include:

```php
 * Requires Plugins:  moonfarmer-reactions-lead-capture
```

Pro admin should also show a clear minimum Free version warning because `Requires Plugins` only checks presence, not feature-level contract compatibility.

## 13. Open Decisions Before Coding

- First ESP provider: Mailchimp, ConvertKit, MailerLite, Brevo, or Beehiiv.
- License stack: custom server, EDD-SL, Freemius, Paddle/LemonSqueezy-managed licensing, or Stripe-backed custom.
- Whether Action Scheduler is bundled, suggested, or optional fallback-only.
- Whether Pro bundles its own Preact copy or treats Free's admin renderer runtime as the only integration point.
- White-label CSS sanitizer library.
- Final paid version number and required Free version.
- Whether Pro starts as one repo only or gets a tiny license-server repo at the same time.

## 14. Immediate Next Step

Start Session P0 only after Free Session 12 is merged/tagged or the target Free branch is chosen. P0 should create the private Pro repo skeleton and a local two-plugin smoke test before any paid feature code begins.
