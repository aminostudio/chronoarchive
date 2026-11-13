# Security audit: Issue #2 (in progress)

**Target:** ChronoArchive `2.1.0-beta1` imported from Amino #LAB.  
**Branch:** `security/issue-2-hardening`  
**Status:** **PARTIAL REVIEW**, not a release/security certification.

## Source and upstream CVE facts

The predecessor plugin **Annual Archive** has documented Stored XSS vulnerabilities:

| Advisory | Affected upstream | Reported trust model |
| --- | --- | --- |
| CVE-2023-0178 | Annual Archive versions earlier than 1.6.0 | Shortcode attributes, contributor+ |
| CVE-2023-49847 | Annual Archive through 1.6.0 | Stored XSS (further vector analysis required) |
| CVE-2024-33598 | Annual Archive through 1.6.0 | Stored XSS, high-privileged operation reported |

Primary references:
- https://nvd.nist.gov/vuln/detail/CVE-2023-0178
- https://www.cve.org/CVERecord?id=CVE-2023-49847
- https://www.cve.org/CVERecord?id=CVE-2024-33598
- https://patchstack.com/database/vulnerability/anual-archive/wordpress-annual-archive-plugin-1-6-0-cross-site-scripting-xss-vulnerability-2

These are **upstream** vulnerability records. They do **not** independently prove that ChronoArchive is vulnerable or that the fork fully fixes those vectors. The exact upstream affected revisions and CVE proof paths remain to be correlated.

## 2.1 beta baseline reviewed

- Shortcode input: `type`, `format`, `tag`, ordering and post type are allowlisted in `shortcode()`; `before` and `after` use `wp_kses_post()`; `select_text` is a sanitized label. This addresses broad classes of untrusted shortcode input but is not exhaustive.
- Classic widget: sanitized on `update()` but direct rendering still consumes legacy stored instances and third-party filters. Previously used `extract( $args )`. This is now replaced by explicit trusted theme wrapper assignments.
- Direct `wp_get_archives_advanced()` call: previously skipped shortcode validation; the change applies a second allowlist and markup sanitization before building SQL and output.
- Permalinks/query variables: `chrono_week` was able to alter the main query even on non-archive pages. It is now scoped to archive queries; malformed `decade` selectors fail closed.
- Post-type selection uses WordPress registered public/viewable post type checks; dynamic SQL columns/ordering are built from a fixed type selection, but direct query filters `getarchives_where`/`getarchives_join` are trusted extension hooks.
- `custom_css` is a privileged configuration field. It is sanitized when saved and escaped in a `<style>` text context; its **CSS semantics, capabilities on multisite and legacy option values still require review**.
- `wp_cache_get_last_changed('posts')` participates in cache keys; large-data and `ONLY_FULL_GROUP_BY` behavior is tracked in issue #4.
- Dropdown JS accepts only same-origin HTTP(S) navigation. No inline `onchange` handler is emitted by the beta shortcode.

## Completed verification so far

- On the actual WordPress LAB, a **non-public** test draft (ID 75) produced escaped output for an invalid container tag, an event-handler attribute in `before`, an invalid sort field, HTML in select text and an unknown post type.
- GitHub Actions syntax validation passed for PHP 7.2, 8.2, 8.4 and JavaScript on the imported baseline.
- New isolated mock regression harness in `tests/security-smoke.php` exercises direct API invalid inputs and legacy widget rendering. It does **not** replace live WordPress integration tests.
- WordPress.org release metadata job remains red (tracked separately by #3).

## Open checks before issue closure

- [ ] Run regression tests in a **real WordPress bootstrapped test environment**, with WordPress role differences (contributor, editor, admin), stored legacy widget options and malicious shortcode attributes.
- [ ] Complete upstream patch-by-patch CVE comparison, especially `CVE-2023-49847` and `CVE-2024-33598`.
- [ ] Inspect custom CSS handling and permissions with old unsanitized stored options.
- [ ] Check backend widgets and admin page HTML with real plugin-check/PHPCS.
- [ ] Audit custom post types and date archive query behavior; examine output filter trust boundaries.
- [ ] Consider secure configuration handling for title filters and custom widget wrappers.
- [ ] Add checks for multisite and network-admin capabilities where relevant.
- [ ] Confirm no regression in cross-year week archives and decade selectors through actual browser navigation.

**Conclusion:** meaningful security hardening has been made, but **do not close #2 or tag a stable release** yet.
