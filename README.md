# Moonfarmer Reactions Lead Capture

Moonfarmer Reactions Lead Capture is a WordPress plugin for privacy-first reactions, inline email capture, and lightweight post-level analytics.

It helps publishers, creators, and small business sites understand how visitors respond to content, then capture email leads when a visitor shows positive intent.

## What It Does

- Adds accessible reaction buttons to WordPress posts.
- Shows reaction counts with fast front-end updates.
- Opens an inline email capture prompt after positive reactions.
- Stores consent-aware capture records in WordPress.
- Provides 30-day reaction and capture analytics.
- Exports captured emails as CSV.
- Supports automatic placement, a Gutenberg block, and the `[moonfarmer-reactions-lead-capture]` shortcode.
- Includes per-post visibility controls and privacy settings.

## Requirements

- WordPress 6.2 or newer
- Tested up to WordPress 7.0
- PHP 7.4 or newer
- Node.js and npm for asset builds
- Composer for PHP development dependencies

## Installation

For normal WordPress use, install the packaged plugin folder:

```text
moonfarmer-reactions-lead-capture
```

Then activate **Moonfarmer Reactions Lead Capture** from the WordPress Plugins screen.

For development:

```bash
composer install
npm install
npm run build
```

## Development Checks

```bash
composer validate --no-check-publish
find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l
./vendor/bin/pest
npm run build
```

## Release Archive

Build the distributable ZIP with:

```bash
./scripts/build-release.sh
```

The generated archive uses the WordPress.org plugin slug:

```text
moonfarmer-reactions-lead-capture
```

## WordPress.org Readme

This repository has two readme files on purpose:

- `README.md` is for GitHub.
- `readme.txt` is for WordPress.org plugin metadata, installation text, FAQ, screenshots, and changelog.

Keep WordPress.org-specific fields such as `Requires at least`, `Tested up to`, `Stable tag`, and the plugin changelog in `readme.txt`.

## License

GPL-2.0-or-later. See [license.txt](license.txt).
