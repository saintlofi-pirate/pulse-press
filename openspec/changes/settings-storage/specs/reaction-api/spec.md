## MODIFIED Requirements

### Requirement: POST /moonfarmer-reactions-lead-capture/v1/react writes a reaction with replacement semantics

The plugin SHALL register `POST /wp-json/moonfarmer-reactions-lead-capture/v1/react` accepting a JSON body with `post_id` (positive integer) and `reaction_type` (string in the Reactions allowlist). The endpoint SHALL require a valid `X-WP-Nonce` header verified with `wp_verify_nonce(..., 'wp_rest')`. When the `allow_guest_reactions` setting is `false`, the endpoint SHALL ALSO require `is_user_logged_in()`. On successful upsert it SHALL respond `200` with `{post_id, reaction_type, status: 'inserted'|'updated', counts}` reflecting the post-write state.

#### Scenario: Anonymous visitor with guest reactions enabled

- **WHEN** `allow_guest_reactions` is `true` (default) and an anonymous visitor with a valid nonce posts a reaction
- **THEN** the response is `200` and a row is written

#### Scenario: Anonymous visitor with guest reactions disabled

- **WHEN** `allow_guest_reactions` is `false` and an anonymous visitor with a valid nonce posts a reaction
- **THEN** the response is `401` with code `moonfarmer_reactions_lead_capture_login_required` and no row is written

#### Scenario: Logged-in user is unaffected by the guest toggle

- **WHEN** `allow_guest_reactions` is `false` and a logged-in editor with a valid nonce posts a reaction
- **THEN** the response is `200` and a row is written

#### Scenario: Same visitor changes reaction

- **WHEN** the same user hash that previously reacted `love` on post 42 posts `{post_id: 42, reaction_type: "angry"}`
- **THEN** the response is `200` with `status: "updated"`, `counts.love` is `1` lower than before, and `counts.angry` is `1` higher
