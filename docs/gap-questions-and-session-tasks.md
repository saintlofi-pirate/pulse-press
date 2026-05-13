# PulsePress Gap Questions And Session Tasks

This document turns the open gaps into decision questions with recommended answers. It also splits the build into small sessions so future Codex or Claude runs can stay focused and avoid burning a large weekly token/session budget.

## How To Use This File

- Treat each question as a product or architecture decision.
- Accept the recommended answer unless there is a strong reason to change it.
- Keep each implementation session to one slice from the task list.
- Start each coding session by reading only:
  - `docs/pulsepress-v1-plan.md`
  - this file's current slice
  - the directly relevant code files
- Promote a slice into an OpenSpec change when it changes API, schema, analytics, privacy, or the Free/Pro boundary.

## Recommended Decisions

Status: the five implementation-gating decisions below were confirmed by the user on 2026-05-12.

### 0. Should the `wp-plugin-matrix` starter be used?

Recommended answer: yes, selectively.

Source:

- Local path: `/Volumes/Projects/work/wp_lab/wp-content/plugins/wp-plugin-matrx`
- Remote: `https://github.com/nkb-bd/wp-plugin-matrix.git`
- Local revision checked during planning: `5ec965b WP Plugin Matrix — modern WordPress plugin boilerplate`

Why: it can save time on plugin bootstrap, Composer autoloading, service providers, route registration, asset registration, migration conventions, Vite, test setup, and packaging files.

Decision: use it as a scaffold source for backend/build foundation, but do not inherit the Vue/Element Plus/Chart.js-heavy admin stack by default.

Confirmed: yes, use it if it makes building faster.

### 1. Should Pro be a separate addon plugin?

Recommended answer: yes.

Why: WordPress.org compliance is cleaner, the free package stays generous and review-friendly, and Pro can hook into Free without shipping locked business logic in the free plugin.

Decision: Free is the foundation plugin. Pro is a separate addon plugin that depends on Free.

Confirmed: yes.

### 2. How should Action Scheduler be handled?

Recommended answer: start with a small internal WP-Cron aggregation adapter, then support Action Scheduler behind an interface.

Why: bundling Action Scheduler in v1 adds dependency and namespace questions. A queue interface lets v1 ship safely while leaving a clean upgrade path for larger sites.

Decision: create `QueueScheduler` abstraction. Use WP-Cron first. Add Action Scheduler support when present or in a later Pro/performance slice.

Confirmed: yes.

### 3. What data should be retained?

Recommended answer:

- Raw reactions: keep indefinitely by default unless the site owner enables retention pruning.
- Daily aggregates: keep indefinitely unless deleted on uninstall.
- Capture emails: keep until deleted/exported by the admin.
- Fraud-review IP metadata: purge after 30 days.
- Uninstall behavior: default keep data, with an explicit "delete data on uninstall" setting.

Decision: add retention settings and an uninstall policy before WordPress.org submission.

Confirmed: yes. Raw reactions are kept indefinitely by default, with optional pruning.

### 4. What consent model should email capture use?

Recommended answer:

- Require explicit consent before saving an email.
- Store consent text version, timestamp, post ID, reaction type, and source.
- Make CSV export include consent timestamp.
- For ESP sync, respect the provider's double opt-in where available.
- Add privacy policy helper text in settings.

Decision: no silent email capture. Consent is required before storage or sync.

Confirmed: yes. Consent requires an explicit checkbox.

### 5. What hash should deduplication use?

Recommended answer:

- Browser deduplication: `localStorage` plus first-party cookie.
- Server soft deduplication: HMAC-SHA256 over IP and user agent using a server-side secret salt.
- Do not use WP nonce as the hash salt because nonce rotation can break dedup consistency.
- Keep hash purpose narrow: deduplication and abuse throttling, not identity tracking.

Decision: replace `SHA256(IP + UA + nonce salt)` with an HMAC using plugin/server secret.

### 6. How should REST endpoints work for cached public pages?

Recommended answer:

