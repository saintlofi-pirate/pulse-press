# PulsePress v1 Product Plan

## Concept

PulsePress is a WordPress plugin for reactions that grow an email list, with analytics that show what content is working. The free tier should be a complete, generous product for WordPress.org distribution. Pro should unlock provider sync, A/B testing, deeper insights, and advanced customization without weakening the free experience.

## Positioning

- Free: complete reactions, inline email capture with CSV export, 30-day analytics, top posts, sentiment insights, Gutenberg block, shortcode, privacy-safe defaults, and two polished widget designs.
- Pro: ESP integrations, 12-month analytics, A/B tests, per-category or per-tag reaction sets, webhooks, white-labeling, and priority support.
- Price target: `$49/year`.
- Design bar: polished, calm, generous spacing, strong hierarchy, one primary accent, sentence case, and a visual feel inspired by Adham Dannaway-style product polish.

## Free vs Pro Scope

| Capability | Free | Pro |
| --- | --- | --- |
| Six reaction types with custom labels | Yes | Yes |
| Show or hide counts | Yes | Yes |
| Threshold count visibility | Yes | Yes |
| Widget designs | 2 designs | 4 designs |
| Email capture after positive reactions | CSV export | ESP direct sync |
| Built-in analytics | 30 days | 12 months |
| Top posts by reaction | Yes | Yes |
| Sentiment insights | Yes | Yes |
| Gutenberg block and shortcode | Yes | Yes |
| Light and dark mode | Yes | Yes |
| GDPR-safe defaults | Yes | Yes |
| Multi-language labels | Yes | Yes |
| ESP integrations | No | Yes |
| A/B widget testing | No | Yes |
| Custom reaction sets by category/tag | No | Yes |
| Webhooks and Zapier | No | Yes |
| White-labeling | No | Yes |
| Priority support | No | Yes |

## Frontend Widget

- Six default reactions: Love, Insightful, Funny, Sad, Surprised, and Angry.
- Hover state subtly lifts border color.
- Click state animates the icon with a 200ms scale-up.
- Active reaction inverts to the brand color.
- Email capture appears inline after positive reactions only, never as a modal interruption.
- Positive reactions are configurable, with Love, Insightful, and Funny as the default set.
- Deduplication uses `localStorage`, a first-party cookie, and server-side soft deduplication.

## Technical Stack

### Bootstrap starter

Use `wp-plugin-matrix` as the bootstrap reference/source when starting the codebase:

- Local path: `/Volumes/Projects/work/wp_lab/wp-content/plugins/wp-plugin-matrx`
- Remote: `https://github.com/nkb-bd/wp-plugin-matrix.git`
- Checked local revision during planning: `5ec965b WP Plugin Matrix — modern WordPress plugin boilerplate`

This should make the initial build faster because it already provides a WordPress plugin entry structure, Composer autoloading, namespaced app classes, service providers, config, Vite, asset registration, migration conventions, REST/AJAX route helpers, admin menu patterns, PHPUnit/Pest wiring, Playwright wiring, and distribution ignore files.

Use it selectively:

- Reuse/adapt: plugin bootstrap, constants, Composer/PSR-4 shape, service provider pattern, Vite config shape, activation/migration conventions, asset registration idea, route registration idea, test config, `.distignore`, and WordPress.org `readme.txt` scaffold.
- Replace/avoid by default: Vue admin SPA, Element Plus, Chart.js, Moment, Quill, large demo components, broad facades, `.env`/Dotenv dependency, and any starter demo UI that does not serve PulsePress.
- Keep PulsePress direction: Preact for the public widget, WordPress-native admin components where practical, uPlot only when time-series charts are needed, small frontend assets, and privacy-first REST/data contracts.

Starter rule: do not run `rename-plugin.php` inside the original starter checkout. Copy only the needed scaffold into PulsePress, then rename/adapt in the PulsePress directory.

### Frontend widget

- Preact for stateful UI with a small runtime.
- Motion One for click and entrance animations.
- Tabler Icons for reaction and admin iconography.

### Admin UI

- `@wordpress/components` for native admin controls.
- `@wordpress/scripts` or Vite integration depending on the final build path.
- uPlot for analytics charts when time-series visualizations land.

### Backend

- Namespaced PHP classes with Composer autoloading.
- WP REST API endpoints for reactions, captures, and counts.
- WP-CLI commands for install and export workflows.
- WP-Cron-first queue adapter for v1 aggregation, with Action Scheduler support later behind the same interface.
- `$wpdb` with prepared statements for custom tables.
- Transients for count caches.

