## MODIFIED Requirements

### Requirement: GET /moonfarmer-reactions-lead-capture/v1/counts/{post_id} returns public per-type counts

The plugin SHALL register `GET /wp-json/moonfarmer-reactions-lead-capture/v1/counts/(?P<post_id>\\d+)` as a public endpoint (`permission_callback: __return_true`). It SHALL return `{post_id, counts, cached}` where `counts` is always a JSON object keyed by reaction type (never `[]` when empty). The endpoint SHALL serve from a `moonfarmer_reactions_lead_capture_counts_{post_id}` transient when present; on miss it SHALL execute a single grouped SELECT against `<prefix>moonfarmer_reactions_lead_capture_reactions` and write the result into the transient with a 300-second TTL before returning.

#### Scenario: Empty counts serialise as JSON object

- **WHEN** post 42 has zero rows in the reactions table
- **THEN** the response body contains `"counts":{}` and not `"counts":[]`

#### Scenario: Cache hit

- **WHEN** the counts transient for post 42 is present and unexpired
- **THEN** the endpoint returns the cached payload with `cached: true` and issues zero SQL queries against the reactions table

#### Scenario: Cache miss

- **WHEN** the counts transient for post 42 is absent
- **THEN** the endpoint runs `SELECT reaction_type, COUNT(*) FROM <prefix>moonfarmer_reactions_lead_capture_reactions WHERE post_id = 42 GROUP BY reaction_type`, returns the result with `cached: false`, and `set_transient('moonfarmer_reactions_lead_capture_counts_42', ...)` is called with a TTL of `300`

#### Scenario: No reactions on a post

- **WHEN** post 42 has zero rows in the reactions table
- **THEN** the endpoint returns `{post_id: 42, counts: {}, cached: false}`

#### Scenario: Missing post

- **WHEN** post id 9999999 does not exist
- **THEN** the endpoint returns `404` with code `moonfarmer_reactions_lead_capture_post_not_found`
