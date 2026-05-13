## 1. Reaction domain types

- [x] 1.1 Create `app/Reactions/Reactions.php` with `final class Reactions` in namespace `PulsePress\Reactions`, exposing `public const TYPES = ['love', 'insightful', 'funny', 'sad', 'surprised', 'angry']` and a static `isValid(string $type): bool` that runs `in_array($type, apply_filters('pulsepress_reaction_types', self::TYPES), true)`.
- [x] 1.2 Create `app/Reactions/UserHash.php` with `final class UserHash` in namespace `PulsePress\Reactions`, exposing static `compute(string $ip, string $userAgent): string` returning the HMAC-SHA256 hex with scope `wp_salt('auth') . 'pulsepress_dedup'`, and static `fromRequest(\WP_REST_Request $request): string` that resolves IP through the `pulsepress_client_ip` filter and UA through `$_SERVER['HTTP_USER_AGENT']` (sanitised).

## 2. Reaction repository

- [x] 2.1 Create `app/Reactions/ReactionRepository.php` with constructor `(\wpdb $wpdb)`, method `replace(int $postId, string $reactionType, string $userHash, \DateTimeInterface $now): string` returning `'inserted'` or `'updated'`. Implementation must use `$wpdb->prepare()` for an `INSERT ... ON DUPLICATE KEY UPDATE` and return the right status via `$wpdb->rows_affected` (`1` = inserted, `2` = updated when ON DUPLICATE KEY UPDATE fires, per MySQL semantics).
- [x] 2.2 Add `countsForPost(int $postId): array<string, int>` that first reads `get_transient('pulsepress_counts_' . $postId)`; on miss runs `SELECT reaction_type, COUNT(*) AS c FROM <prefix>pulsepress_reactions WHERE post_id = %d GROUP BY reaction_type` via `$wpdb->get_results`, casts counts to `int`, and writes the array back via `set_transient` with TTL 300. Public method `lastReadWasCached(): bool` exposes whether the most recent `countsForPost` came from cache.
- [x] 2.3 Add `invalidateCounts(int $postId): void` calling `delete_transient('pulsepress_counts_' . $postId)`.

## 3. REST controller

- [x] 3.1 Create `app/Http/Controllers/ReactionController.php` with constructor `(ReactionRepository $repository, Reactions $reactions, UserHash $userHash)`.
- [x] 3.2 Implement `react(\WP_REST_Request $request): \WP_REST_Response|\WP_Error`. Steps: read `post_id`/`reaction_type` from JSON params; validate post is published+public via `get_post_status`/`is_post_publicly_viewable`; validate reaction type via `Reactions::isValid`; compute user hash; fire `do_action('pulsepress_before_react', ...)` inside a try/catch; call `$repository->replace(...)`; call `$repository->invalidateCounts($postId)`; fire `do_action('pulsepress_after_react', ...)`; return `WP_REST_Response` with `{post_id, reaction_type, status, counts}` where counts come from a fresh `countsForPost` call.
- [x] 3.3 Implement `counts(\WP_REST_Request $request): \WP_REST_Response|\WP_Error`. Validate post exists and is public; call `$repository->countsForPost($postId)`; return `{post_id, counts, cached}` using `lastReadWasCached()`.
- [x] 3.4 Define `app/Http/RestException.php` as `final class RestException extends \RuntimeException` carrying a `WP_Error` payload (`getError(): \WP_Error`). The controller catches this in `react()` and returns the wrapped `WP_Error`.

## 4. Service provider wiring

- [x] 4.1 Create `app/Providers/RestServiceProvider.php` that registers `Reactions`, `UserHash`, `ReactionRepository`, and `ReactionController` as singletons. In `boot()`, hook `rest_api_init` and call `register_rest_route` for both endpoints with the JSON-Schema `args` validation laid out in design.md D9.
- [x] 4.2 Append `RestServiceProvider::class` to `app/bootstrap.php`'s providers array after `DatabaseServiceProvider::class`.

