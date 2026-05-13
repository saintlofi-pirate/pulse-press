## Why

Session 6a put the settings REST contract in place. This slice gives admins the page they'll actually use to drive PulsePress: a Preact-based single-page admin that feels like a modern product, not a wp-admin form. The settings page is where the rubber meets the road for the whole "modern, sleek, minimal, clean, smooth SPA with live widget preview" design bar codified in `docs/pulsepress-v1-plan.md` §Admin UI Design Direction.

Free remains generous: every reactive piece of the front-end widget is also adjustable from this one page. Pro will layer extra tabs into the same shell rather than adding a parallel admin page. Privacy stays first-class: every REST write requires `manage_options`; the page never renders capture data or visitor PII (that's Session 9's analytics dashboard). Accessibility is baked in: full keyboard navigation, ARIA-correct tabs (←/→ arrows, Home/End), visible focus rings, save-state announcements via `role="status"`, error announcements via `role="alert"`.

## What Changes

- Add a second Vite entry `resources/admin/index.tsx` that mounts a Preact app into `#pulsepress-admin` (the placeholder Session 6a registered). The admin app is a separate bundle from the widget bundle so the front-end JS budget stays unaffected.
- Add `resources/admin/App.tsx` with a hash router for tabs (`#display`, `#reactions`, `#capture`, `#privacy`), default tab `#display`. Back/forward browser navigation is honoured.
- Add `resources/admin/api.ts` with `fetchSettings()` and `saveSettings(partial)` typed wrappers around the Session 6a REST contract. The save call sends `X-WP-Nonce` from `PulsePressAdminData.nonce`.
- Add `resources/admin/hooks/useSettingsState.ts` — the state container. Reads initial settings on mount, exposes `update(partial)` that immediately applies the change locally, fires the REST save in the background, rolls back on failure with an inline error toast, and shows a "Saved" pill on success that fades after 1.5 s.
- Add a layout `resources/admin/components/PulsePressLayout.tsx` (header with PulsePress wordmark + version + status pill; left/top tabs; main pane; live preview pane).
- Add four section components: `DisplaySection.tsx` (count visibility, threshold, widget design, icon style, theme mode, auto-insert post types, auto-insert position), `ReactionsSection.tsx` (positive-reactions checklist, allow_guest_reactions toggle), `CaptureSection.tsx` (consent text + version, retention placeholder, delete-on-uninstall toggle), `PrivacySection.tsx` (a privacy summary + the delete-on-uninstall toggle + a "view the privacy doc" link).
- Add `resources/admin/components/LivePreview.tsx` — wraps the existing `ReactionBar` from the widget source tree, feeds it the in-flight settings as if PulsePressData reflected them, supplies mock counts (`{love: 24, insightful: 12, funny: 6, sad: 3, surprised: 2, angry: 1}`) clearly labelled "Preview", mutes clicks (handler is a no-op + announces "Preview is read-only" via aria-live), and re-renders on every settings change.
- Add `resources/admin/components/fields/` primitives: `ToggleField`, `RadioField`, `CheckboxListField`, `TextField`, `TextareaField`, `NumberField`. Each follows the accessibility bar (label, helper text, error region, focus ring, reduced-motion friendly).
- Add `resources/admin/styles/admin.css` — scoped under `.pulsepress-admin` with CSS custom properties matching the widget's accent. Card-based layout; generous spacing; system font; sentence case; 150–200 ms transitions; `:focus-visible` rings; `prefers-reduced-motion` handling.
- Update `vite.config.js` for dual entries (widget + admin), both emitting hashed JS + CSS under `dist/`.
- Add `PulsePress\Providers\AdminServiceProvider` (registered before WidgetServiceProvider in `app/bootstrap.php`) that:
  - On `admin_enqueue_scripts`, when the current page is the PulsePress settings screen, registers + enqueues the admin JS + CSS via the same `Manifest` reader from Session 3, and emits `window.PulsePressAdminData = {restRoot, nonce, settings, defaults, choices, schemaVersion, reactions, version}` via `wp_localize_script`. The payload is filterable via `pulsepress_admin_data`.
- Move the admin submenu render callback out of `SettingsServiceProvider` into `AdminServiceProvider` so the asset enqueue and page output live in one place. The placeholder div from Session 6a stays the mount node.
- **BREAKING**: none.

## Capabilities

### New Capabilities

- `admin-spa`: defines the admin SPA contract — mount node, asset enqueue gating, localised data shape, hash-routing tab semantics, live preview behaviour, optimistic save flow, save/error UX, accessibility bar.

### Modified Capabilities

- `settings-api`: the submenu render callback moves from `SettingsServiceProvider` to `AdminServiceProvider`; the rendered HTML is unchanged. REST behaviour is unchanged.

## Impact

- **New files**: `resources/admin/index.tsx`, `resources/admin/App.tsx`, `resources/admin/api.ts`, `resources/admin/types.ts`, `resources/admin/hooks/useSettingsState.ts`, `resources/admin/components/PulsePressLayout.tsx`, `resources/admin/components/SectionNav.tsx`, `resources/admin/components/LivePreview.tsx`, `resources/admin/components/fields/*.tsx` (6 files), `resources/admin/sections/DisplaySection.tsx`, `resources/admin/sections/ReactionsSection.tsx`, `resources/admin/sections/CaptureSection.tsx`, `resources/admin/sections/PrivacySection.tsx`, `resources/admin/styles/admin.css`, `app/Providers/AdminServiceProvider.php`, `tests/Unit/AdminServiceProviderTest.php`.
- **Modified files**: `vite.config.js` (dual entry), `app/Providers/SettingsServiceProvider.php` (admin-menu render callback delegates to AdminServiceProvider), `app/bootstrap.php` (registers AdminServiceProvider), `tests/Stubs/wp_functions.php` (shim `get_current_screen`).
- **REST API**: no new endpoints; consumes Session 6a verbatim.
- **Filters introduced**: `pulsepress_admin_data` (PHP, filterable admin localized payload).
- **Privacy**: the admin app never displays capture data or visitor PII; it only renders settings. The live preview uses mock counts.
- **Performance**: admin bundle is separate from the widget bundle — adding the admin does not increase the public-page bundle size. Admin assets enqueue only on the settings page, not site-wide.
- **Accessibility**: full WCAG 2.1 AA — semantic `<button>` tabs with WAI-ARIA Tabs pattern (`role="tab"`, `aria-selected`, `aria-controls`, `←/→/Home/End` keys), real `<form>` controls with labels, visible focus rings, role="status" / role="alert" feedback, reduced-motion honoured.
- **Free/Pro boundary**: untouched. Pro can hook `pulsepress_admin_data` to inject additional config (license key UI seed, etc.) and `pulsepress_settings` to layer extra fields. The SPA dynamically renders any unknown setting fields as a generic JSON inspector for forward compatibility.
