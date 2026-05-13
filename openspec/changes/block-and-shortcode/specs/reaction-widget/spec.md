## MODIFIED Requirements

### Requirement: Widget container auto-appended to single-post content

A `the_content` filter SHALL append the widget container HTML (produced by `WidgetMarkup::container`) to the rendered content of single-post views, gated by `apply_filters('pulsepress_widget_auto_insert', true, $postType)` (default `true` for `post`, `false` for other post types). The filter SHALL skip the append when the rendered content already contains the string `data-pulsepress-widget` (block or shortcode already placed the widget). The container SHALL NOT be appended on archive, search, feed, or admin contexts.

#### Scenario: Auto-insert on single post without manual placement

- **WHEN** a visitor loads a single post that contains no `[pulsepress]` shortcode and no `pulsepress/reactions` block
- **THEN** the rendered HTML contains exactly one `<div ... data-pulsepress-widget data-pulsepress-post-id="{id}">` after the post body

#### Scenario: Auto-insert skipped when manual marker is present

- **WHEN** a single post's content already contains the substring `data-pulsepress-widget` (from block or shortcode)
- **THEN** the auto-insert filter returns the content unchanged

#### Scenario: Excerpt context does not inject

- **WHEN** `the_content` runs inside an archive loop where `is_singular()` is `false`
- **THEN** no widget container is appended