- Public count endpoint can be readable without login.
- Write endpoints require a fresh WordPress nonce.
- If nonce validation fails, return a clear recoverable error so frontend can refresh config or ask user to retry.
- Rate-limit by post ID, reaction type, dedup hash, and IP window.
- Duplicate reactions should replace the previous reaction for that post/user, not create multiple active reactions.

Decision: use public read, nonce-protected writes, and replacement semantics.

Confirmed: yes. Users can change reactions; a new reaction replaces the previous one for that post/user.

### 7. What are the analytics formulas?

Recommended answer:

- Positive reactions: configurable set, default Love, Insightful, Funny.
- Sentiment rate: positive reactions divided by total reactions.
- Capture rate: captures divided by positive reactions that showed the capture UI.
- Top posts: sort by total reactions, capture rate, or positive sentiment within the selected time window.
- Daily aggregation timezone: use the site's configured WordPress timezone.

Decision: analytics must define numerator, denominator, and time window for every metric.

### 8. What is the display precedence?

Recommended answer:

1. Shortcode attributes override everything for that render.
2. Block attributes override post and global settings for that block.
3. Post-level settings override global settings.
4. Global settings apply as the fallback.
5. Auto-insert should skip posts that already contain the PulsePress block or shortcode.

Decision: no duplicate widgets from auto-insert.

### 9. What is the realistic frontend size budget?

Recommended answer:

- Widget JS target: under 15 KB gzipped for v1.
- Critical CSS target: under 3 KB.
- Do not load widget assets on pages where the widget cannot render.
- Keep the 8 KB goal as an optimization stretch target, not a launch blocker.

Decision: 15 KB gzipped is the v1 budget; 8 KB is stretch.

### 10. What should v1 explicitly not include?

Recommended answer:

- No ESP sync in Free.
- No AI insights.
- No modal capture UI.
- No comment system.
- No cross-site analytics.
- No visitor identity profiles.
- No raw-event dashboard queries.
- No custom category/tag reaction sets in Free.
- No A/B tests in Free.

Decision: add these as v1 non-goals to prevent scope creep.

## Open Questions For The User

These were confirmed on 2026-05-12:

1. PulsePress Pro is a separate addon plugin.
2. WP-Cron is acceptable for v1 aggregation, with Action Scheduler support later.
3. Raw reactions are kept indefinitely by default, with optional pruning.
4. Email capture requires an explicit checkbox.
5. Duplicate reactions replace the previous reaction for that post/user.

## Token-Conscious Session Plan

Each session should aim to touch one small area, produce one verifiable result, and stop. Avoid reading the whole repo once code exists.

### Session 0: Project Bootstrap

Goal: create the minimal plugin skeleton using `wp-plugin-matrix` selectively.

Scope:

- Copy/adapt the starter's plugin entry shape into `pulsepress.php`.
- Rename constants from `WP_PLUGIN_MATRIX_*` to `PULSEPRESS_*`.
- Rename namespace from `WPPluginMatrix` to `PulsePress`.
- Keep Composer PSR-4 autoloading.
- Keep a slim service provider/bootstrap pattern.
- Keep activation/deactivation hooks.
- Keep only starter files needed for a clean baseline.
- Do not copy `node_modules`, generated `dist`, demo Vue pages, Element Plus components, Chart.js demos, Quill editor, or starter demo routes.

Verification:

- `composer dump-autoload` if Composer is introduced.
- PHP syntax check on touched PHP files.
- Confirm plugin appears in WP admin if local WP is available.
- Confirm no remaining `WPPluginMatrix`, `WP_PLUGIN_MATRIX`, `wp-plugin-matrix`, or starter demo labels remain in PulsePress-owned files.

### Session 1: Schema And Migrations

Goal: create safe database installation and versioning.

Scope:

- Migration service adapted from the starter, but with PulsePress table names and safer versioning.
- `pulsepress_db_version` option.
- Three custom tables.
- Indexes for post/date/reaction/hash lookups.
- Uninstall behavior setting placeholder.

OpenSpec: yes, because this touches schema.

Verification:

