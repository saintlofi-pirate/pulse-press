=== PulsePress ===
Contributors: pulsepress
Tags: reactions, email capture, analytics, sentiment, engagement
Requires at least: 6.2
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Privacy-first reactions, inline email capture, and analytics for WordPress publishers.

== Description ==

PulsePress helps publishers understand how readers feel about each post and gives happy readers a simple way to subscribe.

The free plugin is built to be useful on its own. It includes a front-end reaction widget, inline email capture after positive reactions, CSV export, a settings screen, 30-day analytics, Gutenberg block placement, shortcode placement, per-post overrides, and privacy controls.

= What you can do with PulsePress =

* Add accessible reaction buttons to single posts.
* Show reaction counts immediately with optimistic updates.
* Ask for an email address only after a positive reaction.
* Store explicit consent text and consent version with each email capture.
* Export captured emails as CSV from the admin.
* View 30-day reaction and capture analytics.
* See top posts and sentiment/capture rates.
* Place the widget automatically, with a Gutenberg block, or with the `[pulsepress]` shortcode.
* Disable the widget on individual posts.
* Choose reaction labels, positive reactions, icon style, theme mode, count visibility, and guest reaction behavior.
* Purge fraud-review metadata after the configured retention window.
* Delete PulsePress data on uninstall when you explicitly enable that option.

= Built for privacy =

PulsePress does not need third-party tracking scripts. Reaction deduplication uses a per-site hash derived from request data instead of storing raw visitor IP addresses for reactions. Email capture stores the submitted email, consent timestamp, consent text version, source, post id, and short-lived fraud-review hashes so site owners can review abuse while keeping raw request data out of the database.

= Developer friendly =

PulsePress exposes WordPress hooks for reaction types, widget data, settings, capture flow, CSV export columns, analytics windows, block/shortcode placement, and upcoming admin extensions. Hooks are namespaced under `pulsepress_` so developers can extend the free plugin without editing plugin files.

== Installation ==

1. Upload the `pulsepress` folder to `/wp-content/plugins/`.
2. Activate PulsePress from the Plugins screen.
3. Go to Settings > PulsePress.
4. Configure display, reaction, email capture, analytics, and privacy settings.
5. Open a post to confirm the reaction widget appears, or add the PulsePress block/shortcode where you want it.

== Frequently Asked Questions ==

= Does PulsePress work on its own? =

Yes. The free plugin includes reactions, inline email capture, CSV export, settings, block/shortcode placement, per-post overrides, and 30-day analytics.

= Where are reactions stored? =

Reactions are stored in PulsePress custom database tables. Each visitor can update their reaction for a post; the latest reaction is the one counted.

= Does PulsePress store IP addresses? =

PulsePress stores privacy-safe hashes for reaction deduplication and short-lived fraud-review hashes for captures. It does not need to store raw visitor IP addresses for the reaction count flow.

= Can I export captured emails? =

Yes. Site admins can export captures as a CSV from the PulsePress admin screen.

= Can I place the widget manually? =

Yes. Use the PulsePress Gutenberg block or the `[pulsepress]` shortcode. Automatic insertion can also be controlled from settings and per-post overrides.

= Can developers customize the plugin? =

Yes. PulsePress is built around WordPress actions and filters. The main hooks use the `pulsepress_` prefix.

== Screenshots ==

1. PulsePress display settings with live widget preview.
2. Front-end reaction widget on a single post.
3. Inline email capture after a positive reaction.
4. Analytics dashboard with reaction totals, sentiment, captures, and top posts.
5. Per-post visibility controls in the editor.
6. CSV export controls for captured emails.

== Changelog ==

= 0.1.0 =
* Initial WordPress.org-ready free release.
* Added accessible reaction widget for single posts.
* Added inline email capture after positive reactions.
* Added admin settings, live preview, analytics, CSV export, block, shortcode, and per-post overrides.
* Added privacy controls, retention cleanup, and uninstall data deletion option.
