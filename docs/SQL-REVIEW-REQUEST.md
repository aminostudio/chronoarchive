# SQL reviewer decision: native WordPress API migration (2.1.0 preflight)

## Resolution implemented on release branch

**Status: source fix implemented.** The official [Plugin Repo category run #43795360803](https://github.com/aminostudio/chronoarchive/actions/runs/43795360803) passed with all three previous SQL findings eliminated. That scan exposed only a separate overlong Upgrade Notice warning (subsequently shortened, with a 300-character metadata check added). Verify the next full scan on the exact final ZIP before release.

The 2.1.0 stable preflight in [PR #30](https://github.com/aminostudio/chronoarchive/pull/30) **removes the central `$wpdb->get_results( $query )` execution point and all locally assembled aggregate SQL statements**. All seven archive modes use native WordPress `WP_Query`. Five grouped modes retrieve published public posts in bounded batches of 250, calculate chronological buckets and distinct post counts in PHP, preserve ascending/descending order and limits, and cache results against the WordPress posts cache-generation token. Listing modes use native post queries and deduplicate IDs.

### Explicit compatibility and security boundary

Old `getarchives_where` / `getarchives_join` callbacks that **change raw SQL fragments** are now **fail closed**: ChronoArchive returns no archive rather than silently broadening a visitor's view by discarding custom SQL restrictions. An unchanged no-op callback remains compatible. **This is intentionally backward-incompatible for extensions that modify these fragments.** Site developers should use the standard `posts_where`, `posts_join`, or `pre_get_posts` hooks, scoped to `$query->get( 'chronoarchive_origin' )`. This is documented in the WordPress.org readme and upgrade notice. No fake SQL placeholders, lint ignores, or scanner exemptions have been introduced.

Verified [WordPress matrix #43795360846](https://github.com/aminostudio/chronoarchive/actions/runs/43795360846): **384 assertions × 4 = 1,536**, Chromium 2/2 on the exact installable lowercase ZIP, and a successful 3,000-post benchmark on all seven archive modes. The new PHP grouping approach can impose a greater cost than SQL aggregates on very large archives; performance should be monitored after release.

**Historical beta3 analysis retained below for audit context. It is not a description of active 2.1.0 implementation.**

---

# ChronoArchive SQL review request

**Status:** Human review required before any stable release or WordPress.org submission.  
**Candidate:** 2.1.0-beta3, installed and reported successfully updated by the maintainer on isolated LAB. Development continues in draft PR #28. No stable 2.1.0 package has been submitted.  
**Tracking:** dedicated [SQL release blocker #29](https://github.com/aminostudio/chronoarchive/issues/29), [consolidated draft PR #28](https://github.com/aminostudio/chronoarchive/pull/28), and historical [core-parity PR #20](https://github.com/aminostudio/chronoarchive/pull/20). Issue #4 tracks general WordPress integration rather than SQL adjudication.

## Reviewer decision packet | 15 November 2026

**Single release blocker in source:** `chronoarchive.php`, method `WP_Plugin_ChronoArchive::cached_archive_rows()`, one `$wpdb->get_results( $query )` sink (near line 341). This is the one execution site for five archive aggregation types and the optional historical SQL-hook listing fallback. The official Plugin Check on the **actual lowercase ZIP** reports exactly two errors and one warning:

| Code | Severity | Why it remains |
| --- | --- | --- |
| `PluginCheck.Security.DirectDB.UnescapedDBParameter` | ERROR | Static analyzer sees a SQL variable assembled in another method |
| `WordPress.DB.PreparedSQL.NotPrepared` | ERROR | Static analyzer cannot associate the sink with a published-post-type predicate prepared before the SQL hooks |
| `WordPress.DB.DirectDatabaseQuery.DirectQuery` | WARNING | Direct SQL aggregates date buckets not returned as counts by ordinary `WP_Query` |

**Public-input boundary, confirmed in real WP/MariaDB tests:** `post_type` is reduced to a viewable registered key and bound with `$wpdb->prepare( ... %s ... )`; date grouping is fixed SQL; ASC/DESC is enum-validated; `limit` is an integer; week start is converted to an integer. Malformed `getarchives_where` and `getarchives_join` return types and NUL bytes fail closed. The tests cover both legitimate hook-provided LIKE patterns and deliberate full WHERE rewrites, plus duplicate JOIN rows and query caching. The [four-way matrix #43734204307](https://github.com/aminostudio/chronoarchive/actions/runs/43734204307) passed 253 assertions per environment. This is **strong evidence about tested paths**, not a proof of safety for every callback registered by third-party PHP.

**Code-level extension boundary:** WordPress core explicitly defines `getarchives_where` and `getarchives_join` as **SQL-fragment** hooks, not value hooks. The installed PHP callback is responsible for parameterizing any visitor-provided values it introduces. Blindly passing a complete JOIN or WHERE fragment to `%s` would quote executable SQL syntax as a string, silently break working extensions, and *not* establish safety. `WP_Query` has replaced direct SQL for normal `alpha` and `postbypost` listings without archive SQL hooks; historical hooks select the legacy fallback.

**Requested WordPress.org review disposition:**
1. Is the single reviewed SQL sink acceptable with documented core-hook compatibility and narrowly scoped justifications for the two static errors, even though the default post type is correctly prepared **before** the hook?
2. If not, what is the supported mechanism for preserving `getarchives_where` and `getarchives_join` behavior while satisfying `PreparedSQL.NotPrepared` without adding meaningless dummy `$wpdb->prepare()` calls?
3. Is the *direct aggregate-query* warning acceptable for grouped year/month/day/week/decade counts, when the matching `WP_Query` high-level API does not offer SQL `GROUP BY` count results?

**Nonnegotiable release gate:** The GitHub draft CI can report its *baseline* as expected, but its green status is not a clean scanner result. Non-draft PRs and final WordPress.org packages remain **strictly blocked** until a reviewer-endorsed resolution, redesign, or a defensible and explicit exception is obtained. No sniff suppression is currently present. A new automated draft guard also rejects *additional* findings, even though it does not absolve the three existing ones.

Reference: [WordPress `wp_get_archives()`](https://developer.wordpress.org/reference/functions/wp_get_archives/), [`getarchives_where` hook](https://developer.wordpress.org/reference/hooks/getarchives_where/), [`getarchives_join` hook](https://developer.wordpress.org/reference/hooks/getarchives_join/), [`wpdb::prepare()`](https://developer.wordpress.org/reference/classes/wpdb/prepare/), [Plugin Check #1333](https://github.com/WordPress/plugin-check/issues/1333).

## Current decision boundary (16 November 2026)

The public [WordPress.org Plugin Check support discussion](https://wordpress.org/support/topic/use-placeholders-and-wpdb-prepare-found-query/) says Plugin Check cannot reliably trace a separately prepared SQL variable and recommends an inline `$wpdb->get_results( $wpdb->prepare( ... ) )` call for **static, fully parameterizable** statements. That recommendation alone does not resolve this plugin's actual SQL: the documented WordPress `getarchives_where`/`getarchives_join` hooks return **SQL syntax fragments**, not SQL values, so feeding those fragments into `%s` would alter or break legacy integrations. WordPress/plugin-check [#846](https://github.com/WordPress/plugin-check/issues/846) documents dynamic-query false positives even when a final `prepare( $query, ... )` call is added, and [#1333](https://github.com/WordPress/plugin-check/issues/1333) remains an open false-positive report.

**Reviewer request:** decide whether the existing centralized, source-reviewed sink deserves a narrowly documented exception *after* independent review, or whether an explicitly backward-incompatible redesign removing legacy SQL-filter support is required for WordPress.org. **Do not bypass the scanner by introducing fake placeholders, suppressing sniffs, or hiding calls inside wrappers.** The current two errors and one direct-query warning remain real release gates pending this decision.

**Latest executable security regression:** [WordPress CI #43785264044](https://github.com/aminostudio/chronoarchive/actions/runs/43785264044) passed **430 integration assertions on each of four PHP/WP combinations (1,720 total)**, plus Chromium in both permalink modes on the actual installable lowercase QA ZIP. The shortcode entry points now reject malformed `limit` values on all three aliases before integer coercion, not only in the direct SQL API. Exact-ZIP [Plugin Check #43785264036](https://github.com/aminostudio/chronoarchive/actions/runs/43785264036) still reports **2 existing SQL errors and 1 warning, 0 additional findings**. That advisory CI result does **not** constitute WordPress.org acceptance.

## Precise question for a WordPress plugin reviewer

ChronoArchive extends the archive modes provided by WordPress core:
yearly, monthly, daily, weekly, decade, alphabetical and post-by-post.
Five grouped modes and filtered listing fallbacks require SQL aggregation.
To preserve the documented WordPress extension API, ChronoArchive passes
the **already-prepared** published-post-type WHERE predicate to the existing
`getarchives_where` / `getarchives_join` PHP filters before assembling SQL.

The final query runs through one cache-aware `$wpdb->get_results( $query )`
site. WordPress Plugin Check 2.2.0 reports **two SQL errors and one warning**
at this site, despite all public shortcode arguments being normalized.

**What refactoring or justification would satisfy WordPress.org review without
breaking the documented filter contract?** Please distinguish static analyzer
limitations from unresolved runtime trust-boundary risks. No broad PHPCS
exclusions or ignore codes have been applied.

## Evidence: actual WordPress core contract

- [`wp_get_archives()` source](https://developer.wordpress.org/reference/functions/wp_get_archives/):
  obtains a registered, viewable post type, calls
  `$wpdb->prepare( "WHERE post_type = %s AND post_status = 'publish'", $post_type )`,
  applies `getarchives_where` and `getarchives_join`, and calls
  `$wpdb->get_results( $query )` on the composed SQL.
- [`getarchives_where`](https://developer.wordpress.org/reference/hooks/getarchives_where/)
  supplies a SQL WHERE **fragment** to installed PHP callbacks.
- [`getarchives_join`](https://developer.wordpress.org/reference/hooks/getarchives_join/)
  supplies a SQL JOIN **fragment** to installed PHP callbacks.
- WordPress/plugin-check's issue
  [#846](https://github.com/WordPress/plugin-check/issues/846) documents a
  `PreparedSQL.NotPrepared` complaint where prepared SQL is stored in
  intermediate variables. The issue was closed as not planned.
- [Issue #1333](https://github.com/WordPress/plugin-check/issues/1333)
  documents similar static-analysis false positives. These issues do NOT
  establish that this specific implementation is fully safe.

The plugin source is `chronoarchive.php`. The relevant routines are
`WP_Plugin_ChronoArchive::wp_get_archives_advanced()` and the private static
`cached_archive_rows()` helper. See also
[`docs/ISSUE-4-SQL-REVIEW.md`](ISSUE-4-SQL-REVIEW.md) for tests and history.

## Trust-boundary matrix

| Input or extension | Validation before SQL | Remaining risk |
| --- | --- | --- |
| `post_type` shortcode/widget/direct API argument | `sanitize_key`, registered `get_post_type_object`, `is_post_type_viewable`, then bound with WordPress `$wpdb->prepare(... %s)` | Other installed PHP filters can replace the predicate |
| Query type | Seven explicitly supported modes, invalid input defaults safely | Legacy PHP filters can modify the final clauses |
| Limit | Strict ASCII-decimal validation before `absint`; malformed nonempty input such as `1 UNION SELECT`, `2e2` or `-3` now fails closed as `LIMIT 0`, while zero/omitted remains unlimited | An installed PHP SQL callback may itself generate an unsafe limit |
| Sort directions | Exact `ASC` / `DESC` allowlists | Filter-provided SQL remains outside this allowlist |

**New security regression (16 November 2026):** WordPress's `absint()` accepts a numeric prefix such as `1 UNION SELECT ...` as integer `1`, so the former numeric-only conversion did not actually reject invalid text. The fork now requires an entire ASCII decimal string before integer conversion; malformed nonempty values are forced to a SQL `LIMIT 0`. This is **input-validation hardening**, not evidence that the old code was SQL-injectable. Tests exercise the seven archive modes and check that malicious text never enters grouped SQL, while `0`, `000` and omitted values remain unlimited. The official scanner's 2 SQL errors and 1 direct-query warning remain unaffected by this change.
| `getarchives_where` / `getarchives_join` | Callback return type must be string, WHERE nonempty, embedded NUL rejected; no public caller can register a PHP callback | **Trusted installed PHP code returns raw SQL**. If that code interpolates visitor input without preparing its values, ChronoArchive cannot prove the resulting SQL safe |
| SQL execution | One centralized `get_results()` on cache miss, key derived from SQL and WordPress `posts:last_changed` | Static checker cannot follow an assembled `$query` variable; direct query remains by design |

This is a *limited trust-boundary claim*: shortcode parameters alone are not
embedded as unescaped SQL fragments. We do **not** claim that arbitrary SQL
returned by third-party filters is safe. A vulnerable callback could still
inject unsafe SQL. User-editable content and serialized options must never
be permitted to register such callbacks.

## Latest reproducible scanner findings

[Plugin Check run 43560340374](https://github.com/aminostudio/chronoarchive/actions/runs/43560340374)
on commit [`c1e0554`](https://github.com/aminostudio/chronoarchive/commit/c1e0554a30f05cb8be8d23d801cbc5e9615722ae):

1. **ERROR** `PluginCheck.Security.DirectDB.UnescapedDBParameter`:
   the central `$query` argument to `$wpdb->get_results()` is dynamically assembled.
2. **ERROR** `WordPress.DB.PreparedSQL.NotPrepared`:
   analyzer cannot identify a preparation of the entire final SQL string.
3. **WARNING** `WordPress.DB.DirectDatabaseQuery.DirectQuery`:
   use of direct SQL instead of an appropriate higher-level API.

The direct SQL remains needed for grouped archives and legacy JOIN filters;
unfiltered individual listings already use native `WP_Query`.
There are **no ignore annotations** or special Plugin Check exceptions.

## Test evidence, and what it does not prove

- [Static checks](https://github.com/aminostudio/chronoarchive/actions/runs/43560301939):
  PHP 7.2/8.2/8.4, JavaScript, metadata: 5/5 CI jobs.
- [WordPress/MariaDB integration](https://github.com/aminostudio/chronoarchive/actions/runs/43560301962):
  224 passing checks on each of four WordPress/PHP combinations, including
  strict SQL mode, duplicate JOIN rows, valid/invalid filter returns,
  prepared published-only predicates and hostile public inputs.
- [Multisite](https://github.com/aminostudio/chronoarchive/actions/runs/43560301968):
  two permission matrices passing.
- [3,000-post benchmark](https://github.com/aminostudio/chronoarchive/actions/runs/43560301962):
  seven modes and warm cache reuse successful in an isolated disposable DB.

These tests establish *tested behavior*, not immunity to arbitrary malicious
installed code or complete inherited-CVE remediation. The upstream Annual Archive
advisories CVE-2023-0178 and CVE-2024-33598 still require explicit path mapping
and independent security review (see `docs/RELEASE-GATES.md`).

## Alternatives assessed

1. **Replace grouped archives with `WP_Query`:** individual listing modes now
   use it. Aggregating five archive modes by loading all posts in PHP would
   require separate scale/security analysis; not automatically superior.
2. **Simply call core `wp_get_archives()`:** it also uses the same raw
   SQL-fragment hook contract and does not implement our decade and
   cross-year-week behavior.
3. **Bind a complete JOIN/WHERE fragment as `%s`:** incorrect: a fragment is
   SQL syntax, not a SQL string value. It would change or break the legacy API.
4. **Sentinel and dynamic second prepare:** removed after testing. It added
   complexity and another `PreparedSQL.NotPrepared` warning without
   sanitizing third-party SQL fragments.
5. **Ignore Plugin Check sniffs:** deliberately not done. A reviewer should
   determine whether narrowly documented exceptions are permissible at all.

## Decisions still required

- Obtain a reviewer-supported decision on the trusted SQL-fragment hook design
  and whether the single direct-query site requires a redesign, a narrowly
  justified exception, or updated source-level documentation.
- Map upstream security advisories to actual fork code and test them safely.
- Complete real authenticated LAB navigation/browser QA and verify backup/rollback.
- Run Plugin Check on the exact eventual distribution ZIP, not only repo source.
- Never assert "Plugin Check passed" or "WordPress.org approved" while these
  findings remain open.
