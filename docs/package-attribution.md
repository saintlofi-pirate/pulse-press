# Package Attribution

Moonfarmer Reactions Lead Capture Free ships under GPL-2.0-or-later.

## Runtime PHP

- Moonfarmer Reactions Lead Capture PHP source: GPL-2.0-or-later.
- Composer production autoload files: generated from Composer metadata during packaging.

## Runtime JavaScript

- Moonfarmer Reactions Lead Capture admin and widget source: GPL-2.0-or-later.
- Preact: MIT license. Bundled into the Vite output and compatible with GPL-2.0-or-later distribution.

## Development-Only Dependencies

The following dependencies are used to build or test the package and are not intended to ship in the WordPress.org zip:

- Vite and TypeScript tooling.
- Playwright test tooling.
- Pest test tooling and dev-only Composer dependencies.

## Review Notes

- The release zip should contain `dist/` output, not `resources/` TypeScript/CSS source.
- The release zip should contain Composer production `vendor/`, not dev-only test dependencies.
- Final WordPress.org screenshots, banners, and icons must use assets owned by the project or assets with GPL-compatible rights.
