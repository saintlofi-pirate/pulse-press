## ADDED Requirements

### Requirement: Six reaction types form the default allowlist

The plugin SHALL declare exactly six default reaction types — `love`, `insightful`, `funny`, `sad`, `surprised`, `angry` — in a single source-of-truth constant `PulsePress\Reactions\Reactions::TYPES`. Every code path that accepts a reaction type SHALL validate it against this list (or the filtered version of it) using strict comparison. No reaction type SHALL ever reach SQL without passing through `Reactions::isValid()`.

#### Scenario: Allowlist exposed as a constant

- **WHEN** a developer reads `PulsePress\Reactions\Reactions::TYPES`
- **THEN** it equals `['love', 'insightful', 'funny', 'sad', 'surprised', 'angry']`

#### Scenario: Strict validation rejects near-matches

- **WHEN** `Reactions::isValid('Love')` is invoked
- **THEN** it returns `false` because the comparison is case-sensitive

#### Scenario: Filter can extend the allowlist

- **WHEN** a plugin registers `add_filter('pulsepress_reaction_types', fn() => ['love', 'celebrate'])` and `Reactions::isValid('celebrate')` is invoked
- **THEN** it returns `true`

### Requirement: User hash uses HMAC-SHA256 with per-site wp_salt

`PulsePress\Reactions\UserHash::compute(string $ip, string $userAgent)` SHALL return `hash_hmac('sha256', $ip . '|' . $userAgent, wp_salt('auth') . 'pulsepress_dedup')`. The output SHALL be exactly 64 lowercase hexadecimal characters to fit the `CHAR(64)` column declared in Session 1.

#### Scenario: Deterministic output for the same inputs

- **WHEN** `UserHash::compute('1.2.3.4', 'Mozilla/5.0')` is invoked twice in the same request
- **THEN** both invocations return the same 64-character hex string

#### Scenario: Salt domain-separation prevents collision with wp_salt('auth') consumers

- **WHEN** a hypothetical caller computes `hash_hmac('sha256', '1.2.3.4|Mozilla/5.0', wp_salt('auth'))` (no `pulsepress_dedup` suffix)
- **THEN** the result differs from `UserHash::compute('1.2.3.4', 'Mozilla/5.0')`

#### Scenario: Different IPs hash to different values

- **WHEN** the same UA is hashed with two different IPs
- **THEN** the two results differ

### Requirement: IP source falls back through pulsepress_client_ip filter

`UserHash::fromRequest()` SHALL resolve the client IP through `apply_filters('pulsepress_client_ip', $remoteAddr, $request)` where `$remoteAddr` is `$_SERVER['REMOTE_ADDR']`. The user agent SHALL be `sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? ''))`.

#### Scenario: CDN override via filter

- **WHEN** a site behind a CDN registers `add_filter('pulsepress_client_ip', fn($ip, $req) => $req->get_header('cf-connecting-ip') ?: $ip, 10, 2)`
- **THEN** `UserHash::fromRequest()` uses the CDN-provided IP instead of `REMOTE_ADDR`

#### Scenario: Missing UA gracefully treated as empty

- **WHEN** `$_SERVER['HTTP_USER_AGENT']` is unset
- **THEN** `UserHash::fromRequest()` succeeds with an empty-string UA component and returns a valid 64-character hex

### Requirement: POST /pulsepress/v1/react writes a reaction with replacement semantics

The plugin SHALL register `POST /wp-json/pulsepress/v1/react` accepting a JSON body with `post_id` (positive integer) and `reaction_type` (string in the allowlist). The endpoint SHALL require a valid `X-WP-Nonce` header verified with `wp_verify_nonce(..., 'wp_rest')`. On success, the endpoint SHALL upsert a single row in `<prefix>pulsepress_reactions` using the `UNIQUE KEY (post_id, user_hash)` to replace any prior reaction by the same hash on the same post. The response SHALL include `{post_id, reaction_type, status: "inserted"|"updated", counts: {type: int, ...}}` reflecting the post-write state.

#### Scenario: First reaction by a visitor

- **WHEN** an anonymous visitor with a valid nonce posts `{post_id: 42, reaction_type: "love"}`
- **THEN** the response is `200` with `status: "inserted"` and `counts.love` is `1` higher than before the write

#### Scenario: Same visitor changes reaction

- **WHEN** the same user hash that previously reacted `love` on post 42 posts `{post_id: 42, reaction_type: "angry"}`
- **THEN** the response is `200` with `status: "updated"`, `counts.love` is `1` lower than before, and `counts.angry` is `1` higher

#### Scenario: Missing nonce

- **WHEN** a POST request is made without the `X-WP-Nonce` header
- **THEN** WordPress returns `401` with code `rest_forbidden` (its default for a permission-callback failure) and no row is written

#### Scenario: Invalid nonce

- **WHEN** the `X-WP-Nonce` header is present but does not verify against `wp_rest`
- **THEN** WordPress returns `401` with code `rest_forbidden` and no row is written

