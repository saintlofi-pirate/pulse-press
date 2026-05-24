## Why

Today the widget either auto-inserts on `the_content` or doesn't appear. Authors who want manual placement (above a CTA, inside a custom layout, on a Page) have no way to put it there without a code snippet. Session 7 closes that loop with two equivalent placement APIs — a Gutenberg block and a classic shortcode — both backed by the same widget markup the auto-insert filter emits. Pro and 3rd-party integrations can use either as a Free-supported entry point.

Free remains generous: both placements work out of the box, no settings required. Privacy stays first-class: the block has no JavaScript editor footprint (dynamic render via PHP callback), so the admin asset graph stays unchanged. Accessibility lives in the same component the auto-insert path emits — every placement renders the WCAG-AA reaction bar from Session 3.

## What Changes

- Register a dynamic Gutenberg block `moonfarmer-reactions-lead-capture/reactions` via `register_block_type` reading a `block.json` metadata file at `blocks/reactions/block.json`. The block has no `editor_script` — `render_callback` runs PHP that emits the same `<div class="moonfarmer-reactions-lead-capture" data-moonfarmer-reactions-lead-capture-widget data-moonfarmer-reactions-lead-capture-post-id="…">` container the auto-insert filter uses. The block's editor preview comes from the dynamic render itself.
- Add `[moonfarmer-reactions-lead-capture]` shortcode that accepts optional `post_id` (defaults to the current post ID resolved via `get_the_ID()`). Both block and shortcode share a single `WidgetMarkup::container(int $postId): string` helper so output stays in sync.
- Update `WidgetServiceProvider::maybeAppendWidget` to skip auto-insert when the rendered content already contains `data-moonfarmer-reactions-lead-capture-widget` (cheap substring match). This is the duplicate-detect rule from gap decision 8 D3.
- Add `Moonfarmer\ReactionsLeadCapture\Providers\BlockServiceProvider` registered in `app/bootstrap.php` after `WidgetServiceProvider`. Provides:
  - `init` action handler that calls `register_block_type` with the block.json path
  - `init` action handler that calls `add_shortcode('moonfarmer-reactions-lead-capture', [Shortcode, 'render'])`
  - A small `Moonfarmer\ReactionsLeadCapture\Blocks\ReactionsBlock` class with `render(array $attrs, string $content, $block): string` static method
  - A small `Moonfarmer\ReactionsLeadCapture\Blocks\WidgetMarkup` class with `container(int $postId): string` returning the shared HTML
- Add `moonfarmer_reactions_lead_capture_widget_container_attrs` filter so Pro and theme integrations can adjust the `data-*` attributes on the container before output (e.g., add an `data-moonfarmer-reactions-lead-capture-variant` for A/B testing).
- Update front-end JS: it already resolves multiple `[data-moonfarmer-reactions-lead-capture-widget]` elements via querySelectorAll, so the block + shortcode get the same Preact mount behaviour out of the box.
- Block does NOT need a build step. block.json declares `apiVersion: 3`, `title: "Moonfarmer Reactions Lead Capture reactions"`, `category: "widgets"`, `supports.html: false`, `attributes.postId: { type: "integer" }`. WordPress serves the block from PHP-only.
- **BREAKING**: none. The auto-insert path keeps working as before, just gated by the duplicate-detect for posts that opt into manual placement.

## Capabilities

### New Capabilities

- `block-shortcode-placement`: defines the contracts — block name + JSON schema, shortcode tag + attributes, the shared `data-moonfarmer-reactions-lead-capture-widget` container, the duplicate-detect rule on auto-insert, the `moonfarmer_reactions_lead_capture_widget_container_attrs` filter, and the editor-render behaviour.

### Modified Capabilities

- `reaction-widget`: auto-insert now skips when content already contains `data-moonfarmer-reactions-lead-capture-widget`, preventing duplicate widgets when a post has both manual placement AND auto-insert enabled.

## Impact

- **New files**: `app/Blocks/ReactionsBlock.php`, `app/Blocks/WidgetMarkup.php`, `app/Blocks/Shortcode.php`, `app/Providers/BlockServiceProvider.php`, `blocks/reactions/block.json`, `tests/Unit/WidgetMarkupTest.php`, `tests/Unit/ShortcodeTest.php`.
- **Modified files**: `app/Providers/WidgetServiceProvider.php` (auto-insert now duplicate-detect + delegates container HTML to `WidgetMarkup`), `app/bootstrap.php` (registers `BlockServiceProvider`), `docs/hooks-and-filters.md` (adds `moonfarmer_reactions_lead_capture_widget_container_attrs`).
- **REST API**: unchanged.
- **Database changes**: none.
- **Filters introduced**: `moonfarmer_reactions_lead_capture_widget_container_attrs` (PHP, filterable HTML attributes for the widget container).
- **Privacy**: no new data collected. The block ships zero editor JS; the shortcode is server-rendered.
- **Performance**: no impact on the widget bundle. Block registration is a single `register_block_type` call on `init`. Auto-insert duplicate-detect is one `str_contains` call per post render.
- **Free/Pro boundary**: untouched. Pro can layer extra block variants via `register_block_style` and via `moonfarmer_reactions_lead_capture_widget_container_attrs` for A/B testing tags.
