## D1. Distribution Boundary

The release artifact is built from a temporary copy of the repository, not from the live working tree. The copy applies `.distignore` as the source of truth, then runs:

1. `npm ci`
2. `npm run build`
3. `composer install --no-dev --optimize-autoloader`
4. prune any ignored files that were needed only for the build
5. zip the remaining `pulsepress/` directory

Runtime files kept in the package:

- `pulsepress.php`, `uninstall.php`, `index.php`, `readme.txt`, `license.txt`
- `app/`
- `blocks/`
- `dist/`
- `vendor/`

Repo/build-only files excluded from the package:

- `.git*`, `.github/`, `.idea/`, `.vscode/`
- `docs/`, `openspec/`, `tests/`, coverage and Playwright output
- `node_modules/`
- `resources/` TypeScript/CSS source once compiled into `dist/`
- `AGENTS.md`, `package*.json`, `composer.json`, `composer.lock`, `phpunit.xml`, `vite.config.js`, `tsconfig.json`, scripts

## D2. Readme Scope

The WordPress.org `readme.txt` describes only functionality available in Free. It includes:

- current summary and tags
- feature-focused description
- settings and analytics overview
- privacy note naming stored data and retention behavior
- hook/filter note pointing developers to package docs/source
- screenshot captions
- FAQ
- changelog for `0.1.0`

It avoids claims about Pro-only functionality being available in Free.

## D3. Asset Checklist

Final images are not generated in this slice because they require product taste/brand approval. Instead, `docs/wordpress-org-assets.md` records exact required dimensions, filenames, suggested screenshot sequence, caption text, and review notes. That gives the next visual pass a bounded checklist.

## D4. License And Attribution

PulsePress code is GPL-2.0-or-later. The package includes `license.txt` and `docs/package-attribution.md` records third-party dependencies reviewed for the Free package. The build uses Composer production install and Vite output so dev-only packages do not ship.

## D5. Verification

The change is complete when:

- OpenSpec validates the change in strict mode.
- PHP tests pass.
- Runtime PHP files lint on PHP 7.4, 8.0, 8.1, 8.2, 8.3, and 8.4.
- TypeScript checks pass.
- Vite build emits `dist/.vite/manifest.json`.
- Release builder emits a zip.
- Zip inspection confirms runtime files are present and excluded files are absent.
- `readme.txt` passes local parser/lint checks where available.

## D6. PHP 7.4 Runtime Floor

The WordPress.org package supports PHP 7.4 through PHP 8.4. Runtime code must avoid PHP 8-only syntax: union types, constructor property promotion, named arguments, `readonly` classes, `mixed` type declarations, `match`, `str_contains`, and catch-without-variable. Typed properties, nullable types, arrow functions, and null coalescing assignment are allowed because PHP 7.4 supports them.
