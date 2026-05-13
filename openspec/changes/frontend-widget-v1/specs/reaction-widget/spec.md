## ADDED Requirements

### Requirement: Assets enqueue only on singular post views by default

`PulsePress\Providers\WidgetServiceProvider` SHALL register a `wp_enqueue_scripts` callback that enqueues the widget's JS and CSS only when `is_admin() === false` and either `is_singular('post') === true` or `apply_filters('pulsepress_widget_enqueue', false)` returns `true`. On every other request, no widget asset SHALL be added to the page.

#### Scenario: Front-end single post

- **WHEN** a visitor loads a published single post
- **THEN** `dist/js/widget.<hash>.js` and `dist/assets/widget.<hash>.css` are enqueued and `window.PulsePressData` is emitted

#### Scenario: Front-end archive page

- **WHEN** a visitor loads the home page or a category archive without the `pulsepress_widget_enqueue` filter overridden
- **THEN** no widget script or style tag is added to the page

#### Scenario: Admin page

- **WHEN** an admin loads `/wp-admin/edit.php`
- **THEN** no widget asset is enqueued

#### Scenario: Filter overrides

- **WHEN** a plugin registers `add_filter('pulsepress_widget_enqueue', '__return_true')` and a visitor loads a page or a custom post type
- **THEN** the widget asset is enqueued on that page

### Requirement: PulsePressData payload contains REST root, nonce, and post id

The widget bootstrap SHALL emit `window.PulsePressData` via `wp_localize_script` with at minimum: `root` (the REST URL ending in `/wp-json/pulsepress/v1/`), `nonce` (the output of `wp_create_nonce('wp_rest')`), `postId` (the current `get_the_ID()`), and `reactions` (the output of `apply_filters('pulsepress_reaction_types', Reactions::TYPES)`). The full payload SHALL pass through `apply_filters('pulsepress_widget_data', $payload)` before emission.

#### Scenario: REST root resolves correctly behind permalinks

- **WHEN** a site uses pretty permalinks
- **THEN** `PulsePressData.root` ends in `/wp-json/pulsepress/v1/`

#### Scenario: Nonce travels with the page

- **WHEN** the page is rendered for a logged-out visitor
- **THEN** `PulsePressData.nonce` is a non-empty string produced by `wp_create_nonce('wp_rest')`

#### Scenario: Filter can extend payload

- **WHEN** a plugin registers `add_filter('pulsepress_widget_data', fn($d) => $d + ['variant' => 'a'])`
- **THEN** `PulsePressData.variant === 'a'` in the rendered page

### Requirement: Widget container auto-appended to single-post content

A `the_content` filter SHALL append `<div class="pulsepress" data-pulsepress-widget data-pulsepress-post-id="{id}"></div>` to the rendered content of single-post views, gated by `apply_filters('pulsepress_widget_auto_insert', true, $postType)` (default `true` for `post`, `false` for other post types). The container SHALL NOT be appended on archive, search, feed, or admin contexts.

#### Scenario: Auto-insert on single post

- **WHEN** a visitor loads a single post
- **THEN** the rendered HTML contains exactly one `<div ... data-pulsepress-widget data-pulsepress-post-id="{id}">` after the post body

#### Scenario: Auto-insert disabled via filter

- **WHEN** `add_filter('pulsepress_widget_auto_insert', '__return_false')` is registered
- **THEN** no widget container is added by the auto-insert filter (manual block/shortcode placement still works in Session 7)

#### Scenario: Excerpt context does not inject

- **WHEN** `the_content` runs inside an archive loop where `is_singular()` is `false`
- **THEN** no widget container is appended

### Requirement: Vite manifest reader resolves hashed asset URLs

`PulsePress\View\Manifest::resolve(string $entry): array{js: ?string, css: ?string}` SHALL read `dist/.vite/manifest.json` and return the hashed URLs for the given source entry. Results SHALL be cached in a transient keyed `pulsepress_vite_manifest_v1` for 24 hours; the cache key SHALL incorporate the manifest file's `filemtime()` so a fresh build invalidates the cache automatically.

#### Scenario: Resolve known entry after a build

- **WHEN** the manifest contains `"resources/widget/index.ts": {"file": "js/widget.abc123.js", "css": ["assets/widget.def456.css"]}`
- **THEN** `Manifest::resolve('resources/widget/index.ts')` returns `['js' => 'dist/js/widget.abc123.js', 'css' => 'dist/assets/widget.def456.css']` (relative to plugin URL)

#### Scenario: Missing manifest returns empty result

- **WHEN** `dist/.vite/manifest.json` does not exist (no build yet)
- **THEN** `Manifest::resolve` returns `['js' => null, 'css' => null]` and never throws

#### Scenario: Manifest mtime invalidates cache

- **WHEN** the cached manifest has mtime `T` and the file's current `filemtime()` is `T+1`
- **THEN** the next `resolve` call re-reads the file and refreshes the cache

### Requirement: Widget renders six reactions with counts and active state

The Preact widget SHALL render exactly six `<button>` elements (one per reaction in `PulsePressData.reactions`), each containing an inline SVG icon, a visible count, an `aria-label` naming the reaction, and `aria-pressed` reflecting whether that reaction is the visitor's current choice. On first paint, counts SHALL come from a `GET /counts/{postId}` request; the active reaction SHALL come from `localStorage` under `pulsepress:reaction:{postId}`.

