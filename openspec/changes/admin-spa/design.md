## Context

The widget bundle is the public-facing surface; budget is tight (15 KB gzipped). The admin bundle has different constraints: it loads on a single admin page, so absolute size matters less than feel. We can afford ~40 KB gzipped if it buys a smooth SPA experience — comparable to FluentCRM, RankMath, etc. The audience is also different: admins want a focused product; visitors just want a fast reaction button.

Two boundaries this slice deliberately preserves:

1. **One Preact runtime for both bundles**. Vite tree-shakes; both entries share the same preact import, so no duplicate runtime ships.
2. **The widget code is the source of truth for the preview**. We import `ReactionBar` from `resources/widget/components/ReactionBar.tsx` into the admin's `LivePreview.tsx` — the same component renders in both contexts. The widget keeps its existing behaviour; the admin passes in props that map to the in-flight settings.

## Goals / Non-Goals

**Goals:**

- Admin opens `/wp-admin/options-general.php?page=moonfarmer-reactions-lead-capture` and sees a Preact SPA that loads settings, renders the Display tab by default, and shows a live widget preview alongside the controls.
- Toggling a setting (e.g., icon style emoji) updates the preview in the same render cycle and fires a background REST save.
- Save success surfaces as a transient "Saved" pill near the field/section title (fades after 1.5 s).
- Save failure rolls back the local state, leaves the field at its prior value, and renders an inline error with `role="alert"`.
- Hash-routed tabs survive page reload and browser back/forward.
- Every interaction is keyboard-accessible following the WAI-ARIA Tabs pattern.
- Live preview reuses the front-end widget code so admin and front-end can never drift.
- Admin asset bundle is separate from the widget bundle; the widget budget is unaffected.

**Non-Goals:**

- No `@wordpress/components` package import in this slice. The five reference plugins listed in the design bar (FluentCRM, RankMath, Spectra, Kadence Blocks, WP Migrate DB) all blend WP-native primitives with custom design; the Spectra/Kadence approach (custom controls in WP-shell aesthetic) is what we're targeting. `@wordpress/components` integration is a polish session if the user testing surfaces it.
- No per-post override meta box (post editor); that's a separate slice.
- No analytics dashboard rendering (Session 9).
- No upgrade card layout polish beyond a small stub. Session 13 (Pro boundary plan) lands the upgrade UX.
- No telemetry. No PostHog ping.
- No multi-step wizard / onboarding. The SPA is a single page.
- No drag-and-drop reordering for the positive-reactions list. Checkbox list is enough.
- No `@wordpress/api-fetch`; we use plain `fetch` for symmetry with the widget bundle.

## Decisions

### D1. Separate admin entry, single shared Preact runtime

`vite.config.js` declares two entries: `widget` and `admin`. Each emits its own JS + CSS hash. Vite shares Preact across the two via the import graph; the widget continues to gzip near 8 KB and the admin lands around 30–40 KB gzipped.

The widget bundle never gains admin code. Vite's per-entry chunking prevents accidental imports from `resources/admin/` reaching the public page.

### D2. Hash-routed tabs

`window.location.hash` drives the active tab. Tabs are `#display`, `#reactions`, `#capture`, `#privacy`. Empty / unknown hash defaults to `#display`. `popstate` listener updates state on back/forward. Clicking a tab updates `location.hash`, which feeds back through the listener — single source of truth.

**Alternative considered**: HTML5 history API. Rejected — would require server-side handling of arbitrary `/moonfarmer-reactions-lead-capture/<tab>` URLs, conflicts with WP's admin routing, and offers no real UX win over hash routing for this scale.

### D3. WAI-ARIA Tabs pattern with keyboard support

```jsx
<div role="tablist" aria-label="Settings sections">
  <button role="tab" aria-selected={active === 'display'} aria-controls="panel-display" id="tab-display" />
  …
</div>
<div role="tabpanel" id="panel-display" aria-labelledby="tab-display" tabIndex={0} />
```

Keyboard handlers on the tablist:
- ←/→ moves selection (wraps).
- Home/End jumps to first/last.
- Enter/Space activates selection (same as click).
- Tab from tablist enters the tabpanel.

### D4. Optimistic settings save with rollback

```ts
async function update(partial) {
  const before = settings;
  setSettings({ ...before, ...partial });
  setSaving(true);
  try {
    const response = await saveSettings(partial);
    setSettings(response.settings);
    flashSaved();
  } catch (err) {
    setSettings(before);
    showError(err.message);
  } finally {
    setSaving(false);
  }
}
```

The local state takes the optimistic update before the network fires. The server's response replaces local state on success (it's authoritative — the sanitiser may have adjusted values), or local state rolls back on failure.

`flashSaved()` renders a "Saved" pill with `role="status"` aria-live="polite" near the field/section that fades after 1.5 s. `showError()` renders an inline `role="alert"` that stays until the next save or until the user dismisses.

### D5. Live preview re-renders the front-end widget

`LivePreview.tsx` imports `ReactionBar` from `resources/widget/components/ReactionBar.tsx`. It builds a `MoonfarmerReactionsLeadCaptureData` object from the in-flight admin settings + mock counts and renders the bar inside a labelled card. Mock counts: `{love: 24, insightful: 12, funny: 6, sad: 3, surprised: 2, angry: 1}` so every reaction has a visible number.

