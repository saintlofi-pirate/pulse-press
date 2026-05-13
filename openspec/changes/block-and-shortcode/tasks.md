## 1. Shared container helper

- [ ] 1.1 Create `app/Blocks/WidgetMarkup.php` (`final class`). Static method `container(int $postId): string` returning the `<div>` HTML; filters attributes via `pulsepress_widget_container_attrs`; serialises attributes in insertion order with empty-string values rendered as bare attributes.
- [ ] 1.2 Private `attrString(array $attrs): string` helper that builds the attribute string with `esc_attr` on values.

## 2. Block + shortcode

- [ ] 2.1 Create `blocks/reactions/block.json` with the metadata from design.md D1 (apiVersion 3, supports html=false + align=['wide','full'], attributes.postId).
- [ ] 2.2 Create `blocks/reactions/render.php` that calls `PulsePress\Blocks\ReactionsBlock::render($attributes, $content, $block)`.
- [ ] 2.3 Create `app/Blocks/ReactionsBlock.php` with `static render(array $attrs, string $content, $block): string` that resolves the post id (`$attrs['postId']` falling back to `get_the_ID()`), validates the post (skip if not public), and returns `WidgetMarkup::container($postId)`.
- [ ] 2.4 Create `app/Blocks/Shortcode.php` with `static render(array $atts, ?string $content, string $tag): string` that resolves the same way; returns empty string when invalid.

## 3. Service provider

- [ ] 3.1 Create `app/Providers/BlockServiceProvider.php` extending `ServiceProvider`. In `boot()`, hook `init` to `register_block_type(PULSEPRESS_DIR . 'blocks/reactions')` and `add_shortcode('pulsepress', [Shortcode::class, 'render'])`.
- [ ] 3.2 Register `BlockServiceProvider::class` in `app/bootstrap.php` after `WidgetServiceProvider`.

## 4. Update auto-insert

- [ ] 4.1 In `WidgetServiceProvider::maybeAppendWidget`, after the singular + filter checks, add: `if (str_contains($content, 'data-pulsepress-widget')) { return $content; }`.
- [ ] 4.2 Replace the inline sprintf with `WidgetMarkup::container($postId)`.

## 5. Tests

- [ ] 5.1 `tests/Unit/WidgetMarkupTest.php`: container produces the expected HTML; filter receives postId; filter can add/remove/replace attributes; empty-string values render as bare attributes.
- [ ] 5.2 `tests/Unit/ShortcodeTest.php`: with no atts returns the markup for current post; with `post_id` returns markup for that id; with invalid `post_id` returns empty string.
- [ ] 5.3 Update `tests/Unit/WidgetServiceProviderTest.php` with a scenario: content containing `data-pulsepress-widget` is not appended to.
- [ ] 5.4 Update `tests/Unit/BootstrapTest.php` autoload assertions for `WidgetMarkup`, `ReactionsBlock`, `Shortcode`, `BlockServiceProvider`.
- [ ] 5.5 Run `composer test`; confirm green.

## 6. Manual verification

- [ ] 6.1 Reactivate plugin.
- [ ] 6.2 Open a post in Gutenberg, search for "PulsePress" in the inserter, insert the block. Save. View the post. Confirm one widget renders.
- [ ] 6.3 Open a different post, add `[pulsepress]` to the body. View. Confirm one widget renders.
- [ ] 6.4 Edit a post that has both the block AND auto-insert enabled. View. Confirm exactly one widget renders (the block's, auto-insert skips).
- [ ] 6.5 Test `[pulsepress post_id="<other-post-id>"]` — confirm the widget renders against the other post's counts.

## 7. Docs and final

- [ ] 7.1 Update `docs/hooks-and-filters.md`: add `pulsepress_widget_container_attrs`.
- [ ] 7.2 Run `openspec validate block-and-shortcode --strict --no-interactive` clean.
- [ ] 7.3 Commit (no co-auth).
