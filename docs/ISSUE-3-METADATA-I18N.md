# Issue #3 · Metadata and internationalization review

**Work branch:** `release/issue-3-metadata-i18n` (stacked on the verified, still-draft security integration branch `security/issue-2-combined-validation`).

**State:** Metadata and i18n **development corrections**, not approval for WordPress.org or a stable release.

## Changes made

- Added `Requires at least: 6.5`, `Requires PHP: 7.2`, `Text Domain: chronoarchive`, `License: GPLv2 or later` and a license URL to the main plugin header. These minimum versions match the CI configuration; the lab remains on a newer WordPress/PHP combination.
- Matched the 48 PHP translation-domain references to the lowercase WordPress.org plugin slug `chronoarchive`; kept user-facing product name `ChronoArchive`.
- Delayed explicit text-domain loading to `init`; supplied the conventional languages path.
- **Kept** `$domain = 'ChronoArchive'` as the historical Settings API group identifier. Changing it without a migration could silently lose legacy option lookups. This value is **not a translation text domain**.
- Rewrote `readme.txt` in the WordPress.org format (real headers; description, installation, FAQ, changelog and upgrade notice).
- Limited tags to five and the short description to 150 characters.
- Removed speculative/obsolete social, donation, documentation and translation links from the distribution readme until actually available.
- Preserved the historical Annual Archive changelog separately in `docs/UPSTREAM-CHANGELOG.md` and kept Twinpictures attribution visible.
- Added a development-time **metadata schema** test to the standard CI and preserved the independent **strict release gate**. The latter rejects a `-beta` version even when formatting is correct.

## Validation to collect

- [x] PHP 7.2 / 8.2 / 8.4 lint and 43/43 mock assertions per PHP version; [run 43451028707](https://github.com/aminostudio/chronoarchive/actions/runs/43451028707).
- [x] Four real WordPress/PHP combinations, 69/69 assertions each (276 passes), plus 200/404 HTTP archive checks; [run 43451028734](https://github.com/aminostudio/chronoarchive/actions/runs/43451028734).
- [x] Genuine multisite network/subsite capability tests, 14/14 assertions × 2 environments (28 passes); [run 43451028924](https://github.com/aminostudio/chronoarchive/actions/runs/43451028924).
- [x] All 5 static CI jobs passed, including WordPress.org metadata consistency and a test confirming the beta correctly **fails** the strict stable-release gate; [run 43451028707](https://github.com/aminostudio/chronoarchive/actions/runs/43451028707).
- [ ] Independent WordPress Plugin Check scan of final release candidate: i18n/readme observations resolved, SQL observations triaged separately (#4).
- [ ] WordPress.org SVN/tag packaging and human review (after final stable version).
- [ ] WordPress i18n extraction / POT check; language packs after WordPress.org acceptance.

## Important distinction

A successful `python3 scripts/check-release-metadata.py` means **only that local metadata structure and consistency pass**. Running `python3 scripts/check-release-metadata.py --release` must currently **fail** because this code still identifies itself as `2.1.0-beta1`.

Do not change to a stable version just to make release CI green. WordPress.org submission is blocked until security #2, SQL #4, full Plugin Check, an installable ZIP and acceptance review are complete.

## Source and API compatibility

The original Annual Archive options group `ChronoArchive` and persisted `WP_ChronoArchive_options` option name were not renamed. Shortcodes, widget identifiers, and archive URLs were not changed by this metadata sprint. Historical upstream authorship remains acknowledged.

As with other stacked PRs, no live Amino #LAB files or production sites were modified.

## CI checkpoint (2026-11-13)

At commit `60ecb9835577737b4643e90b0cb727f468dd2fd4`, the development metadata schema, PHP/JS, WordPress integration and multisite suites all passed. This is **433 successful assertions** across their matrices (129 mock + 276 WordPress + 28 multisite), plus syntax/metadata and HTTP checks.

**Not executed on this draft PR:** official Plugin Check and strict release-readiness jobs, which are intentionally skipped until ready-for-review or manual invocation. Previous inherited SQL and packaging findings remain tracked under #4 and the final release checklist. A passing metadata-schema job is **not** proof that all WordPress.org requirements or security risks are resolved.

The main branch remains a public project landing page; this branch is still a draft and does not update Amino #LAB.


## Follow-on WordPress.org compatibility (2026-11-14)

The official Plugin Check 2.2.0 run [43552800337](https://github.com/aminostudio/chronoarchive/actions/runs/43552800337) reported `load_plugin_textdomain()` as discouraged on WordPress.org. The cleanup removes the `init` hook and explicit loader, leaving the consistent `chronoarchive` text domain intact.

WordPress.org manages language packs and just-in-time translation loading for hosted plugins. This project presently contains no bundled `languages/` folder. Distribution-specific bundled translations would need their own loading plan and tests.

The historical legal/attribution notice is now at [NOTICE.md](NOTICE.md), with upstream attribution retained in the JS file, distribution readme and root LICENSE. No release or deployment follows from this cleanup.
