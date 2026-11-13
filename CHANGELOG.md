# Changelog

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

ChronoArchive is a fork of Annual Archive. Historical upstream changelog and attribution appear in `readme.txt` and `NOTICE.md`.

## Unreleased (security review branch only)

- Validate public archive renderer arguments independently of shortcode sanitization.
- Escape archive labels, filtered titles, and legacy widget values.
- Harden weekly/decade archive query variables against invalid input.
- Reject malformed widget settings and stored CSS types; retain historical widget count compatibility.
- Introduce PHP security-contract smoke tests and a PHP version matrix.

**Not shipped to LAB or production. No stable release claim.**
