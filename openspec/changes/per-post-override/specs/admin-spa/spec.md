## MODIFIED Requirements

### Requirement: MoonfarmerReactionsLeadCaptureAdminData payload is localized

The plugin SHALL emit `window.MoonfarmerReactionsLeadCaptureAdminData` via `wp_localize_script` on the admin page, containing at minimum: `restRoot`, `nonce`, `settings`, `defaults`, `choices`, `schemaVersion`, `reactions`, `version`, `i18n`. The `choices` map SHALL include a `post_types` entry produced from `get_post_types(['public' => true], 'objects')` at request time, shaped as `{slug: label}`. The payload SHALL pass through `apply_filters('moonfarmer_reactions_lead_capture_admin_data', $payload)` before emission.

#### Scenario: choices.post_types reflects the site's actual public CPTs

- **WHEN** a site has registered a custom post type `recipe` and the admin loads the settings page
- **THEN** `MoonfarmerReactionsLeadCaptureAdminData.choices.post_types.recipe` exists with a label

#### Scenario: Settings DisplaySection uses choices.post_types

- **WHEN** the admin opens the Display tab
- **THEN** both "Auto-insert on" and "Never show on" CheckboxListField components render an option per key in `choices.post_types`

## ADDED Requirements

### Requirement: Per-post meta box rendered on every public post type

`Moonfarmer\ReactionsLeadCapture\Admin\WidgetStateMetaBox` SHALL register a meta box on every post type returned by `apply_filters('moonfarmer_reactions_lead_capture_meta_box_post_types', get_post_types(['public' => true]))`. The meta box SHALL render a `<fieldset>` with three radio inputs (Auto / Always show / Always hide) bound to the `_moonfarmer_reactions_lead_capture_widget_state` post meta. Save SHALL be handled by the standard post-save flow + REST `register_post_meta`.

#### Scenario: Meta box appears on Posts

- **WHEN** an admin opens any post in the editor
- **THEN** a meta box titled "Moonfarmer Reactions Lead Capture reactions" appears in the sidebar with three radio options

#### Scenario: Meta box appears on Pages

- **WHEN** an admin opens any Page in the editor
- **THEN** the meta box appears (Pages are public by default)

#### Scenario: Filter can remove the meta box

- **WHEN** `add_filter('moonfarmer_reactions_lead_capture_meta_box_post_types', fn() => ['post'])` is registered
- **THEN** the meta box appears only on Posts (and not on Pages or CPTs)

#### Scenario: Save persists to post meta

- **WHEN** the admin selects "Always hide" and saves the post
- **THEN** `get_post_meta($postId, '_moonfarmer_reactions_lead_capture_widget_state', true)` returns `'off'`

#### Scenario: REST exposes the meta

- **WHEN** an authenticated REST `GET /wp/v2/posts/{id}` request is made
- **THEN** the response's `meta` object includes `_moonfarmer_reactions_lead_capture_widget_state` so the block editor can save it via REST
