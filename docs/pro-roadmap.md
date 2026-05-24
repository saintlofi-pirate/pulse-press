# Moonfarmer Reactions Lead Capture Pro Roadmap

This doc holds everything the v1 plan deferred about Pro: the product spec, the codebase plan, and the per-feature designs. It does *not* replace `docs/moonfarmer-reactions-lead-capture-v1-plan.md §Free vs Pro Scope` (the durable Free/Pro principle) or `docs/moonfarmer-reactions-lead-capture-v1-plan.md §Pro Extension Seams` (the hook contract). Read this when:

- Pricing or licensing decisions need a reference.
- Pro session work begins (Session 13 onward).
- A Free feature touches a Pro seam and we need to confirm the boundary stays clean.

Cross-references to specific Free filters use the canonical names from `docs/hooks-and-filters.md`.

---

## 1. Product Spec

### 1.1 Target customer segments

| Segment | Pain | Why Pro lands |
| --- | --- | --- |
| Independent bloggers / niche publishers (1 site) | Want reader sentiment without a heavy stack | Affordable single-site tier; ESP sync to ConvertKit/Mailchimp |
| Small editorial teams (1–5 sites) | Capturing leads from posts; comparing performance | Multi-site license; sentiment over time; weekly digest |
| Agencies running client WordPress sites (10–50 sites) | Need white-labeling, scheduled reports per client | Unlimited-site agency tier; white-label; scheduled exports |
| Newsletter operators using WordPress as CMS | Already use ESP; want native bridge instead of Zapier | Direct ESP sync with tags; A/B test capture copy |

Non-targets: enterprise CDP customers (out of scope), one-off WP installs that already have Yoast/RankMath analytics (Free is enough).

### 1.2 Pricing tiers

Three tiers, annual renewal:

- **Personal** — 1 site. Direct ESP sync (one provider), comparison windows, A/B test engine, segmentation.
- **Professional** — 5 sites. Everything in Personal + multi-ESP, scheduled exports, weekly digests, async report generation.
- **Agency** — unlimited sites. Everything above + white-label, priority support SLA, custom CSS field.

Lifetime tier deliberately omitted at launch. Lifetime sales hurt long-term sustainability for products with ongoing ESP API maintenance costs.

Discount mechanics: 50% off year-two renewal automatic. Educational/non-profit discount available on request, manually applied (no automation needed v1).

### 1.3 License model

- License key is a 32-char opaque string (UUIDv4 hyphens stripped + 4 random bytes hex).
- Stored as a WP option: `moonfarmer_reactions_lead_capture_pro_license` (string), `moonfarmer_reactions_lead_capture_pro_license_status` (`active|invalid|expired|grace`), `moonfarmer_reactions_lead_capture_pro_license_checked_at` (unix).
- License → activations binding is server-side (the license server holds the activation list, not the plugin).
- Activation == a (license, site_url) pair. Deactivating in admin frees the slot.
- Grace period: 14 days after a renewal lapses or the server is unreachable. Pro features stay on; admin shows a warning banner. After 14 days, Pro features quietly stop (filters return defaults, scheduled jobs stop firing). The user's data stays intact.
- Pro never deletes Free data when a license lapses.

### 1.4 License server architecture

Self-hosted on Anthropic-stack-equivalent (whatever lives at `moonfarmer-reactions-lead-capture.io` when we get there). Stack:

- **Public endpoints** (called by Pro plugin):
  - `POST /api/v1/license/activate {license, site_url, plugin_version}` → `{status, expires_at, features[], signature}`
  - `POST /api/v1/license/deactivate {license, site_url}` → `{status}`
  - `POST /api/v1/license/check {license, site_url}` → same shape as activate
  - All responses include `signature` = HMAC-SHA256 of the response body with a private key. Pro verifies with the embedded public key. Prevents on-network tampering.
- **Plugin behavior**:
  - Check on activate, on first admin page load, then weekly via a `wp_schedule_event` cron.
  - Cache the last successful check for the cron interval + grace.
  - All checks are async via wp-cron, never block admin requests.
- **Server stack** (keep boring): Cloudflare → Fastify or Hono on Node 20 → Postgres. Single region. Hourly backups. License keys + activations + customer email + Stripe customer id, nothing more.
- **Stripe** is the source of truth for paid status. Webhooks update license rows. License server never holds card data.

### 1.5 Update channel

WordPress.org will host **Free only**. Pro is delivered as a paid plugin update via an EDD-SL–style mechanism (or Freemius — TBD, see §Open Questions). Mechanics either way:

