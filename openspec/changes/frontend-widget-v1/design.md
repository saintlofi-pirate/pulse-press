## Context

Moonfarmer Reactions Lead Capture is a public-page widget first and an admin tool second. Most installs will run on cached pages served by Cloudflare, LiteSpeed, or a host's edge cache. That constrains every design choice in this slice:

- The widget MUST work on a fully cached HTML page where PHP never runs for a given visitor. The counts endpoint stays cacheable (GET, public, no nonce); the write endpoint is the only PHP-touching request per visitor per post.
- The JS bundle MUST stay small. The v1 plan commits to ≤ 15 KB gzipped with 8 KB as a stretch. That budget is tight enough that we cannot afford a framework runtime; Preact at ~3 KB gzipped is the largest dependency we'll accept.
- First paint MUST show counts. Visitors landing on a cached page see the buttons, the counts, and the active reaction (from `localStorage`) immediately. The widget mounts after `DOMContentLoaded`, hits `GET /counts/{postId}` once to reconcile, and re-renders if the server is ahead of cache.
- The widget MUST be keyboard accessible from day one. Session 11 will run a full a11y pass, but it's cheaper to design accessibly than to retrofit.

The Vite manifest is the integration seam. Vite produces hashed filenames in `dist/js/widget.<hash>.js` and CSS in `dist/assets/widget.<hash>.css`. PHP reads `dist/.vite/manifest.json` once per request to know which hashed filename corresponds to the `resources/widget/index.ts` source entry. This is the only PHP↔JS coupling.

## Goals / Non-Goals

**Goals:**

- A visitor sees six reaction buttons after every single-post body without configuration.
- Clicking a button optimistically increments the count, then reconciles with the server response.
- A returning visitor sees their previous reaction highlighted on next visit (localStorage).
- Re-clicking the same reaction is a no-op visually; clicking a different reaction switches the active state and posts the new type (server-side replacement handles it).
- The widget degrades gracefully without JS: no script, no buttons rendered at all. We don't attempt a noscript form fallback in v1.
- Asset bundle stays under 15 KB gzipped; CSS under 3 KB.
- Editor-friendly TypeScript with JSX support; production bundle stays untyped (Preact runtime).

**Non-Goals:**

- No email capture UI yet — that's Session 4 (storage) and Session 5 (inline UI).
- No Motion One or motion library. The 200ms scale animation from the v1 plan can be a CSS transition; the v1 plan listed Motion One but we don't need its API surface yet.
- No Gutenberg block. Session 7.
- No shortcode. Session 7.
- No light/dark mode toggle. Session 11 will add `data-moonfarmer-reactions-lead-capture-theme` with CSS custom properties; this slice just ships the custom-property scaffolding.
- No multi-language icon labels beyond `wp_localize_script` text. Session 6 will introduce a settings page that exposes per-type label overrides.
- No A/B test variant. Pro session 5–6.
- No per-category reaction sets. Pro.
- No retry/back-off on network failure beyond rolling back the optimistic state and showing a brief inline error.

## Decisions

### D1. Preact over plain DOM, even at 15 KB

Plain DOM with a small custom state library would fit under 5 KB, but the maintenance cost compounds quickly once we add capture flow, animation, and theme controls in Sessions 5/11. Preact gives us:

- JSX, which the team will read faster than `el(...)` chains.
- Hooks, which match the mental model (`useState`, `useEffect`) the rest of the codebase will assume.
- A stable, well-known render layer that won't surprise us when we add the inline capture UI.

At ~3 KB gzipped runtime, Preact is well inside the budget. The 15 KB target leaves ~12 KB for our own code, which is enough for the widget + storage + API client + JSX overhead.

**Alternative considered**: lit-html / vanilla-html-tagged-templates. Rejected — the second-developer cost is higher than the runtime cost saved.

### D2. Mount points discovered via `data-moonfarmer-reactions-lead-capture-widget` attribute

`resources/widget/index.ts` runs `document.querySelectorAll('[data-moonfarmer-reactions-lead-capture-widget]')` at `DOMContentLoaded` and mounts a Preact root into every match. Each match's `data-moonfarmer-reactions-lead-capture-post-id` overrides `MoonfarmerReactionsLeadCaptureData.postId` so a single page with multiple post excerpts (e.g., a "you might also like" widget) can host multiple independent reaction bars.

