# PulsePress v1 Product Plan

## Concept

PulsePress is a WordPress plugin for reactions that grow an email list, with analytics that show what content is working. The free tier should be a complete, generous product for WordPress.org distribution. Pro should unlock provider sync, A/B testing, deeper insights, and advanced customization without weakening the free experience.

## Positioning

- Free: complete reactions, inline email capture with CSV export, 30-day analytics, top posts, sentiment insights, Gutenberg block, shortcode, privacy-safe defaults, two icon presets (Classic + Emoji), two widget designs (Minimal + Expressive), per-post overrides, guest-reactions toggle.
- Pro: ESP integrations, 12-month analytics, A/B tests, per-category or per-tag reaction sets, IP allowlist/blocklist, webhooks, white-labeling, and priority support.
- Price target: `$49/year`.
- Design bar: polished, calm, generous spacing, strong hierarchy, one primary accent, sentence case, and a visual feel inspired by Adham Dannaway-style product polish.

## Code Quality Principles

Every line of PulsePress is held to the same bar. These principles are checked at PR time and during OpenSpec design review; failures are returned for revision rather than merged with a "we'll clean it up later" promise.

**Clean:**

- Names earn their length. A class called `ReactionRepository` does reaction-row reads and writes — nothing else, nothing surprising. A method named `replace` does exactly what MySQL `ON DUPLICATE KEY UPDATE` does.
- No dead code. Removed features are removed cleanly — not commented out, not gated behind a permanent feature flag.
- Comments explain **why**, never **what**. The code says what; a comment is reserved for the non-obvious constraint (a workaround, a subtle invariant, a domain rule that isn't visible in the diff).
- No premature abstractions. Three similar lines beat a clever helper that hides one of them. If a second use case ever appears, extract then — not before.
- No dead error handling. Don't catch exceptions you can't act on; don't validate input that the framework or the type system already guards.

**Modular:**

- One responsibility per file. `Schema.php` declares table SQL; `Migrator.php` runs it; `DatabaseServiceProvider.php` wires them up. None of those three does the others' job.
- Constructor injection over service location. A class takes its collaborators in `__construct`; tests pass stubs; production passes real instances via the service provider.
- Static helpers are reserved for value objects (`Reactions::isValid`, `UserHash::compute`). Anything with state, side effects, or DB access is an instance class so it can be swapped in tests.
- Service providers are the wiring layer. Feature code never news-up its own dependencies — `app/Providers/*` does.
- Small files. A class over ~200 lines is a smell; split before it grows.

**Maintainable:**

- Test the contract, not the implementation. Pest specs assert "the migrator writes the version exactly once when current < latest" — not "the migrator calls `dbDelta` exactly three times". The implementation detail is free to change; the behaviour isn't.
- Type everything that PHP 8.1 lets us type — parameters, returns, properties (with `readonly` where possible). `mixed` is a code smell unless we genuinely don't know.
- Use `final class` for everything that isn't deliberately designed for extension (e.g., service providers). Inheritance is a contract; declare it only when you mean it.
- Reuse existing helpers before writing new ones. `Schema::tableName($wpdb, ...)` already exists for every prefixed table name; don't string-concatenate `$wpdb->prefix . 'pulsepress_…'` in a controller.
- Migrations and option keys are append-only. Renaming or removing either is a breaking change with a deprecation cycle.

**Easy to extend:**

- Every decision point gets a WordPress filter; every side effect gets a WordPress action. See the next section ("Extensibility — Hooks and Filters First") for the full spec.
- Public methods of repositories/services are the API surface for both internal code and (where reasonable) Pro plugins. Private methods are free to refactor; public ones aren't, once shipped.
- Feature flags are temporary by design. If a flag survives two releases without flipping, it becomes the default and the flag is removed.
- Settings keys, post-meta keys, transient keys, and option keys are all namespaced under `pulsepress_` / `_pulsepress_` and are part of the documented contract.

These principles are not aspirational. A PR that violates them goes back for changes; an OpenSpec design that violates them gets rewritten before code starts.

## Extensibility — Hooks and Filters First

Every decision point and every side-effect in PulsePress is exposed as a WordPress action or filter. This is non-negotiable and applies to Free, Pro, and every future module.

**The principle:**

- **Decision points** (which post types, which reactions, which IPs, where to render, what to show, who can act) are wrapped in `apply_filters('pulsepress_<noun>', $value, ...$context)`.
- **Side effects** (a reaction was cast, a capture was stored, a setting was saved, the schema was migrated) fire `do_action('pulsepress_<noun>_<verb>', ...$context)` immediately after they happen.
- **Defaults stay sensible.** A site that installs Free and never writes a snippet must still get a complete, generous product. Filters exist to *adjust*, not to *complete*.
- **Hooks are the Pro contract.** Pro never modifies Free internals; it attaches through hooks/filters and through the `pulsepress_widget_data` and `pulsepress_widget_icons` extension seams already wired into Sessions 2 and 6.

**Naming conventions:**

- Filters: `pulsepress_<thing>` — returns a value (e.g., `pulsepress_reaction_types`, `pulsepress_client_ip`, `pulsepress_widget_data`).
- Actions: `pulsepress_<noun>_<verb>` or `pulsepress_before_<noun>` / `pulsepress_after_<noun>` — fires a side effect (e.g., `pulsepress_before_react`, `pulsepress_after_react`, `pulsepress_capture_saved`).
- Always pass enough context arguments to be useful (post id, reaction type, user hash, full request when applicable) so the hook is meaningful without a follow-up query.

**What this looks like in practice:**

- Reaction set is a constant *plus* `pulsepress_reaction_types` filter (Session 2).
- Client IP is `$_SERVER['REMOTE_ADDR']` *plus* `pulsepress_client_ip` filter for CDN/proxy overrides (Session 2).
- Auto-insert is on for `post` *plus* `pulsepress_widget_auto_insert` filter for other post types (Session 3).
- Asset enqueue gates on `is_singular('post')` *plus* `pulsepress_widget_enqueue` filter (Session 3).
- Localized JS payload is filtered through `pulsepress_widget_data` before emission (Session 3).
- Every reaction write fires `pulsepress_before_react` (lets rate-limit / abuse modules short-circuit by throwing `RestException`) and `pulsepress_after_react` (lets aggregators, webhooks, ESP sync hook in) (Session 2).

**Rules for new code:**

- Every Session that adds a feature MUST document the new hooks in its OpenSpec spec under "ADDED Requirements" and in `readme.txt` under "Hooks and filters".
- Every Session that adds a settings option MUST decide whether the option is also a filter target (so programmatic overrides are first-class, not bolted on later).
- Removing or renaming a hook is a breaking change and requires a major version bump.
- Adding a filter that wraps an existing decision is a non-breaking improvement and should land in the same session as the feature, not deferred.

## Accessibility — WCAG 2.1 AA First

Accessibility is not a Session 11 cleanup; it is a constraint on every UI slice that ships. The bar:

**Semantics:**

- Every interactive element is a real `<button>`, `<a>`, `<input>`, `<select>`, or `<dialog>` — never a `<div>` with a click handler.
- Form fields always have an associated `<label>`. Placeholder text is never the only label.
- State changes that aren't visually obvious announce via `aria-live` (polite for counts, assertive for errors).
- Active and toggled state uses `aria-pressed`, `aria-expanded`, `aria-selected` — semantic, not just visual.
- Disabled state uses `disabled` (which blocks focus) or `aria-disabled="true"` (which keeps focus) depending on whether the user needs to know the control *exists* but can't be used right now.

**Keyboard:**

- Every interaction works from the keyboard alone. If you can do it with a mouse, you can do it with Tab + Enter + Space + arrow keys.
- Tab order matches visual order. No `tabindex` greater than 0.
- Focus is always visible. `:focus-visible` styling shows a ring; `outline: none` is forbidden unless replaced by an equally visible alternative.
- Modals, popovers, and drawers manage focus: focus moves in on open, returns to the trigger on close, and is trapped while open.
- Escape closes anything that opens.

**Colour and motion:**

- Text contrast ≥ 4.5:1 against its background (WCAG AA). Large text ≥ 3:1.
- Non-text UI contrast ≥ 3:1 (icon strokes, borders, focus rings).
- Colour is never the only cue. Active reactions are also `aria-pressed="true"`; errors are also iconographed; required fields are also labelled "required".
- Honour `prefers-reduced-motion: reduce` — transitions disabled, motion stripped, transforms zeroed. Already wired into `widget.css`; every later component does the same.
- No auto-play media, no auto-advancing carousels, no flashing > 3 Hz.

**Screen reader UX:**

- Icon-only buttons always carry `aria-label`. Decorative icons are `aria-hidden="true"`.
- Form errors are described by `aria-describedby` pointing at an error message with `role="alert"`.
- Page titles, headings, and landmark regions (`<main>`, `<nav>`, `<aside>`) match the visual structure.
- Loading states announce ("Loading reactions…") rather than spinning silently.
- Validation messages are full sentences, not codes ("Email address is required to continue", not "ERR_REQUIRED").

**Testing:**

- Every shipped UI slice runs through keyboard-only navigation before the OpenSpec change is marked complete.
- Every shipped UI slice is tested with VoiceOver on macOS (or the equivalent on the contributor's OS) for the golden path and one error path.
- Automated tests assert ARIA attributes where applicable (`aria-pressed`, `aria-label`, `aria-invalid`).
- Session 11's accessibility pass becomes a *regression-prevention* check, not a *find-and-fix-everything* slog.

**What this looks like in practice today:**

- Widget buttons are real `<button>` with `aria-pressed` and `aria-label` (Session 3).
- Counts announce via `aria-live="polite"` (Session 3).
- `:focus-visible` styling renders a 2px accent ring on every interactive element (Session 3).
- `prefers-reduced-motion` disables the 200 ms transform (Session 3).
- The settings page (Session 6) will be navigable Tab-only with WCAG AA contrast on every state.
- The inline capture form (Session 5) will use `<label>`/`<input>` pairs, `aria-describedby` for the consent helper text, and `role="alert"` for validation errors.
- The admin dashboard (Session 9) will use semantic table markup with `<caption>`/`<th scope>`/`<tbody>` for the top-posts table.

If a contributor or model is about to ship a UI change and can't tick every relevant box above, the change goes back for revision before merge.

## Admin UI Design Direction

Every admin surface (settings page, post meta box, analytics dashboard, upgrade card) follows the same bar:

- **Modern, sleek, minimal, clean.** No dense WordPress-default tables. No multi-column boxed layouts. White space is a feature, not a bug.
- **One primary accent colour.** Same accent the widget uses on the front end so the product feels coherent. Reserved for the most important action on a page; nothing else is accent-coloured.
- **Sentence case everywhere.** "Save changes", not "Save Changes". "Allow guest reactions", not "Allow Guest Reactions".
- **Strong typographic hierarchy.** Three sizes max per page (page title, section title, body). System font stack; no custom web font.
- **Generous spacing.** Section padding ≥ 1.5rem; field gap ≥ 1rem; never crowd inputs.
- **Smooth, restrained motion.** 150–200 ms ease-in-out on hover/focus/active and on toggling sections. Respects `prefers-reduced-motion`. No bouncy springs, no decorative animation.
- **WordPress-native components first.** Use `@wordpress/components` (Button, ToggleControl, RadioControl, Notice) where they fit; reskin via CSS variables when they don't match the bar. Avoid Element Plus, Bootstrap, or any framework whose look-and-feel pulls us off-bar.
- **Empty and loading states are designed.** Never a blank panel — always a one-line explanation plus a clear next action.
- **Accessibility is baked in.** Visible focus rings, ARIA-correct controls, keyboard-first interaction; never `outline: none` without a replacement.
- **No dark patterns for the upgrade card.** A single restrained card at the bottom of the settings page with one CTA, never a modal, never above-the-fold blocking content.

## Free vs Pro Scope

| Capability | Free | Pro |
| --- | --- | --- |
| Six reaction types with custom labels | Yes | Yes |
| Show or hide counts | Yes | Yes |
| Threshold count visibility | Yes | Yes |
| Icon style preset (Classic outline + Emoji/Facebook-style) | 2 presets | 4 presets |
| Widget designs (Minimal + Expressive) | 2 designs | 4 designs |
| Per-post override (Auto / Force on / Force off) | Yes | Yes |
| Allow guest reactions toggle | Yes | Yes |
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
| IP allowlist / blocklist for reactions | No | Yes |
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
- Icon style is a preset, not freeform. Free ships two presets: **Classic** (current hand-curated outline SVGs) and **Emoji** (Facebook/Twitter-style filled emoji glyphs). Pro adds two more. Switching is one setting and a single `pulsepress_widget_icons` filter; reaction types and storage are untouched.
- Guest reactions are allowed by default. Admins can require login via the "Allow guest reactions" toggle; when off, the `/react` permission callback also checks `is_user_logged_in()`.

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
