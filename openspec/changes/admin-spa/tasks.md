## 1. Vite config + admin source skeleton

- [x] 1.1 Update `vite.config.js`: declare two entries (`widget` and `admin`) under `build.rollupOptions.input`. Both emit hashed JS + CSS into `dist/`.
- [x] 1.2 Create `resources/admin/index.tsx` mounting `<App />` into `#moonfarmer-reactions-lead-capture-admin` on `DOMContentLoaded`.
- [x] 1.3 Create `resources/admin/types.ts` with `MoonfarmerReactionsLeadCaptureAdminData` interface mirroring the PHP payload.
- [x] 1.4 Create `resources/admin/api.ts` with `fetchSettings()` and `saveSettings(partial)`.

## 2. State + hooks

- [x] 2.1 Create `resources/admin/hooks/useSettingsState.ts` exposing `{ settings, defaults, choices, schema, savingFields, errors, update(partial), reset(sectionKeys) }`. Initial state seeds from `MoonfarmerReactionsLeadCaptureAdminData.settings`. `update` is the optimistic save flow described in design.md D4.

## 3. Layout + nav

- [x] 3.1 Create `resources/admin/App.tsx` with hash-routed tab switching and a `popstate` listener.
- [x] 3.2 Create `resources/admin/components/Moonfarmer Reactions Lead CaptureLayout.tsx` (header with wordmark + version + status pill; main two-column layout where wide screens show preview alongside, narrow screens stack).
- [x] 3.3 Create `resources/admin/components/SectionNav.tsx` — WAI-ARIA Tabs with ←/→/Home/End keys, `role="tablist"` + `role="tab"` + `aria-selected` + `aria-controls`.

## 4. Field primitives

- [x] 4.1 Create `resources/admin/components/fields/ToggleField.tsx` — labelled `<input type="checkbox" role="switch">` with helper + aria-describedby + saved/saving pill slot.
- [x] 4.2 Create `resources/admin/components/fields/RadioField.tsx` — fieldset+legend+radio group.
- [x] 4.3 Create `resources/admin/components/fields/CheckboxListField.tsx` — fieldset with multiple checkboxes (for positive_reactions, auto_insert_post_types).
- [x] 4.4 Create `resources/admin/components/fields/TextField.tsx`, `TextareaField.tsx`, `NumberField.tsx`.

## 5. Section components

- [x] 5.1 `resources/admin/sections/DisplaySection.tsx` — count visibility, threshold (NumberField, only when threshold mode), widget design, icon style, theme mode, auto-insert post types (CheckboxListField; default `['post', 'page']` derived from the choices map), auto-insert position.
- [x] 5.2 `resources/admin/sections/ReactionsSection.tsx` — positive_reactions (CheckboxListField against Reactions::TYPES), allow_guest_reactions (ToggleField).
- [x] 5.3 `resources/admin/sections/CaptureSection.tsx` — consent_text (TextareaField), consent_text_version (TextField), retention_days (NumberField 0–3650), small explanatory copy.
- [x] 5.4 `resources/admin/sections/PrivacySection.tsx` — privacy summary, delete_on_uninstall (ToggleField), retention summary read-only.

## 6. Live preview

- [x] 6.1 Create `resources/admin/components/LivePreview.tsx` that imports `ReactionBar` from `resources/widget/components/ReactionBar.tsx` and renders it inside a labelled card with mock counts and a "Preview is read-only" live region.
- [x] 6.2 Extend the widget types if needed so `ReactionBar` accepts a `previewMode` prop OR wrap with a shadow renderer that intercepts click handlers. Smaller invasion: pass a sentinel root that triggers a guard in the post handler.

## 7. CSS

- [x] 7.1 Create `resources/admin/styles/admin.css` scoped under `.moonfarmer-reactions-lead-capture-admin` with: CSS custom properties (`--pp-accent`, `--pp-text`, `--pp-bg`, etc.), header layout, tablist styling, card styling, field primitives, preview pane layout, save-state pills (`.moonfarmer-reactions-lead-capture-pill--saved`, `.moonfarmer-reactions-lead-capture-pill--saving`), error styling, reduced-motion handling.
- [x] 7.2 Reset rules: keep WordPress's `.wrap` defaults but override `.wrap` padding to match the design bar.

## 8. PHP

- [x] 8.1 Create `app/Providers/AdminServiceProvider.php` with:
  - `register()` binding nothing new (uses Manifest + SettingsRepository).
  - `boot()` hooks `admin_enqueue_scripts` (gated on the settings screen) and supplies the localized payload.
  - `renderPage()` outputs the mount HTML.
- [x] 8.2 Update `app/Providers/SettingsServiceProvider.php::registerAdminMenu()` to delegate the render callback to `AdminServiceProvider`.
- [x] 8.3 Register `AdminServiceProvider::class` in `app/bootstrap.php` after `SettingsServiceProvider`.

## 9. Tests

- [x] 9.1 `tests/Unit/AdminServiceProviderTest.php`: enqueue does NOT fire on the front end; enqueue DOES fire when hook context matches the settings page; MoonfarmerReactionsLeadCaptureAdminData payload contains the required fields and passes through `moonfarmer_reactions_lead_capture_admin_data` filter.
- [x] 9.2 Update `tests/Unit/BootstrapTest.php` autoload assertion for `AdminServiceProvider`.
- [x] 9.3 Run `composer test`; confirm green.

## 10. Manual browser verify

- [x] 10.1 `npm run build` — confirm two entry chunks in `dist/.vite/manifest.json` (`resources/admin/index.tsx` and `resources/widget/index.ts`).
- [x] 10.2 Reactivate plugin. Visit `/wp-admin/options-general.php?page=moonfarmer-reactions-lead-capture` as admin. Confirm SPA renders with the header, tabs, Display section, and preview pane.
- [x] 10.3 Click each tab. Confirm hash routing + preview pane stays.
- [x] 10.4 Toggle `icon_style` to "Emoji" — confirm preview updates immediately; "Saved" pill appears; reload the page; the setting persists.
- [x] 10.5 Toggle `allow_guest_reactions` off — confirm anon `POST /react` returns 401 (re-verify Session 6a flow).
- [x] 10.6 Tab through every control with keyboard only. Confirm visible focus rings on each.
- [x] 10.7 Enable VoiceOver. Confirm tab navigation announces selection ("Display, tab, 1 of 4, selected"); save events announce via the status region.

## 11. Final

- [x] 11.1 Run `openspec validate admin-spa --strict --no-interactive` clean.
- [x] 11.2 Update `docs/hooks-and-filters.md`: add `moonfarmer_reactions_lead_capture_admin_data` filter.
- [x] 11.3 Commit (no Co-Authored-By).
