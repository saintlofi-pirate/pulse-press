=== PulsePress ===
Contributors: saintlofi
Tags: reactions, email capture, analytics, sentiment, engagement
Requires at least: 6.2
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Reactions, inline email capture, and analytics — privacy-first and built for WordPress.

== Description ==

PulsePress lets readers react to posts and turns positive reactions into an opportunity to grow your email list, with privacy-safe analytics that show which content is working.

The free plugin is built to stand on its own: reactions, inline email capture, CSV export, display settings, shortcode/block rendering, privacy controls, and a lightweight analytics dashboard are all included.

### Free features in 0.1.0

* Add a reaction widget to posts automatically or with the `[pulsepress]` shortcode.
* Use a Gutenberg block when you want per-post placement.
* Let readers respond with configurable positive, neutral, and negative reactions.
* Choose minimal, expressive, story, or clap-counter widget styles.
* Show reaction counts always, never, or only after a threshold.
* Capture emails inline after positive reactions with explicit consent.
* Export captured emails to CSV from the WordPress admin.
* Review top posts, sentiment, captures, and aggregate engagement in an admin dashboard.
* Control auto-insert by public post type and hide the widget on specific posts.
* Customize labels for multi-language or brand-specific reaction wording.
* Keep data local to WordPress with no third-party tracking by default.

### Privacy-first by default

PulsePress does not send reader reactions or captured emails to a third-party service by default. Reaction deduplication uses a soft user hash, email captures stay in your WordPress database, and CSV export is available when you want to move data manually.

== Installation ==

1. Upload the `pulsepress` folder to `/wp-content/plugins/`.
2. Activate the plugin from the Plugins screen.
3. Go to Settings → PulsePress to configure reactions, display rules, capture settings, and analytics.

== Frequently Asked Questions ==

= Does PulsePress send visitor data to a third-party service? =

No. PulsePress stores reactions, captures, and analytics locally in WordPress by default.

= Can I export captured emails? =

Yes. Captures can be exported to CSV from the PulsePress admin screen.

= Does PulsePress require a paid account? =

No. The free plugin is designed to work on its own.

== Changelog ==

= 0.1.0 =
* Initial public release with reactions, inline email capture, CSV export, display settings, shortcode/block support, and analytics.
