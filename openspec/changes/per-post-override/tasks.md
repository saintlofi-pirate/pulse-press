## 1. Resolver

- [ ] 1.1 Create `app/Visibility/VisibilityResolver.php` (`final class`). Constructor `(SettingsRepository $settings)`. Methods: `mode(int $postId): string`, `shouldRender(int $postId, string $context): bool`, public static `sanitiseMode(mixed $value): string`.
- [ ] 1.2 `mode()` reads `_moonfarmer_reactions_lead_capture_widget_state` post meta, coerces unknown values to `'auto'`, passes through `moonfarmer_reactions_lead_capture_visibility_mode` filter with `($mode, $postId, '')`.
- [ ] 1.3 `shouldRender()` consults `mode()` first: 'on' → true, 'off' → false. Then checks `hide_on_post_types` against the post's type. Then context-specific gate: `'auto'` → `auto_insert_post_types` check, `'block'` and `'shortcode'` → true.

## 2. Settings + sanitiser

- [ ] 2.1 Update `Settings::DEFAULTS` to add `hide_on_post_types => []`.
- [ ] 2.2 Update `Settings::sanitise` to handle `hide_on_post_types` as a string array; drop non-strings.
- [ ] 2.3 Add `moonfarmer_reactions_lead_capture_meta_box_post_types` and `moonfarmer_reactions_lead_capture_visibility_mode` filter docs in `docs/hooks-and-filters.md`.

## 3. Meta box + register_post_meta

- [ ] 3.1 Create `app/Admin/WidgetStateMetaBox.php` (`final class`). Methods: `register()` (hooks `add_meta_boxes` + `save_post_{type}` for every applicable type), `addBoxes()` (calls `add_meta_box` per type), `render(WP_Post $post)` (renders the fieldset), `save(int $postId, WP_Post $post)` (sanitises + saves).
- [ ] 3.2 Register `register_post_meta` for each applicable post type in the meta box's `register()` so REST and block editor see it. Sanitise via `VisibilityResolver::sanitiseMode`. Auth via `current_user_can('edit_post', $object_id)`.
- [ ] 3.3 Hook nonce check on save via `wp_verify_nonce`.

## 4. Provider wiring

- [ ] 4.1 In `AdminServiceProvider::register()` bind `VisibilityResolver` as a singleton (resolves `SettingsRepository` from container).
- [ ] 4.2 In `AdminServiceProvider::boot()` instantiate `WidgetStateMetaBox` and call its `register()` method (which hooks `init` for `register_post_meta` and `admin_init` for `add_meta_boxes`).
- [ ] 4.3 In `AdminServiceProvider::maybeEnqueueAssets()` extend the localized payload's `choices` with `post_types` => `array_map(fn($pt) => $pt->labels->singular_name ?: $pt->labels->name ?: $pt->name, get_post_types(['public' => true], 'objects'))`.

## 5. Consume the resolver

- [ ] 5.1 `WidgetServiceProvider::maybeAppendWidget` — replace the `moonfarmer_reactions_lead_capture_widget_auto_insert` check with `$resolver->shouldRender($postId, 'auto')`. Keep the existing `data-moonfarmer-reactions-lead-capture-widget` duplicate-detect.
- [ ] 5.2 `ReactionsBlock::render` — after the existing post-validity checks, call `$resolver->shouldRender($postId, 'block')`. Return empty string when false.
- [ ] 5.3 `Shortcode::render` — same pattern with `'shortcode'` context.
- [ ] 5.4 `WidgetMarkup::container` is unchanged for now; the resolver is consulted by callers.

## 6. Admin SPA

- [ ] 6.1 Update `resources/admin/types.ts`: `SettingsState` gains `hide_on_post_types: string[]`. `SettingsChoices` gains `post_types: Record<string, string>`.
- [ ] 6.2 Update `resources/admin/sections/DisplaySection.tsx`:
  - "Auto-insert on" CheckboxListField uses `Object.entries(adminData.choices.post_types).map(([value, label]) => ({value, label}))` instead of the hardcoded `[Posts, Pages]`.
  - Add a new "Never show on" CheckboxListField bound to `settings.hide_on_post_types` using the same options.

## 7. Tests

- [ ] 7.1 `tests/Unit/VisibilityResolverTest.php`: every precedence combo — meta 'on' beats hide; meta 'off' beats auto-insert; auto + hide list = false; auto + auto-insert list match per context; filter override wins.
- [ ] 7.2 Update `tests/Unit/SettingsTest.php` to include `hide_on_post_types` sanitiser case.
- [ ] 7.3 Update `tests/Unit/BootstrapTest.php` autoload assertions for `VisibilityResolver` + `WidgetStateMetaBox`.
- [ ] 7.4 Run `composer test`; confirm green.

## 8. Manual verification

- [ ] 8.1 Reactivate plugin. Open any post → confirm "Moonfarmer Reactions Lead Capture reactions" meta box renders with three radios (Auto/Always show/Always hide).
- [ ] 8.2 Save with "Always hide"; view the post; confirm no widget renders. Inspect `wp post meta get <id> _moonfarmer_reactions_lead_capture_widget_state` → `off`.
- [ ] 8.3 Save with "Always show" on a Page; confirm widget renders on the Page even though Pages aren't in auto-insert.
- [ ] 8.4 Settings page → confirm "Auto-insert on" lists the current public CPTs (not just Post + Page).
- [ ] 8.5 Toggle "Never show on" Pages, then `[moonfarmer-reactions-lead-capture]` shortcode on a Page → confirm shortcode returns empty.
- [ ] 8.6 Override one of those Pages to "Always show" → confirm widget renders despite "Never show on" containing Pages.

## 9. Docs + final

- [ ] 9.1 Update `docs/hooks-and-filters.md`: add `moonfarmer_reactions_lead_capture_visibility_mode` filter, `moonfarmer_reactions_lead_capture_meta_box_post_types` filter.
- [ ] 9.2 Run `openspec validate per-post-override --strict --no-interactive` clean.
- [ ] 9.3 PHP lint clean.
- [ ] 9.4 Commit (no co-auth).
