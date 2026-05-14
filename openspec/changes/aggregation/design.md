## Context

Gap decisions from `docs/gap-questions-and-session-tasks.md` set the constraints:

- **Gap 2** — start with a small WP-Cron aggregation adapter; add Action Scheduler support behind a `QueueScheduler` interface later.
- **Gap 7** — analytics must define numerator, denominator, and time window per metric; daily aggregation uses the WordPress site timezone.
- **No raw-event dashboard queries** — Session 9 reads only the daily aggregate table.

The reactions table is the source. Each row carries `post_id`, `reaction_type`, `updated_at`. Aggregation buckets rows by site-local day and emits one row per `(date, post_id, reaction_type)` triple into `pulsepress_daily_agg`. The schema's UNIQUE KEY enforces upsert semantics; no read-then-write race.

## Goals / Non-Goals

**Goals:**

- A daily cron walks yesterday's reactions and writes aggregate rows.
- Aggregation is idempotent — re-running for the same date overwrites the existing aggregate rows with the current count.
- Day boundaries respect the site's `wp_timezone()` so the dashboard shows aligned per-day numbers across DST shifts.
- The aggregator is exposed as a service so future code (WP-CLI command, "rebuild stats" admin button) can call it directly with a target date.
- WP-Cron is the only scheduler in Free. The `QueueScheduler` interface keeps Action Scheduler swap-in a one-line change in the provider.

**Non-Goals:**

- No analytics dashboard rendering. Session 9.
- No "top posts" SELECT. Same.
- No sentiment-rate / capture-rate / top-N computation. The aggregate table is the building block; metric computation lives in Session 9.
- No Action Scheduler integration in this slice. The interface is enough.
- No multi-day backfill on activation. First-time installs simply start aggregating from tomorrow; the historical raw table is small on day 1.
- No WP-CLI command in this slice. The service exposes `runFor(date)` so the CLI hook is one tiny class in a future session.

## Decisions

### D1. `QueueScheduler` is an interface, not an abstract class

```php
interface QueueScheduler {
    public function schedule(string $hook, string $recurrence, ?int $firstRunTime = null): void;
    public function unschedule(string $hook): void;
    public function isScheduled(string $hook): bool;
}
```

Two implementations available conceptually: `WpCronScheduler` (ships in Free) and `ActionSchedulerScheduler` (future). The provider binds `QueueScheduler` to whichever is configured. Today only WP-Cron is wired.

**Alternative considered**: abstract class with template method. Rejected — interface keeps consumers loose; nothing in the contract needs default behaviour.

### D2. Aggregate "yesterday" by default; filter can override

The cron handler computes `$date = (new DateTimeImmutable('yesterday', wp_timezone()))->modify('00:00:00')` and passes that into `Aggregator::aggregate()`. The handler runs at 02:00 site-local; "yesterday" at that hour reliably points to the previous day.

The filter `apply_filters('pulsepress_aggregation_date', $date)` lets ops scripts rebuild a specific day:

```php
add_filter('pulsepress_aggregation_date', fn() => new DateTimeImmutable('2026-05-10', wp_timezone()));
```

After one cron tick the override fires for that day, then the filter is removed and normal "yesterday" resumes.

### D3. `wp_timezone()` is the source of truth

Site-local day boundaries come from `wp_timezone()`. The filter `pulsepress_aggregation_timezone` is exposed only for edge cases (multi-tenant networks); the default returns `wp_timezone()` directly.

The SQL boundary uses `DATE_FORMAT` rather than MySQL's `TIMEZONE` so the application controls the conversion:

```sql
WHERE updated_at >= '2026-05-13 00:00:00'  -- site-local converted to UTC by app layer
  AND updated_at <  '2026-05-14 00:00:00'
```

Wait — `updated_at` is stored in UTC by Session 2 (`new DateTimeImmutable('now', new DateTimeZone('UTC'))`). The aggregator converts site-local day bounds to UTC before binding:

```php
$startLocal = $localDate;                     // 2026-05-13 00:00:00 PST
$endLocal   = $localDate->modify('+1 day');   // 2026-05-14 00:00:00 PST
$startUtc   = $startLocal->setTimezone(new DateTimeZone('UTC'));
$endUtc     = $endLocal->setTimezone(new DateTimeZone('UTC'));
```

This is the correct way to bucket "PST day Tuesday" given UTC timestamps in the DB.

### D4. One grouped SELECT, one upsert per result row

```sql
SELECT post_id, reaction_type, COUNT(*) AS c
FROM <prefix>pulsepress_reactions
WHERE updated_at >= %s AND updated_at < %s
GROUP BY post_id, reaction_type
```

