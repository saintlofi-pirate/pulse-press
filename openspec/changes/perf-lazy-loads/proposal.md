## Why

Session 11b is the perf half of the planned a11y + perf pass. The widget entry chunk currently ships at **8.34 KB gzipped** (baseline `dist/js/ReactionBar.Dan9Ru8k.js`, 22,293 B raw → 8,545 B gzip). The v1 hard target is 15 KB and the stretch goal is 8 KB. The biggest deferrable in the bundle is `CaptureForm` (181 lines): it only renders when a visitor clicks a *positive* reaction, yet it ships eagerly inside `ReactionBar`.

The admin chunk (`dist/js/admin.CQh6HU0H.js`) ships at 7.32 KB gzip and bundles `DailySeriesChart` + `TopPostsTable`, both of which are only rendered on the Analytics tab. Most admins land on Display first; the chart and table are pure cold cost.

Both wins are pure code-splitting — no logic changes, no API changes, no a11y regressions.

## What Changes

- **Widget — lazy CaptureForm**: Replace the eager `import { CaptureForm } from './CaptureForm';` in `ReactionBar.tsx` with a dynamic `import()` triggered by the first state where `showCapture` becomes true. Hold the imported component in `useState`; render `null` (or a tiny "Loading…" placeholder under a `prefers-reduced-motion` aware spinner) while the chunk is in flight. Preserve the existing a11y contract: once mounted, CaptureForm still auto-focuses email, still announces as dialog, still supports Escape + focus return.
- **Admin SPA — lazy chart + table**: Same pattern inside `AnalyticsSection.tsx`. Kick off `import('../components/DailySeriesChart')` and `import('../components/TopPostsTable')` *only* when the analytics fetch resolves with non-empty data. Render the existing layout shell (header + metric cards + insight callout) immediately; show small skeleton placeholders where the chart and table will land.
- Vite already emits a separate chunk per dynamic import (we saw `ReactionBar.*.js` appear as its own chunk when we made it dynamic; CaptureForm + chart + table will do the same).
- **BREAKING**: none. Markup stays the same once components mount; only the cold-load timing changes.

## Capabilities

### Modified Capabilities

- `widget`: CaptureForm is lazy-loaded; first-paint cost drops; no behavioral change after mount.
- `admin-spa`: Analytics tab's chart + top-posts table are lazy-loaded; tab opens with metrics cards immediately, heavier widgets stream in.

## Impact

- **Modified files**: `resources/widget/components/ReactionBar.tsx`, `resources/admin/sections/AnalyticsSection.tsx`, `resources/admin/styles/admin.css` (skeleton placeholders), `resources/widget/widget.css` (capture spinner if needed).
- **New chunks emitted by Vite**: `CaptureForm.*.js`, `DailySeriesChart.*.js`, `TopPostsTable.*.js` — each loaded on demand.
- **Bundle budget impact (expected, will be re-measured)**:
  - Widget gzip: 8.34 KB → ~5.0 KB (clears 8 KB stretch).
  - Admin gzip: 7.32 KB → ~5.0 KB.
- **No new** filters, REST routes, DB writes, or i18n strings.
- **Tests**: existing pest suite stays green (no PHP behavior change). The dynamic imports are TypeScript-level; no test changes needed unless a UI smoke test breaks (none currently exist).
- **Manual verification**: On wp_lab.test, click a positive reaction and confirm the capture form still mounts under 500 ms over local dev. Open Analytics tab and confirm the chart streams in cleanly. Keep an eye on the Network panel: there should be one extra small JS request per lazy chunk.
- **A11y**: zero regression — the lazy wrappers don't touch ARIA, focus, or keyboard contracts. Skeleton placeholders use `role="status" aria-live="polite"` so screen readers announce the brief loading state.