- Pro plugin shows in `wp-admin → Plugins` like any other plugin (manual install on first purchase, hosted ZIP behind license check).
- Updates flow through WP's plugin update API, intercepted via `pre_set_site_transient_update_plugins` to point at our update server.
- Update server returns `{version, package_url}` only after license-status check passes (active or grace).
- ZIPs are signed; the update endpoint includes a SHA256 hash so the plugin verifies the downloaded package before unpacking.

This is the standard "premium WP plugin" pattern. Nothing exotic.

---

## 2. Pro Plugin Codebase Plan

### 2.1 Distribution

Separate plugin folder (`moonfarmer-reactions-lead-capture-pro/`), separate Git repo (`moonfarmer-reactions-lead-capture-pro` private). **Not** a monorepo with Free — keeps each repo's `composer.json` and Vite config independent and makes the license boundary obvious (Free is GPL; Pro is GPL-with-paid-distribution, same as Yoast / EDD).

Repo layout mirrors Free:

```
moonfarmer-reactions-lead-capture-pro/
├── moonfarmer-reactions-lead-capture-pro.php       # plugin entry, declares "Requires Plugins: moonfarmer-reactions-lead-capture"
├── app/
│   ├── Core/Application.php
│   ├── Providers/...
│   ├── Licensing/...
│   ├── Esp/...
│   ├── AbTesting/...
│   ├── Rollups/...
│   └── WhiteLabel/...
├── resources/
│   ├── admin/...            # Pro-only admin UI mounted into Free's seams
│   └── widget/...           # A/B variant scripts
├── tests/Pest.php
└── composer.json            # PSR-4: Moonfarmer Reactions Lead CapturePro\
```

