## ADDED Requirements

### Requirement: GET /moonfarmer-reactions-lead-capture/v1/analytics/summary returns the metrics envelope

The plugin SHALL register `GET /wp-json/moonfarmer-reactions-lead-capture/v1/analytics/summary` returning a JSON object with the keys `from`, `to`, `clamped`, `totalReactions`, `positiveReactions`, `totalCaptures`, `sentimentRate`, `captureRate`, `dailySeries`, `topPosts`, `postTitles`, `positiveSet`. The endpoint SHALL require `current_user_can('manage_options')`. Query parameters `from` and `to` SHALL accept `Y-m-d` site-local dates; when missing or malformed, the endpoint SHALL default to the trailing 30 days. The endpoint SHALL clamp the window to 30 days maximum in Free.

#### Scenario: Default window

- **WHEN** an admin sends `GET /wp-json/moonfarmer-reactions-lead-capture/v1/analytics/summary` with no query parameters
- **THEN** the response is `200` with `from` 30 days before today (site-local) and `to` today (site-local), and `clamped: false`

#### Scenario: Window larger than 30 days is clamped

- **WHEN** the admin sends `from=2024-01-01&to=2026-05-14`
- **THEN** `from` is clamped to `to − 30 days` and the response `clamped` is `true`

#### Scenario: Non-admin is rejected

- **WHEN** an unauthenticated visitor or a non-`manage_options` user requests the endpoint
- **THEN** the response is `403`

#### Scenario: Empty install renders zero metrics with nullable rates

- **WHEN** a fresh install with no reactions or captures requests the endpoint
- **THEN** `totalReactions` is `0`, `totalCaptures` is `0`, `sentimentRate` is `null`, `captureRate` is `null`, `dailySeries` is an empty object, `topPosts` is an empty array, and the response status is `200`

### Requirement: dailySeries shape

`dailySeries` SHALL be an object keyed by site-local date string (`Y-m-d`) where each value is an object keyed by reaction type with integer counts.

#### Scenario: Series respects the window

- **WHEN** the window is 2026-04-14..2026-05-14
- **THEN** `dailySeries` contains zero or more keys with dates in that inclusive range and never outside it

### Requirement: topPosts shape

`topPosts` SHALL be an array of at most 10 objects, each `{post_id: int, total: int, positive: int, captures: int}`, sorted by `total` descending. `postTitles` SHALL be a parallel object keyed by `post_id` mapping to the post title (or the literal string "(deleted post)" when the post no longer exists).

#### Scenario: Sort by total descending

- **WHEN** posts 7, 13, and 42 have totals 5, 12, and 3 respectively in the window
- **THEN** `topPosts[0].post_id === 13`, `topPosts[1].post_id === 7`, `topPosts[2].post_id === 42`

#### Scenario: Deleted post resolves to "(deleted post)"

- **WHEN** a `topPosts` entry references a post id that has been deleted
- **THEN** `postTitles[id]` equals `"(deleted post)"`

### Requirement: Sentiment rate formula

`sentimentRate` SHALL equal `positiveReactions / totalReactions` rounded to four decimal places. When `totalReactions` is `0`, `sentimentRate` SHALL be `null` (not zero). `positiveReactions` SHALL be the sum of counts for reaction types in `settings.positive_reactions`.

#### Scenario: Six positives out of ten total

- **WHEN** the window has 6 positive reactions out of 10 total
- **THEN** `sentimentRate` equals `0.6`

#### Scenario: Zero total → null rate

- **WHEN** the window has 0 reactions
- **THEN** `sentimentRate` is `null`

#### Scenario: Filter overrides the positive set

- **WHEN** `moonfarmer_reactions_lead_capture_settings` filter changes `positive_reactions` mid-request
- **THEN** the metrics use the filtered set, not the stored one

### Requirement: Capture rate formula

`captureRate` SHALL equal `totalCaptures / positiveReactions` rounded to four decimal places. When `positiveReactions` is `0`, `captureRate` SHALL be `null`. The denominator uses positive reactions (not "positive reactions that showed the capture UI") — this is documented as an upper-bound denominator.

#### Scenario: Three captures from six positives

- **WHEN** the window has 6 positive reactions and 3 captures
- **THEN** `captureRate` equals `0.5`

#### Scenario: Captures but zero positive reactions

- **WHEN** the window has 0 positive reactions and 0 captures
- **THEN** `captureRate` is `null`

### Requirement: Reads only from daily_agg + captures

The analytics path SHALL NOT issue any SELECT against `moonfarmer_reactions_lead_capture_reactions`. The repository SHALL only touch `moonfarmer_reactions_lead_capture_daily_agg` and `moonfarmer_reactions_lead_capture_captures`.

#### Scenario: Codebase grep audit

- **WHEN** running `grep -rE "FROM .+moonfarmer_reactions_lead_capture_reactions" app/Analytics/` and `grep -rE "FROM .+moonfarmer_reactions_lead_capture_reactions" app/Http/Controllers/AnalyticsController.php`
- **THEN** the search returns no matches

### Requirement: moonfarmer_reactions_lead_capture_analytics_window filter is the final say on window bounds

The controller SHALL apply `apply_filters('moonfarmer_reactions_lead_capture_analytics_window', ['from' => $fromUtc, 'to' => $toUtc, 'clamped' => $clamped], $request)` after computing defaults and clamping. Filters MAY override bounds (Pro will extend Free's 30-day clamp to 12 months this way).

#### Scenario: Pro extends to 12 months

- **WHEN** a Pro plugin registers `add_filter('moonfarmer_reactions_lead_capture_analytics_window', fn($w) => ['from' => $w['from']->modify('-12 months'), 'to' => $w['to'], 'clamped' => false], 10, 2)`
- **THEN** the request honours a 12-month window and reports `clamped: false`