For each result row:

```sql
INSERT INTO <prefix>pulsepress_daily_agg
  (agg_date, post_id, reaction_type, count, updated_at)
VALUES (%s, %d, %s, %d, %s)
ON DUPLICATE KEY UPDATE
  count      = VALUES(count),
  updated_at = VALUES(updated_at)
```

The UNIQUE KEY `(agg_date, post_id, reaction_type)` enforces uniqueness; the upsert handles "second run for the same day". `agg_date` is a `DATE` column so the application formats site-local-day as `Y-m-d`.

**Alternative considered**: single multi-VALUES insert. Rejected — bind-arg complexity is high and the per-row upsert is fast enough at our scale (max ~6 reaction types × N posts active that day).

### D5. The cron handler computes the date inside the closure, not at registration

Registering with a static "next-run date" baked in would fail across days. The handler each fire:

1. Computes the target date as "yesterday in site-local time".
2. Filters via `pulsepress_aggregation_date`.
3. Calls `Aggregator::aggregate($date)`.

### D6. Activation schedules the cron at 02:00 site-local

```php
if (!$scheduler->isScheduled('pulsepress_aggregate_reactions')) {
    $firstRun = (new DateTimeImmutable('today 02:00', wp_timezone()))
        ->modify('+1 day')->getTimestamp();
    $scheduler->schedule('pulsepress_aggregate_reactions', 'daily', $firstRun);
}
```

If activation happens before 02:00 local, "+1 day" still gives the next sensible night-time slot.

### D7. AggregationResult is a readonly DTO with timing

```php
final readonly class AggregationResult {
    public function __construct(
        public DateTimeImmutable $date,
        public int $rowsWritten,
        public int $groupsProcessed,
        public int $tookMicros,
    ) {}
}
```

Returned from `aggregate()` so the after-action hook can include timing for telemetry. Pro will use this for "you have a top post" notifications.

### D8. Failure mode: log and continue

If the SELECT fails, the aggregator logs via `error_log('[PulsePress] aggregation failed: …')` and returns an AggregationResult with zero counts. The cron does not re-run automatically; the next daily fire picks up. Manual recovery via the `pulsepress_aggregation_date` filter + a fresh cron run.

### D9. No PII in aggregates

The SELECT explicitly excludes `user_hash`. The aggregate row has no traceable visitor data. Site admins viewing the dashboard see counts, never user-level rows.

## Risks / Trade-offs

- **Risk**: a DST transition makes a 25-hour or 23-hour day. → Mitigation: site-local bounds correctly handle this — the `+1 day` modify operates in `wp_timezone()` so the conversion to UTC straddles the DST shift naturally.
- **Risk**: WP-Cron is unreliable on low-traffic sites. → Mitigation: documented in Session 4 — same caveat applies. A future session can add a "Set up real cron" hint in the admin.
- **Risk**: site admin manually edits a row in `pulsepress_daily_agg`. → Mitigation: the next aggregation overwrites — counts are derived, not source-of-truth.
- **Risk**: the aggregator runs while a write is in flight; the count is off by one. → Mitigation: write skew of ±1 is acceptable for analytics. The day's bucket converges within seconds when the in-flight write commits.
- **Risk**: aggregation runs forever on a site with millions of reactions per day. → Mitigation: the GROUP BY uses the `idx_updated` index; on a 1M-row daily window the query is sub-second. If needed, a future session can chunk by post id ranges.
- **Trade-off**: no "incremental aggregation" within a day. We re-aggregate the full day each run. Acceptable — once per day, mostly idle.
- **Trade-off**: no per-tag / per-category aggregates. The schema doesn't carry taxonomy. Future Pro feature.

## Migration Plan

No data migration. Cron starts running the next 02:00 site-local after activation. For sites that have been collecting reactions for a while before this slice ships, a one-time admin-run "Rebuild last 30 days" is a future feature; on a fresh install yesterday's reactions just start aggregating.

Rollback: `git revert`. The aggregate table remains; the cron stops; reads still work.

## Open Questions

- **Q1**: should `aggregate()` also accept a date range, not just a single day? → **Decided no for v1.** A single date is the cron contract; backfill loops can call `aggregate()` per day.
- **Q2**: should the after-aggregate action receive the raw query result for debugging? → **Decided no.** Pro hooks see the AggregationResult DTO; raw rows are an implementation detail.
- **Q3**: should we add a `dropAggregates(int $beforeDays)` for retention? → **Deferred to a privacy/retention slice.** Sessions 1 + 6 reserve `retention_days` settings; pruning logic lands together.
