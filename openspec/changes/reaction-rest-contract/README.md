# reaction-rest-contract

Public reaction REST endpoints: POST /pulsepress/v1/react (nonce-protected, replacement semantics) and GET /pulsepress/v1/counts/{post_id} (public, transient-cached). Six reaction types, HMAC-SHA256 server dedup over IP+UA using wp_salt.
