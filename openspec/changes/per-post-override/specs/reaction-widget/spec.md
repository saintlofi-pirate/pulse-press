## MODIFIED Requirements

### Requirement: Widget container auto-appended to single-post content

A `the_content` filter SHALL append the widget container HTML (produced by `WidgetMarkup::container`) to single-post content only when `VisibilityResolver::shouldRender($postId, 'auto')` returns `true`. The filter SHALL also skip the append when the rendered content already contains the string `data-moonfarmer-reactions-lead-capture-widget` (block or shortcode already placed the widget). The container SHALL NOT be appended on archive, search, feed, or admin contexts.

#### Scenario: Resolver returns false skips auto-insert

- **WHEN** `_moonfarmer_reactions_lead_capture_widget_state = 'off'` on the current post
- **THEN** the auto-insert filter returns the content unchanged

#### Scenario: Resolver returns true and no manual marker present

- **WHEN** the resolver returns `true` and the content does not contain `data-moonfarmer-reactions-lead-capture-widget`
- **THEN** one widget container is appended

#### Scenario: Resolver returns true but content already has marker

- **WHEN** the resolver returns `true` and the content already contains `data-moonfarmer-reactions-lead-capture-widget`
- **THEN** auto-insert returns content unchanged (block/shortcode already placed it)
