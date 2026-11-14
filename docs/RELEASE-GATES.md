# ChronoArchive release gates

**LAB installed baseline:** `2.1.0-beta1`. **Integration candidate:** `2.1.0-beta2` (not deployed). **Stable release status: BLOCKED.** No stable tag, WordPress.org submission or production recommendation.

## P0 blockers

- [x] Import the four LAB source files on a dedicated branch and preserve the original beta baseline.
- [ ] Verify the source against a byte-exact source export/checksum. WPVibe read-only source retrieval masks recognized secret patterns; the retrieved four files had no redaction tokens, but byte-level equality has not been independently established.
- [ ] Investigate the historical Annual Archive XSS advisories (`CVE-2023-0178`, `CVE-2024-33598`) and test the fork's actual affected paths.
- [ ] Audit all shortcode and widget arguments, HTML output, SQL, custom CSS and archive URLs. Patch defects with regression tests.
- [ ] Correct plugin headers (`Requires at least`, `Requires PHP`, `License URI`, `Text Domain`) and replace the old WordPress.org `readme.txt` structure.
- [ ] Replace obsolete Annual Archive support/reviews/promotional URLs in the administration page; preserve upstream attribution.
- [ ] Execute WordPress Plugin Check (general + plugin repository checks) on the final ZIP and triage findings.
- [ ] Verify actual frontend archive navigation with Under Construction bypass **only for authorized testing**; preserve protection for normal visitors.
- [ ] Test PHP 7.2, 8.2 and 8.4 and the oldest claimed WordPress version with shortcodes, widgets, blocks and CPTs.
- [ ] Review compatibility and performance on a larger data set, SQL grouping / `ONLY_FULL_GROUP_BY` and cache invalidation.
- [ ] Validate build root, installable ZIP, license attribution, translations, readme and rollback procedure.
- [ ] Review passing CI results; create final signed-off tag and release only after all critical checks are green.

## P1 improvements

- [ ] Replace legacy `extract( $args )` in widget rendering.
- [ ] Normalize PHP formatting and use the lowercase `chronoarchive` text domain throughout.
- [ ] Check dropdown behavior and duplicate IDs when multiple widgets and shortcodes are rendered.
- [ ] Add multisite, custom post type, and block-theme browser tests.
- [ ] Document minimum PHP/WordPress versions based on actual testing.
- [ ] Configure GitHub private vulnerability reporting and branch protection.
- [ ] Maintain a public changelog for each audited patch.

## Publishing sequence

1. Baseline import (not a production release).
2. Security and quality fixes in isolated PRs.
3. Release candidate package and complete test matrix.
4. GitHub stable release/tag if approved by maintainers.
5. WordPress.org new plugin submission, human review, and SVN publishing **only after approval**.

Always identify whether a check was run or merely planned.

## LAB beta2 gate (separate from stable publication)

- [ ] Download the reproducible beta2 development ZIP from the successful integration GitHub Actions run.
- [ ] Confirm the ZIP runtime/attribution allowlist, **case-sensitive `ChronoArchive/` top-level folder matching the LAB plugin basename**, and matching version metadata.
- [ ] Back up LAB plugin files and database; retain a working 2.1.0-beta1 rollback path.
- [ ] Perform authorized administrator-only QA with Under Construction still active for visitors.
- [ ] Confirm page 70 archives, page 75 private cases, historical week/decade links, CPT links, keyboard navigation, widget settings and cache.
- [ ] Record actual LAB HTTP/network results in GitHub before promotion.

**LAB beta acceptance is not WordPress.org approval.** The two SQL checker errors, direct-query warning, human trust-boundary review and upstream CVE analysis remain stable-release blockers.
