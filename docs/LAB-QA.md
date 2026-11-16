# Amino #LAB: ChronoArchive beta QA

**Site:** https://labo.aminostudio.fr/ (WordPress 7.1.4, PHP 8.4.27)  
**Installed plugin:** ChronoArchive 2.1.0-beta1  
**Published demonstration page:** https://labo.aminostudio.fr/lab-chronoarchive-qa/ (WordPress page ID 70)  
**Private security-test draft:** WordPress page ID 75

## Test data

A dedicated `LAB · Archives QA` category contains 30 dated fictional posts, one per year from 1997 through 2026. Eight previously published ChronoArchive fixtures and the original `Hello world!` post remain in the lab, for 39 published posts. The test set covers transitions at 1999/2000, 2009/2010 and 2019/2020.

## WordPress-rendered checks

| Mode | Observed output |
| --- | --- |
| Decade | Four links: 1990–1999 (3), 2000–2009 (10), 2010–2019 (12), 2020–2029 (14); match SQL grouping |
| Yearly | 30 distinct years, ascending and matching DB counts |
| Monthly | 10 most recent groups (limit respected) |
| Daily | 10 most recent groups |
| Weekly | 10 most recent groups |
| Post-by-post | 12 most recent posts |
| Alphabetical | 12 posts |
| Dropdown | 30 year options plus prompt; no inline onchange |
| Invalid type | Safe fallback to yearly |
| [chronoarchive] | Decade shortcode alias renders four groups |
| Week boundary | 31 chronological week groups, including 1996-12-30 to 1997-01-05 |

These were checked against WordPress REST API rendered shortcode output. Counts and generated markup are demonstrated, but navigation to archive destinations is **not certified**.

## Input robustness smoke tests

A separate non-public draft checked an invalid `tag` (fallback to `ul`), unsafe event attributes in `before` (stripped), malformed `select_text` HTML (not executable), unknown post type (no links) and invalid sort option (safe fallback). These are **targeted smoke tests**, not complete XSS or security assurance.

## Remaining gaps

- Under Construction masks the frontend from anonymous browser requests. Actual archive-link navigation, status codes, 404 behavior, pagination and keyboard tests are not fully exercised.
- Real permalink combinations, CPT archive/no-archive and cross-year weekly queries need tests.
- WordPress Plugin Check has been installed but its full scan has **not** been run.
- Test PHP 7.2/8.2/8.4 and claimed WordPress minimum before release.

## Planned beta2 follow-up (not yet installed)

Integration candidate: `2.1.0-beta2`; this page still documents actual **2.1.0-beta1** LAB observations. Do not alter the historical test outcomes until the candidate has been installed and retested. See [BETA2-LAB-PLAN.md](BETA2-LAB-PLAN.md) for authorized installation, QA and rollback.
