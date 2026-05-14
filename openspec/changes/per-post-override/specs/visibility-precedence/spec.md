## ADDED Requirements

### Requirement: VisibilityResolver is the single source of "should the widget render"

`PulsePress\Visibility\VisibilityResolver` SHALL expose `shouldRender(int $postId, string $context): bool` and `mode(int $postId): string` returning one of `'auto'|'on'|'off'`. Every render path (auto-insert, block, shortcode, WidgetMarkup) SHALL consult the resolver before emitting markup.

#### Scenario: Single source of truth audit

- **WHEN** running `grep -rE "should.*render|widget.*should|in_array.*auto_insert" app/` (excluding `app/Visibility/`)
- **THEN** the only matches are calls into `VisibilityResolver::shouldRender(...)` or the resolver's own implementation

### Requirement: Post meta wins precedence

The resolver SHALL read `get_post_meta($postId, '_pulsepress_widget_state', true)` first. When the value is `'on'`, the resolver SHALL return `true` regardless of any other setting. When the value is `'off'`, the resolver SHALL return `false` regardless of any other setting (including `hide_on_post_types`).

#### Scenario: Explicit on beats hide list

- **WHEN** `_pulsepress_widget_state = 'on'` for post 42 AND the post type is in `hide_on_post_types`
- **THEN** `shouldRender(42, 'auto')` returns `true`

#### Scenario: Explicit off beats auto-insert list

- **WHEN** `_pulsepress_widget_state = 'off'` for post 42 AND the post type is in `auto_insert_post_types`
- **THEN** `shouldRender(42, 'auto')` returns `false`

#### Scenario: Explicit off blocks block + shortcode too

- **WHEN** `_pulsepress_widget_state = 'off'` and someone places the `pulsepress/reactions` block on the post
- **THEN** the block render returns an empty string

### Requirement: Hide list suppresses auto, block, and shortcode

When the post meta is missing or `'auto'`, the resolver SHALL check `hide_on_post_types`. When the post type is in the list, the resolver SHALL return `false` for every context.

#### Scenario: Hide list catches block placement

- **WHEN** Pages are in `hide_on_post_types` and a Page contains the `pulsepress/reactions` block
- **THEN** the block render returns an empty string

#### Scenario: Hide list catches shortcode

- **WHEN** Pages are in `hide_on_post_types` and a Page contains `[pulsepress]`
- **THEN** `do_shortcode('[pulsepress]')` returns an empty string

### Requirement: Auto-insert list gates only the auto context

When post meta is `'auto'` and the post type is NOT in `hide_on_post_types`, the resolver SHALL behave per context:

- `'auto'` context returns `true` only when the post type is in `auto_insert_post_types`.
- `'block'` context returns `true` (manual placement is trusted).
- `'shortcode'` context returns `true` (manual placement is trusted).

#### Scenario: Block renders on a non-auto-insert post type

- **WHEN** the auto-insert list excludes Pages but a Page contains the block
- **THEN** the block render produces the widget container

#### Scenario: Auto-insert respects the list

- **WHEN** the auto-insert list excludes Pages and a Page is rendered (no block/shortcode)
- **THEN** `WidgetServiceProvider::maybeAppendWidget` does not append the container

### Requirement: pulsepress_visibility_mode filter is a last-mile override

The resolver SHALL pass the computed mode through `apply_filters('pulsepress_visibility_mode', $mode, $postId, $context)` before applying it. The filter MAY return any of `'auto'|'on'|'off'`; invalid values fall back to the original computed mode.

#### Scenario: Pro overrides for a paid feature

- **WHEN** a Pro plugin registers `add_filter('pulsepress_visibility_mode', fn() => 'off', 10, 3)` for unlicensed sites
- **THEN** every `shouldRender` call returns `false` regardless of post meta or settings

### Requirement: mode() returns sanitised string

`VisibilityResolver::mode(int $postId)` SHALL return exactly one of `'auto'|'on'|'off'`. Missing or unrecognised meta values SHALL be coerced to `'auto'`.

#### Scenario: Missing meta becomes auto

- **WHEN** `_pulsepress_widget_state` is unset for post 42
- **THEN** `mode(42)` returns `'auto'`

#### Scenario: Garbage value becomes auto

- **WHEN** `_pulsepress_widget_state` is set to `'xyz'` (manually corrupted)
- **THEN** `mode(42)` returns `'auto'`
