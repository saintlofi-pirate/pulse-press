## ADDED Requirements

### Requirement: Capture tab includes an "Export captures" action region

The admin SPA's Capture tab SHALL include an "Export captures" action region containing a primary button. Clicking the button SHALL fetch `GET /moonfarmer-reactions-lead-capture/v1/captures.csv` with `X-WP-Nonce` and `credentials: 'same-origin'`, read the response as a Blob, and trigger a browser download via a temporary `<a download>` element pointing at the Blob's object URL. The button SHALL set `aria-busy="true"` while in flight and render a transient "Download started." status pill on success.

#### Scenario: Button renders on the Capture tab

- **WHEN** an admin opens the Capture tab
- **THEN** an "Export captures" button is visible with a short helper sentence

#### Scenario: Click triggers a download

- **WHEN** the admin clicks the button on a site with captures
- **THEN** a network request to `/wp-json/moonfarmer-reactions-lead-capture/v1/captures.csv` fires with `X-WP-Nonce` header and the browser downloads a file named `moonfarmer-reactions-lead-capture-captures-{timestamp}.csv`

#### Scenario: Loading state announces

- **WHEN** the network request is in flight
- **THEN** the button has `aria-busy="true"` and its label changes from "Export captures" to "Preparing…"

#### Scenario: Success state announces

- **WHEN** the download completes
- **THEN** a `role="status"` element renders "Download started." for ~1.5 seconds before fading

#### Scenario: Error state is accessible

- **WHEN** the request returns a non-2xx response
- **THEN** a `<p role="alert">` renders the server's message and the button re-enables for a retry
