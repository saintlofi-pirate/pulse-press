## MODIFIED Requirements

### Requirement: Widget container auto-appended to single-post content

A `the_content` filter SHALL append the widget container HTML (produced by `WidgetMarkup::container`) to the rendered content of single-post views, gated by `apply_filters('moonfarmer_reactions_lead_capture_widget_auto_insert', true, $postType)` (default `true` for `post`, `false` for other post types). The filter SHALL skip the append when the rendered content already contains the string `data-moonfarmer-reactions-lead-capture-widget` (block or shortcode already placed the widget). The container SHALL NOT be appended on archive, search, feed, or admin contexts.

#### Scenario: Auto-insert on single post without manual placement

- **WHEN** a visitor loads a single post that contains no `[moonfarmer-reactions-lead-capture]` shortcode and no `moonfarmer-reactions-lead-capture/reactions` block
- **THEN** the rendered HTML contains exactly one `<div ... data-moonfarmer-reactions-lead-capture-widget data-moonfarmer-reactions-lead-capture-post-id="{id}">` after the post body

#### Scenario: Auto-insert skipped when manual marker is present

- **WHEN** a single post's content already contains the substring `data-moonfarmer-reactions-lead-capture-widget` (from block or shortcode)
- **THEN** the auto-insert filter returns the content unchanged

#### Scenario: Excerpt context does not inject

- **WHEN** `the_content` runs inside an archive loop where `is_singular()` is `false`
- **THEN** no widget container is appended
