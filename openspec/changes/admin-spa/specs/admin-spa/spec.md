## ADDED Requirements

### Requirement: Admin SPA mounts on the Moonfarmer Reactions Lead Capture settings screen

The admin app SHALL mount into `<div id="moonfarmer-reactions-lead-capture-admin">` on the WordPress page registered at `Settings → Moonfarmer Reactions Lead Capture` (slug `moonfarmer-reactions-lead-capture`). The mount SHALL run on `DOMContentLoaded` (or immediately if already loaded). The JS bundle and CSS SHALL be enqueued only on that page; no other admin or front-end page SHALL include them.

#### Scenario: Mount on the settings page

- **WHEN** an admin loads `/wp-admin/options-general.php?page=moonfarmer-reactions-lead-capture`
- **THEN** the page contains `<div id="moonfarmer-reactions-lead-capture-admin">` populated by the Preact app, and the admin JS/CSS handles are enqueued

#### Scenario: No enqueue on other admin pages

- **WHEN** an admin loads `/wp-admin/edit.php` or `/wp-admin/index.php`
- **THEN** the admin SPA assets are not enqueued

#### Scenario: No enqueue on the front end

- **WHEN** a visitor loads any front-end page
- **THEN** the admin SPA assets are not enqueued (only the widget assets are)

### Requirement: MoonfarmerReactionsLeadCaptureAdminData payload is localized

The plugin SHALL emit `window.MoonfarmerReactionsLeadCaptureAdminData` via `wp_localize_script` on the admin page, containing at minimum: `restRoot` (REST URL ending in `/wp-json/moonfarmer-reactions-lead-capture/v1/`), `nonce` (`wp_create_nonce('wp_rest')`), `settings` (current effective settings), `defaults` (`Settings::DEFAULTS`), `choices` (`Settings::CHOICES`), `schemaVersion` (`Settings::SCHEMA_VERSION`), `reactions` (filtered Reactions::TYPES), `version` (plugin version), and `i18n` (UI strings). The payload SHALL pass through `apply_filters('moonfarmer_reactions_lead_capture_admin_data', $payload)` before emission.

#### Scenario: Payload contains the settings and choices

- **WHEN** the admin page loads
- **THEN** `MoonfarmerReactionsLeadCaptureAdminData.settings.icon_style` is a string, `MoonfarmerReactionsLeadCaptureAdminData.choices.icon_style` is `['classic', 'emoji']`, and `MoonfarmerReactionsLeadCaptureAdminData.schemaVersion` is an integer

#### Scenario: Filter can extend the payload

- **WHEN** a plugin registers `add_filter('moonfarmer_reactions_lead_capture_admin_data', fn($d) => $d + ['proLicense' => '…'])`
- **THEN** `MoonfarmerReactionsLeadCaptureAdminData.proLicense` equals `'…'` on the rendered page

### Requirement: Hash-routed tabs

The SPA SHALL implement four tabs — `display`, `reactions`, `capture`, `privacy` — each addressable via `window.location.hash`. An empty or unrecognised hash SHALL default to `display`. The tablist SHALL follow the WAI-ARIA Tabs pattern: `role="tablist"`, each tab a `<button role="tab" aria-selected="…" aria-controls="…">`, the panel `<section role="tabpanel" aria-labelledby="…">`.

#### Scenario: Default tab on first visit

- **WHEN** the admin loads the page with no hash
- **THEN** the `display` tab renders as active

#### Scenario: Deep-linking to a tab

- **WHEN** the admin loads `?page=moonfarmer-reactions-lead-capture#capture`
- **THEN** the `capture` tab is active and its panel is visible

#### Scenario: Keyboard arrow navigation

- **WHEN** focus is on the `display` tab and the admin presses `ArrowRight`
- **THEN** focus and selection move to the `reactions` tab; `aria-selected` flips on the new tab and off the old one

#### Scenario: Home and End keys

- **WHEN** focus is on `reactions` and the admin presses `End`
- **THEN** focus moves to `privacy`

#### Scenario: Back/forward navigation

- **WHEN** the admin clicks through tabs and then presses the browser back button
- **THEN** the previous tab is restored without a full page reload

### Requirement: Optimistic settings update with rollback on failure

When the admin toggles a control, the local state SHALL apply the change immediately, then fire `POST /moonfarmer-reactions-lead-capture/v1/settings`. On success, the server response SHALL replace local state and a transient "Saved" pill SHALL render near the field for ~1.5 seconds. On failure, local state SHALL roll back and an inline error SHALL render with `role="alert"` until the next save or until the field is changed again.

