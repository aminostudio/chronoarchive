# ChronoArchive

**Chronological archives for WordPress.** A community-maintained GPL fork of **Annual Archive** by Twinpictures.

> **Development beta.** The LAB is still running `2.1.0-beta1`. A separate `2.1.0-beta2` integration candidate is being prepared and has **not** been deployed. Its SQL review and WordPress.org approval remain open; do not install it on production sites.

## Features

- Yearly, monthly, weekly, daily and decade archives.
- Post-by-post and alphabetical listings, optional counts, limits and ordering.
- Accessible dropdown navigation (vanilla JavaScript).
- Classic WordPress widget and Shortcode block integration.
- Support for public custom post types where their archive configuration permits it.
- Backwards-compatible `[archives]` and `[arcpromat]` aliases.

## Shortcodes

```text
[chronoarchive type="yearly" show_post_count="1"]
[chronoarchive type="decade" order="ASC"]
[chronoarchive type="weekly" limit="10"]
[chronoarchive type="monthly" format="option" select_text="Choose a month"]
[chronoarchive type="alpha" limit="20"]
[chronoarchive type="postbypost" limit="20"]
```

Allowed archive types: `yearly`, `monthly`, `weekly`, `daily`, `decade`, `alpha`, `postbypost`. The LAB uses `WordPress 7.1.4` and `PHP 8.4.27`. Tests on the minimum supported versions, custom post types and public frontend are incomplete.

## Project status

The `develop/import-lab-beta1` branch imports the four files installed on [Amino #LAB](https://labo.aminostudio.fr/): `chronoarchive.php`, `readme.txt`, `assets/js/archive-dropdown.js`, `js/widget_form.js`.

A fixture dataset of 30 dated posts (1997–2026), plus eight older test posts, supports 11 archive demonstrations. The WordPress-rendered archive lists matched SQL counts; direct navigation remains obscured by the laboratory's Under Construction plugin. See [the QA notes](docs/LAB-QA.md).

The original LAB baseline required repairs to the header, readme, shortcode/widget validation, permissions and SQL. The integration candidate carries reviewed changes and automated regression tests, but **not** stable release approval. See [LAB beta test plan](docs/BETA2-LAB-PLAN.md) and [SQL reviewer dossier](docs/SQL-REVIEW-REQUEST.md).

## Development

Run `bash scripts/check-static.sh` for syntax checks. Run `wp eval-file scripts/qa-wordpress.php` **only on an isolated, populated WordPress test installation**. The WordPress.org stable-release gate intentionally rejects the beta version, even when development metadata checks pass.

Consult [release blockers](docs/RELEASE-GATES.md), [security policy](SECURITY.md) and [contribution guide](CONTRIBUTING.md). Make fixes in feature branches with reviewable PRs. Do not tag or publish a stable release until WordPress Plugin Check, security review and full regression testing pass.

GitHub and WordPress.org are separate distribution channels. WordPress.org publication requires independent human review and SVN workflow following approval.

## Credits and license

GPL-2.0-or-later. Independently maintained fork of **Annual Archive** by Twinpictures. Original attributions and the permissive license notice in the inherited `js/widget_form.js` must be preserved. See [provenance and third-party notices](docs/NOTICE.md) and [LICENSE](LICENSE).

Maintainer: [Amino Studio](https://aminostudio.fr).
