## MODIFIED Requirements

### Requirement: pulsepress/reactions block is registered

The plugin SHALL register a Gutenberg block named `pulsepress/reactions` via `register_block_type` reading a `block.json` metadata file. The block SHALL be a dynamic block — its `render_callback` is `PulsePress\Blocks\ReactionsBlock::render` and it SHALL NOT register an editor or view script. `supports.html` SHALL be `false`. `attributes.postId` SHALL be declared as `{ type: 'integer' }`. The render callback SHALL consult `VisibilityResolver::shouldRender($postId, 'block')` and return an empty string when the resolver returns `false`.

#### Scenario: Block renders the widget container by default

- **WHEN** the block is placed on a post whose meta is `'auto'` and the post type is not hidden
- **THEN** the output HTML contains exactly one widget container

#### Scenario: Block returns empty when meta is off

- **WHEN** the block is placed on a post whose `_pulsepress_widget_state` is `'off'`
- **THEN** the block renders an empty string

#### Scenario: Block returns empty when post type is hidden

- **WHEN** the block is placed on a Page and Pages are in `hide_on_post_types`
- **THEN** the block renders an empty string

### Requirement: [pulsepress] shortcode is registered

The plugin SHALL register a shortcode `pulsepress` with optional `post_id` attribute. With no `post_id`, the shortcode SHALL default to `get_the_ID()`. The shortcode SHALL consult `VisibilityResolver::shouldRender($postId, 'shortcode')` and return an empty string when the resolver returns `false`. Unknown or non-public post ids SHALL also cause the shortcode to render an empty string silently.

#### Scenario: Shortcode renders on a post marked auto with hidden post type, but explicit on overrides

- **WHEN** Pages are in `hide_on_post_types` but a Page's meta is `_pulsepress_widget_state = 'on'`
- **THEN** `[pulsepress]` on that Page renders a widget container

#### Scenario: Shortcode honours hide list

- **WHEN** Pages are in `hide_on_post_types` and a Page's meta is `'auto'`
- **THEN** `[pulsepress]` on that Page renders an empty string
