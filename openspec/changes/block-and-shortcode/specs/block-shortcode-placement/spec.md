## ADDED Requirements

### Requirement: pulsepress/reactions block is registered

The plugin SHALL register a Gutenberg block named `pulsepress/reactions` via `register_block_type` reading a `block.json` metadata file. The block SHALL be a dynamic block — its `render_callback` is `PulsePress\Blocks\ReactionsBlock::render` and it SHALL NOT register an editor or view script. `supports.html` SHALL be `false`. `attributes.postId` SHALL be declared as `{ type: 'integer' }`.

#### Scenario: Block appears in the inserter

- **WHEN** an admin opens the Gutenberg post editor and searches for "PulsePress"
- **THEN** the `pulsepress/reactions` block appears in the Widgets category

#### Scenario: Block renders the widget container

- **WHEN** the block is placed on post 42 and the editor or front-end renders it
- **THEN** the output HTML contains exactly one `<div class="pulsepress" data-pulsepress-widget data-pulsepress-post-id="42"></div>`

#### Scenario: Block with explicit postId attribute

- **WHEN** the block is saved with `postId` attribute `99`
- **THEN** the rendered HTML uses `99` as the `data-pulsepress-post-id` value (even if rendered on a different page)

### Requirement: [pulsepress] shortcode is registered

The plugin SHALL register a shortcode `pulsepress` with optional `post_id` attribute that emits the same widget container HTML the block emits. With no `post_id`, the shortcode SHALL default to `get_the_ID()`. Unknown or non-public post ids SHALL cause the shortcode to render an empty string silently.

#### Scenario: Shortcode with no attributes inside a post

- **WHEN** the post body contains `[pulsepress]` and the post is rendered
- **THEN** the output HTML contains `<div class="pulsepress" data-pulsepress-widget data-pulsepress-post-id="<current-post-id>"></div>`

#### Scenario: Shortcode with explicit post_id

- **WHEN** the post body contains `[pulsepress post_id="123"]` and post 123 exists and is public
- **THEN** the rendered HTML uses `123` as the `data-pulsepress-post-id` value

#### Scenario: Invalid post_id

- **WHEN** `[pulsepress post_id="9999999"]` is used and the post does not exist
- **THEN** the shortcode renders an empty string (no error, no markup)

### Requirement: WidgetMarkup::container is the single source of HTML

Every placement path — auto-insert, block render, shortcode render — SHALL call `PulsePress\Blocks\WidgetMarkup::container(int $postId): string` to produce the widget container HTML. The function SHALL apply `apply_filters('pulsepress_widget_container_attrs', $attrs, $postId)` to the attribute map before serialising into the element string.

#### Scenario: Output is consistent across paths

- **WHEN** the auto-insert filter, the block render, and the shortcode all run on post 42
- **THEN** each produces the same `<div class="pulsepress" data-pulsepress-widget data-pulsepress-post-id="42"></div>` string

#### Scenario: Filter can extend attributes

- **WHEN** a plugin registers `add_filter('pulsepress_widget_container_attrs', fn($a, $id) => $a + ['data-pulsepress-variant' => 'b'], 10, 2)`
- **THEN** the rendered HTML contains `data-pulsepress-variant="b"`

### Requirement: pulsepress_widget_container_attrs filter exposes a complete attribute map

The filter SHALL receive an associative array containing at minimum `class`, `data-pulsepress-widget`, `data-pulsepress-post-id` keys. The filter SHALL receive the post id as its second argument. The returned array SHALL be serialised in insertion order, with empty-string values rendered as bare attributes (`data-pulsepress-widget` with no `="…"`).

#### Scenario: Default attributes

- **WHEN** no filter is registered
- **THEN** `apply_filters('pulsepress_widget_container_attrs', ...)` returns `['class' => 'pulsepress', 'data-pulsepress-widget' => '', 'data-pulsepress-post-id' => '42']`

#### Scenario: Filter receives postId

- **WHEN** a filter is registered as `fn($attrs, $postId) => …`
- **THEN** `$postId` equals the integer post id passed into `WidgetMarkup::container`

## MODIFIED Requirements

### Requirement: Widget container auto-appended to single-post content

A `the_content` filter SHALL append the widget container HTML (produced by `WidgetMarkup::container`) to the rendered content of single-post views, gated by `apply_filters('pulsepress_widget_auto_insert', true, $postType)` (default `true` for `post`, `false` for other post types). The filter SHALL skip the append when the rendered content already contains the string `data-pulsepress-widget` (block or shortcode already placed the widget). The container SHALL NOT be appended on archive, search, feed, or admin contexts.

#### Scenario: Auto-insert on single post without manual placement

- **WHEN** a visitor loads a single post that contains no `[pulsepress]` shortcode and no `pulsepress/reactions` block
- **THEN** the rendered HTML contains exactly one `<div ... data-pulsepress-widget data-pulsepress-post-id="{id}">` after the post body

#### Scenario: Auto-insert skipped when block is present

- **WHEN** a single post contains the `pulsepress/reactions` block
- **THEN** the rendered HTML contains exactly one widget container (from the block) and the auto-insert filter does not append a second one

#### Scenario: Auto-insert skipped when shortcode is present

- **WHEN** a single post contains the `[pulsepress]` shortcode
- **THEN** the rendered HTML contains exactly one widget container (from the shortcode) and the auto-insert filter does not append a second one

#### Scenario: Auto-insert disabled via filter

- **WHEN** `add_filter('pulsepress_widget_auto_insert', '__return_false')` is registered
- **THEN** no widget container is added by the auto-insert filter (manual block/shortcode placement still works)

#### Scenario: Excerpt context does not inject

- **WHEN** `the_content` runs inside an archive loop where `is_singular()` is `false`
- **THEN** no widget container is appended
