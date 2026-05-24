## 1. Domain types + repository

- [ ] 1.1 Create `app/Analytics/MetricsEnvelope.php` (`final readonly class`) carrying the response shape: `from` (DateTimeImmutable UTC), `to`, `clamped` (bool), `totalReactions` (int), `positiveReactions` (int), `totalCaptures` (int), `sentimentRate` (?float), `captureRate` (?float), `dailySeries` (array), `topPosts` (array), `postTitles` (array), `positiveSet` (string[]).
- [ ] 1.2 Create `app/Analytics/AnalyticsRepository.php` with `dailySeries(DateTimeImmutable $fromUtc, DateTimeImmutable $toUtc)`, `topPosts(...)` returning post id + total + positive + captures, and `captureRollup(...)`. Each uses prepared statements; ranges are inclusive on `from`, exclusive on `to`.
- [ ] 1.3 Add `topPosts` query joining daily_agg sums per post + a subquery counting captures (`LEFT JOIN (SELECT post_id, COUNT(*) c FROM captures WHERE consent_at >= ? AND consent_at < ? GROUP BY post_id) cap ON …`). `ORDER BY total DESC LIMIT $limit`.

## 2. Calculator + controller

- [ ] 2.1 Create `app/Analytics/MetricsCalculator.php` (`final class`). Constructor `(AnalyticsRepository $repo, SettingsRepository $settings)`. Method `calculate(DateTimeImmutable $fromUtc, DateTimeImmutable $toUtc, bool $clamped): MetricsEnvelope`. Reads positive set from settings. Computes sentiment / capture rates (null when denominator is zero). Resolves post titles via `get_the_title` per top-post id.
- [ ] 2.2 Create `app/Http/Controllers/AnalyticsController.php`. Method `summary(WP_REST_Request $request)`. Parses `from` / `to` `Y-m-d` site-local; defaults to trailing 30 days. Clamps to 30 days max (Free). Converts to UTC. Runs through `moonfarmer_reactions_lead_capture_analytics_window` filter. Calls calculator. Returns the envelope as a `WP_REST_Response` with the date fields serialised as `Y-m-d` site-local strings, dailySeries object keyed by site-local date.

## 3. REST registration

- [ ] 3.1 Extend `app/Providers/AnalyticsServiceProvider.php` to bind `AnalyticsRepository`, `MetricsCalculator`, and `AnalyticsController`. Register `GET /moonfarmer-reactions-lead-capture/v1/analytics/summary` on `rest_api_init` with `permission_callback => fn() => current_user_can('manage_options')`. Args validate `from`/`to` as ISO-8601 dates (`type: string`, regex if WP-REST supports it).

## 4. Admin SPA — types + tab + section

- [ ] 4.1 Update `resources/admin/types.ts`:
  - Add `'analytics'` to `TabId`.
  - Add `MetricsEnvelope` interface mirroring the PHP DTO.
  - Add `i18n.tabs.analytics` and `i18n.analytics.*` strings (cards labels, helper copy, empty/loading/error copy, sentiment-insight templates).
- [ ] 4.2 Update `resources/admin/App.tsx` to include `analytics` in `TAB_IDS` order and route to a new `AnalyticsSection`.
- [ ] 4.3 Create `resources/admin/sections/AnalyticsSection.tsx`. On mount fetch summary via a new `fetchAnalytics(restRoot, nonce)` in `resources/admin/api.ts`. Use `useState` for `{status: 'loading'|'ready'|'empty'|'error', data, error}`. Render MetricCard grid, sentiment callout, DailySeriesChart, TopPostsTable based on status.

## 5. Admin SPA — components

- [ ] 5.1 `resources/admin/components/MetricCard.tsx` — props `{ title, value, helper, formatted? }`. Renders `<section role="region" aria-labelledby={id}><h3 id={id}>{title}</h3><strong>{value}</strong><p>{helper}</p></section>`. Loading skeleton variant.
- [ ] 5.2 `resources/admin/components/TopPostsTable.tsx` — semantic `<table>` with caption + scope headers; empty state when `rows.length === 0`.
- [ ] 5.3 `resources/admin/components/DailySeriesChart.tsx` — inline SVG bar chart. `viewBox="0 0 400 120"`. Bars sized proportionally to max(day total). Each `<rect>` wrapped in `<g>` with `<title>` containing date + count. `prefers-reduced-motion` skips the scale-up.

## 6. Admin SPA — styles

- [ ] 6.1 Extend `resources/admin/styles/admin.css` with:
  - `.moonfarmer-reactions-lead-capture-metric-grid` (responsive grid 1–4 columns)
  - `.moonfarmer-reactions-lead-capture-metric-card` (matching the section-card aesthetic)
  - `.moonfarmer-reactions-lead-capture-top-posts` (compact table styling)
  - `.moonfarmer-reactions-lead-capture-chart` (SVG container) + bar fill colour + hover ring
  - `.moonfarmer-reactions-lead-capture-empty-state` / `.moonfarmer-reactions-lead-capture-loading-skeleton` variants

## 7. PHP localization payload

- [ ] 7.1 Update `AdminServiceProvider::i18n()` to include analytics strings (card labels, helper copy, empty + loading + error states, sentiment insight template with `{type}` and `{percent}` placeholders, table column headers, chart aria label).

## 8. Tests

- [ ] 8.1 `tests/Unit/AnalyticsRepositoryTest.php` — assert query shape (table names, GROUP BY columns, date placeholders, LIMIT). Use the WpdbStub with `resultsByQuery` to feed canned rows.
- [ ] 8.2 `tests/Unit/MetricsCalculatorTest.php` — happy path metrics, zero-total → null rate, capture rate null when positive=0, top posts ordering, positive-set comes from settings.
- [ ] 8.3 Update `tests/Unit/BootstrapTest.php` autoload assertions for `AnalyticsRepository`, `MetricsCalculator`, `MetricsEnvelope`, `AnalyticsController`.
- [ ] 8.4 Run `composer test`; confirm green.

## 9. Manual verification

- [ ] 9.1 Seed: insert 30 reactions across 3 posts with mixed reaction types and a few captures.
- [ ] 9.2 Run the aggregation cron once: `wp cron event run moonfarmer_reactions_lead_capture_aggregate_reactions`.
- [ ] 9.3 Hit `GET /wp-json/moonfarmer-reactions-lead-capture/v1/analytics/summary` as admin — confirm the response shape matches the spec.
- [ ] 9.4 Open the settings page → click Analytics tab → confirm the four cards, table, callout, chart render. Click a top post → opens the post.
- [ ] 9.5 Hover a chart bar → tooltip shows date + count. Keyboard-Tab into a bar → focus ring visible.
- [ ] 9.6 Toggle `prefers-reduced-motion` in browser dev tools → confirm no animation.
- [ ] 9.7 Empty install: delete all reactions and captures, hit endpoint → confirm calm empty state.

## 10. Docs + final

- [ ] 10.1 Update `docs/hooks-and-filters.md`: add `moonfarmer_reactions_lead_capture_analytics_window` filter, `moonfarmer_reactions_lead_capture_admin_analytics_panels` filter (Pro extension seam — kept for next session).
- [ ] 10.2 Run `openspec validate analytics-dashboard --strict --no-interactive` clean.
- [ ] 10.3 PHP lint clean.
- [ ] 10.4 Commit (no co-auth).
