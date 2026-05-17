## 1. OpenSpec

- [x] 1.1 Create `openspec/changes/wordpress-org-package`.
- [x] 1.2 Add proposal/design/spec/tasks for the WordPress.org package contract.
- [x] 1.3 Run `openspec validate wordpress-org-package --strict --no-interactive`.

## 2. WordPress.org Readme

- [x] 2.1 Replace the scaffold `readme.txt` with current Free-plugin features.
- [x] 2.2 Add privacy, FAQ, screenshots, and changelog sections.
- [x] 2.3 Remove stale text claiming the configuration UI ships later.

## 3. Package Boundary

- [x] 3.1 Tighten `.distignore` around runtime vs repo-only files.
- [x] 3.2 Add a local release build script that emits `build/pulsepress-0.1.0.zip`.
- [x] 3.3 Add package attribution and WordPress.org asset checklist docs.
- [x] 3.4 Add `license.txt` to the runtime package.
- [x] 3.5 Lower runtime PHP floor to 7.4 and remove PHP 8-only syntax from runtime files.

## 4. Verification

- [x] 4.1 Install PHP and Node dependencies as needed.
- [x] 4.2 Run `./vendor/bin/pest`.
- [x] 4.3 Run `npx tsc --noEmit`.
- [x] 4.4 Run `npm run build`.
- [x] 4.5 Lint runtime PHP files with PHP 7.4, 8.0, 8.1, 8.2, 8.3, and 8.4.
- [x] 4.6 Run the release builder and inspect the zip contents.
- [x] 4.7 Check for a WordPress readme parser and run a local structural readme check.

## 5. Review

- [x] 5.1 Review `git diff` for package-only scope.
- [x] 5.2 List remaining tasks that require user input, such as final brand assets or WordPress.org account submission.
