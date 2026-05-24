## Why

Session 12 is the WordPress.org packaging pass. Moonfarmer Reactions Lead Capture already has the free-product surface promised by the v1 plan: reactions, inline email capture, CSV export, 30-day analytics, block/shortcode placement, settings, per-post overrides, privacy controls, and a Pro extension surface. The repository still presents itself like an early scaffold, though: `readme.txt` is outdated, the package boundary has not been exercised, and there is no submission checklist for screenshots or plugin assets.

WordPress.org review depends on the shipped artifact, not the working tree. This change makes the free package auditable before submission.

## What Changes

- Replace the stub `readme.txt` with a WordPress.org-ready readme that describes the current free feature set, privacy posture, screenshots, FAQ, hooks, and changelog.
- Add a deterministic local release builder that installs production dependencies, builds Vite assets, copies only distribution files, and creates a zip for inspection.
- Tighten `.distignore` so repo-only assets, tests, source TypeScript, OpenSpec, docs, and tooling stay out of the WordPress.org package while runtime `dist/`, `vendor/`, `app/`, `blocks/`, and plugin entry files stay in.
- Add package-facing license and attribution notes for GPL compatibility review.
- Add a WordPress.org assets checklist for icons, banners, screenshots, captions, and submission notes.
- Lower the runtime PHP floor to 7.4 and verify syntax compatibility through PHP 8.4.

## Out Of Scope

- Creating final branded screenshot, banner, and icon image files. This change records the required asset list and captions; final visuals need brand/product approval.
- Submitting to WordPress.org or SVN. That requires account access and the final plugin slug.
- Version bumping beyond the existing `0.1.0` metadata.

## Free/Pro Boundary

The package is for the Free plugin only. It must not include Pro code, Pro-only locked UI, provider sync code, license checks, or upgrade-gated behavior that makes Free feel incomplete. The readme may mention Pro as future extension direction only where it clarifies hooks or roadmap boundaries.
