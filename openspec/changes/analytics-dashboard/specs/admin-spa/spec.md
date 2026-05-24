## ADDED Requirements

### Requirement: Analytics tab renders metric cards, top posts, and daily chart

The admin SPA SHALL register an **Analytics** tab as the second top-level tab (after Display, before Reactions) addressable via `#analytics`. The tab SHALL fetch `GET /moonfarmer-reactions-lead-capture/v1/analytics/summary` on activation and render:

- Four metric cards in a responsive grid: Total reactions, Total captures, Sentiment %, Capture %. Each card is a `<section role="region" aria-labelledby="…">` with an `<h3>` title, a large number, and a one-line helper.
- A top-posts `<table>` with `<caption class="screen-reader-only">`, four columns (`<th scope="col">`: Post / Total / Positive / Captures). Each row uses `<th scope="row">` for the post title.
- A sentiment insight callout (`<p role="status">` containing computed copy like "Your readers are mostly Loving this content (78%)" — the dominant reaction + sentiment rate).
- A daily series chart rendered as inline SVG (`<svg role="img" aria-labelledby="…">`) with one rect per day across the window. Each rect has a `<title>` reading the date + total count.

#### Scenario: Tab present in the nav

- **WHEN** an admin loads the settings page
- **THEN** the tab list contains buttons for Display, Analytics, Reactions, Capture, Privacy in that order

#### Scenario: Tab fetches on activation

- **WHEN** an admin clicks the Analytics tab for the first time
- **THEN** a network request to `/wp-json/moonfarmer-reactions-lead-capture/v1/analytics/summary` fires with no `from`/`to` parameters (using the 30-day default)

#### Scenario: Cards render with the right shape

- **WHEN** the response contains `totalReactions: 42, totalCaptures: 7, sentimentRate: 0.64, captureRate: 0.26`
- **THEN** four `<section role="region">` cards render with those values formatted as `42`, `7`, `64%`, `26%`

#### Scenario: Top posts table is semantic

- **WHEN** the response contains three `topPosts` entries
- **THEN** the rendered table is a real `<table>` with one `<caption>`, four `<th scope="col">` headers, and three `<tr>` rows each with one `<th scope="row">` cell

#### Scenario: Empty state renders calm copy

- **WHEN** the response indicates zero reactions and zero captures
- **THEN** the page renders a single calm "No reactions yet — visit a post and react to see numbers here." message and the chart pane shows nothing scary

#### Scenario: Loading state has no CLS

- **WHEN** the data is still in flight
- **THEN** skeleton placeholders match the final layout's footprint so the page does not jump when data arrives

#### Scenario: Error state surfaces a Retry

- **WHEN** the fetch returns 500
- **THEN** the tab renders `<p role="alert">` with the server's message and a `<button>Retry</button>` that re-fetches on click

### Requirement: Chart is keyboard accessible and reduced-motion aware

The SVG chart SHALL respect `prefers-reduced-motion: reduce` by skipping entrance scaling. Each bar SHALL have an associated `<title>` and `aria-label` so screen-reader users can read per-day counts. The chart container SHALL describe itself via `aria-labelledby` referencing an offscreen text caption.

#### Scenario: Reduced-motion user

- **WHEN** the admin's browser has `prefers-reduced-motion: reduce`
- **THEN** the chart bars appear at full height immediately with no scaling animation

#### Scenario: Screen reader user

- **WHEN** a screen reader focuses the chart container
- **THEN** the linked `aria-labelledby` text reads the chart's purpose and the per-day totals are accessible via the bars' `<title>` elements

## MODIFIED Requirements

### Requirement: Hash-routed tabs

The SPA SHALL implement five tabs — `display`, `analytics`, `reactions`, `capture`, `privacy` — each addressable via `window.location.hash`. An empty or unrecognised hash SHALL default to `display`. The tablist SHALL follow the WAI-ARIA Tabs pattern.

#### Scenario: Default tab on first visit

- **WHEN** the admin loads the page with no hash
- **THEN** the `display` tab renders as active

#### Scenario: Deep-linking to analytics

- **WHEN** the admin loads `?page=moonfarmer-reactions-lead-capture#analytics`
- **THEN** the Analytics tab is active and its panel begins fetching summary data
