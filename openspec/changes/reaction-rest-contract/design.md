## Context

Session 1 published two constraints we now have to honour at the application layer:

1. `<prefix>moonfarmer_reactions_lead_capture_reactions` has a `UNIQUE KEY (post_id, user_hash)` — duplicate reactions for the same `(post, user)` must replace the prior row, not insert a second.
2. The plugin commits to per-site privacy: the user-hash is HMAC-SHA256 over IP and UA, salted with a per-site secret. Gap 5 in `docs/gap-questions-and-session-tasks.md` explicitly rejected using the WordPress nonce as the salt, because nonce rotation would invalidate dedup mid-session.

This slice adds the public-facing surface that turns those constraints into behavior: a write endpoint that takes a post id and reaction type, computes the user-hash, and runs an idempotent upsert; a read endpoint that returns per-type counts; a transient layer so cached pages don't hit the DB on every render.

Two open questions from Session 1's design.md land here:

- **Q1 (deferred)**: where the salt lives. Decided in D3 below.
- **Q3 (carried)**: the rate-limiting policy from the gap decisions. Decided in D8 below.

## Goals / Non-Goals

**Goals:**

- Anonymous visitors can react and unreact without logging in.
- Repeated reactions from the same user-hash on the same post replace the existing row (no inflated counts).
- Counts read is a single transient lookup on cache hit, a single grouped SELECT on miss.
- Writes are protected by a WordPress REST nonce.
- No raw PII (IP, UA) lands in the database — only HMAC hex.
- The reaction set is configurable through a filter without code changes downstream.
- Every public input is validated against an allowlist or numeric range; nothing is interpolated into SQL.
- Test coverage covers: allowlist validation, hash determinism, repository upsert SQL shape, transient invalidation.

**Non-Goals:**

- No rate limiting at the application layer in this slice. The plan calls for rate-limiting by post id, reaction type, dedup hash, and IP window; that lands in a later session behind a clean `moonfarmer_reactions_lead_capture_before_react` filter. The UNIQUE KEY already eliminates the "spam reactions" failure mode; what's left is "spam reaction *changes*" which is a UX nuisance, not a data integrity issue.
- No JS widget. That's Session 3.
- No `moonfarmer_reactions_lead_capture_captures` writes. That's Session 4.
- No aggregation. That's Session 8.
- No admin UI. That's Session 6.
- No CSRF for the read endpoint. The counts are public data on a public page — there is no secret to protect.
- No support for arbitrary post types in Free yet. Only published, publicly viewable posts. Custom-post-type opt-in is a Pro/settings concern.

## Decisions

### D1. Two endpoints, not one combined "interact" endpoint

`POST /react` writes, `GET /counts/{post_id}` reads. Splitting them keeps:

- The read endpoint cacheable at the HTTP layer (CDNs and page caches respect `GET` cacheability).
- The write endpoint clearly authenticated.
- Permission callbacks simple: one is `__return_true`, the other is a single-line nonce check.

**Alternative considered**: one POST endpoint that returns counts in its response, omitting the separate GET. Rejected — the widget needs to render counts on first paint before any interaction, before a write nonce is even available.

### D2. Replacement semantics enforced at the storage layer

The repository uses:

```sql
INSERT INTO <prefix>moonfarmer_reactions_lead_capture_reactions (post_id, reaction_type, user_hash, created_at, updated_at)
VALUES (...)
ON DUPLICATE KEY UPDATE
    reaction_type = VALUES(reaction_type),
    updated_at    = VALUES(updated_at);
```

The UNIQUE KEY does the work. The application layer doesn't need to read-modify-write; there's no TOCTOU race; concurrent reactions from the same user produce one final state.

**Alternative considered**: `SELECT ... FOR UPDATE` inside a transaction, then `UPDATE` or `INSERT`. Rejected — needs InnoDB-specific locking, slower, no advantage over the upsert.

### D3. User-hash salt: `wp_salt('auth') . 'moonfarmer_reactions_lead_capture_dedup'`

Three options were on the table:

