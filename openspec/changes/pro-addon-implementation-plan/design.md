## D1. Separate Repo

Pro lives in a private `moonfarmer-reactions-lead-capture-pro` repo. This keeps WordPress.org Free packaging separate from paid distribution, license code, provider credentials, and private update plumbing.

## D2. Reuse Contract

Pro consumes the Free surface in `docs/hooks-and-filters.md`:

- PHP filters/actions for settings, capture, analytics, exports, widgets, and lifecycle.
- JS renderer registry exposed as `window.MoonfarmerReactionsLeadCaptureAdmin.register*Renderer`.
- Free constants only for version/path checks.

Pro must not edit or monkey-patch Free files.

## D3. PHP Compatibility

Pro matches Free's runtime floor: PHP 7.4 through 8.4. Runtime code avoids PHP 8-only syntax so the paid addon can run wherever the Free plugin says it can run.

## D4. Sessionized Build

The plan splits Pro into implementation sessions:

- P0 bootstrap and compatibility gate
- P1 license shell
- P2 admin extension runtime
- P3 ESP sync
- P4 rollups and comparison analytics
- P5 A/B testing
- P6 segments
- P7 webhooks
- P8 async reports
- P9 white-label

Each session has a deliverable and verification target.

## D5. Coordination Rule

Any Pro need that cannot be implemented through an existing Free hook becomes a Free additive-hook change first. Breaking hook changes require paired Free/Pro release coordination.
