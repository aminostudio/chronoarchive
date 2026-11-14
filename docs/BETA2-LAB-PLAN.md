# ChronoArchive 2.1.0-beta2: controlled LAB acceptance plan

**Not installed yet.** Amino #LAB currently runs **2.1.0-beta1**.
The candidate package is a development-only artifact on the integration branch,
not a production release or WordPress.org-approved plugin.

## Preflight

1. Confirm `labo.aminostudio.fr` is the authorized LAB target and
   `Under Construction` still masks the site for anonymous visitors.
2. Make and verify a restorable copy of the existing `ChronoArchive` plugin
   directory (`wp-content/plugins/ChronoArchive`, exact capitalization), the database and the current plugin settings.
   The LAB's plugin has four files and is active as `ChronoArchive/chronoarchive.php`.
   **Do not install a lowercase `chronoarchive/` package**: on Linux this creates a second plugin directory.
3. In GitHub Actions, open the integration PR's successful
   **LAB beta2 reproducible ZIP** workflow and download the
   `chronoarchive-2.1.0-beta2-LAB-NOT-PRODUCTION` artifact.
4. Check the ZIP's `SHA256` from its GitHub Actions build log and confirm
   it expands to one top-level `ChronoArchive/` directory, matching the installed LAB basename `ChronoArchive/chronoarchive.php`. Never use a
   source-code archive generated automatically by GitHub as the plugin ZIP.
5. Confirm the code checks pass and the official WordPress Plugin Check
   remains correctly marked **blocked** on its two SQL errors and warning.

## Safe administrator-only installation

1. Use the normal WordPress plugin update from ZIP on the LAB only, keeping
   a rollback copy of `2.1.0-beta1`.
2. Verify the plugin basename remains `ChronoArchive/chronoarchive.php` and that **only** ChronoArchive changes version, and that custom
   settings remain intact. Do not touch Amino Privacy, Amino ORBIT or other
   LAB plugins.
3. Keep Under Construction enabled. Do not open public access merely to
   simplify testing.
4. Recheck the PHP error log and WordPress Site Health after activation.

## LAB regression checklist

| Area | Verification |
| --- | --- |
| WordPress admin | Plugin shows 2.1.0-beta2; no activation/PHP fatal error |
| Archive QA page | Page 70, `/lab-chronoarchive-qa/`: check year, month, day, week, decade, alpha, postbypost and dropdown output |
| Private security cases | Page 75 remains non-public; no unauthorized output |
| Historic weeks | Cross-year 1996/1997, 2019/2020 and 2025/2026 navigation works when authenticated |
| Decades | Count and link boundaries; invalid decade results in a 404 |
| Permalinks | No unintended redirects or lost original post URLs |
| Custom post types | Public CPT links and CPT without native archive base |
| UX/accessibility | Shortcode/widget HTML, keyboard operability and visible label on dropdown |
| Cache | Second archive view reuses cache; publishing/removing a controlled fixture updates counts |
| Settings | Existing admin option values and multisite CSS permissions remain unchanged |
| Browser | Authenticated Firefox console and Network show no unexpected errors |
| Protection | Anonymous visitor still sees Under Construction holding page |

If a test fails: restore the previously backed-up plugin directory or ZIP,
revert database/settings only if changed, keep the holding page enabled and
document the failure with an issue and minimum reproduction.

## Acceptance boundary

Passing the LAB checks permits only further beta testing, **not** a stable
GitHub tag, a production recommendation or WordPress.org submission. The
remaining SQL trust-boundary review, upstream CVEs, and final ZIP Plugin Check
are separate release blockers.
