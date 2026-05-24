## MODIFIED Requirements

### Requirement: WordPress admin menu page registered

The plugin SHALL register a WordPress submenu under "Settings → Moonfarmer Reactions Lead Capture" via `add_options_page('Moonfarmer Reactions Lead Capture', 'Moonfarmer Reactions Lead Capture', 'manage_options', 'moonfarmer-reactions-lead-capture', $callback)`. The render callback SHALL be owned by `Moonfarmer\ReactionsLeadCapture\Providers\AdminServiceProvider` (moved from `SettingsServiceProvider`) and SHALL output `<div class="wrap"><div id="moonfarmer-reactions-lead-capture-admin">Loading…</div></div>` so the Preact SPA can mount. The settings submenu registration itself SHALL remain in `SettingsServiceProvider` so the REST surface and admin page are still independently wired.

#### Scenario: Provider ownership

- **WHEN** inspecting the codebase
- **THEN** `SettingsServiceProvider::registerAdminMenu()` references `[$this->app->get(AdminServiceProvider::class), 'renderPage']` (or equivalent) for the callback, and `AdminServiceProvider::renderPage()` outputs the mount HTML

#### Scenario: HTML output is unchanged from the user perspective

- **WHEN** the admin loads the settings page
- **THEN** the page contains `<div class="wrap"><div id="moonfarmer-reactions-lead-capture-admin">Loading…</div></div>` exactly as before, even though the render callback now lives in a different class