1. **A plugin-owned option `moonfarmer_reactions_lead_capture_hash_salt`** seeded on activation with `wp_generate_password(64, false)`. Pros: Moonfarmer Reactions Lead Capture controls rotation. Cons: another option to back up, and rotation is a destructive action with no UI yet.
2. **`wp_salt('auth')` directly.** Pros: WordPress already manages it. Cons: shared with every other plugin that calls `wp_salt('auth')`, so two plugins fingerprinting the same visitor would land on the same hash — minor cross-plugin correlation risk.
3. **`wp_salt('auth') . 'moonfarmer_reactions_lead_capture_dedup'`** (chosen). Pros: per-site uniqueness from WP's secrets infrastructure, plus a scope string that domain-separates Moonfarmer Reactions Lead Capture from any other plugin using `wp_salt`. Zero new options to manage. If admins rotate `wp_salt` (rare and intentional), dedup resets — acceptable, since salt rotation already invalidates auth cookies.

The scope string `'moonfarmer_reactions_lead_capture_dedup'` is a constant that lives next to the hash function. If we ever need to migrate the salt scheme, we'll bump the scope string and document the dedup reset.

### D4. IP source: `REMOTE_ADDR` with a `moonfarmer_reactions_lead_capture_client_ip` filter

Default to `$_SERVER['REMOTE_ADDR']` because that's the connection's source IP and cannot be spoofed by a header. CDN-behind sites need `X-Forwarded-For` or equivalent; rather than guessing, we expose `apply_filters('moonfarmer_reactions_lead_capture_client_ip', $remoteAddr, $request)`. CDN integration is a deliberate, documented action by the site admin, not a default.

**Alternative considered**: auto-detect proxy headers when WordPress sees a reverse proxy. Rejected — the WordPress core has no canonical "are we behind a proxy" detection, and getting it wrong is a security bug (spoofable IP). Filter is the safer default.

### D5. Transient cache: 300 seconds, per-post, deleted on write

`moonfarmer_reactions_lead_capture_counts_{post_id}` is set with `set_transient($key, $counts, 300)`. On every successful write, `delete_transient($key)` runs. This:

- Keeps reads cheap on cached pages.
- Bounds staleness to 5 minutes worst-case (cache miss after write but before invalidation? No — `delete_transient` is synchronous and runs after the DB write completes).
- Plays well with object-cache plugins (Redis, Memcached) without code changes.

**Alternative considered**: longer TTL (e.g., 1 hour) with no write invalidation. Rejected — the widget should reflect the user's own reaction immediately on the page after a react click, and a 1-hour stale window is too long.

### D6. Reaction type validation against `Reactions::TYPES` (filterable)

`Reactions::isValid($type)` checks `in_array($type, apply_filters('moonfarmer_reactions_lead_capture_reaction_types', self::TYPES), true)` — strict comparison, no type juggling. Pro and the future settings page extend the list through the filter. The repository never receives unvalidated types.

### D7. Response shape and status codes

`POST /react` success (200): `{post_id: int, reaction_type: string, status: "inserted"|"updated", counts: {type: int}}`. The counts in the response are the post-write state, so the widget can update optimistically without a follow-up read.

`POST /react` errors:

