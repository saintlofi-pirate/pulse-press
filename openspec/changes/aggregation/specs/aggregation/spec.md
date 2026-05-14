## ADDED Requirements

### Requirement: Aggregator processes a single site-local date

`PulsePress\Analytics\Aggregator::aggregate(\DateTimeImmutable $localDate): AggregationResult` SHALL accept a site-local date (time-component zeroed by the caller) and:

1. Convert the day bounds `[localDate, localDate + 1 day)` to UTC via `setTimezone(new DateTimeZone('UTC'))`.
2. Run one grouped SELECT against `<prefix>pulsepress_reactions` filtered by `updated_at` between those UTC bounds, grouping by `post_id` + `reaction_type`.
3. For each result row, upsert into `<prefix>pulsepress_daily_agg` via `INSERT ... ON DUPLICATE KEY UPDATE` keyed by `(agg_date, post_id, reaction_type)`.
4. Return an `AggregationResult` carrying the date, `rowsWritten`, `groupsProcessed`, and microsecond timing.

#### Scenario: First aggregation for a day with five reactions

- **WHEN** five reactions exist for post 42 with `updated_at` inside the local day's UTC window — three `love`, two `angry` — and `aggregate($date)` runs
- **THEN** the SELECT returns two rows (`love: 3`, `angry: 2`), two upserts fire, and `<prefix>pulsepress_daily_agg` contains exactly two rows for `(2026-05-13, 42, love)=3` and `(2026-05-13, 42, angry)=2`

#### Scenario: Re-running the same day is idempotent

- **WHEN** `aggregate($date)` has produced rows for 2026-05-13 and is invoked a second time without intervening reactions
- **THEN** the same rows exist with identical counts and `updated_at` reflects the most recent run; no duplicates

#### Scenario: Re-running with new reactions overrides the count

- **WHEN** between two runs, two additional `love` reactions land for post 42 on the same site-local day
- **THEN** the second run's upsert sets `count = 5` for `(2026-05-13, 42, love)` (3 from before + 2 new)

### Requirement: Day boundaries respect wp_timezone

The aggregator SHALL compute UTC SELECT bounds from a site-local day produced via `wp_timezone()`. The filter `pulsepress_aggregation_timezone` MAY override the timezone; default is `wp_timezone()`.

#### Scenario: Site in PST aggregating a Tuesday

- **WHEN** `wp_timezone()` returns `America/Los_Angeles` and the target local date is `2026-05-13`
- **THEN** the SELECT bounds are `2026-05-13 07:00:00` to `2026-05-14 07:00:00` in UTC (PST is UTC-8, but during DST the offset is UTC-7)

#### Scenario: Filter override

- **WHEN** a plugin registers `add_filter('pulsepress_aggregation_timezone', fn() => new DateTimeZone('Europe/London'))` and the aggregator runs
- **THEN** the SELECT bounds reflect London's offset for that date

### Requirement: Daily cron event is scheduled at activation, unscheduled at deactivation

The plugin SHALL register the WP-Cron event `pulsepress_aggregate_reactions` to run daily. Activation SHALL schedule the event for the next 02:00 site-local time when not already scheduled. Deactivation SHALL unschedule it. The cron handler SHALL compute the target date inside the closure (yesterday in site-local time), allow `pulsepress_aggregation_date` to filter it, then call `Aggregator::aggregate()`.

#### Scenario: First activation schedules the cron

- **WHEN** the plugin is activated and `wp_next_scheduled('pulsepress_aggregate_reactions') === false`
- **THEN** `wp_next_scheduled` returns a timestamp after the call

#### Scenario: Reactivation does not double-schedule

- **WHEN** the plugin is deactivated and reactivated
- **THEN** `wp_next_scheduled('pulsepress_aggregate_reactions')` returns exactly one timestamp

#### Scenario: Deactivation unschedules

- **WHEN** the plugin is deactivated
- **THEN** `wp_next_scheduled('pulsepress_aggregate_reactions')` returns `false`

#### Scenario: Cron handler computes "yesterday" lazily

- **WHEN** the cron fires
- **THEN** the handler computes `new DateTimeImmutable('yesterday', wp_timezone())->modify('00:00:00')` and passes that through `pulsepress_aggregation_date` filter before calling `aggregate()`

### Requirement: QueueScheduler abstracts the cron implementation

`PulsePress\Analytics\QueueScheduler` SHALL be an interface declaring `schedule(string $hook, string $recurrence, ?int $firstRunTime = null): void`, `unschedule(string $hook): void`, `isScheduled(string $hook): bool`. The default binding SHALL be `WpCronScheduler` which delegates to `wp_schedule_event`, `wp_unschedule_event`, and `wp_next_scheduled` respectively.

#### Scenario: WpCronScheduler schedules a daily event

- **WHEN** `WpCronScheduler::schedule('test_hook', 'daily', 1234567890)` is called
- **THEN** `wp_schedule_event(1234567890, 'daily', 'test_hook')` is invoked

#### Scenario: isScheduled reflects WP's state

- **WHEN** `wp_next_scheduled('test_hook')` returns `1234567890`
- **THEN** `WpCronScheduler::isScheduled('test_hook')` returns `true`

#### Scenario: Provider can swap the implementation

- **WHEN** a hypothetical Action Scheduler binding replaces `QueueScheduler` in the container
- **THEN** the cron-registering code in `AnalyticsServiceProvider` keeps working without modification (consumer talks to the interface)

### Requirement: Action hook fires after every successful aggregation

The aggregator SHALL fire `do_action('pulsepress_after_aggregate', AggregationResult $result)` after a successful run. The hook SHALL NOT fire when the aggregation fails (logged + zero-result returned without dispatching the hook).

#### Scenario: Successful aggregation fires the action

- **WHEN** `aggregate($date)` completes without error and `$result->groupsProcessed > 0`
- **THEN** the hook fires once with the result as its argument

#### Scenario: Empty-day still fires the action

- **WHEN** the day has no reactions (groupsProcessed = 0)
- **THEN** the hook fires once with rowsWritten = 0, groupsProcessed = 0 — Pro can rely on the once-a-day signal

#### Scenario: Failed SELECT does not fire the action

- **WHEN** the SELECT throws or returns false
- **THEN** the hook does not fire, the failure is logged via `error_log`, and the cron continues running on its schedule

### Requirement: No raw-event reads from outside Aggregator

The aggregator is the only code in the codebase that SELECTs from `<prefix>pulsepress_reactions` for analytics purposes. The dashboard (Session 9), CSV export (Session 10), and any future "top posts" feature SHALL read from `<prefix>pulsepress_daily_agg` exclusively. Reaction-count reads via `ReactionRepository::countsForPost` are read-by-post and stay scoped to the widget's needs — they are not analytics queries.

#### Scenario: Codebase grep audit

- **WHEN** running `grep -rE "FROM .+pulsepress_reactions" app/` (excluding `app/Reactions/`)
- **THEN** the only match is in `app/Analytics/Aggregator.php`
