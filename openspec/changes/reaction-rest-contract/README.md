# reaction-rest-contract

Public reaction REST endpoints: POST /moonfarmer-reactions-lead-capture/v1/react (nonce-protected, replacement semantics) and GET /moonfarmer-reactions-lead-capture/v1/counts/{post_id} (public, transient-cached). Six reaction types, HMAC-SHA256 server dedup over IP+UA using wp_salt.
