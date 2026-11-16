# ChronoArchive beta2 · Real LAB acceptance evidence

**Date:** 2026-11-14  
**Site:** `https://labo.aminostudio.fr` (authorized Amino #LAB, NOT production)  
**Status:** `2.1.0-beta2` **installed and active** by site administrator. This document records actual WordPress connector observations after installation, not CI fixtures alone.  
**Review PR:** [#21](https://github.com/aminostudio/chronoarchive/pull/21), integration branch `integration/2.1.0-beta2-lab`.

## Verified on the real connected LAB

- Connected WordPress version: **7.1.4**; PHP **8.4.27**.
- `ChronoArchive` is **active**, version **2.1.0-beta2**, and the actual plugin basename is **`ChronoArchive/chronoarchive.php`**. The plugin header and class version in the installed PHP both match `2.1.0-beta2`.
- The plugin's installed directory is `wp-content/plugins/ChronoArchive` with four runtime plugin files visible through the read-only source listing (the two license/notice files in the ZIP also were included in the successful GitHub build, but not enumerated in the restricted source view). No second lowercase plugin appears in the active plugin list.
- **Under Construction 5.83 remains active.** Its `ucp_options` status is `"1"` and the whitelisted role is `administrator`. Anonymous frontend rendering of the QA URL returns the Under Construction holding page, not the archive HTML. Neither Elementor nor Elementor Pro is installed; introducing a page-builder solely for the holding page is unnecessary.
- Published `page ID 70`, `/lab-chronoarchive-qa/`, rendered successfully through **authenticated WordPress REST** after the beta2 update. This is a server-side rendering check, not an anonymous/browser-navigation claim.
- Its rendered HTML includes populated sections for decades, years, months, days, weekly archives, chronological and alphabetical individual-post listings, annual `<select>` dropdown, invalid-type fallback and the `[chronoarchive]` alias. The dropdown includes a screen-reader label and a linked select ID.
- The rendered weekly listing includes the cross-year links `chrono_week=1999-12-27`, `chrono_week=2019-12-30`, `chrono_week=2020-12-28` and `chrono_week=2025-12-29`. A separately inspected SQL read-only query confirms the 2019/2020 fixture dates.
- **Real MariaDB count cross-check:** `39` published standard posts over `30` years, 1997–2026. Grouped decades: **1990s=3**, **2000s=10**, **2010s=12**, **2020s=14**. These totals exactly match the decade archive links rendered on page 70. Year 2019 has 3 posts and 2026 has 3, matching the visible annual counters.
- Week beginning **2019-12-30** contains **four** posts (2019-12-30, 2019-12-31, twice on 2020-01-01), matching the rendered week counter.
- Security testing **page ID 75** is still a **draft**; its source contains intentionally hostile shortcode inputs for testing, not for publication. It was not published or exposed as part of this QA.
- WordPress permalink structure is `/%year%/%monthnum%/%day%/%postname%/`.
- The plugin settings option `WP_ChronoArchive_options` is not yet persisted in this LAB; shortcode rendering uses built-in defaults. This does **not** validate form saving of new settings.
- No `wp-content/debug.log` was found via the read-only connector. Absence of this file is **not proof** that PHP generated no warnings.

## Not yet verified on the real LAB

- [ ] Use an authenticated **administrator browser session** (allowlisted by Under Construction) to visit `/lab-chronoarchive-qa/` and click the year/decade/week/post links.
- [ ] Confirm historic week URLs and malformed weeks/decades return the expected HTTP status under the actual Nginx/permalink configuration. The anonymous public view is intentionally shielded.
- [ ] Exercise dropdown navigation with keyboard and mouse, and inspect the actual frontend JavaScript/console/network behavior.
- [ ] Review rendered output for malicious cases in draft page 75 through an authorized preview, without publishing the page.
- [ ] Save and reload standard plugin settings and verify expected permissions and persistence on a disposable test site.
- [ ] Review server-side PHP/FPM/Nginx logs using the operator's server access, since `debug.log` is not available.
- [ ] Independently confirm a fresh, restorable pre-upgrade backup. The connector did not verify a new backup after 2026-11-12.
- [ ] Complete inherited-CVE mapping and manual WordPress.org SQL trust-boundary review.

## CI evidence and release boundary

- [Upgrade and rollback CI](https://github.com/aminostudio/chronoarchive/actions/runs/43563301642) passed using the exact case-sensitive ZIP and a disposable WordPress/MariaDB setup. The imported beta1 source was compared line-by-line to the installed LAB beta1 before the operator's upgrade (normalizing line endings, not a byte-exact backup).
- [Real WordPress integration](https://github.com/aminostudio/chronoarchive/actions/runs/43563301632): four green matrices, 224/224 assertions each.
- [Multisite security](https://github.com/aminostudio/chronoarchive/actions/runs/43563301646): two green matrices.
- [Distribution Plugin Check](https://github.com/aminostudio/chronoarchive/actions/runs/43561801623) last confirmed **2 SQL errors plus 1 direct SQL warning**. Findings were not ignored, and the WordPress.org stable gate intentionally blocks beta metadata.
- This successful LAB installation is **not** authorization for deploying to a client site, merging into public `main` or submitting to WordPress.org.

**Holding-page decision:** Keep the existing Under Construction plugin until browser QA finishes. Its current administrator allowlist should permit a logged-in operator to inspect frontend routes without exposing the LAB. Replacing it with Elementor would add a large unrelated dependency; a small custom maintenance page can be considered separately if visual branding is desired.
