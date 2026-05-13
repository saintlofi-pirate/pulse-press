## Why

Session 1 created the storage floor; nothing writes to it yet. The widget (Session 3), the email capture flow (Session 4), and the analytics dashboard (Session 9) all depend on a working reaction write path. This change is the smallest hop: two REST endpoints that let any reader cast or change a reaction on a post and let any reader (including unauthenticated visitors) fetch the current per-type counts.

Free remains generous: anonymous visitors can react without logging in, and counts are publicly readable so the widget renders fast on cached pages. Privacy stays first-class: the identifier we store is an HMAC-SHA256 of the visitor's IP and user agent salted with `wp_salt('auth')`, not raw PII; the salt is per-site, so two PulsePress installs cannot correlate the same visitor across sites; the nonce protects against CSRF on writes; rate limiting and abuse controls have explicit extension points but are out of scope for this slice.

## What Changes

- Add `PulsePress\Reactions\Reactions` final class with `public const TYPES = ['love', 'insightful', 'funny', 'sad', 'surprised', 'angry']` and `isValid(string $type): bool`. The list is filterable via `apply_filters('pulsepress_reaction_types', self::TYPES)` so future settings or Pro can extend or replace it without touching the class.
- Add `PulsePress\Reactions\UserHash::compute(string $ip, string $userAgent): string` that returns `hash_hmac('sha256', $ip . '|' . $userAgent, wp_salt('auth') . 'pulsepress_dedup')`. A separate `UserHash::fromRequest(\WP_REST_Request $request): string` resolves the IP through `$_SERVER['REMOTE_ADDR']` with a `pulsepress_client_ip` filter for CDN/proxy overrides and the UA through `$_SERVER['HTTP_USER_AGENT']`.
- Add `PulsePress\Reactions\ReactionRepository` whose `replace(int $postId, string $reactionType, string $userHash, \DateTimeInterface $now): string` returns `'inserted'` or `'updated'` and uses `$wpdb->query($wpdb->prepare("INSERT INTO ... ON DUPLICATE KEY UPDATE reaction_type = VALUES(reaction_type), updated_at = VALUES(updated_at)"))`. `countsForPost(int $postId): array<string,int>` returns `[reactionType => count]` with a 300-second transient cache keyed `pulsepress_counts_{post_id}`. `invalidateCounts(int $postId): void` deletes the transient.
- Add `PulsePress\Http\Controllers\ReactionController` with two methods. `react(\WP_REST_Request $request)` validates input (`post_id` must be a published, publicly viewable post; `reaction_type` must be in the allowlist), computes the user hash, calls `ReactionRepository::replace`, invalidates the counts transient, and returns `{post_id, reaction_type, counts, status}`. `counts(\WP_REST_Request $request)` returns `{post_id, counts, cached_at}` for the requested post; missing post returns 404.
- Add `PulsePress\Providers\RestServiceProvider` that binds the controller, repository, and helpers as singletons in `register()`, and in `boot()` adds an `rest_api_init` callback that registers two routes under the `pulsepress/v1` namespace: `POST /react` (permission callback: nonce check via `wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest')`) and `GET /counts/(?P<post_id>\\d+)` (permission callback: `__return_true`, public).
- Wire `RestServiceProvider::class` into `app/bootstrap.php`'s providers array after `DatabaseServiceProvider`.
- Reactions written to `<prefix>pulsepress_reactions` MUST satisfy the UNIQUE KEY `(post_id, user_hash)` from Session 1; the repository uses `ON DUPLICATE KEY UPDATE` to enforce replacement semantics at the storage layer.
- **BREAKING**: none — this is the first REST surface.

## Capabilities

### New Capabilities

- `reaction-api`: defines the public REST contract for reactions — endpoint paths, methods, request/response shapes, validation rules, permission callbacks, replacement semantics, transient-cache contract, user-hash construction, and the filter/extension points future sessions and Pro will hook into.

### Modified Capabilities

None — this slice adds endpoints and a repository on top of the schema. The `database-schema` capability stays unchanged; the repository is a consumer of the contract it already published.

## Impact

- **New files**: `app/Reactions/Reactions.php`, `app/Reactions/UserHash.php`, `app/Reactions/ReactionRepository.php`, `app/Http/Controllers/ReactionController.php`, `app/Providers/RestServiceProvider.php`, `tests/Unit/ReactionsTest.php`, `tests/Unit/UserHashTest.php`, `tests/Unit/ReactionRepositoryTest.php`.
- **Modified files**: `app/bootstrap.php` (registers `RestServiceProvider`); `tests/Stubs/wp_functions.php` (adds shims for `wp_salt`, `apply_filters`, `get_transient`, `set_transient`, `delete_transient`); `tests/Stubs/WpdbStub.php` (extends shim with `insert_id`, `last_error`, expanded `prepare` semantics for INSERT).
- **REST API**: two new endpoints under `/wp-json/pulsepress/v1/`: `POST /react` and `GET /counts/{post_id}`.
- **Filters introduced**: `pulsepress_reaction_types` (array, default `Reactions::TYPES`), `pulsepress_client_ip` (string, default `$_SERVER['REMOTE_ADDR']`).
- **Transients introduced**: `pulsepress_counts_{post_id}` with 300-second TTL, invalidated on every successful write to that post.
- **Database changes**: none. The schema from Session 1 is unchanged.
- **Privacy**: no raw PII stored. The user hash is salted per-site and is not reversible into IP/UA. The `pulsepress_captures` table is not touched in this slice.
- **Performance**: counts read is a transient hit by default (zero queries on cached pages); on a miss it is a single grouped SELECT against the indexed `(post_id, reaction_type)` index. Writes are a single INSERT-or-UPDATE plus a transient delete.
- **Free/Pro boundary**: not crossed. The reaction set, hash construction, and route namespace are stable contracts that Pro can extend through `pulsepress_reaction_types` and the controller's filterable post-write hook (introduced in this slice as `do_action('pulsepress_after_react', $postId, $reactionType, $userHash)`).