## 5. Test stubs

- [x] 5.1 Extend `tests/Stubs/wp_functions.php`: add namespaced shims for `wp_salt`, `apply_filters`, `do_action`, `get_transient`, `set_transient`, `delete_transient`, `wp_unslash`, `sanitize_text_field`, `get_post_status`, `is_post_publicly_viewable` for use inside `PulsePress\Reactions` and `PulsePress\Http\Controllers` namespaces.
- [x] 5.2 Extend `tests/Stubs/WpdbStub.php` with: `$rows_affected` property, `query()` returning rows-affected after recording the SQL, `get_results($sql, $output)` returning a configurable array, and a `last_query` accessor.
- [x] 5.3 Add `TransientStore` and `FilterRegistry` helper classes in `tests/Stubs/` so tests can seed filters/transients deterministically.

## 6. Tests

- [x] 6.1 `tests/Unit/ReactionsTest.php`: asserts `TYPES` exact content, case-sensitive validation, filter extension.
- [x] 6.2 `tests/Unit/UserHashTest.php`: asserts determinism, IP-and-UA sensitivity, scope-string difference from raw `wp_salt('auth')`, fallback through `pulsepress_client_ip`.
- [x] 6.3 `tests/Unit/ReactionRepositoryTest.php`: `replace` produces the expected SQL with prepared values, returns `'inserted'`/`'updated'` based on `rows_affected`; `countsForPost` hits transient and `lastReadWasCached` reports correctly; cache miss runs the SELECT and primes the transient; `invalidateCounts` deletes the transient.
- [x] 6.4 `tests/Unit/BootstrapTest.php`: add autoload assertions for `Reactions`, `UserHash`, `ReactionRepository`, `ReactionController`, `RestServiceProvider`, `RestException`.
- [x] 6.5 Run `composer test`; confirm all green.

## 7. Manual REST verification

- [x] 7.1 Activate the plugin (already active from Session 1). `wp db query "DELETE FROM wp_pulsepress_reactions"` for a clean slate.
- [x] 7.2 Pick an existing published post id (e.g. `wp post list --post_type=post --post_status=publish --format=ids | head`).
- [x] 7.3 Hit `curl https://wp_lab.test/wp-json/pulsepress/v1/counts/{id}` and confirm `{post_id, counts: {}, cached: false}`.
- [x] 7.4 Mint a nonce: `wp eval "echo wp_create_nonce('wp_rest');"` (or use an admin login session).
- [x] 7.5 `curl -X POST -H "X-WP-Nonce: <nonce>" -H "Content-Type: application/json" -d '{"post_id":<id>,"reaction_type":"love"}' https://wp_lab.test/wp-json/pulsepress/v1/react`. Confirm `200` with `status: "inserted"`.
- [x] 7.6 Re-hit `/counts/{id}` and confirm `counts.love === 1`.
- [x] 7.7 POST again with `reaction_type: "angry"` and confirm `status: "updated"`, then `/counts` shows `counts.love === 0` (key absent or zero) and `counts.angry === 1`.
- [x] 7.8 POST without `X-WP-Nonce` and confirm `401 rest_cookie_invalid_nonce`.
- [x] 7.9 POST with `reaction_type: "applause"` and confirm `422 pulsepress_invalid_reaction_type`.
- [x] 7.10 POST with `post_id: 9999999` and confirm `404 pulsepress_post_not_found`.

## 8. Final verification

- [x] 8.1 Run `find app pulsepress.php uninstall.php -name '*.php' -print0 | xargs -0 -n1 /opt/homebrew/opt/php@8.3/bin/php -l` and confirm no syntax errors.
- [x] 8.2 Run `openspec validate reaction-rest-contract --strict --no-interactive` and confirm clean.
- [x] 8.3 Confirm the WordPress debug log has no new pulsepress errors.
- [x] 8.4 Commit using the AGENTS.md PR-style body.