To mute interactions:
- Pass `previewMode: true` (a new optional prop) into a thin wrapper that intercepts clicks and announces "Preview is read-only" via `role="status"`.
- Storage flags are not written (the wrapper supplies storage stubs).
- The capture form does not auto-render even when a positive reaction is "selected" in preview — the preview is for the bar, not the form. A future iteration can add a "preview capture form" toggle if user testing shows admins want it.

### D6. Card-based section layout

Each setting group is a `<section class="moonfarmer-reactions-lead-capture-admin-card">` with a header (`<h2>` with sentence case), a one-line helper paragraph, and the controls. Cards stack vertically; spacing is `1.25rem` between cards. The live preview is a fixed-position panel on screens wider than ~960 px; on narrower screens it stacks below the active section.

### D7. Form controls are custom but accessible

We build minimal field primitives instead of pulling in `@wordpress/components`:

- `<ToggleField>` — a styled `<input type="checkbox" role="switch">` with `aria-checked` mirroring the visual state.
- `<RadioField>` — fieldset with legend, semantic `<input type="radio">` group.
- `<CheckboxListField>` — fieldset with multiple `<input type="checkbox">`.
- `<TextField>` — labelled `<input type="text">`.
- `<TextareaField>` — labelled `<textarea>`.
- `<NumberField>` — labelled `<input type="number">` with min/max + helper.

Every primitive renders: label → control → helper text → optional error. `aria-describedby` ties helper + error to the control.

### D8. Save indicator is per-field, not global

When `update()` fires, the field that triggered it shows a tiny "Saved" pill next to its label (or "Saving…" while in flight). Global save bar is intentionally avoided — fields save independently as the admin tunes them, so a global bar would constantly flash.

### D9. Localized payload `MoonfarmerReactionsLeadCaptureAdminData`

```js
window.MoonfarmerReactionsLeadCaptureAdminData = {
    restRoot: 'https://example.com/wp-json/moonfarmer-reactions-lead-capture/v1/',
    nonce: '<wp_create_nonce("wp_rest")>',
    settings: { /* current settings */ },
    defaults: { /* Settings::DEFAULTS */ },
    choices: { /* Settings::CHOICES */ },
    schemaVersion: 1,
    reactions: ['love', 'insightful', 'funny', 'sad', 'surprised', 'angry'],
    version: '0.1.0',
    i18n: { /* strings */ }
};
```

Passes through `apply_filters('moonfarmer_reactions_lead_capture_admin_data', $payload)` before emission. Pro / extensions can add fields.

### D10. AdminServiceProvider owns the page + asset enqueue

The Session 6a `SettingsServiceProvider` registered the submenu and rendered a placeholder. This slice moves the render callback to `AdminServiceProvider::renderPage()` so the page output and the asset enqueue live in one place. The settings provider keeps the submenu registration (and the REST routes) so the REST surface remains separable from the UI.

Enqueue gates: `add_action('admin_enqueue_scripts', fn($hook) => $hook === 'settings_page_moonfarmer-reactions-lead-capture' && enqueue())`.

## Risks / Trade-offs

- **Risk**: the admin bundle bloats over time as Pro adds tabs. → Mitigation: filterable `moonfarmer_reactions_lead_capture_admin_data` lets Pro inject lazy-load triggers; bundle size for Free stays bounded. We'll add a CI check in Session 11.
- **Risk**: the live preview drifts from the front-end widget when the widget is updated. → Mitigation: the admin imports the same `ReactionBar.tsx`. Drift is impossible by construction.
- **Risk**: a setting saved by the admin doesn't immediately reflect on the front-end widget because the page is cached. → Mitigation: documented behaviour; cache must be flushed for settings changes to be visible. We'll add a "Flush cache" link in Session 12 alongside WordPress.org packaging.
- **Risk**: keyboard tab navigation doesn't match the visual order for screen-reader users. → Mitigation: tab order is left-to-right; no `tabindex > 0`; tab list is a single landmark.
- **Trade-off**: no @wordpress/components. We get visual consistency at the cost of admins seeing UI that doesn't exactly match other plugins' style. Acceptable — the design bar explicitly references Spectra/Kadence which also do custom UI.
- **Trade-off**: no animations between tabs. Acceptable for first cut; if user testing flags the abrupt feel we can add a 150 ms cross-fade.

## Migration Plan

No data migration. Activation is the same. Rollback is `git revert` + `npm run build`.

For deployment safety:

1. Land the change.
2. `npm run build` (dual entry now).
3. Visit `/wp-admin/options-general.php?page=moonfarmer-reactions-lead-capture` as admin.
4. Confirm the SPA renders with all four tabs accessible.
5. Toggle `icon_style` to "Emoji" → confirm preview updates immediately.
6. Confirm a "Saved" pill appears next to the field.
7. Reload the page. Hash routing keeps the current tab.
8. Disable JS in DevTools. Confirm the page degrades to "Loading…" plus the styled-but-static layout (graceful — visitors can't accidentally lock themselves out).

## Open Questions

- **Q1**: should the admin show the current Moonfarmer Reactions Lead Capture version near the wordmark? → **Decided yes** — keeps "what version am I on?" question one glance away.
- **Q2**: should the live preview show the capture form too? → **Decided no** for first cut; admins find the form more useful in context (on a real post) than mocked-up.
- **Q3**: should we ship "Reset to defaults" in this slice? → **Decided yes** — small button at the bottom of every section. Posts the section's defaults; doesn't reset the whole settings array.
