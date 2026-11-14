# ChronoArchive SQL review request

**Status:** Human review required before any stable release or WordPress.org submission.  
**Candidate:** 2.1.0-beta2, integration branch (LAB still runs 2.1.0-beta1).  
**Tracking:** [issue #4](https://github.com/aminostudio/chronoarchive/issues/4) and [core-parity PR #20](https://github.com/aminostudio/chronoarchive/pull/20).

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
| Limit | Non-negative integer via `absint`, normalized `LIMIT n`; unparseable nonempty value fails closed at zero | An installed PHP SQL callback may itself generate an unsafe limit |
| Sort directions | Exact `ASC` / `DESC` allowlists | Filter-provided SQL remains outside this allowlist |
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
