## 1. DTO + interface

- [ ] 1.1 Create `app/Analytics/AggregationResult.php` as a `final readonly` DTO with `\DateTimeImmutable $date`, `int $rowsWritten`, `int $groupsProcessed`, `int $tookMicros`.
- [ ] 1.2 Create `app/Analytics/QueueScheduler.php` as an `interface` declaring `schedule`, `unschedule`, `isScheduled`.
- [ ] 1.3 Create `app/Analytics/WpCronScheduler.php` implementing the interface via `wp_schedule_event`, `wp_unschedule_event`, `wp_next_scheduled`.

## 2. Aggregator

- [ ] 2.1 Create `app/Analytics/Aggregator.php` (`final class`). Constructor `(\wpdb $wpdb)`. Method `aggregate(\DateTimeImmutable $localDate): AggregationResult`.
- [ ] 2.2 Body: convert `$localDate` and `$localDate + 1 day` to UTC. Run a prepared `SELECT post_id, reaction_type, COUNT(*) AS c FROM <prefix>pulsepress_reactions WHERE updated_at >= %s AND updated_at < %s GROUP BY post_id, reaction_type`. Time the call.
- [ ] 2.3 For each row, run `INSERT INTO <prefix>pulsepress_daily_agg (agg_date, post_id, reaction_type, count, updated_at) VALUES (%s, %d, %s, %d, %s) ON DUPLICATE KEY UPDATE count = VALUES(count), updated_at = VALUES(updated_at)`.
- [ ] 2.4 Build and return `AggregationResult`. On SELECT failure: `error_log('[PulsePress] aggregation failed: ' . $wpdb->last_error)` and return a zero-result without firing the hook.
- [ ] 2.5 Fire `do_action('pulsepress_after_aggregate', $result)` only when the SELECT succeeded.

## 3. Service provider + cron wiring

- [ ] 3.1 Create `app/Providers/AnalyticsServiceProvider.php` (extends `ServiceProvider`). In `register()` bind `Aggregator` and `QueueScheduler` as singletons (QueueScheduler → WpCronScheduler).
- [ ] 3.2 In `boot()` add a `pulsepress_aggregate_reactions` action handler that computes `(new DateTimeImmutable('yesterday', wp_timezone()))->modify('00:00:00')`, applies `apply_filters('pulsepress_aggregation_date', $date)`, and calls `$this->app->get(Aggregator::class)->aggregate($date)`.
- [ ] 3.3 In `boot()` also seed the `pulsepress_aggregation_timezone` filter handler at priority 5 returning `wp_timezone()` (consumers can override at default priority 10+).
- [ ] 3.4 Register `AnalyticsServiceProvider::class` in `app/bootstrap.php` after `CaptureServiceProvider`.

## 4. Activation + deactivation cron

- [ ] 4.1 Update `pulsepress.php` activation closure to also schedule `pulsepress_aggregate_reactions` daily at the next 02:00 site-local (guarded by `wp_next_scheduled === false`).
- [ ] 4.2 Update `pulsepress.php` deactivation closure to unschedule it.

## 5. Tests

- [ ] 5.1 `tests/Unit/AggregatorTest.php`: aggregate() builds the expected SELECT with UTC bounds derived from a site-local date + timezone; upsert SQL has VALUES placeholders and ON DUPLICATE KEY UPDATE; returns AggregationResult with the right counts; fires `pulsepress_after_aggregate` on success; suppresses the hook on failure.
- [ ] 5.2 `tests/Unit/WpCronSchedulerTest.php`: schedule/unschedule/isScheduled delegate correctly to the WP cron functions. Use the CronSpy stub from Session 4 (if present) or extend it.
- [ ] 5.3 Update `tests/Unit/BootstrapTest.php` autoload assertions for `Aggregator`, `AggregationResult`, `QueueScheduler`, `WpCronScheduler`, `AnalyticsServiceProvider`.
- [ ] 5.4 Run `composer test`; confirm green.

## 6. Manual verification

- [ ] 6.1 Reactivate plugin. `wp cron event list | grep pulsepress_aggregate_reactions` → scheduled daily.
- [ ] 6.2 Seed five reactions across two days via `wp db query` (manipulate `updated_at` to land in yesterday's site-local day).
- [ ] 6.3 `wp cron event run pulsepress_aggregate_reactions` → confirm rows appear in `wp_pulsepress_daily_agg` for the target day with correct counts.
- [ ] 6.4 Re-run `wp cron event run pulsepress_aggregate_reactions` → confirm no duplicate rows; counts unchanged.
- [ ] 6.5 Add two more reactions in yesterday's window; re-run; confirm the count grows by exactly 2.

## 7. Docs + final

- [ ] 7.1 Update `docs/hooks-and-filters.md`: add `pulsepress_aggregation_date` filter, `pulsepress_aggregation_timezone` filter, `pulsepress_after_aggregate` action.
- [ ] 7.2 Run `openspec validate aggregation --strict --no-interactive` clean.
- [ ] 7.3 PHP lint clean.
- [ ] 7.4 Commit (no co-auth).
