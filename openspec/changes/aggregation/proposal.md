## Why

Session 9 (admin dashboard) needs to render top-posts and sentiment numbers without scanning the entire `moonfarmer_reactions_lead_capture_reactions` table — that's the explicit "no raw-event dashboard queries" commitment from gap 7. Session 8 fills the gap with a small daily aggregator that walks reactions for a single date and writes summarised rows to `moonfarmer_reactions_lead_capture_daily_agg`. Until this lands, the dashboard cannot perform at scale, and any "top posts last 30 days" query would do a full table scan on a busy site.

Free remains generous: aggregation runs out of the box without admin action; the daily window is the site's WordPress timezone (gap 7 D7). Privacy stays first-class: no PII enters the aggregation pipeline — only post id, reaction type, and counts. Accessibility lives downstream — Session 9 renders these aggregates into the dashboard. Hooks and filters first: every decision point (which date to aggregate, the timezone, what counts as a day boundary) is filterable; both before- and after-aggregate actions fire so Pro can attach ESP sync of "top post" notifications.

## What Changes

- Add `Moonfarmer\ReactionsLeadCapture\Analytics\Aggregator` with `aggregate(\DateTimeImmutable $localDate): AggregationResult`. Computes site-local day bounds via `wp_timezone()`, runs one `SELECT post_id, reaction_type, COUNT(*) FROM <prefix>moonfarmer_reactions_lead_capture_reactions WHERE updated_at >= ? AND updated_at < ? GROUP BY post_id, reaction_type` query, then upserts each row into `<prefix>moonfarmer_reactions_lead_capture_daily_agg` with `INSERT ... ON DUPLICATE KEY UPDATE count = VALUES(count), updated_at = VALUES(updated_at)`. Returns a small DTO with `date`, `rowsWritten`, `groupsProcessed`.
- Add `Moonfarmer\ReactionsLeadCapture\Analytics\AggregationResult` readonly DTO carrying `date`, `rowsWritten`, `groupsProcessed`, `tookMicros`.
- Add `Moonfarmer\ReactionsLeadCapture\Analytics\QueueScheduler` interface with `schedule(string $hook, string $recurrence, ?int $firstRunTime = null): void`, `unschedule(string $hook): void`, `isScheduled(string $hook): bool`. Lets future sessions swap WP-Cron for Action Scheduler without touching consumers.
- Add `Moonfarmer\ReactionsLeadCapture\Analytics\WpCronScheduler` implementing the interface — wraps `wp_schedule_event` / `wp_unschedule_event` / `wp_next_scheduled`.
- Add `Moonfarmer\ReactionsLeadCapture\Providers\AnalyticsServiceProvider` registered after `CaptureServiceProvider` in `app/bootstrap.php`. In `register()` binds `Aggregator`, `QueueScheduler`. In `boot()` hooks the daily cron event `moonfarmer_reactions_lead_capture_aggregate_reactions` whose handler computes yesterday's site-local date and calls `Aggregator::aggregate()`. The provider also exposes a `runFor(\DateTimeImmutable $date): AggregationResult` helper so WP-CLI rebuilds and manual triggers stay one call away.
- Update `moonfarmer-reactions-lead-capture.php` activation to also schedule `moonfarmer_reactions_lead_capture_aggregate_reactions` daily (similar to the fraud-purge cron from Session 4). Deactivation unschedules it. The cron offset is 02:00 site-local on the first run (a quiet hour for most sites).
- Add filters `moonfarmer_reactions_lead_capture_aggregation_date` (filters the target date before aggregation), `moonfarmer_reactions_lead_capture_aggregation_timezone` (rarely needed — defaults to `wp_timezone()`), and `moonfarmer_reactions_lead_capture_after_aggregate` action (with the AggregationResult).
- The repository never overwrites a manual edit: the upsert always writes the current count for that `(date, post_id, reaction_type)` triple. Re-running the aggregation for the same date produces identical results.
- **BREAKING**: none.

## Capabilities

### New Capabilities

- `aggregation`: defines the contract — input table (`moonfarmer_reactions_lead_capture_reactions`), output table (`moonfarmer_reactions_lead_capture_daily_agg`), date semantics (site-local day via `wp_timezone()`), idempotency rule (upsert by composite key), the daily WP-Cron event name and offset, the QueueScheduler abstraction the cron sits behind, before/after action hooks, the AggregationResult shape.

### Modified Capabilities

None — `database-schema` and `reaction-api` are read by this slice but unchanged.

## Impact

- **New files**: `app/Analytics/Aggregator.php`, `app/Analytics/AggregationResult.php`, `app/Analytics/QueueScheduler.php`, `app/Analytics/WpCronScheduler.php`, `app/Providers/AnalyticsServiceProvider.php`, `tests/Unit/AggregatorTest.php`, `tests/Unit/WpCronSchedulerTest.php`.
- **Modified files**: `moonfarmer-reactions-lead-capture.php` (activation schedules aggregation cron, deactivation unschedules), `app/bootstrap.php` (registers `AnalyticsServiceProvider`), `docs/hooks-and-filters.md` (adds `moonfarmer_reactions_lead_capture_aggregation_date`, `moonfarmer_reactions_lead_capture_aggregation_timezone`, `moonfarmer_reactions_lead_capture_after_aggregate`).
- **REST API**: unchanged. Session 9's dashboard endpoints will be a separate slice.
- **WP-Cron events introduced**: `moonfarmer_reactions_lead_capture_aggregate_reactions` (daily). Idempotent — re-running for the same date produces identical aggregate rows.
- **Filters introduced**: `moonfarmer_reactions_lead_capture_aggregation_date`, `moonfarmer_reactions_lead_capture_aggregation_timezone`.
- **Actions introduced**: `moonfarmer_reactions_lead_capture_after_aggregate`.
- **Database changes**: none. Reads `moonfarmer_reactions_lead_capture_reactions`, writes `moonfarmer_reactions_lead_capture_daily_agg` (both established in Session 1).
- **Privacy**: aggregation reads no PII. The reactions table's `user_hash` is not in the SELECT.
- **Performance**: one grouped SELECT per cron run (typical 5–50 ms even on big sites because of the `(post_id, reaction_type)` and `(updated_at)` indexes from Session 1). One UPSERT per (post, reaction-type) group. Daily cron offset 02:00 site-local — quiet hour.
- **Free/Pro boundary**: untouched. `moonfarmer_reactions_lead_capture_after_aggregate` lets Pro attach ESP "top-post" notifications. Aggregator is a service Pro can extend or replace via the container.