- Activation creates expected tables.
- Re-running migration is idempotent.
- SQL uses `dbDelta` or a carefully reviewed equivalent.
- No direct `information_schema` dependency unless tested against the local WordPress database.

### Session 2: Reaction REST Contract

Goal: make reaction writes and counts work.

Scope:

- `POST /pulsepress/v1/react`.
- `GET /pulsepress/v1/counts/{post_id}`.
- Nonce validation.
- Dedup/replacement semantics.
- Transient count cache invalidation.

OpenSpec: yes, because this touches public API and privacy.

Verification:

- REST happy path.
- Duplicate reaction replacement.
- Invalid nonce handling.
- Count cache updates after write.

### Session 3: Frontend Widget V1

Goal: render the minimal reaction widget.

Scope:

- Preact widget.
- Six reactions.
- Active state.
- Optimistic count update.
- LocalStorage/cookie dedup.
- Basic CSS variables.

OpenSpec: optional unless behavior changes from Session 2.

Verification:

- Build passes.
- Widget renders on a post.
- Keyboard interaction works for buttons.
- Assets load only when widget is present.

### Session 4: Email Capture Storage

Goal: save consented captures safely.

Scope:

- `POST /pulsepress/v1/capture`.
- Consent fields.
- Email validation.
- Capture table writes.
- Fraud metadata purge shape.

OpenSpec: yes, because this touches PII and consent.

Verification:

- No capture is saved without consent.
- Valid capture saves expected metadata.
- Invalid email returns clear error.

### Session 5: Inline Capture UI

Goal: show email capture after positive reactions.

Scope:

- Inline capture component.
- Positive reaction gate.
- Consent checkbox/copy.
- Success and error states.

Verification:

- Capture does not show for Sad/negative reactions.
- Capture does show for positive reactions.
- Form is keyboard and screen-reader usable.

### Session 6: Settings Page

Goal: expose core Free settings through a modern, sleek, minimal admin page.

Design bar (re-stated from `pulsepress-v1-plan.md` §Admin UI Design Direction): clean layout, generous spacing, one accent, sentence case, system font, 150–200 ms motion, `prefers-reduced-motion` respected, WordPress-native components reskinned via CSS variables, designed empty/loading states, accessible focus rings, no dark patterns.

Scope:

- Count visibility (always / never / threshold).
- Threshold value.
- Widget design preset (Minimal / Expressive).
- **Icon style preset (Classic outline / Emoji)** — exposed to JS via `pulsepress_widget_icons` filter so Pro can plug in additional presets without code changes.
- Positive reactions (which types trigger the future capture UI).
- Auto-insert post types (checkbox list of public post types).
- Auto-insert position (above / below / both).
- Theme mode (light / dark / auto via `prefers-color-scheme`).
- Data retention / uninstall options (already-reserved option keys).
- **Allow guest reactions toggle** — when off, `/react` permission callback adds `is_user_logged_in()`.
- **Per-post override meta box** on the post editor (Auto / Force on / Force off). Saved as `_pulsepress_widget_state` post meta. Display precedence: post-level wins over global auto-insert settings (gap 8 D1).
- Reskin starter admin menu / page pattern is acceptable only if it lands lighter than a custom page. Otherwise build a custom page that honours the design bar.
- Prefer WordPress-native components over Element Plus. Style via CSS variables, never inline overrides.

OpenSpec: yes — settings become part of the durable admin contract and the icon-style + per-post meta keys are filterable surfaces that Pro and 3rd parties will consume.

Verification:

- Settings save and reload across sessions.
- Sanitization rejects invalid values (numeric ranges, allowlisted post types, allowlisted icon-style keys).
- Per-post override beats global auto-insert in every combination.
- Disabling "Allow guest reactions" causes anonymous `POST /react` to return 401.
- Frontend respects every setting (theme, position, icon style, auto-insert post types).
- Admin assets load only on PulsePress admin pages and on the post editor screen where the meta box renders.
- Visible focus rings on every interactive control; tab order matches visual order; `prefers-reduced-motion` disables transitions.
- Settings page renders cleanly at 1280px and 2560px widths; no horizontal scrollbars at common breakpoints.