**Alternative considered**: an `id="moonfarmer-reactions-lead-capture-widget"` singleton. Rejected — single-id locks us out of multi-post pages and is harder to test in the editor.

### D3. Asset enqueue on `is_singular('post')` only, with a filter

```php
add_action('wp_enqueue_scripts', function () {
    if (is_admin() || !is_singular('post')) {
        if (!apply_filters('moonfarmer_reactions_lead_capture_widget_enqueue', false)) {
            return;
        }
    }
    // ...enqueue...
});
```

Non-post pages pay zero asset cost. A site that wants the widget on Pages can `add_filter('moonfarmer_reactions_lead_capture_widget_enqueue', '__return_true')`. Session 6's settings page will surface this as an admin toggle.

### D4. Vite manifest reader caches via a 24-hour transient

`Moonfarmer\ReactionsLeadCapture\View\Manifest::resolve($entry)` reads `dist/.vite/manifest.json` once, caches the parsed array in transient `moonfarmer_reactions_lead_capture_vite_manifest_v1` for 24 hours. The cache key includes the manifest's `filemtime()` so a `npm run build` invalidates automatically (the next read sees a stale cache, mtime mismatch, and re-reads).

**Alternative considered**: no caching, read every request. Rejected — for sites with object cache disabled and lots of traffic, a `file_get_contents` + `json_decode` per request is wasted work for a file that changes at most a few times per release.

### D5. `wp_localize_script` payload is a tight object

```js
window.MoonfarmerReactionsLeadCaptureData = {
    root: 'https://example.com/wp-json/moonfarmer-reactions-lead-capture/v1/',
    nonce: '<wp_create_nonce("wp_rest")>',
    postId: 209,
    reactions: ['love', 'insightful', 'funny', 'sad', 'surprised', 'angry'],
    i18n: { loading: 'Loading…', error: 'Please try again.' }
};
```

The `reactions` list is passed through `apply_filters('moonfarmer_reactions_lead_capture_reaction_types', Reactions::TYPES)` so JS and PHP stay in sync. Custom labels per type live in `i18n` for Session 6's settings.

The whole config is filterable via `apply_filters('moonfarmer_reactions_lead_capture_widget_data', $payload)` so Pro and integrations can inject additional context (e.g., A/B variant id) without re-implementing the bootstrap.

### D6. Optimistic update with rollback on failure

When the user clicks a button:

1. The component updates local state: `activeType = newType`; the count for `newType` increments and the count for the previous `activeType` (if any) decrements.
2. `setStoredReaction(postId, newType)` writes localStorage.
3. `react(postId, newType)` fires.
4. On success: the response's `counts` object replaces local state (server is authoritative).
5. On failure: previous state restored, inline error rendered for 4 seconds.

The 4-second error toast is rendered inside the widget's bounding box, not a global toast — keeps the widget self-contained.

### D7. localStorage key is plain, namespaced, and post-scoped

Key: `moonfarmer-reactions-lead-capture:reaction:{postId}`. Value: the reaction type string. No JSON, no timestamps, no version. Two reasons:

- It's read on every widget mount; small payload, no parse cost.
- If the contract evolves, a new key prefix (`moonfarmer-reactions-lead-capture:reaction:v2:`) is cheaper than a migration.

### D8. CSS uses custom properties only; no `:root` overrides

```css
.moonfarmer-reactions-lead-capture {
    --moonfarmer-reactions-lead-capture-accent: #6366f1;
    --moonfarmer-reactions-lead-capture-text: #111827;
    --moonfarmer-reactions-lead-capture-border: #e5e7eb;
    --moonfarmer-reactions-lead-capture-radius: 12px;
    --moonfarmer-reactions-lead-capture-gap: 0.5rem;
}
```

Variables are scoped to `.moonfarmer-reactions-lead-capture`, not `:root`, so the widget can't pollute or be polluted by site themes. Theme toggling in Session 11 will switch via `[data-moonfarmer-reactions-lead-capture-theme="dark"]` on the same root.