#### Scenario: First paint on a post with no prior reactions

- **WHEN** the widget mounts on a post whose counts endpoint returns `{counts: {}}`
- **THEN** six buttons render, each with a count of `0` and `aria-pressed="false"`

#### Scenario: Returning visitor sees their stored active state

- **WHEN** `localStorage.getItem('pulsepress:reaction:42')` returns `'love'` and the page is for post 42
- **THEN** the `love` button renders with `aria-pressed="true"` and is visually tinted with `var(--pulsepress-accent)`

#### Scenario: Counts from server replace localStorage on reconciliation

- **WHEN** the server returns counts including `love: 5` and `localStorage` did not record an active reaction
- **THEN** the love button shows `5` and remains `aria-pressed="false"`

### Requirement: Optimistic click with rollback on failure

When a visitor clicks a reaction button, the widget SHALL immediately update local state (active reaction set, counts adjusted), write `localStorage`, and fire `POST /react`. On success, the server's response counts SHALL replace local state. On HTTP failure, the prior local state SHALL be restored, `localStorage` SHALL be rolled back to its previous value, and an inline error message SHALL render for 4 seconds.

#### Scenario: Successful first reaction

- **WHEN** a visitor clicks `love` and the server responds 200 with `{status: 'inserted', counts: {love: 1}}`
- **THEN** the love button shows `1`, `aria-pressed="true"`, `localStorage['pulsepress:reaction:42']` is `'love'`, and no error message renders

#### Scenario: Switching reactions

- **WHEN** the active reaction is `love` (count 1) and the visitor clicks `angry`
- **THEN** during the network round-trip the love count drops by 1 and the angry count rises by 1; on server response `{status: 'updated', counts: {angry: 1}}` the displayed state matches that exactly

#### Scenario: Network failure rolls back

- **WHEN** the visitor clicks `love` and the `fetch` rejects with a network error
- **THEN** the love count returns to its pre-click value, `aria-pressed` returns to `false`, `localStorage['pulsepress:reaction:42']` is removed (or restored to its previous value), and an inline "Please try again." message renders for 4 seconds

### Requirement: Re-clicking the active reaction is a no-op

If the visitor clicks the button that is already `aria-pressed="true"`, the widget SHALL NOT issue any network request, MUST NOT modify counts, and MUST NOT alter `localStorage`. The button retains its visual active state.

#### Scenario: Click on already-active

- **WHEN** the `love` button is `aria-pressed="true"` and the visitor clicks it again
- **THEN** zero network requests are made, no counts change, and no console errors appear

### Requirement: Keyboard activation through standard semantics

Each reaction button SHALL respond to Enter and Space keys exactly as a left mouse click. Tab order SHALL match visual order. Focus rings SHALL be visible via `:focus-visible` styling — never suppressed.

#### Scenario: Tab through the bar

- **WHEN** a keyboard user tabs from the post body into the widget
- **THEN** focus lands on the first reaction button, subsequent Tab presses advance through the remaining five, and each focused button shows a visible focus ring

#### Scenario: Enter activates focused button

- **WHEN** the `funny` button is focused and the user presses Enter
- **THEN** the same optimistic-click path runs as a mouse click

### Requirement: Bundle stays within the v1 size budget

The built widget JavaScript bundle SHALL be ≤ 15 KB gzipped. The built widget CSS bundle SHALL be ≤ 3 KB minified. The widget SHALL not import any module from `node_modules` that exceeds 10 KB minified outside of `preact`.

#### Scenario: Post-build size check

- **WHEN** `npm run build` completes successfully
- **THEN** `wc -c < dist/js/widget.*.js` (after gzip) reports a value ≤ 15360 bytes and the corresponding CSS file is ≤ 3072 bytes

## MODIFIED Requirements

### Requirement: GET /pulsepress/v1/counts/{post_id} returns public per-type counts

The plugin SHALL register `GET /wp-json/pulsepress/v1/counts/(?P<post_id>\\d+)` as a public endpoint (`permission_callback: __return_true`). It SHALL return `{post_id, counts, cached}` where `counts` is always a JSON object (never an array literal, even when empty). The endpoint SHALL serve from a `pulsepress_counts_{post_id}` transient when present; on miss it SHALL execute a single grouped SELECT against `<prefix>pulsepress_reactions` and write the result into the transient with a 300-second TTL before returning.

#### Scenario: Empty counts serialise as JSON object

- **WHEN** post 42 has zero rows in the reactions table
- **THEN** the response body contains `"counts":{}` and not `"counts":[]`

#### Scenario: Cache hit

- **WHEN** the counts transient for post 42 is present and unexpired
- **THEN** the endpoint returns the cached payload with `cached: true` and issues zero SQL queries against the reactions table

#### Scenario: Cache miss

- **WHEN** the counts transient for post 42 is absent
- **THEN** the endpoint runs `SELECT reaction_type, COUNT(*) FROM <prefix>pulsepress_reactions WHERE post_id = 42 GROUP BY reaction_type`, returns the result with `cached: false`, and `set_transient('pulsepress_counts_42', ...)` is called with a TTL of `300`

#### Scenario: Missing post

- **WHEN** post id 9999999 does not exist
- **THEN** the endpoint returns `404` with code `pulsepress_post_not_found`
