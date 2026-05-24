## Why

Aggregation (Session 8) turned raw reactions into pre-rolled daily rows; nothing reads them yet. Session 9 closes the v1 loop: admins open the Moonfarmer Reactions Lead Capture settings page, click **Analytics**, and see a clean dashboard of what's actually happening on their site. Until this lands, the value proposition "reactions that grow an email list, with analytics that show what content is working" stays half-shipped.

Free remains generous: the dashboard ships with a 30-day window, four metric cards, a top-10 posts table, a sentiment insight callout, and a daily-counts series chart — out of the box, no extension required. Privacy stays first-class: only manage_options users read the endpoint; the SQL touches only `moonfarmer_reactions_lead_capture_daily_agg` and `moonfarmer_reactions_lead_capture_captures`, never the raw reactions table. Accessibility: WCAG AA on every metric card, `role="region"` landmarks, semantic `<table>` markup, `<th scope>` on every column, screen-reader-only captions. Hooks-first: metrics list and ordering are filterable, and Pro can layer extra panels via `moonfarmer_reactions_lead_capture_admin_analytics_panels`.

## What Changes

- Add `Moonfarmer\ReactionsLeadCapture\Analytics\AnalyticsRepository` with three pure read methods backed by `moonfarmer_reactions_lead_capture_daily_agg` and `moonfarmer_reactions_lead_capture_captures`:
  - `dailySeries(\DateTimeImmutable $from, \DateTimeImmutable $to): array<string, array<string,int>>` — each day's per-reaction-type counts (keyed by `Y-m-d`).
  - `topPosts(\DateTimeImmutable $from, \DateTimeImmutable $to, int $limit = 10): array<int, array{post_id:int, total:int, positive:int, captures:int}>` — sorted by total reactions desc.
  - `captureRollup(\DateTimeImmutable $from, \DateTimeImmutable $to): array<int, int>` — per-post capture counts.
  All site-local-day math happens in the controller; the repository takes UTC `DateTimeImmutable` bounds.
- Add `Moonfarmer\ReactionsLeadCapture\Analytics\MetricsCalculator` (`final readonly class`) that turns the repository's raw data into a normalised metrics envelope: `{totalReactions, totalCaptures, positiveReactions, sentimentRate, captureRate, dailySeries, topPosts, positiveSet, sinceUtc, untilUtc}`. Reads `positive_reactions` from the SettingsRepository so the rates respect the admin's positive set.
- Add `Moonfarmer\ReactionsLeadCapture\Http\Controllers\AnalyticsController` with one method `summary(\WP_REST_Request $request): WP_REST_Response|WP_Error`. Accepts `from` and `to` as `Y-m-d` site-local dates (filterable via `moonfarmer_reactions_lead_capture_analytics_window`, defaults to the trailing 30 days). Validates the range (max 30 days in Free; clamps if larger). Returns JSON shaped as the metrics envelope plus `post_titles` map (slug → title) so the SPA renders human-readable rows.
- Register `GET /moonfarmer-reactions-lead-capture/v1/analytics/summary` in a new `AnalyticsRestServiceProvider` (or extend `AnalyticsServiceProvider`'s `boot()` with the route, keeping providers minimal). `permission_callback => current_user_can('manage_options')`.
- Add the **Analytics** tab to the admin SPA between **Display** and **Reactions** so admins see numbers first:
  - Four metric cards (Total reactions, Total captures, Sentiment %, Capture %) — semantic `<section role="region">` with `<h3>` titles + large numbers + small delta line + sr-only descriptions.
  - Top posts table — semantic `<table>` with `<caption>` and `<th scope>` headers; first column is the post title (linking to the post if `view_post` cap permits), then total reactions / positive reactions / captures.
  - Sentiment insight callout — a single line of computed copy ("Your readers are mostly Loving this content (78%)") with a colour accent that respects `prefers-color-scheme`.
  - Daily counts chart — inline SVG bar chart over the 30-day window. Pure SVG, no charting library, ≈400 px wide × 120 px tall. Each bar is a button with `aria-label` reading the date + total count.
- Add filters `moonfarmer_reactions_lead_capture_analytics_window` (`(['from'=>..,'to'=>..]): array`) and `moonfarmer_reactions_lead_capture_admin_analytics_panels` (`array $panels`) so Pro can register extra sections without touching Free.
- Add empty state to every panel: "No reactions yet — visit a post and react to see numbers here."
- **BREAKING**: none.

## Capabilities

### New Capabilities

- `analytics-api`: defines the REST contract — endpoint, query parameters, response shape (metrics envelope), permission gating, the window clamp rule, and the supported metric formulas.

### Modified Capabilities

- `admin-spa`: gains the Analytics tab as the second top-level tab. Tab order: Display, Analytics, Reactions, Capture, Privacy. Hash routing extends to `#analytics`.

## Impact

- **New files**: `app/Analytics/AnalyticsRepository.php`, `app/Analytics/MetricsCalculator.php`, `app/Analytics/MetricsEnvelope.php` (DTO), `app/Http/Controllers/AnalyticsController.php`, `tests/Unit/MetricsCalculatorTest.php`, `tests/Unit/AnalyticsRepositoryTest.php`, `resources/admin/sections/AnalyticsSection.tsx`, `resources/admin/components/MetricCard.tsx`, `resources/admin/components/TopPostsTable.tsx`, `resources/admin/components/DailySeriesChart.tsx`.
- **Modified files**: `app/Providers/AnalyticsServiceProvider.php` (registers controller + route), `app/bootstrap.php` (no change — provider already registered), `resources/admin/App.tsx` (adds the Analytics tab), `resources/admin/types.ts` (adds `MetricsEnvelope` shape + tab id `'analytics'`), `resources/admin/components/SectionNav.tsx` (no change — tabs already dynamic), `resources/admin/styles/admin.css` (analytics card / table / chart styles), `app/Providers/AdminServiceProvider.php` (extra i18n strings), `docs/hooks-and-filters.md` (adds the two new filters).
- **REST API**: one new endpoint, `GET /wp-json/moonfarmer-reactions-lead-capture/v1/analytics/summary?from=YYYY-MM-DD&to=YYYY-MM-DD`. Requires `manage_options`.
- **Database changes**: none — analytics reads from `moonfarmer_reactions_lead_capture_daily_agg` and `moonfarmer_reactions_lead_capture_captures` only. No raw-event SELECTs.
- **Privacy**: dashboard never exposes capture rows, never lists email addresses, never includes IP/UA hashes.
- **Performance**: one indexed-range query per cron-rolled-up day, plus one indexed-range query on captures. Both bounded to 30 days in Free. The response payload is ≤ 8 KB JSON for a 30-day window with 10 top posts. SPA does the chart math in JS.
- **Accessibility**: WCAG 2.1 AA — semantic `<table>` with caption + scoped headers, role="region" on each card section, sr-only descriptions for the SVG chart, focus-visible rings on every interactive element, `prefers-reduced-motion` honoured for chart animations.
- **Free/Pro boundary**: untouched. Pro can extend by hooking `moonfarmer_reactions_lead_capture_admin_analytics_panels` and `moonfarmer_reactions_lead_capture_analytics_window` (e.g. 12-month window).