### Session 6.5: Icon Preset Wiring (folds into Session 6 if scope holds)

Goal: ship the Emoji icon preset alongside Classic and let admins switch via Settings.

Scope:

- Extract `resources/widget/icons.ts` into a presets map: `{ classic: {love: '<svg…>', …}, emoji: {love: '❤️', …} }`.
- Add a `pulsepress_widget_icons` filter that lets PHP override icon strings before they reach JS through `pulsepress_widget_data`. Pro plugs in two more presets here.
- JS chooses preset based on `PulsePressData.iconStyle`, default `'classic'`.
- Settings UI shows a card-based preset picker (visual preview thumbnails, not a dropdown) styled to the admin design bar.

OpenSpec: covered under Session 6's settings change; no separate spec needed.

Verification:

- Switching the preset in Settings changes the rendered icons within one cached-asset cycle.
- Emoji preset stays within the 15 KB JS budget (emoji glyphs are font-rendered, so this should be net negative bytes vs Classic).
- Screen-reader labels survive the swap — icons stay `aria-hidden`; the button's `aria-label` comes from `i18n` regardless of preset.

### Session 7: Block And Shortcode

Goal: support manual placement.

Scope:

- Gutenberg block.
- `[pulsepress]` shortcode.
- Attribute parity.
- Display precedence.
- Auto-insert duplicate detection.

OpenSpec: yes, because this defines placement contract.

Verification:

- Block renders in editor and frontend.
- Shortcode renders with attributes.
- Auto-insert skips existing block/shortcode.

### Session 8: Aggregation

Goal: make analytics scalable.

Scope:

- Aggregation job.
- Daily aggregate table writes.
- Site timezone handling.
- Retry/idempotency behavior.

OpenSpec: yes, because this touches analytics semantics.

Verification:

- Aggregation creates expected rows.
- Re-running does not double count.
- Dashboard queries do not read raw reaction rows.

### Session 9: Admin Dashboard

Goal: show useful Free analytics.

Scope:

- Metric cards.
- Top posts table.
- Sentiment insight callout.
- 30-day default window.

Verification:

- Dashboard loads from aggregate tables.
- Empty states are polished.
- Metrics match formula definitions.

### Session 10: CSV Export

Goal: let Free users export captured emails.

Scope:

- Admin export action.
- Capability check.
- Nonce check.
- CSV fields with consent timestamp.

OpenSpec: optional unless export format becomes a committed contract.

Verification:

- Unauthorized users cannot export.
- CSV escapes fields correctly.
- Export includes required consent metadata.

### Session 11: Accessibility And Performance Pass

Goal: make v1 usable and lean.

Scope:

- ARIA labels.
- Focus states.
- Reduced-motion behavior.
- Lazy/deferred loading.
- Bundle size check.

Verification:

- Keyboard-only path works.
- Reduced-motion users are respected.
- Bundle stays under v1 budget.

### Session 12: WordPress.org Package

Goal: prepare submission assets and docs.

Scope:

- `readme.txt`.
- Screenshots captions.
- Banner/icon task list.
- Plugin assets checklist.
- License and attribution review.

Verification:

- WordPress readme parser check if available.
- No Pro-only locked code in free package.
- All bundled assets are GPL-compatible.

### Session 13: Pro Boundary Plan

Goal: define the addon contract before writing Pro code.

Scope:

- Free hooks/filters for Pro.
- License-independent Free behavior.
- ESP sync extension points.
- A/B testing extension points.
- White-label policy.

OpenSpec: yes, because this is the Free/Pro contract.

Verification:

- Free works without Pro.
- Pro can attach without modifying Free internals.
- No Free settings disappear when Pro is inactive.

## Session Budget Rules

- One session should usually read fewer than 10 files.
- One session should edit fewer than 8 files unless it is a scaffold/build setup.
- Prefer one OpenSpec change per contract-heavy slice.
- Do not run broad audits during implementation slices.
- Save broad review for explicit review sessions.
- End each session with:
  - touched files
  - verification commands
  - next recommended slice
