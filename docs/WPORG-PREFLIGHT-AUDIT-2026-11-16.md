# WordPress.org preflight follow-up — 16 November 2026

Base: `release/2.1.0-preflight`, commit
`32ed0793fdc3b89b19d7b0d7ff08cad60572086d` (PR #30, stacked on #28).
This follow-up contains only a newly reproduced query-policy cache defect.
It does not repeat PR #30's SQL refactor or modify the LAB.

## Current evidence, superseding historical SQL failures

The base commit's official all-category scan
[43796261103](https://github.com/aminostudio/chronoarchive/actions/runs/43796261103)
reports `Success: Checks complete. No errors found.` and the report verifier
records zero findings. The mandatory `plugin_repo` scan
[43796261236](https://github.com/aminostudio/chronoarchive/actions/runs/43796261236)
also passed. WordPress integration
[43796261089](https://github.com/aminostudio/chronoarchive/actions/runs/43796261089)
passed four environments with 384 assertions each, both Chromium permalink
runs and all seven 3,000-post benchmark modes. Static, multisite and stable
packaging workflows passed on that same SHA. The old two SQL errors and one
warning in #28 and earlier sections of #30 are no longer current defects.

## Reproduced defect: grouped cache bypasses native restrictions

The new aggregate cache checked six result-shaping hooks, but omitted
`parse_query`, `pre_get_posts`, search and late SQL clause/request hooks.
If the cache was populated before such a callback ran, it could return
unrestricted dates and counts without executing that callback. A policy
changing within the same user/post-cache generation could also reuse stale
results. This affects yearly, monthly, daily, weekly and decade archives;
individual listings do not use this aggregate cache.

Ordinary WordPress registers `_close_comments_for_old_posts` on `the_posts`,
which already disables this optimization. The regression explicitly removes
that callback (a valid site customization), then restores it. It primes the
cache, installs a priority-zero policy callback, toggles the restriction
without flushing the cache or changing the user, and checks both restriction
and reopening. Seven hook paths and all five grouped modes are covered.
This is disclosure of archive metadata excluded by a site's query policy;
it is not evidence of arbitrary private-post access on default WordPress.

The fix bypasses the aggregate cache when a native query/result-shaping
hook is installed, including early query actions and late request hooks.
WordPress retains its own query caching. No SQL execution, scanner suppression,
version promotion or new feature is introduced.

## Local verification

On a separate disposable Docker WordPress/PHP 8.4/MariaDB 11.4 instance:

- Unmodified base with new regressions: **454 passed, 35 failed**; all 35
  failing checks concern restricted warm-cache output.
- Full WordPress suite with fix: **489 passed, zero failed** (105 new assertions).
- PHP 8.4 syntax and mock security: **44 passed, zero failed**.
- Scanner-report guard, no-direct-SQL guard, synthetic stable release gate,
  symlink package guard and stable metadata validation pass.

The PR's GitHub checks provide the final four-environment matrix, Chromium,
multisite, deterministic archive and official exact-ZIP scan evidence.

## Decisions still reserved for release owners

The independent disposition of upstream CVE-2023-49847 and CVE-2024-33598
remains unsigned. Existing source-derived mitigations and tests do not prove
reproduction of confidential advisory payloads. Protected LAB final acceptance
and a restorable backup are also still unverified. These are existing release
sign-off gates, not newly demonstrated source defects or current Plugin Check
errors. No evidence is waived by this follow-up.

No merge, release/tag, LAB change or WordPress.org submission is authorized
by this audit. Final approval remains with the release owners.
