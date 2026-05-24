## Why

Sessions 0–2 give us a plugin, three tables, and two REST endpoints. Nothing on a published post lets a reader actually react yet. The widget closes that loop: the visible, clickable surface that turns the REST contract into engagement. Until it lands, the plugin has no observable behaviour on the front end and there is no UX to validate any later product decision against.

Free remains generous: the widget is rendered after every post body by default, with no admin configuration needed; per-post override and global controls land in Session 6's settings. Privacy stays first-class: localStorage stores only the chosen reaction type per post (no identifiers), the visitor never sees the server-side HMAC, and the optimistic-update path falls back gracefully when JS is disabled or the network fails.

## What Changes

- Add `resources/widget/index.ts` that mounts a Preact root into every element with `data-moonfarmer-reactions-lead-capture-widget` attached, reading `window.MoonfarmerReactionsLeadCaptureData` (post id, REST root, nonce) for configuration.
- Add `resources/widget/components/ReactionBar.tsx` rendering six buttons (love, insightful, funny, sad, surprised, angry) with SVG icons inlined, ARIA labels, focus-visible styling, and a single "active reaction" highlight.
- Add `resources/widget/api.ts` exposing typed `fetchCounts(postId)` and `react(postId, type)` functions that wrap `fetch` against the v1 REST endpoints. The write call sends `X-WP-Nonce` from `MoonfarmerReactionsLeadCaptureData.nonce` and `Content-Type: application/json`.
- Add `resources/widget/storage.ts` exposing `getStoredReaction(postId): string | null` and `setStoredReaction(postId, type)`, persisting per-post state in `localStorage` under `moonfarmer-reactions-lead-capture:reaction:{postId}`. This is local-only convenience — server-side dedup remains the source of truth.
- Add `resources/widget/widget.css` with CSS custom properties (`--moonfarmer-reactions-lead-capture-accent`, `--moonfarmer-reactions-lead-capture-text`, `--moonfarmer-reactions-lead-capture-border`, `--moonfarmer-reactions-lead-capture-radius`, `--moonfarmer-reactions-lead-capture-gap`) and base layout. No CSS reset, no Tailwind — under 3 KB minified.
- Add `app/Providers/WidgetServiceProvider` (and register it in `app/bootstrap.php`). On `wp_enqueue_scripts` (front end only), when `is_singular('post')` is true, it reads the Vite manifest at `dist/.vite/manifest.json`, enqueues the hashed JS/CSS, and emits `window.MoonfarmerReactionsLeadCaptureData = {root, nonce, postId}` via `wp_localize_script`.
- Add a `the_content` filter that appends `<div data-moonfarmer-reactions-lead-capture-widget data-moonfarmer-reactions-lead-capture-post-id="{id}"></div>` to single-post bodies, filterable through `moonfarmer_reactions_lead_capture_widget_auto_insert` (default `true` for `post`, `false` for everything else).
- Add `Moonfarmer\ReactionsLeadCapture\View\Manifest` helper that resolves the Vite manifest into `(handle => url)` mappings, with a single transient cache (24 hours, invalidated whenever the manifest's mtime changes) so the file isn't re-read on every request.
- Update `package.json` to keep the existing Vite + Preact dependencies and add a single TypeScript-friendly tsconfig stub for editor support.
- Update `.distignore` to keep `dist/` in the release zip and exclude `resources/widget/` source.
- **BREAKING**: none. This is the first front-end surface; nothing exists to break.

## Capabilities

### New Capabilities

- `reaction-widget`: defines the front-end contract — when the widget renders, what it renders, how it talks to the REST endpoints, how it persists local state, what extension points it exposes (filter for auto-insert behaviour, container element selector for manual placement, optional `data-` attributes for opting out), and the asset-loading contract (only on singular posts unless a filter opts in elsewhere).

### Modified Capabilities

- `reaction-api`: tightened — the widget assumes `counts.{type}` is always an object (never `[]`). The existing `(object)` cast in `ReactionController` already enforces this; we promote the assumption into the contract.

## Impact

- **New files**: `resources/widget/index.ts`, `resources/widget/components/ReactionBar.tsx`, `resources/widget/api.ts`, `resources/widget/storage.ts`, `resources/widget/widget.css`, `resources/widget/types.ts`, `app/Providers/WidgetServiceProvider.php`, `app/View/Manifest.php`, `tests/Unit/ManifestTest.php`, `tests/Unit/WidgetServiceProviderTest.php`, `tsconfig.json`.
- **Modified files**: `app/bootstrap.php` (registers `WidgetServiceProvider`), `vite.config.js` (adds CSS entry alongside JS), `package.json` (no dependency changes), `tests/Stubs/wp_functions.php` (shims for `is_singular`, `wp_enqueue_script`, `wp_enqueue_style`, `wp_localize_script`).
- **REST API**: no new endpoints. The widget consumes the Session 2 contract verbatim.
- **Database changes**: none.
- **Filters introduced**: `moonfarmer_reactions_lead_capture_widget_auto_insert` (boolean, per-post-type), `moonfarmer_reactions_lead_capture_widget_data` (associative array of the localized config before `wp_localize_script`).
- **Hooks consumed**: `wp_enqueue_scripts`, `the_content`.
- **Privacy**: localStorage stores `{postId: reactionType}` per post — no PII. Asset bundle has no third-party trackers, no analytics, no external network requests beyond the plugin's REST endpoints.
- **Performance**: widget JS target ≤ 15 KB gzipped (8 KB stretch); CSS target ≤ 3 KB minified. Asset enqueues only on singular post views; non-post pages pay no widget cost. Vite manifest read is cached for 24 hours.
- **Free/Pro boundary**: untouched. Pro can replace the widget by hooking `moonfarmer_reactions_lead_capture_widget_auto_insert` to `false` and rendering its own block; the Free widget stays minimal and replaceable.
