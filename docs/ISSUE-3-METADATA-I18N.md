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

- [ ] PHP 7.2 / 8.2 / 8.4 lint and mock tests.
- [ ] Real WordPress + HTTP archive integration matrix.
- [ ] Genuine WordPress multisite matrix.
- [ ] Metadata schema CI success for plugin header/readme.
- [ ] Independent WordPress Plugin Check scan of final release candidate: i18n/readme observations resolved, SQL observations triaged separately (#4).
- [ ] WordPress.org SVN/tag packaging and human review (after final stable version).
- [ ] WordPress i18n extraction / POT check; language packs after WordPress.org acceptance.

## Important distinction

A successful `python3 scripts/check-release-metadata.py` means **only that local metadata structure and consistency pass**. Running `python3 scripts/check-release-metadata.py --release` must currently **fail** because this code still identifies itself as `2.1.0-beta1`.

Do not change to a stable version just to make release CI green. WordPress.org submission is blocked until security #2, SQL #4, full Plugin Check, an installable ZIP and acceptance review are complete.

## Source and API compatibility

The original Annual Archive options group `ChronoArchive` and persisted `WP_ChronoArchive_options` option name were not renamed. Shortcodes, widget identifiers, and archive URLs were not changed by this metadata sprint. Historical upstream authorship remains acknowledged.

As with other stacked PRs, no live Amino #LAB files or production sites were modified.