- 400 `rest_invalid_param` — malformed body (missing keys, wrong types, etc., produced by `register_rest_route`'s `args` validation).
- 401 `rest_cookie_invalid_nonce` — missing or invalid `X-WP-Nonce`. WordPress's REST framework returns this automatically when `wp_verify_nonce` fails.
- 404 `moonfarmer_reactions_lead_capture_post_not_found` — post id resolves to nothing, or post is non-public.
- 422 `moonfarmer_reactions_lead_capture_invalid_reaction_type` — type is well-formed but not in the allowlist.

`GET /counts/{post_id}` success (200): `{post_id: int, counts: {type: int}, cached: bool}`.

`GET /counts/{post_id}` errors: 404 `moonfarmer_reactions_lead_capture_post_not_found` if the post id is unknown.

### D8. Rate limiting deferred, extension points reserved

`do_action('moonfarmer_reactions_lead_capture_before_react', $postId, $reactionType, $userHash, $request)` fires inside `react()` before the repository write. A future rate-limit module can hook this, inspect arguments, and short-circuit by throwing a `WP_Error`-compatible exception caught by the controller. The reverse hook `do_action('moonfarmer_reactions_lead_capture_after_react', $postId, $reactionType, $userHash)` fires after a successful write; Pro and aggregation will hook this in Sessions 4 and 8.

### D9. Permission callbacks: minimal, declarative

```php
register_rest_route('moonfarmer-reactions-lead-capture/v1', '/react', [
    'methods'             => 'POST',
    'callback'            => [$controller, 'react'],
    'permission_callback' => fn ($request) => wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest') !== false,
    'args'                => [
        'post_id'       => ['type' => 'integer', 'required' => true, 'minimum' => 1],
        'reaction_type' => ['type' => 'string',  'required' => true, 'minLength' => 1, 'maxLength' => 32],
    ],
]);

register_rest_route('moonfarmer-reactions-lead-capture/v1', '/counts/(?P<post_id>\\d+)', [
    'methods'             => 'GET',
    'callback'            => [$controller, 'counts'],
    'permission_callback' => '__return_true',
]);
```

`args` validation is handled by core, so the controller can trust the types of inputs. The `__return_true` on counts is deliberate and audited — the data is public.

### D10. Controller does not query `wp_options` directly

All option reads (transient lookups, salt construction) go through the helpers (`get_transient`, `wp_salt`). This makes the controller mockable without bootstrapping WordPress.

### D11. PHP 8.1 readonly DTOs for controller responses

Internal value objects (`ReactionWriteResult { string $status; array $counts; }`) are PHP 8.1 `readonly` classes. Public REST responses are plain associative arrays so JSON serialization stays predictable.

## Risks / Trade-offs

- **Risk**: A site behind a CDN that doesn't override `moonfarmer_reactions_lead_capture_client_ip` will see every visitor sharing the CDN's IP, so the per-post dedup degrades to "per-CDN-IP per-post per-UA". → Mitigation: documented in `readme.txt` under "Behind a CDN" with the filter recipe; UA still adds entropy.
- **Risk**: A user clearing cookies and switching browsers within seconds re-reacts and inflates a counter. → Mitigation: UA-based dedup catches the cookie-clear case but not the browser-switch case. Acceptable — the value is engagement signal, not voting integrity.
- **Risk**: `wp_salt('auth')` rotation invalidates all existing dedup. → Mitigation: documented behaviour; salt rotation already invalidates session cookies, so the dedup reset coincides with an event admins know about. The schema's UNIQUE KEY is by hash, not by IP, so old rows just stop matching new clicks (correct behaviour).
- **Risk**: Transient cache returns stale counts during the brief window between a write and the next read. → Mitigation: `delete_transient` runs synchronously inside the write handler after the DB commit; a concurrent read can only observe stale state during a few microseconds. Acceptable.
- **Risk**: A site with object-cache disabled and millions of posts could fill `wp_options` with `_transient_moonfarmer_reactions_lead_capture_counts_*` rows. → Mitigation: 300-second TTL keeps the lifetime short, and WP core's transient garbage collection removes expired rows. If this becomes a problem, a future session can move counts behind `wp_cache_*` directly.
- **Trade-off**: No "unreact" endpoint in this slice. The plan implies replacement, not deletion. A user reacting `love` then `angry` ends at `angry`; there's no path to "no reaction at all". Acceptable for v1; an `unreact` endpoint can be added later as `DELETE /react?post_id=...`.
- **Trade-off**: The widget will need a fresh nonce per page render. WordPress's default nonce lifetime (24 hours) is sufficient. We don't add cookie-based session tokens.

## Migration Plan

No data migration. The endpoints become available the moment `RestServiceProvider` is registered. Rollback is a `git revert` and a reload.

For deployment safety:

1. Land the change.
2. Hit `GET /wp-json/moonfarmer-reactions-lead-capture/v1/counts/1` against a post with no reactions and confirm `{post_id: 1, counts: {}, cached: false}`.
3. Mint a nonce via `wp_create_nonce('wp_rest')` from an authenticated session (or the front-end `wpApiSettings.nonce`) and `POST` a reaction.
4. Hit the counts endpoint again and confirm the count increments.
5. POST again with a different reaction type and confirm the count for the original type drops to zero and the new type's count is 1 (replacement working).

## Open Questions

- **Q1**: Should the `moonfarmer_reactions_lead_capture_after_react` action fire async (queued) or sync? → **Decided sync** for this slice. Async firing is an aggregation concern that lands in Session 8 behind the `QueueScheduler` abstraction.
- **Q2**: Should we return ETag headers on `GET /counts` for HTTP-layer caching? → **Decided no** for v1. Adding ETag means adding an "updated_at" cursor to the counts response; transient cache already keeps the work cheap enough. Revisit if dashboard performance pushes it.
- **Q3**: Should reaction writes increment a daily counter immediately (poor-man's aggregation), or rely entirely on Session 8's nightly aggregator? → **Decided rely on Session 8**. Synchronous double-writes complicate the test surface for negligible win at our expected read patterns.
