## 1. Lazy CaptureForm

- [x] 1.1 In `resources/widget/components/ReactionBar.tsx`, replace the eager `import { CaptureForm }` with a typed `const CaptureFormPromise = () => import('./CaptureForm');` pattern.
- [x] 1.2 Add `const [LoadedCaptureForm, setLoadedCaptureForm] = useState<typeof import('./CaptureForm').CaptureForm | null>(null);`.
- [x] 1.3 In a `useEffect` keyed on `showCapture`, when `showCapture` becomes true and `LoadedCaptureForm === null`, call the dynamic import, set the resolved `CaptureForm` into state, and ignore the resolution if the effect cleaned up (cancellation flag).
- [x] 1.4 Render placeholder when `showCapture && !LoadedCaptureForm` — small `<p role="status" aria-live="polite" class="pulsepress-capture-loading">{data.i18n.capture.submitting}</p>` style placeholder that uses the already-translated "Submitting…" string as a generic "Loading…" stand-in (or add a new i18n key if cleaner).
- [x] 1.5 Once loaded, render `<LoadedCaptureForm {...captureProps} />` exactly where the eager component previously rendered.

## 2. Lazy DailySeriesChart + TopPostsTable

- [x] 2.1 In `resources/admin/sections/AnalyticsSection.tsx`, replace the eager imports of `DailySeriesChart` and `TopPostsTable` with two dynamic-import state hooks (same pattern as 1.2–1.3).
- [x] 2.2 Trigger the imports inside the same `useEffect(() => { void load(); }, [load])` block — kick off both `import()`s alongside the analytics fetch so the JS arrives during the network wait.
- [x] 2.3 In the render path, swap the eager `<DailySeriesChart .../>` / `<TopPostsTable .../>` for placeholder skeleton divs while the dynamic chunks are loading.
- [x] 2.4 Skeleton CSS: add `.pulsepress-skeleton` rule in `resources/admin/styles/admin.css` (subtle pulsing block respecting reduced motion).

## 3. Measure + verify

- [x] 3.1 `npm run build` — confirm Vite emits `CaptureForm.*.js`, `DailySeriesChart.*.js`, `TopPostsTable.*.js` as separate chunks.
- [x] 3.2 Record raw + gzip sizes for the ReactionBar chunk and the admin chunk before and after; include the diff in the commit message.
- [x] 3.3 `npx tsc --noEmit` clean.
- [x] 3.4 `./vendor/bin/pest` — full suite green.
- [x] 3.5 Manual on wp_lab.test: click positive reaction → capture form mounts; Analytics tab → chart + table stream in cleanly.

## 4. Commit

- [x] 4.1 Commit Session 11b with the before/after size table.