### D9. Six buttons, one inline SVG sprite per reaction

Icons are inlined SVG strings imported as TypeScript constants. No external sprite, no Tabler npm package — that pulls 6 MB of icons we'd then have to tree-shake against. We pay a one-time author cost for six hand-curated 24×24 SVGs.

**Alternative considered**: `tabler-icons-react` package. Rejected — bundle bloat and CSS variant cost for a fixed six-icon set.

### D10. ARIA pattern: `<button role="button" aria-pressed="true|false">`

Each reaction is a `<button>` with `aria-pressed` indicating active state. `aria-label` provides the reaction name; `aria-live="polite"` on the count region announces server-confirmed changes without interrupting screen readers. Keyboard tab order is left-to-right; Enter and Space activate.

### D11. No motion library; CSS transitions only

A 200ms `transform: scale(1.05)` on `:active` matches the v1 plan's "click animation" without importing Motion One. The bundle savings (~3 KB) bring the stretch 8 KB goal within reach.

If Session 11 decides we need richer animation (e.g., a confetti burst on a milestone), we add Motion One then.

## Risks / Trade-offs

- **Risk**: A site theme overrides our buttons with global `button { }` styles. → Mitigation: every widget selector starts with `.moonfarmer-reactions-lead-capture` and uses high specificity. We don't `!important`; we use `.moonfarmer-reactions-lead-capture button.moonfarmer-reactions-lead-capture-reaction` which beats most theme defaults.
- **Risk**: A visitor's browser blocks third-party cookies or `localStorage`. → Mitigation: storage is wrapped in try/catch; on failure the widget operates statelessly (every click re-shows the full bar). Server-side replacement still works.
- **Risk**: The page is served from a CDN with no JS execution (some text-mode caches). → Mitigation: no degradation; the static HTML container is just an empty `<div>`.
- **Risk**: Bundle size creeps past 15 KB as Sessions 4/5/11 add inline capture and animation. → Mitigation: budget is published in `widget.css` size baseline and tested in CI (Session 11 will add a size-limit check). For now, manual `wc -c < dist/js/widget.*.js` after every change.
- **Risk**: The Vite manifest schema changes between Vite 5 and Vite 6. → Mitigation: `Manifest::resolve` reads the documented `manifest[entry].file` key and ignores everything else; future schema changes only affect that line.
- **Risk**: Multiple widget mounts on one page cause N counts requests at first paint. → Mitigation: the widget batches mounts in `index.ts` and issues one combined fetch per unique `postId`. With at most one post id per render in v1, this is a non-issue, but the code is structured for it.
- **Trade-off**: No noscript fallback. Visitors without JS see no widget. Acceptable for v1; sites that want a noscript path can ship a PHP-rendered counts read.
- **Trade-off**: localStorage dedup is per-browser, not per-device. A user switching browsers re-sees a "no active" widget but server-side dedup prevents a second count. Acceptable.

## Migration Plan

No data migration. Rollback is a `git revert` and a `npm run build` to rebuild a previous `dist/`.

The deployment path:

1. Land this change.
2. `npm run build` to generate `dist/.vite/manifest.json` and hashed JS/CSS.
3. On a wp_lab.test single-post URL, view source to confirm `window.MoonfarmerReactionsLeadCaptureData` is emitted and the widget container exists after the content.
4. Open the page in a browser; click a reaction; confirm the count increments visually and persists across reload.
5. Inspect `localStorage` and confirm `moonfarmer-reactions-lead-capture:reaction:{postId}` key exists.

## Open Questions

- **Q1**: Should the widget show counts even when they're zero? → **Decided yes** for transparency. The count is the engagement signal; hiding zero would feel dishonest. Session 6's settings will add a "Hide zero counts" toggle for the count visibility decision in gap 8.
- **Q2**: Should the active reaction be tinted with the accent colour, or just outlined? → **Decided tint with `var(--moonfarmer-reactions-lead-capture-accent)`** for visual weight. Outline-only failed contrast checks in a quick local prototype.
- **Q3**: Should clicking the active reaction "unreact"? → **Decided no** for this slice. The server has no DELETE/unreact endpoint yet (Session 2 D8 deferred it). Re-clicking is a no-op visually.