#### Scenario: Invalid reaction type

- **WHEN** a POST request is made with `reaction_type: "applause"` (not in the allowlist)
- **THEN** the response is `422` with code `pulsepress_invalid_reaction_type` and no row is written

#### Scenario: Unknown post id

- **WHEN** a POST request is made with `post_id: 9999999` that does not resolve to a public published post
- **THEN** the response is `404` with code `pulsepress_post_not_found` and no row is written

#### Scenario: Non-public post

- **WHEN** a POST request targets a post whose status is `draft` or `private`
- **THEN** the response is `404` with code `pulsepress_post_not_found` and no row is written

### Requirement: GET /pulsepress/v1/counts/{post_id} returns public per-type counts

The plugin SHALL register `GET /wp-json/pulsepress/v1/counts/(?P<post_id>\\d+)` as a public endpoint (`permission_callback: __return_true`). It SHALL return `{post_id, counts, cached}` where `counts` is an associative array keyed by reaction type. The endpoint SHALL serve from a `pulsepress_counts_{post_id}` transient when present; on miss it SHALL execute a single grouped SELECT against `<prefix>pulsepress_reactions` and write the result into the transient with a 300-second TTL before returning.

#### Scenario: Cache hit

- **WHEN** the counts transient for post 42 is present and unexpired
- **THEN** the endpoint returns the cached payload with `cached: true` and issues zero SQL queries against the reactions table

#### Scenario: Cache miss

- **WHEN** the counts transient for post 42 is absent
- **THEN** the endpoint runs `SELECT reaction_type, COUNT(*) FROM <prefix>pulsepress_reactions WHERE post_id = 42 GROUP BY reaction_type`, returns the result with `cached: false`, and `set_transient('pulsepress_counts_42', ...)` is called with a TTL of `300`

#### Scenario: No reactions on a post

- **WHEN** post 42 has zero rows in the reactions table
- **THEN** the endpoint returns `{post_id: 42, counts: {}, cached: false}`

#### Scenario: Missing post

- **WHEN** post id 9999999 does not exist
- **THEN** the endpoint returns `404` with code `pulsepress_post_not_found`

### Requirement: Counts transient invalidated on every successful write

After every successful `POST /react`, the controller SHALL call `delete_transient('pulsepress_counts_' . $postId)` before returning. The next read SHALL miss the cache and recompute fresh counts.

#### Scenario: Counts reflect a fresh write on the next read

- **WHEN** a visitor reacts `love` on post 42, then immediately fetches counts for post 42
- **THEN** the counts response shows `cached: false` and the new `love` count is included

#### Scenario: Failed writes do not invalidate cache

- **WHEN** a POST returns 404 or 422 because of validation failure
- **THEN** the existing counts transient is untouched

### Requirement: Action hooks expose extension points before and after each write

The controller SHALL fire `do_action('pulsepress_before_react', int $postId, string $reactionType, string $userHash, \WP_REST_Request $request)` before calling the repository, and `do_action('pulsepress_after_react', int $postId, string $reactionType, string $userHash, string $status)` after a successful upsert and cache invalidation. The `before` hook SHALL run inside a `try`-block that catches `WP_Error`-wrapping exceptions, enabling future rate-limit modules to short-circuit a write by throwing.

#### Scenario: Rate-limiter rejects a write

- **WHEN** a plugin hooks `pulsepress_before_react` and throws a `\PulsePress\Http\RestException` wrapping a `WP_Error('pulsepress_rate_limited', 'too many', ['status' => 429])`
- **THEN** the controller returns the WP_Error to the REST framework, which responds `429`, and no DB write or cache invalidation occurs

#### Scenario: Aggregator hooks the after action

- **WHEN** a plugin registers `add_action('pulsepress_after_react', $callback, 10, 4)` and a write succeeds
- **THEN** the callback runs once with `(post_id, reaction_type, user_hash, status)` arguments

### Requirement: RestServiceProvider wires routes on rest_api_init

`PulsePress\Providers\RestServiceProvider` SHALL bind `Reactions`, `UserHash`, `ReactionRepository`, and `ReactionController` as singletons in `register()`, and in `boot()` SHALL register an `rest_api_init` callback that calls `register_rest_route` twice — once for `/react` and once for `/counts/(?P<post_id>\\d+)` — under the `pulsepress/v1` namespace. The provider SHALL be appended to `app/bootstrap.php`'s providers array after `DatabaseServiceProvider`.

#### Scenario: Routes registered exactly once per request

- **WHEN** WordPress fires `rest_api_init` during a request
- **THEN** `register_rest_route` is called twice with the two PulsePress route definitions and not invoked again later in the same request

#### Scenario: Provider boots without WP-loaded errors

- **WHEN** the plugin is active on PHP 8.1+ and WP 6.2+
- **THEN** no PHP warnings or notices appear in the WordPress debug log on a normal request that does not hit a PulsePress endpoint