Namespace: `Moonfarmer Reactions Lead CapturePro\` → `app/`.

### 2.2 Bootstrap & compatibility

Pro plugin's entry:

1. `Requires Plugins: moonfarmer-reactions-lead-capture` header (WP 6.5+ enforces this; Pro stays deactivated if Free isn't active).
2. Checks `defined('MOONFARMER_REACTIONS_LEAD_CAPTURE_VERSION')` and `version_compare(MOONFARMER_REACTIONS_LEAD_CAPTURE_VERSION, '1.0.0', '>=')`. If not, admin notice + don't boot.
3. Hooks `plugins_loaded` at priority **20** (Free boots at 5, so Free's services are already in the container).
4. Registers Pro service providers; each provider adds its hooks to Free's filters/actions documented in `docs/hooks-and-filters.md`.

### 2.3 Code boundaries

- Pro NEVER imports a class from Free's `app/` directly. The only Free types Pro depends on are explicit public types (likely `\Moonfarmer\ReactionsLeadCapture\Analytics\AggregationResult`, `\Moonfarmer\ReactionsLeadCapture\Settings\Settings`) — and only via interfaces that Free will publish as a "stable surface" once Pro starts shipping.
- Pro NEVER touches Free's tables with raw SQL. Reads go through Free's repositories (passed in via the container) or Free's REST API. Writes go to Pro's own tables.
- Pro's own tables: `wp_moonfarmer_reactions_lead_capture_pro_esp_jobs`, `wp_moonfarmer_reactions_lead_capture_pro_ab_assignments`, `wp_moonfarmer_reactions_lead_capture_pro_weekly_agg`, `wp_moonfarmer_reactions_lead_capture_pro_monthly_agg`. Migrations live in Pro's own `Schema::VERSION`.
- Pro registers its REST routes under `moonfarmer-reactions-lead-capture/v1/pro/*`. Each route has its own permission callback that consults license status before serving anything beyond a stub response.

### 2.4 Build pipeline

- Pro uses its own Vite config; emits its own admin + widget chunks loaded via Pro's own `wp_enqueue_script` calls.
- Pro's admin JS calls `window.MoonfarmerReactionsLeadCaptureAdmin.registerTabRenderer / registerCardRenderer / registerPanelRenderer` (shipped in Session 9.6) to mount into Free's admin SPA. No Preact-from-Free import — Pro can ship its own Preact instance if needed (file size budget allows).
- Pro's widget JS attaches via the `moonfarmer_reactions_lead_capture_widget_data` filter (PHP, Session 3) and a separate widget enqueue tied to A/B variant assignment.

---

## 3. Pro Feature Designs

### 3.1 ESP Sync Engine

**Job**: when a positive reaction yields a capture, push the email to the configured ESP with tags carrying post id / reaction type / consent version.

**Design**:

- Hook into `moonfarmer_reactions_lead_capture_after_capture` (action, Session 4). On every captured email, push a row into `wp_moonfarmer_reactions_lead_capture_pro_esp_jobs` with status `queued`.
- Background worker runs via Action Scheduler (preferred when active) or WP-Cron (fallback). Worker picks up `queued` rows in batches of 50, calls the ESP API, marks `synced` / `failed` / `dead`.
- Retry policy: exponential backoff, max 5 attempts, then dead-letter. Admin sees a "failed syncs" count in the Pro tab.
- ESP adapters are interface-driven: `EspAdapter::push(Capture $row): SyncResult`. Each provider (Mailchimp, ConvertKit, MailerLite, Brevo, Beehiiv) is one class implementing the interface. Adding a sixth provider is a one-file change.
- API keys stored encrypted at rest (sodium_crypto_secretbox with a key derived from `wp_salt('auth')`).
- Surface in Free admin: nothing extra. ESP-related UI lives entirely in Pro's tab, registered via `moonfarmer_reactions_lead_capture_admin_tabs`.

### 3.2 A/B Test Engine

**Job**: let admins compare two widget designs / icon styles / capture prompts and see which gets a higher capture rate.

**Design**:

- Variant assignment: deterministic hash of the user's dedup hash (same one Free already computes) mod the live variant count. Sticky per visitor, no cookie needed.
- Test config stored in `wp_moonfarmer_reactions_lead_capture_pro_ab_tests`: `id, name, variant_a (json), variant_b (json), traffic_split (int %), starts_at, ends_at, status`.
- Variant assignment recorded in `wp_moonfarmer_reactions_lead_capture_pro_ab_assignments` on first reaction; subsequent events for the same visitor read the assignment.
- Widget receives variant id via `moonfarmer_reactions_lead_capture_widget_data` filter; the widget loads variant-specific settings before paint (no flash of original variant).
- Stats: classical proportion test (two-sided, 95% CI). Admin sees per-variant capture rate, lift, p-value when reaching 1k assignments per variant.
- A/B UI lives in a Pro-registered admin tab. No Free changes.

### 3.3 Segmentation

**Job**: filter analytics ("show me sentiment only on posts in category X" / "only on posts published in the last 30 days" / "only from logged-in readers").

**Design**:

- Implemented as a server-side query layer wrapping Free's `AnalyticsRepository`. Pro doesn't touch Free's repo; it registers a Pro-only REST route (`/moonfarmer-reactions-lead-capture/v1/pro/analytics`) that runs the same queries with extra WHERE clauses (category id IN, author id IN, post_date BETWEEN, user_id IS NOT NULL).
- Segment definitions persist in `wp_moonfarmer_reactions_lead_capture_pro_segments` as JSON (post_type, taxonomy, author, post_date range, user_logged_in). Reusable; admin saves them.
- Admin SPA wiring: Pro registers a "Segments" sidebar in the Analytics tab via `moonfarmer_reactions_lead_capture_admin_analytics_panels`. Selecting a segment refetches via the Pro endpoint instead of Free's.
- Out of scope v1: traffic-source segmentation (would require a privacy-careful referrer capture mechanism).

### 3.4 Pre-Aggregated Rollups & Comparisons

**Job**: make multi-year queries snappy by serving from weekly/monthly rollup tables instead of `daily_agg`. Enables "vs previous period" comparisons cheaply.

**Design**:

- New tables `wp_moonfarmer_reactions_lead_capture_pro_weekly_agg` (week starts Monday UTC, configurable via filter) and `wp_moonfarmer_reactions_lead_capture_pro_monthly_agg`.
- Pro hooks `moonfarmer_reactions_lead_capture_after_aggregate` (action, Session 8). After Free's daily aggregator finishes, Pro upserts the affected week and month rows.
- Comparison metric cards (already documented in Free as injection target via `moonfarmer_reactions_lead_capture_admin_metric_cards`): Pro registers cards like "vs previous 30 days" and feeds them with two summed windows from the rollup tables.
- `moonfarmer_reactions_lead_capture_analytics_max_days` filter raised to ~30 years in Pro (effectively unlimited). The performance budget is preserved because Pro queries the rollup, not `daily_agg`.

### 3.5 White-Label

**Job**: agencies hide "Moonfarmer Reactions Lead Capture" attribution and apply their own brand.

**Design**:

- Three settings (Pro-only, in Pro tab):
  - **Brand name** (string, default "Moonfarmer Reactions Lead Capture"). Used in the admin page title, dashboard widget, README link.
  - **Custom CSS** (textarea). Injected into `<head>` on the admin page and the widget output, scoped to plugin selectors. Sanitised through `wp_kses_post` is too aggressive; we'll use a CSS sanitizer (likely sabberworm/php-css-parser) to strip `@import`, `expression()`, `url(...)` to non-image schemes, and unknown at-rules.
  - **Hide credit link** (bool). When on, removes the "Powered by …" text from the widget footer (if Free even renders one — TBD on the v1 widget; deferred).
- White-label settings are read by Pro and used to filter Free's `moonfarmer_reactions_lead_capture_widget_data` and the admin SPA's `moonfarmer_reactions_lead_capture_admin_data` payloads.
- No DB-level branding tokens; everything is presentation-layer.

### 3.6 Webhooks

**Job**: fire HTTPS POSTs to user-configured URLs on plugin events.

**Design**:

- Hook list (configurable per webhook): `reaction.created`, `capture.created`, `capture.synced` (ESP-sync result), `aggregation.complete`, `ab_test.winner_declared`.
- Each webhook row: `url`, `secret` (used for HMAC signature header `X-Moonfarmer Reactions Lead Capture-Signature`), `events` (json array), `active` (bool), `last_status`, `last_attempted_at`.
- Delivery via the same job queue as ESP sync. Retries: 5 attempts with exponential backoff.
- Out of scope v1: per-event filtering (e.g. only positive reactions); add later as filterable.

### 3.7 Async Report Generation

**Job**: generate huge analytics windows (e.g., "all reactions for the last 5 years across 50k posts") without blocking a REST worker.

**Design**:

- Pro REST route `POST /moonfarmer-reactions-lead-capture/v1/pro/reports` accepts a window spec; returns a report id immediately.
- A scheduled job runs the heavy query in chunks, writes to a temp table, then writes a CSV to a temporary uploads-dir path. Status surfaces via `GET /moonfarmer-reactions-lead-capture/v1/pro/reports/{id}` (queued / running / ready / failed).
- Admin UI polls for status; downloads the CSV when ready. Reports auto-delete after 7 days.
- Uses Action Scheduler when available; otherwise falls back to a self-rescheduling WP-Cron event with bounded execution time per tick.

---

## 4. Free/Pro Contract Boundary (Quick Reference)

These are the *only* things Pro is allowed to do. Anything else means Free needs a new hook.

- Register routes under `moonfarmer-reactions-lead-capture/v1/pro/*`.
- Register settings via `moonfarmer_reactions_lead_capture_settings` / `moonfarmer_reactions_lead_capture_settings_default`.
- Register admin UI surface via `moonfarmer_reactions_lead_capture_admin_tabs`, `moonfarmer_reactions_lead_capture_admin_metric_cards`, `moonfarmer_reactions_lead_capture_admin_analytics_panels`, and the JS `window.MoonfarmerReactionsLeadCaptureAdmin.register*Renderer` API.
- Modify analytics behaviour via `moonfarmer_reactions_lead_capture_analytics_window`, `moonfarmer_reactions_lead_capture_analytics_max_days`.
- Hook side effects via `moonfarmer_reactions_lead_capture_after_react`, `moonfarmer_reactions_lead_capture_after_capture`, `moonfarmer_reactions_lead_capture_after_aggregate`, `moonfarmer_reactions_lead_capture_settings_saved`.
- Modify capture handling via `moonfarmer_reactions_lead_capture_capture_sources`, `moonfarmer_reactions_lead_capture_capture_email`, `moonfarmer_reactions_lead_capture_consent_text_version`.
- Modify CSV via `moonfarmer_reactions_lead_capture_export_columns`.
- Modify widget bootstrap via `moonfarmer_reactions_lead_capture_widget_data`, `moonfarmer_reactions_lead_capture_widget_icons`, `moonfarmer_reactions_lead_capture_widget_container_attrs`, `moonfarmer_reactions_lead_capture_visibility_mode`.
- Read Free's repositories from the container by interface, never by concrete class.

---

## 5. Open Questions

- **License delivery tech: EDD-SL vs Freemius vs build our own.** Leaning custom because both EDD and Freemius bundle telemetry we don't want shipped in Pro. Custom is 2–3 days of plumbing. Decision deferred until Session 13.
- **Stripe vs Paddle vs Lemonsqueezy for billing.** Paddle handles EU VAT compliance natively; Stripe requires manual setup. Lemonsqueezy is cheapest but less mature. Leaning Paddle.
- **A/B engine bucketing across multiple posts.** Should a user bucketed into variant A on post 1 stay in variant A on post 5? Probably yes (consistency wins) but doubles assignment-table size. Need a benchmark.
- **White-label CSS sanitizer choice.** sabberworm/php-css-parser is the most-used PHP CSS parser but is 6+ years old. Alternative: regex allowlist for declarations. Decide when implementing.
- **Pro's Preact instance: bundled or shared with Free?** Sharing means Pro chunks load smaller but couples versions tightly. Leaning bundled to start, share later if size proves a real cost.
- **Should `moonfarmer_reactions_lead_capture_after_capture` be sync or async?** Currently sync; ESP push is fire-and-forget into the queue. If a user wants synchronous double-opt-in confirmation pages, the action fires synchronously and Pro must respond fast (< 200 ms). Probably fine; flag for benchmarking.
