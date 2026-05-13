## MODIFIED Requirements

### Requirement: PulsePressData payload contains REST root, nonce, and post id

The widget bootstrap SHALL emit `window.PulsePressData` via `wp_localize_script` with at minimum: `root` (the REST URL ending in `/wp-json/pulsepress/v1/`), `nonce` (the output of `wp_create_nonce('wp_rest')`), `postId` (the current `get_the_ID()`), `reactions` (the output of `apply_filters('pulsepress_reaction_types', Reactions::TYPES)`), `positiveReactions` (from settings, post-filter), `iconStyle` (from settings), `themeMode` (from settings), `widgetDesign` (from settings), `countVisibility` (from settings), `countThreshold` (from settings), and the `i18n` strings. The full payload SHALL pass through `apply_filters('pulsepress_widget_data', $payload)` before emission.

#### Scenario: Settings reach the payload

- **WHEN** the admin saves `icon_style = 'emoji'` and `theme_mode = 'dark'`
- **THEN** the next page render's `PulsePressData.iconStyle === 'emoji'` and `PulsePressData.themeMode === 'dark'`

#### Scenario: Defaults until first save

- **WHEN** no admin has saved settings yet
- **THEN** `PulsePressData.iconStyle === 'classic'`, `PulsePressData.themeMode === 'auto'`, `PulsePressData.positiveReactions === ['love', 'insightful', 'funny']`
