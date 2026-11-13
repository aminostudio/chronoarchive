# Security issue #2 · Shortcode, returned output and HTTP archive regression

**Branch:** `security/issue-2-real-shortcode-regression`  
**Stacked on:** `security/issue-2-hardening` (draft PR #6)  
**Status:** reviewed code with automated evidence, **not security-certified and not published**.

## Trust boundaries addressed

### 1. Shortcode filters

WordPress invokes the `shortcode_atts_archives` filter after parsing shortcode attributes. A trusted third-party extension may unexpectedly return values of other PHP types, including arrays, objects or a non-array result. The plugin now:

- Defaults to an empty input array if the incoming shortcode attribute collection is not an array.
- Re-checks the filtered collection, fills missing defaults and normalizes non-scalar values **before** calling `sanitize_key()`, `wp_kses_post()`, `sanitize_text_field()`, `strtoupper()` and `absint()`.
- Retains allowed archive periods, markup containers and output formats, and preserves historical truthy `use_cpt_archive` options.

This is defense-in-depth for a WordPress PHP hook and legacy compatibility. It is **not** a claim of a newly demonstrated vulnerability in ChronoArchive.

### 2. Returned archive markup

Earlier security work escaped HTML output when the API was called with `echo=1`, but the `echo=0` branch, used by shortcode rendering, returned the filtered markup without the same final output policy.

This change applies late escaping to **both** paths. Return-mode output now uses:

- `wp_kses_post()` for normal archive links, preserving WordPress-permitted safe tags while removing script tags and unsafe event handlers.
- `wp_kses()` with an `option` / `value` allowlist for dropdown options.
- `wp_kses()` with a `link` / `rel`, `title`, `href` allowlist for LINK output.

Testing includes a WordPress `get_archives_link` filter that appends script tags and an image event handler. Tests expect the unsafe **script and attributes** to disappear; `wp_kses_post()` may legitimately preserve a safe `<img>` element.

## Verification results

| Test | Result | Evidence |
| --- | --- | --- |
| PHP syntax and isolated security mock suite | **36/36** for PHP 7.2, 8.2, 8.4 (**108 passes**) | [GitHub static run 43431075863](https://github.com/aminostudio/chronoarchive/actions/runs/43431075863) |
| Real WordPress/MariaDB integration | **63/63** per environment across 4 versions (**252 passes**) | [Run 43431075859](https://github.com/aminostudio/chronoarchive/actions/runs/43431075859) |
| Real anonymous HTTP weekly archive | **200**, returns Dec 31 2019 and Jan 1 2020 fixture articles | Same integration run |
| Real anonymous HTTP invalid week | **404**, no unrelated archive results | Same integration run |
| Real anonymous HTTP decade 2010–2019 | **200**, contains December 2019 fixture and excludes January 2020 fixture | Same integration run |
| Real anonymous HTTP invalid decade | **404** | Same integration run |

**Matrix:** WordPress 6.5.5/PHP 7.2, WordPress 6.5.5/PHP 8.2, WordPress latest/PHP 8.2, WordPress latest/PHP 8.4. MariaDB 11 is run as a CI service.

HTTP tests run **plain-permalink query URLs**, inside disposable WordPress and with a compatible Twenty Twenty-Four theme installed. Initially the CI web server returned HTTP 200 with an empty body because `wp core download --skip-content` also omitted the theme. The test environment now installs a theme explicitly before issuing HTTP requests. These are **real PHP web requests**, distinct from CLI `WP_Query` tests, but do **not** cover the Nginx/Apache pretty-permalink rewrite layer.

## Security advisory scope

Verified public advisory sources for upstream **Annual Archive**:

- [CVE-2023-0178](https://nvd.nist.gov/vuln/detail/CVE-2023-0178): shortcode attribute sanitization/escaping, versions earlier than 1.6.0, contributor+.
- [CVE-2023-49847](https://wpscan.com/vulnerability/96c8b2d4-9dfd-409a-a83c-a7148fb69c58/): stored XSS, versions through 1.6.0, contributor+.
- [CVE-2024-33598](https://patchstack.com/database/wordpress/plugin/anual-archive/vulnerability/wordpress-annual-archive-plugin-1-6-0-cross-site-scripting-xss-vulnerability-2): stored XSS, versions through 1.6.0, high privileges.

The advisories do not provide complete publicly verified proof paths in the files reviewed here. They **must not** be treated as proof the current fork is vulnerable or entirely remediated. A byte-precise upstream 1.6.0 comparison remains a release gate.

## Remaining blockers

- [ ] Genuine multisite testing with network super admin and subsite admin (separate [CSS hardening PR #9](https://github.com/aminostudio/chronoarchive/pull/9)).
- [ ] WordPress editor save/publish/preview flows for actual contributor-authored shortcode content, plus accessibility checks.
- [ ] Pretty-permalink HTTP routes on Nginx and Apache, including redirects, invalid links and status.
- [ ] Archive SQL preparation and strict grouping, including all Plugin Check database findings (#4).
- [ ] WordPress.org metadata, readme, i18n and packaging (#3).
- [ ] Review all Plugin Check security findings on the final combined release candidate.

**Release decision:** Keep #2 and PR #6 open, keep stacked PRs as drafts until reviewed, and do not tag a stable version or deploy these changes to the LAB without full regression and explicit QA.
