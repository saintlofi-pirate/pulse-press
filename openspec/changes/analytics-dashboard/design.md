## Context

By Session 8 we have `moonfarmer_reactions_lead_capture_daily_agg` filling daily and `moonfarmer_reactions_lead_capture_captures` filling on demand. Session 9 turns those into the only screen admins actually open: the analytics dashboard. The visible bar from `docs/moonfarmer-reactions-lead-capture-v1-plan.md` §Admin UI Design Direction is intentionally high — match FluentCRM / RankMath / Spectra / WP Migrate DB in feel — and the data bar from gap 7 is intentionally precise: every metric must define its numerator, denominator, and time window.

The constraint that drives the design: **no raw-event queries**. Aggregator owns reading `moonfarmer_reactions_lead_capture_reactions`. Analytics owns reading `moonfarmer_reactions_lead_capture_daily_agg` + `moonfarmer_reactions_lead_capture_captures`. A grep over the codebase enforces this — Session 8 already verified.

## Goals / Non-Goals

**Goals:**

- An admin opens Settings → Moonfarmer Reactions Lead Capture → Analytics and sees four cards, a top-posts table, a sentiment callout, and a daily series chart over the last 30 days.
- All metrics use site-local day boundaries (consistent with Session 8's aggregation).
- Sentiment rate = positive reactions / total reactions, where the positive set is the admin's saved choice.
- Capture rate = captures / positive reactions (approximation — every positive reaction shows the capture UI in v1, so denominator ≈ positive reactions; documented).
- Top posts ordered by total reactions desc, with positive + captures shown alongside so an admin can sort visually.
- Empty states for fresh installs: a calm "No reactions yet…" callout with no broken numbers.
- Bundle size stays under the existing admin budget. The chart is inline SVG, not a library.

**Non-Goals:**

- No 12-month window in Free. Free is 30 days; longer windows are explicitly Pro.
- No CSV export from the dashboard. Session 10 owns export.
- No client-side filtering / drill-down. Click a top post → opens the post in a new tab (link). Per-post breakdown is Pro.
- No real-time. The dashboard reflects the latest aggregation (≤ 24 hours stale).
- No charts beyond the daily bar series. No pie chart, no donut, no per-reaction-type stacked bars. Keep first cut tight.
- No metric-card delta indicators in Free. The number is the number. Pro can add "vs previous 30 days" deltas.

## Decisions

### D1. Repository reads only from `daily_agg` + `captures`

Three pure read methods (`dailySeries`, `topPosts`, `captureRollup`). Each takes UTC `DateTimeImmutable` bounds (already in UTC) and returns plain arrays. Why three methods, not one? Each query has a different shape and the test cases stay readable:

- `dailySeries`: `SELECT agg_date, reaction_type, SUM(count) … GROUP BY agg_date, reaction_type`.
- `topPosts`: `SELECT post_id, SUM(count) AS total, SUM(case when reaction_type IN (…positive…) then count else 0 end) AS positive … GROUP BY post_id ORDER BY total DESC LIMIT N`.
- `captureRollup`: `SELECT post_id, COUNT(*) FROM captures WHERE consent_at BETWEEN … GROUP BY post_id`.

The calculator joins them in PHP (post-by-post).

### D2. Calculator is pure and stateless

`MetricsCalculator::calculate(DateTimeImmutable $fromUtc, DateTimeImmutable $toUtc): MetricsEnvelope`. Takes UTC bounds, calls the repository, layers in positive-set logic, returns the immutable envelope. No side effects, easy to test.

### D3. Window math: site-local days, UTC SQL bounds

`from` and `to` in the request are `Y-m-d` strings in the site's `wp_timezone()`. The controller:

1. Parses them as site-local midnights.
2. Adds one day to `to` (we want INCLUSIVE end-of-day).
3. Converts both to UTC.
4. Hands to the calculator.

The clamp rule: if (to − from) > 30 days in Free, the controller silently clamps `from` to `to − 30 days` and includes a `clamped: true` flag in the response so the SPA can show "Window capped at 30 days (Free)" copy.

### D4. Window default: trailing 30 days

If `from` / `to` missing or malformed, default to:
- `to` = today (site-local) end of day
- `from` = 30 days ago (site-local) start of day

Filter `moonfarmer_reactions_lead_capture_analytics_window` runs after this default so site code can extend (Pro will do this).

### D5. Sentiment rate definition

`sentimentRate = positive / total` where:
- `positive` = sum of counts for reactions in `Settings::get()['positive_reactions']`.
- `total` = sum of counts for all reaction types in the window.

When `total = 0`, sentiment rate is `null` (not `0`) and the card shows a designed empty state ("No reactions yet").

### D6. Capture rate definition

`captureRate = captures / positive`. The denominator is positive reactions, not "positive reactions that showed the capture UI" — because in v1 every positive reaction with no captured flag shows the capture UI. The gap between "positive reactions" and "positive reactions that showed UI" is the visitors who already captured on that post (form skipped). For aggregate analytics this is a small bias; documented in the spec.

When `positive = 0`, capture rate is `null` (not `0` / not divide-by-zero).

### D7. Top posts: 10 entries, sorted by total reactions desc

We do not pre-compute "sentiment per post" — that's expensive at scale and noisy for posts with few reactions. The table shows raw numbers; admins eyeball the ratio. Pro will add per-post-sentiment columns + sortable header in its dashboard slice.

`post_titles` map is fetched in PHP via `get_posts(['post__in' => $ids, 'fields' => 'ids', 'post_status' => 'any'])` and then `get_the_title()` per id. Bounded by `LIMIT 10`, so 10 lookups max.

### D8. Inline SVG chart, no library

The daily series chart is hand-rolled SVG (≈40 lines of TSX) drawing one rect per day. Why not uPlot (per the original plan)?

- uPlot is ~12 KB gzipped — a real bundle hit for one chart.
- We have one 30-bar chart with no interactivity beyond hover labels.
- Inline SVG keeps the chart visually consistent with the rest of the SPA and reduces moving parts.

Hover/keyboard focus reveals the day's number + date via `<title>` (native SVG tooltip) + `aria-label` for screen readers. The chart itself is `<svg role="img" aria-labelledby="…">` with a descriptive text element. `prefers-reduced-motion` skips the entrance scale animation.

### D9. Analytics tab placement

Tab order: Display, **Analytics**, Reactions, Capture, Privacy. Analytics is second because it's what admins return to most often after the initial configuration session. Hash routing uses `#analytics`.

### D10. Empty / loading / error states

Every panel renders one of:

- **Loading** — skeleton matching the final layout's footprint (no CLS).
- **Empty** — descriptive copy + a calm icon (the existing widget icon set), no scary error tone.
- **Error** — `role="alert"` with the response's message and a Retry button.

The dashboard fetches once on tab activation, caches the result in component state (no auto-refresh), and refetches via the Retry button.

### D11. Permission gating

Endpoint requires `manage_options`. We do not expose this data to lower-cap roles. Editors who want a read-only view can be added in a future capability-mapping session.

## Risks / Trade-offs

- **Risk**: a site has so many posts that the top-posts SUM scan is slow. → Mitigation: the `idx_post_date` index on `moonfarmer_reactions_lead_capture_daily_agg` (Session 1) makes the query range-scan. 30-day window bounds the work. Performance budget: < 50 ms on a 100k-row daily_agg table.
- **Risk**: positive set changes — historical sentiment rates shift. → Mitigation: documented. The rate reflects the current positive set against historical raw counts. Admins changing the set are expected to know this.
- **Risk**: a post gets deleted but still appears in top posts because the daily_agg row references its id. → Mitigation: the `post_titles` lookup uses `post_status => 'any'`; deleted posts return no title and we render "(deleted post)" instead of an empty cell.
- **Risk**: chart renders awkwardly at narrow widths. → Mitigation: SVG viewBox + container-driven sizing keeps it responsive. Below 480 px the chart shrinks proportionally.
- **Trade-off**: no per-reaction-type breakdown in the chart. Could mislead admins who expect a stacked view. Acceptable for v1 — Pro adds stacked-by-type.
- **Trade-off**: capture-rate denominator approximation. The form is shown for every positive reaction by default; once captured, it stops showing — but the rate uses positive total, not "shown to" count. Small overcounting in the denominator means under-reported capture rate. We accept this and document it clearly in the card's helper text.

## Migration Plan

No data migration. The dashboard reads existing tables. Rollback is `git revert`.

## Open Questions

- **Q1**: should the chart show captures as a second series? → **Decided no for v1.** One series keeps the chart focused on engagement; capture trend is implicit via the metric card. Pro stacks both.
- **Q2**: should we lazy-load the analytics bundle (separate Vite entry)? → **Decided no for v1.** Admin already loads a single bundle on the settings page; one more tab isn't worth the chunking complexity.
- **Q3**: should we cache the analytics response in a transient to handle rapid tab toggling? → **Decided no.** The query is bounded to 30 days against indexed tables; a 30-second cache adds staleness with no real win.