### Database

- `pulsepress_reactions`: `post_id`, `reaction_type`, `user_hash`, `timestamp`.
- `pulsepress_captures`: `post_id`, `email`, `reaction_type`, `timestamp`, `consent`, fraud-review metadata with timed purging.
- `pulsepress_daily_agg`: `date`, `post_id`, `reaction_type`, `count`.

## Privacy And Abuse Controls

- No cross-site tracking by default.
- Store soft deduplication hashes instead of raw identity fields for reactions.
- Use a WP nonce on every write endpoint.
- Optional Turnstile or hCaptcha for high-abuse sites.
- Capture consent timestamp with every email.
- Auto-purge fraud-review IP metadata after 30 days.

## Week 1: Foundation

- Scaffold plugin entry, Composer autoloader, namespaces, activation hooks, and Vite/build pipeline.
- Create the three custom tables on activation.
- Add REST endpoints:
  - `POST /pulsepress/v1/react`
  - `POST /pulsepress/v1/capture`
  - `GET /pulsepress/v1/counts/{post_id}`
- Build the Preact widget with six reactions, hover/active states, Motion One click animation, and local deduplication.
- Implement `user_hash = SHA256(IP + UA + nonce salt)` soft deduplication.

## Week 2: Display Modes And Settings

- Add count visibility modes: always, never, and threshold-based.
- Support site-level and post-level display configuration.
- Ship two free designs:
  - Minimal: compact, editorial, calm.
  - Expressive: larger icons with hover labels.
- Add Gutenberg block controls for design, reaction set, count visibility, and email capture.
- Add `[pulsepress]` shortcode with parity parameters.
- Create settings page with native WordPress components.
- Add auto-insert option for selected post types.

## Week 3: Email Capture And Analytics

- Build the inline post-reaction email capture component.
- Store capture consent timestamp, source post, and reaction source.
- Add CSV export for free users.
- Add Action Scheduler aggregation from raw reactions into `pulsepress_daily_agg`.
- Build dashboard metric cards, top posts table, and insights callout.
- Add heuristic sentiment insights, such as category-level positive-reaction and capture-rate callouts.

## Week 4: Polish And WordPress.org Readiness

- Audit accessibility: ARIA labels, keyboard navigation, focus rings, and screen-reader-safe icons.
- Defer frontend script until idle.
- Keep critical widget CSS small and inlineable.
- Ensure database queries use indexed columns.
- Add 5-minute transient cache for counts.
- Add light/dark theme support through CSS custom properties and `data-pulsepress-theme`.
- Write documentation covering install, hooks, shortcode, block usage, and customization.
- Prepare WordPress.org assets: screenshots, banner, icon, `readme.txt`, tags, and slug readiness.
- Add a restrained in-plugin Pro upgrade card.

## Week 5-6: Pro Post-Launch

- Mailchimp integration first.
- ConvertKit, MailerLite, Brevo, and Beehiiv after Mailchimp.
- A/B test engine for widget design and capture-rate measurement.
- Per-category or per-tag custom reaction sets.
- Webhooks and Zapier integration.
- White-label toggle and custom CSS field.

## Performance Commitments

- Target frontend widget bundle: under 15 KB gzipped for v1, with 8 KB as a stretch goal.
- Optimistically update reactions before background sync.
- Fetch cached counts after page idle with a single counts request.
- Never query raw reactions from the dashboard; use aggregated rows.
- Keep admin charts responsive on low-powered admin machines.

## V1 Non-Goals

- No ESP sync in Free.
- No AI-generated insights.
- No modal email capture.
- No comment system.
- No visitor identity profiles.
- No cross-site analytics.
- No dashboard queries over raw reaction events.
- No custom category/tag reaction sets in Free.
- No A/B testing in Free.

## Gap Decisions And Session Plan

See `docs/gap-questions-and-session-tasks.md` for the current decision questionnaire, recommended answers, and token-conscious implementation slices.

## Open Questions

- Confirm whether Vite or `@wordpress/scripts` is the primary build system for v1. The current preference is Vite for speed, while still using WordPress packages where useful.
- Confirm exact default reaction icons and labels.
- Confirm whether WP-Cron-first aggregation is acceptable for v1, with Action Scheduler support behind an adapter later.
- Confirm whether Pro is definitely a separate addon plugin.
