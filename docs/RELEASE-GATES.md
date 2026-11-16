# ChronoArchive release gates

**Last reviewed:** 16 November 2026. **Stable preflight candidate:** `2.1.0` on isolated [draft PR #30](https://github.com/aminostudio/chronoarchive/pull/30), based on [draft PR #28](https://github.com/aminostudio/chronoarchive/pull/28). **Production release:** NOT YET APPROVED. **LAB:** still runs `2.1.0-beta3`. The repository's public `main` is intentionally still a landing page, not a published plugin.

## Verified work completed

- [x] Main plugin headers and WordPress.org readme use the lowercase `chronoarchive` text domain and consistent minimum requirements.
- [x] Code-level hardening: input normalization, output escaping, capability-checked CSS, safe archive URLs, shortcode and widget protection.
- [x] All seven modes use native `WP_Query`. Five grouped archives fetch posts in 250-record batches and group/count unique posts in PHP; there are **no plugin-direct SQL execution sinks**. Changed historical SQL-fragment callbacks now fail closed and must migrate to scoped native post query hooks. See [SQL review](SQL-REVIEW-REQUEST.md).
- [x] WordPress/PHP matrix on stable preflight: **384 assertions per environment, 1,536/1,536 total**, native SQL filters, custom post types, safe legacy hook restrictions and all shortcode aliases. [CI #43795360846](https://github.com/aminostudio/chronoarchive/actions/runs/43795360846) passed 4/4 plus Chromium 2/2 and 3,000-post benchmark 7/7.
- [x] Real Chromium tests on a **disposable localhost WordPress site**: plain and pretty permalink archive navigation, external URL rejection, CSS/shortcode XSS regressions and authenticated settings-save/reload.
- [x] Deterministic beta3 ZIPs: LAB installation root `ChronoArchive/` and a separate lowercase WordPress.org QA root `chronoarchive/`, each built byte-identically in CI. See [workflow #43760394191](https://github.com/aminostudio/chronoarchive/actions/runs/43760394191).
- [x] Official WordPress.org **Plugin repo category** on the exact lowercase stable preflight ZIP: [run #43795360803](https://github.com/aminostudio/chronoarchive/actions/runs/43795360803) passed; **all 3 former SQL findings disappeared**. One unrelated overlong Upgrade Notice warning was fixed and enforced in metadata preflight. Recheck the final exact ZIP before approving publication.
- [x] LAB admin reported successful installation of `ChronoArchive-2.1.0-beta3-LAB.zip` on 15 November 2026. A 16 November authenticated **read-only** check independently verified active beta3, protected Under Construction state, published QA fixture #70 and draft security fixture #75. See [LAB acceptance notes](LAB-ACCEPTANCE-2026-11-16.md). The site has not had its actual settings saved for this QA: the `WP_ChronoArchive_options` row is absent. This is **not** completed frontend, settings-save or backup acceptance.
- [x] Recorded the exact published CVE-2023-0178 shortcode payload and checked it on three aliases, contributor role and real Chromium. Reviewed the archived Annual Archive 1.6.0 code for inherited HTML/CSS/widget sinks and added source-derived regression tests. Details in [UPSTREAM-CVE-MAPPING.md](UPSTREAM-CVE-MAPPING.md).

## Remaining P0 gates

- [x] **SQL source refactor:** the 2 errors and 1 warning were eliminated without scanner ignores by replacing raw SQL aggregation with paginated native `WP_Query`, and documenting the deliberate compatibility change. Keep [issue #29](https://github.com/aminostudio/chronoarchive/issues/29) open until the clean final exact-ZIP scan is archived.
- [ ] **Security sign-off:** obtain a defensible disposition for two advisories without public source-specific reproductions: `CVE-2023-49847` and `CVE-2024-33598`. Existing reviewed paths and passing CI are not a full independent security audit.
- [ ] **Protected LAB acceptance:** the connected read-only checks are recorded. Still verify a fresh restorable backup, browser navigation, actual settings persistence and classic widget rendering. Keep Under Construction enabled and security fixture #75 unpublished. No credentials or secrets in public issues.
- [x] **Exact stable package assembled and tested:** header, version constant, readme, changelog and upgrade notice are consistently `2.1.0`; the reproducible `chronoarchive/` ZIP is real and passed the stable packaging and WP/PHP test matrix. This is still an **audit candidate**, not an approved WordPress.org submission.
- [ ] **Strict release checks:** all CI jobs green on the same SHA, including *non-draft* Plugin Check on the **exact final ZIP** with any findings explicitly resolved or accepted by human review. Draft advisory success is NOT acceptance.
- [ ] **Maintainer approval and WordPress.org submission:** final audit acceptance and explicit sign-off, then upload a complete plugin ZIP using the developer account at https://wordpress.org/plugins/developers/add/. Review is manual. Only after approval does the WordPress.org SVN release process apply.

## Packaging rules

- The LAB test folder is **case-sensitive** `ChronoArchive/`; the future WordPress.org installable slug is lowercase `chronoarchive/`. Do **not** install both as active plugins on the same site.
- Beta builds must **fail** `scripts/build-wporg-zip.py --release` and `scripts/check-release-metadata.py --release`.
- Future genuine stable builds must **pass** both `--release` commands, build reproducibly, pass the under-10-MB limit and have a Git tag exactly matching `v<plugin version>`.
- The release-ready workflow and exact-ZIP Plugin Check both run on non-draft PRs and version tags. **Never use a green draft check as a permission to publish.**
- Do not put CI workflows, tests, prebuilt ZIPs, node modules, private QA pages or environment secrets in the WordPress.org distribution.

## P1 after first approval

- Additional locale assets and translated readmes.
- Extended block-theme/browser matrix and editor UX improvements.
- Independent performance studies on larger datasets.
- Optional publishing automation between GitHub and WordPress.org SVN.
- Aesthetic/admin enhancements and new archive features.

These items are deliberately postponed. The goal is to ship a small, reliable **2.1.0**, not an endlessly expanding pre-release.