#### Scenario: Successful save

- **WHEN** the admin flips `icon_style` to `emoji` and the REST endpoint returns 200
- **THEN** the field shows the new value, a "Saved" pill renders with `role="status" aria-live="polite"`, the pill disappears after ~1500 ms

#### Scenario: Failed save rolls back

- **WHEN** the REST endpoint returns 500
- **THEN** the field returns to its prior value, an inline `<p role="alert">` shows the error message, and the field remains editable

#### Scenario: Server-side sanitisation reflected back

- **WHEN** the admin posts `count_threshold = 99999` and the server clamps it to `1000`
- **THEN** local state updates to `1000` (the server response is authoritative)

### Requirement: Live widget preview reuses the front-end ReactionBar

The admin SHALL render a "Live preview" pane that imports and mounts the same `ReactionBar` component the front-end widget uses, with mock counts `{love: 24, insightful: 12, funny: 6, sad: 3, surprised: 2, angry: 1}` and a `data` prop built from the in-flight settings. The preview SHALL re-render on every settings change. Clicks on preview buttons SHALL NOT fire REST writes, SHALL NOT modify `localStorage`, and SHALL announce "Preview is read-only" via an `aria-live` region.

#### Scenario: Preview reflects icon style change

- **WHEN** the admin changes `icon_style` from `classic` to `emoji`
- **THEN** the preview pane re-renders with the new icon style in the same React commit

#### Scenario: Preview is muted

- **WHEN** the admin clicks a preview reaction button
- **THEN** no REST request is made, `localStorage` is unchanged, and a `role="status"` region announces "Preview is read-only"

#### Scenario: Preview labelled "Preview"

- **WHEN** inspecting the rendered preview
- **THEN** the pane has a visible "Preview" label and a helper sentence indicating the counts are illustrative

### Requirement: Every interactive control is keyboard accessible with visible focus

Every `<button>`, `<input>`, `<select>`, `<textarea>` rendered in the admin SHALL be reachable via Tab. Focus SHALL render a visible focus ring via `:focus-visible`. Tab order SHALL match visual order. `outline: none` SHALL NOT appear in admin CSS without an equally visible replacement.

#### Scenario: Tab traversal lands on every control

- **WHEN** the admin presses Tab repeatedly from the page title
- **THEN** focus visits every interactive control in visual order with a visible ring on each

#### Scenario: Reduced-motion friendly

- **WHEN** the admin browser has `prefers-reduced-motion: reduce`
- **THEN** all admin transitions are zeroed and field highlights snap rather than animate

### Requirement: Reset-to-defaults button per section

Each section SHALL include a "Reset to defaults" affordance at the bottom that POSTs the section's defaults (a subset of `Settings::DEFAULTS`) and updates local state. The button SHALL require an explicit click — no implicit reset on tab change.

#### Scenario: Reset narrows to section

- **WHEN** the admin on the `display` tab clicks "Reset to defaults"
- **THEN** every display-section field returns to its default; other-tab fields are unchanged

## MODIFIED Requirements

### Requirement: WordPress admin menu page registered

The plugin SHALL register a WordPress submenu under "Settings → Moonfarmer Reactions Lead Capture" via `add_options_page('Moonfarmer Reactions Lead Capture', 'Moonfarmer Reactions Lead Capture', 'manage_options', 'moonfarmer-reactions-lead-capture', $callback)`. The callback SHALL output `<div class="wrap"><div id="moonfarmer-reactions-lead-capture-admin">Loading…</div></div>` so the SPA can mount into `#moonfarmer-reactions-lead-capture-admin`. The callback SHALL be defined in `AdminServiceProvider` (moved from `SettingsServiceProvider`) so asset enqueue and page output live in the same provider.

#### Scenario: Menu appears for admins

- **WHEN** an admin loads `/wp-admin/options-general.php`
- **THEN** a submenu item labelled "Moonfarmer Reactions Lead Capture" exists linking to `/wp-admin/options-general.php?page=moonfarmer-reactions-lead-capture`

#### Scenario: Page renders the SPA mount node

- **WHEN** an admin loads `/wp-admin/options-general.php?page=moonfarmer-reactions-lead-capture`
- **THEN** the HTML contains `<div id="moonfarmer-reactions-lead-capture-admin">Loading…</div>`

#### Scenario: Page is not visible to non-admins

- **WHEN** a non-admin loads `/wp-admin/`
- **THEN** the "Moonfarmer Reactions Lead Capture" submenu item does not appear
