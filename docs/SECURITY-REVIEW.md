# Security review backlog

This file is a **review plan**, not a completed vulnerability audit.

## Upstream XSS history

Annual Archive, the predecessor project, has published security advisories including `CVE-2023-0178` and `CVE-2024-33598`. Before release, fetch original advisories and identify vulnerable parameters, attacker capabilities, the exact code paths and upstream fixes. Test whether inherited ChronoArchive paths are affected. Do not infer safety merely from changed version numbers.

## Inputs and sinks to review

- Shortcode `type`, `limit`, `format`, `tag`, `post_type`, `select_text`, `before`, `after` and legacy count arguments.
- Saved options `custom_css` and `use_cpt_archive`.
- Widget fields (`before`, `after`, `title`, `format`, `post_type`, ordering) and fallback HTML.
- `get_archives_link` filters, WordPress post titles and third-party content.
- SQL fragments `getarchives_where` / `getarchives_join` provided by filters (trusted extension integration only), post type lookup and limit.
- `chrono_week` / `decade` query variables, invalid dates and query broadening.
- The admin settings screen: capability checks, nonce, escaping and obsolete external links.

## Particular questions

- Does custom CSS printing use a suitable safe model and avoid unexpected content?
- Does HTML escaping occur at the correct sink, rather than double-escaping functional markup?
- Do weekly group links return exactly the expected results across 31 December / 1 January?
- Could plugins alter shortcode output via WordPress filters, and what trust boundary applies?
- Is `only_full_group_by` SQL mode compatible with every group query?
- Can any invalid post-type choice produce a database error or reveal private posts?

## Evidence to collect

Store a minimal test case, before/after output, regression test and environment for every fix. Re-run the full QA suite and Plugin Check on a release candidate. Do not publish exploit details before a fix.

## Ticket #2: first hardening pass (review branch, 13 November 2026)

**Reviewed source:** `chronoarchive.php` imported from Amino #LAB `2.1.0-beta1`. **Not a complete security certification.**

### Historical advisories researched

- [CVE-2023-0178 (NVD)](https://nvd.nist.gov/vuln/detail/CVE-2023-0178): predecessor Annual Archive before 1.6.0, stored XSS via inadequately validated/escaped shortcode attributes; described as contributor role and above.
- [CVE-2023-49847 (Patchstack)](https://patchstack.com/database/wordpress/plugin/anual-archive/vulnerability/wordpress-annual-archive-plugin-1-6-0-cross-site-scripting-xss-vulnerability): Annual Archive through 1.6.0, stored XSS requiring contributor-level privileges and user interaction. An additional advisory distinct from CVE-2023-0178.
- [CVE-2024-33598 (Patchstack)](https://patchstack.com/database/wordpress/plugin/anual-archive/vulnerability/wordpress-annual-archive-plugin-1-6-0-cross-site-scripting-xss-vulnerability-2): predecessor Annual Archive through 1.6.0, stored XSS with privileged (administrator) access and user interaction.

These advisories refer to **Annual Archive**, not independently verified vulnerabilities of **ChronoArchive**. The public advisory summaries do not establish an exact exploit chain for this fork. No production exploitation has been attempted.

### Code observations and mitigations implemented in this branch

1. **Core WordPress link escaping:** `get_archives_link()` escapes URLs but **does not escape its text argument**. ChronoArchive now escapes link labels at each of six call sites and strips tags from filtered post titles. [WordPress source](https://developer.wordpress.org/reference/functions/get_archives_link/).
2. **Core API defense in depth:** `wp_get_archives_advanced()` now validates archive type, display format, published post type, ordering and numeric limit, and runs markup fragments through `wp_kses_post()`. Direct callers and classic-widget filters no longer bypass these checks merely because they don't use shortcodes.
3. **Dropdown/option context:** HTML markup is not permitted in user-configurable prefixes/suffixes when producing `<option>` nodes.
4. **Historical widget data:** Removed `extract( $args )`; added scalar guards to `widget()`, `update()` and `form()`; display-only text is sanitized and attributes/text are escaped. Historical `showcount` count setting remains supported.
5. **Custom URL query vars:** restricted `chrono_week` and `decade` manipulation to main archive queries; invalid decades and invalid calendar weeks get an empty result rather than a broadened query.
6. **Legacy custom CSS option:** non-string or overlong stored values are ignored; its markup is HTML-escaped before embedding in `<style>`. This addresses literal HTML termination in the inspected path, **but is not a complete CSS policy**. Admin-controlled CSS may still contain external resource requests and requires a separate design decision before stable release.
7. **Direct entry:** top-level `ABSPATH` guard and removal of closing PHP tag.

### Executed validation

- GitHub Actions syntax matrix on PHP 7.2/8.2/8.4 and JavaScript.
- PHP security-contract smoke suite on PHP 7.2/8.4, with WordPress API stubs simulating `get_archives_link()` and widgets. These tests exercise safeguards but **do not** prove real WordPress behavior.
- Previous LAB 2.1.0-beta1 shortcodes and render tests documented in [LAB-QA.md](LAB-QA.md) are baseline only, **not** validation of these new changes.

### Still open before this ticket can be closed

- Run the changes in a real WordPress staging site, including contributors' shortcodes, malformed stored widgets, custom CSS, real `wp_kses_post`, and public archive navigation behind a controlled authenticated preview.
- Run **Plugin Check** on the modified plugin; investigate all warnings relevant to security, escaped output and settings. The checker has not been executed on this branch.
- Review the exact predecessor advisory input/sink correspondence if additional technical disclosures become available; don't claim the CVEs are fully neutralized without evidence.
- Review dynamic SQL filtering by trusted extensions, CSS external resource policy and multisite/large-database behavior.
- Only then decide whether to close [issue #2](https://github.com/aminostudio/chronoarchive/issues/2).

## Initial official Plugin Check run (GitHub CI)

**Executed:** [WordPress runtime security checks 43370019219](https://github.com/aminostudio/chronoarchive/actions/runs/43370019219), actual WordPress Plugin Check installed in an ephemeral `wp-env` environment.

- WordPress integration: **14/14** checks passed at this checkpoint.
- Plugin Check ran and printed **169 errors, 14 warnings**. Its command exit code was zero: this does **not** mean zero findings.
- Dominant categories: `TextDomainMismatch` 74 (#3); `OutputNotEscaped` 40 and `UnsafePrintingFunction` 25 (older admin/widget output); `DirectDB.UnescapedDBParameter` 6 and `PreparedSQL.NotPrepared` 6 for the six archive SQL queries; `DirectDatabaseQuery.DirectQuery` 6. Also translator comments and package-content warnings.
- The scanner included the entire GitHub working tree, including development scripts, tests, dotfiles and README, which will be excluded from a release package.
- The admin settings and widget wrappers were **further changed after this run**, so the counts are a baseline only. Re-scan before reporting updated findings.

**SQL review note:** the imported baseline assembles SQL from `$sql_where = $wpdb->prepare( "WHERE post_type = %s AND post_status = 'publish'", $r['post_type'] )`, an allowlisted `$order`, numeric `$limit`, and trusted `$wpdb->posts`. The `getarchives_where` / `getarchives_join` filters originate from WordPress PHP extensions and can alter the query. This reduces the untrusted-input risk but does not prove the final SQL safe. Evaluate each query and test filters; don't suppress Plugin Check findings without evidence.

**Release remains blocked.**
