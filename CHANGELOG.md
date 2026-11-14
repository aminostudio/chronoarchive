# Changelog

## 2.1.0-beta2 (integration candidate, NOT deployed or stable)

- Security and permissions: type-safe shortcode/widget inputs, multisite CSS approval and late output escaping.
- SQL: strict grouping compatibility, distinct post counts under JOINs, one audited query/cache sink and malformed-hook fail-closed behavior.
- Native WP_Query for ordinary alphabetical and post-by-post listing modes, with historical hooks retained.
- Correct zero/unlimited limits and post cache-generation invalidation.
- PHP 7.2/8.2/8.4, WordPress 6.5.5/recent, real MariaDB tests and 3,000-post benchmark.
- Reproducible LAB ZIP packaging and documented security-review blockers.
- **Not for production.** Plugin Check retains two SQL errors and one direct-query warning; historical CVE mapping and actual LAB guest navigation remain open.

## 2.1.0-beta1 (Amino #LAB baseline, unreleased)

- Calendar weeks grouped by actual week start across year boundaries using `chrono_week`.
- Decades display explicit ranges (e.g. 2020–2029).
- Custom post type archive links fall back when a registered post type has no archive base.
- Accessible dropdown with an explicit label and vanilla JavaScript instead of inline `onchange`.
- New `[chronoarchive]` shortcode, preserving `[archives]` and `[arcpromat]`.

The baseline is **not a public stable release**.

## 2.0.1 (LAB hotfix)

- Initial shortcode/widget sanitization and output escaping changes.
- Decade counting and ordering fixes.
- Improved post type and historical count argument handling.

ChronoArchive is a fork of Annual Archive. Historical upstream changelog and attribution appear in `readme.txt` and `docs/NOTICE.md`.
