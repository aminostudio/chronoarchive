# ChronoArchive security audit · Issue #2

**Baseline:** `2.1.0-beta1` imported from Amino #LAB (unmodified baseline on `develop/import-lab-beta1`).  
**Patch branch:** `security/issue-2-hardening` → [draft PR #6](https://github.com/aminostudio/chronoarchive/pull/6).  
**Assessment:** **PARTIAL SECURITY REVIEW, NOT CERTIFIED FOR PRODUCTION**. This is a source hardening and regression-testing effort, not a penetration test.

## 1. Upstream CVE provenance

The historical WordPress plugin **Annual Archive** (`anual-archive`, Twinpictures) had published XSS advisories:

| Identifier | Upstream versions | Confirmed advisory description | Source |
| --- | --- | --- | --- |
| CVE-2023-0178 | Earlier than 1.6.0 | Contributor+ stored XSS through insufficiently validated/escaped shortcode attributes. | [NVD](https://nvd.nist.gov/vuln/detail/CVE-2023-0178) |
| CVE-2023-49847 | Up to and including 1.6.0 | Contributor+ stored XSS; WPScan currently lists **no known fix**. | [WPScan](https://wpscan.com/vulnerability/96c8b2d4-9dfd-409a-a83c-a7148fb69c58/) |
| CVE-2024-33598 | Up to and including 1.6.0 | Stored XSS requiring administrator-level privileges; no official patch identified by Patchstack. | [Patchstack](https://patchstack.com/database/wordpress/plugin/anual-archive/vulnerability/wordpress-annual-archive-plugin-1-6-0-cross-site-scripting-xss-vulnerability-2) |

**Do not transfer these CVEs blindly onto ChronoArchive.** The fork differs from the reported versions. Determining whether each vulnerable code path survived the fork requires full reproduction and code comparison.

The [WordPress plugin mirror](https://github.com/wp-plugins/anual-archive) is an archived upstream resource and does not contain a confirmed exact 1.6.0 source snapshot. A precise revision-level diff remains outstanding.

## 2. Implemented hardening (branch only)

| Area | Change | Trust boundary |
| --- | --- | --- |
| Standalone PHP request | `ABSPATH` guard; removed closing `?>` tag to avoid unintended output during activation | Direct access and WordPress activation |
| Archive SQL arguments | `wp_get_archives_advanced()` revalidates types, date archive format, sorting, limits and registered public post types even for non-shortcode callers | Programmatic and legacy widget input |
| Shortcode HTML | `before`/`after` sanitized with WordPress `wp_kses_post()`; limited tag names and safe dropdown options | Contributor-authored content |
| Legacy widget | Removed `extract($args)`; normalized malformed scalar input in `update()`, `form()`, and `widget()`; handled unexpected filtered values | Old stored options and filter integrations |
| Custom styles | Type/size checks on previously saved values, no raw `<style>` output; use `wp_register_style`/`wp_enqueue_style`/`wp_add_inline_style`, and HTML-safe `<` CSS escaping | Administrative CSS stored in options |
| Options admin screen | Explicit `manage_options` capability guard; WordPress Settings API manages nonces | Administrator-only settings |
| Archive query vars | `chrono_week` scoped to main archive query, invalid decades fail closed | Public URL/query input |

### CSS policy limitation

An administrator can still supply custom CSS, including rules that request external resources. `safe_custom_css()` is a **markup boundary** and not a comprehensive CSS parser or an outbound-request policy. The old value is revalidated at runtime. Further review is required for multisite permissions and CSS semantics.

### SQL trust boundary

The plugin prepares post-type input and limits SQL sort directions, but `getarchives_where` and `getarchives_join` are **trusted WordPress PHP filter extensions**. Arbitrary PHP code in other installed plugins is outside this plugin's security boundary. `ONLY_FULL_GROUP_BY` compatibility and large archive datasets remain tracked in [#4](https://github.com/aminostudio/chronoarchive/issues/4).

## 3. Evidence: GitHub Actions and WordPress

### Mock PHP robustness (test stubs)

`tests/security-smoke.php`: **25/25 assertions successful** on PHP 7.2, 8.2 and 8.4 (75 passing matrix assertions). Covered input arrays, invalid format/sort, HTML event attributes, widget corruption, CSS output, unsafe week/decade selectors, and safe read/write paths.

### Real WordPress / MariaDB 11

`tests/wp-integration.php`: **55/55 assertions successful in each environment** in the four-cell WordPress/PHP matrix: WordPress 6.5.5 with PHP 7.2 and 8.2, and WordPress 7.1.4 with PHP 8.2 and 8.4 (**220 test assertions**). [Verified extended matrix, run #43375610692](https://github.com/aminostudio/chronoarchive/actions/runs/43375610692). This includes late escaping for option/link formats, malformed weekly archive dates and invalid decade query results.

The suite creates 30 synthetic dated posts (1997–2026), inserts the 2019-12-31 / 2020-01-01 boundary cases, and tests:

- All three shortcode aliases, annual/decade/month/day/week/alpha/post-by-post rendering and limits.
- Explicitly executing the SQL-backed cross-year query, verifying that it retrieves posts from **both calendar years**.
- Executing an entire decade query, with the test bypassing WordPress's default ten-post pagination to verify both endpoints.
- Restricted editor input: **contributor** shortcut attributes containing event-handler payloads are stripped; contributor cannot view plugin settings.
- Malformed historical widget settings and classic widget frontend/form rendering.
- Real WordPress Styles API custom CSS behavior, rejecting embedded HTML escape sequences.
- Public custom post type without an archive base falls back to a safe parameterized link.
- Guest (unauthenticated) settings access refused.

Earlier QA on Amino #LAB confirmed generated archive markup and SQL grouping counts, but **Under Construction** prevents anonymous browser navigation checks. This PR does **not** deploy its changed files to that site.

### Activation regression discovered by CI

The first integration test revealed output during `wp plugin activate` from a trailing closing PHP tag in the inherited file. The closing tag was removed and activation subsequently passed.

### Plugin Check status

An automated [official WordPress Plugin Check](https://github.com/WordPress/plugin-check-action) workflow scans this repository, annotates errors and uploads the findings artifact. It measured **163 findings** on the inherited beta, **105** after replacing the legacy admin settings UI, **74** after widget output escaping, and **71** after hardening direct HTML output and removing discouraged stripping calls ([verified run #43375281884](https://github.com/aminostudio/chronoarchive/actions/runs/43375281884)). These are dated checkpoints, not a security certification; the job remains red due to unresolved metadata and SQL findings.

Triage at the **71-finding checkpoint**:
- **#2:** 0 remaining `WordPress.Security.EscapeOutput.*` findings **detected by Plugin Check**, compared with 32 at an earlier checkpoint. This is not proof that all XSS vectors have been fixed.
- **#3, i18n / distribution:** 48 text-domain mismatches, one discouraged manual text-domain loader, one missing translator comment, two readme parser warnings, one `NOTICE.md` warning (upstream licensing notices must be retained when repackaging).
- **#4, database / query:** six `PluginCheck.Security.DirectDB.UnescapedDBParameter` errors, six `WordPress.DB.PreparedSQL.NotPrepared` errors, and six direct-query warnings. The post-type condition is prepared, with allowlisted sort and numeric limits; trusted PHP filters may extend `JOIN` and `WHERE`. Review with actual SQL and eliminate scanner findings through safe construction rather than blind suppressions.
- **Total:** 0 output-escaping observations + 53 metadata/distribution observations + 18 SQL observations = **71**.

WordPress.org release metadata gate intentionally fails on the old plugin header/readme, tracked in [#3](https://github.com/aminostudio/chronoarchive/issues/3). **No release permitted while critical findings remain.**

## 4. Remaining blockers

- [ ] Reproduce / map each upstream CVE against the full historical 1.6.0 source, including stored XSS variants. Do not claim all CVEs fixed without the evidence.
- [ ] Review the official Plugin Check output, resolve security findings and clearly route non-security issues to #3/#4.
- [ ] Reproduce historical and multisite options behavior with actual sites and capability matrices; test admin editor with malicious preexisting option values.
- [ ] Verify all generated links and negative URL paths through a browser HTTP session, including malformed `chrono_week`, archives and 404s. The CI suite exercises malformed values at the real WordPress WP_Query layer, but is not a full browser test.
- [x] Test the minimum claimed WordPress/PHP combinations: WordPress 6.5.5 / PHP 7.2, WordPress 6.5.5 / PHP 8.2, WordPress 7.1.4 / PHP 8.2 and WordPress 7.1.4 / PHP 8.4. All 55 assertions pass per environment on run #43375610692. **Alternate theme and multisite tests remain pending.**
- [ ] Review escaping of administrator-facing markup, JS accessibility and the exact plugin distribution ZIP.
- [ ] Perform a direct LAB file export to confirm byte-exact provenance of the imported baseline.

**Release policy:** #2 stays open; #6 stays draft; do not merge into main or publish a stable tag while P0 items remain.
