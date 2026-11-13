# Security issue #2 · Shortcode argument type-hardening

**Scope:** additional review on `security/issue-2-shortcode-validation`, branched off `security/issue-2-hardening`.  
**Status:** reviewable patch, NOT a complete audit or production release.

## Why this patch exists

WordPress's `shortcode_atts()` can invoke a named filter. ChronoArchive registers `[chronoarchive]`, `[archives]` and `[arcpromat]` aliases; all use `shortcode_atts( ..., 'archives' )` in this inherited code.

Even when normal shortcode parsing yields text, the `shortcode_atts_archives` filter can supply unexpected PHP types. Calling `sanitize_key()`, `strtoupper()`, `wp_kses_post()`, `absint()`, or `sanitize_text_field()` on arrays and objects can trigger warnings or TypeErrors. Normalizing **after** the filter is therefore necessary.

This patch:
1. Ensures the returned attribute collection is an array; falls back to defaults otherwise.
2. Uses `wp_parse_args()` to fill missing keys after the filter.
3. Validates each attribute as scalar before conversion to string and invoking WordPress sanitizers.
4. Preserves existing allowlists, text filtering and output escaping.
5. Preserves historical truthy `use_cpt_archive` values as a compatibility constraint.

There is no claim that every filtered input is attacker-controlled: third-party shortcode hooks normally run trusted code. This is **defense in depth** and compatibility hardening, not evidence of a new exploitable CVE in ChronoArchive.

## Evidence

Seven new isolated tests cover malformed arrays/objects in each attribute and a completely non-array `shortcode_atts_archives` filter return. They verify:
- No PHP warning or exception.
- Invalid `tag` falls back to a safe `<ul>`.
- No `<img>` or `<script>` escapes into rendered HTML.
- Yearly archive output remains available.
- The existing tests cover the direct API, widget wrappers, query-var scoping and malformed decades.

**Current mock suite:** 18 assertions, run on PHP 7.2 / 8.2 / 8.4 in GitHub Actions. Browser integration and role-based testing have not been run for this patch.

## Original Annual Archive advisory cross-check

- `CVE-2023-0178`: shortcode attribute sanitization/escaping, contributor-level attacker, versions before 1.6.0. [NVD](https://nvd.nist.gov/vuln/detail/CVE-2023-0178)
- `CVE-2023-49847`: authenticated contributor+ stored XSS through Annual Archive 1.6.0. [WPScan](https://wpscan.com/vulnerability/96c8b2d4-9dfd-409a-a83c-a7148fb69c58/) and [Patchstack](https://patchstack.com/database/wordpress/plugin/anual-archive/vulnerability/wordpress-annual-archive-plugin-1-6-0-cross-site-scripting-xss-vulnerability).
- `CVE-2024-33598`: stored XSS through Annual Archive 1.6.0, higher required privilege reported. [CVE program mirror](https://vulnerability.circl.lu/vuln/CVE-2024-33598).

These records establish an **upstream** risk history, not proof of exploitability in the fork. The exact affected input/output paths still need direct comparison with upstream code and real WordPress role tests.

## Remaining required release gates

- [ ] Real WordPress end-to-end tests of shortcode aliases, posts edited by contributor, preview/publish boundaries and both classic/block widgets.
- [ ] Malicious legacy saved options, custom CSS, admin settings XSS/CSRF review.
- [ ] Check all archive output formats and `get_archives_link` hooks with trusted vs untrusted data.
- [ ] Verify behavior with multiple custom post types, PHP and WP compatibility versions.
- [ ] Run WordPress Plugin Check and document all findings.
- [ ] Keep this PR as a draft until integration evidence is recorded.
