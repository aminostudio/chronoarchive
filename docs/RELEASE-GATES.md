# ChronoArchive release gates

**Last reviewed:** 15 November 2026. **Development candidate:** `2.1.0-beta3`. **Stable release:** NOT YET APPROVED. **Consolidated source:** [draft PR #28](https://github.com/aminostudio/chronoarchive/pull/28). The repository's public `main` is intentionally still a landing page, not a published plugin.

## Verified work completed

- [x] Main plugin headers and WordPress.org readme use the lowercase `chronoarchive` text domain and consistent minimum requirements.
- [x] Code-level hardening: input normalization, output escaping, capability-checked CSS, safe archive URLs, shortcode and widget protection.
- [x] Five grouped date queries run through one cache-aware database sink; ordinary `alpha` and `postbypost` listing paths use `WP_Query` unless registered legacy SQL hooks require the original query contract.
- [x] WordPress/PHP matrix: four integration jobs, **262 passing assertions per environment**, MariaDB grouped SQL, cached empty/failure behavior, CPTs and legacy aliases. Latest verified [CI #43760394136](https://github.com/aminostudio/chronoarchive/actions/runs/43760394136).
- [x] Real Chromium tests on a **disposable localhost WordPress site**: plain and pretty permalink archive navigation, external URL rejection, CSS/shortcode XSS regressions and authenticated settings-save/reload.
- [x] Deterministic beta3 ZIPs: LAB installation root `ChronoArchive/` and a separate lowercase WordPress.org QA root `chronoarchive/`, each built byte-identically in CI. See [workflow #43760394191](https://github.com/aminostudio/chronoarchive/actions/runs/43760394191).
- [x] Official Plugin Check runs on the **exact extracted lowercase QA ZIP**. Latest [run #43760394167](https://github.com/aminostudio/chronoarchive/actions/runs/43760394167) reported **zero new findings**, with existing SQL findings still unresolved.
- [x] LAB admin reported successful installation of `ChronoArchive-2.1.0-beta3-LAB.zip` on 15 November 2026. This is a **user-confirmed install**, not a complete independently authenticated LAB smoke test.
- [x] Recorded the exact published CVE-2023-0178 shortcode payload and checked it on three aliases, contributor role and real Chromium. Reviewed the archived Annual Archive 1.6.0 code for inherited HTML/CSS/widget sinks and added source-derived regression tests. Details in [UPSTREAM-CVE-MAPPING.md](UPSTREAM-CVE-MAPPING.md).

## Remaining P0 gates

- [ ] **SQL reviewer decision:** official Plugin Check still reports **2 errors** (`PluginCheck.Security.DirectDB.UnescapedDBParameter`, `WordPress.DB.PreparedSQL.NotPrepared`) and **1 direct-query warning** at the central `$wpdb->get_results( $query )` sink. The default post-type predicate is prepared; the public WordPress `getarchives_where`/`getarchives_join` hooks can supply additional SQL fragments from installed PHP extensions. Do not hide, blindly quote or misrepresent these hooks. See [issue #29](https://github.com/aminostudio/chronoarchive/issues/29) and [SQL-REVIEW-REQUEST.md](SQL-REVIEW-REQUEST.md).
- [ ] **Security sign-off:** obtain a defensible disposition for two advisories without public source-specific reproductions: `CVE-2023-49847` and `CVE-2024-33598`. Existing reviewed paths and passing CI are not a full independent security audit.
- [ ] **Protected LAB acceptance:** verify a fresh backup and rollback path; run authenticated smoke tests of live page navigation, settings persistence, widget behavior and historical URLs without disabling Under Construction for normal visitors. No credentials or secrets in public issues.
- [ ] **Exact stable package:** set header constant/readme/changelog/upgrade notice to `2.1.0` in one reviewed change; remove beta-only user-facing copy, keep source copyright notices, verify clean contents and `chronoarchive/` root, and test against the required WP/PHP matrix.
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
