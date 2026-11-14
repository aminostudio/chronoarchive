# Issue #4 · SQL grouping with strict MariaDB mode

**Branch:** `db/issue-4-strict-grouping`, based on `release/issue-3-metadata-i18n`, which itself is stacked on the security integration work. This is a development change only, not a release.

## Confirmed defect

The historical monthly, yearly and daily queries group the `post_date` column by year/month/day components but use `ORDER BY post_date`. A raw timestamp is not uniquely defined inside a group. MariaDB or MySQL with `ONLY_FULL_GROUP_BY` can reject those statements or return ambiguous sorting.

The decade query selects `decade + 9` from a grouped derived table. To avoid relying on the engine's functional-dependency inference, the end year is now explicitly derived as `MAX(decade) + 9` within each group.

## Correction

| Mode | Before | After |
| --- | --- | --- |
| Yearly | `ORDER BY post_date` | `ORDER BY year` |
| Monthly | `ORDER BY post_date` | `ORDER BY year, month` |
| Daily | `ORDER BY post_date` | `ORDER BY year, month, dayofmonth` |
| Weekly | Already grouped and sorted on `week_start` | Unchanged |
| Decade | Grouped `decade + 9` in SELECT | `MAX(decade) + 9` |

The requested ASC/DESC sort still applies to *all* components of the date bucket. Group counts, limits, public links, shortcodes, post-type support and saved settings remain unchanged.

## Testing strategy

- The mock test now expects a grouped year sort, not the historical timestamp.
- Real WordPress integration suite temporarily enables `ONLY_FULL_GROUP_BY` **on the disposable CI database connection only**, resets the archive cache and executes yearly, monthly, daily, weekly and decade archive queries with ASC and a LIMIT.
- Each grouped query must return links and leave `$wpdb->last_error` empty.
- A yearly DESC / LIMIT 2 check verifies ordering.
- The test restores the original SQL session mode after the check. It aborts outside the dedicated `chronoarchive-ci` WordPress site.
- The existing WordPress 6.5.5 + PHP 7.2/8.2 and latest WP + PHP 8.2/8.4 CI matrix must pass before integration.

## Still unresolved

The six direct `$wpdb->get_results()` calls are inherited from Annual Archive. Their **dynamic `getarchives_where` and `getarchives_join` filters are trusted WordPress extension hooks**, not free-form public inputs. The `post_type` predicate is prepared, order is strictly allowlisted to ASC/DESC and limit is integer-normalized. However Plugin Check currently flags direct query and `$query` preparation patterns. This branch does **not** claim to resolve those separate WordPress.org policy/quality observations.

Before release:
- Review the SQL filter trust contract and default direct DB calls against official Plugin Check results.
- Assess JOIN multiplicity, query plan/index use, caching with a large corpus and private/CPT post filtering.
- Verify `ONLY_FULL_GROUP_BY` under a genuine MariaDB service in CI, including nightly/current versions where supported.
- Review week boundaries under different `start_of_week` settings.

**No changes to Amino #LAB, the public landing branch, or any production website.**

## Empty archive caching (second checkpoint)

The five legacy list modes (monthly, yearly, daily, decade, alphabetical/post-by-post) originally treated `[]` from `wp_cache_get()` as a cache miss. Since an empty array can represent a valid result for a public CPT without published posts, every request repeated the same SQL unnecessarily.

These modes now distinguish `false` (actual miss) from empty result sets, matching the existing weekly cache implementation. No cache namespace or invalidation policy changes were made.

The disposable real WordPress integration suite registers an empty public CPT and asserts that its first archive request performs SQL while a repeated request uses the cached empty result. This verifies behavior rather than only scanning source text.

Remaining perf reviews: large post corpora, filtered JOIN cardinality and persistent-object-cache behavior in deployed setups.


## JOIN cardinality regression (follow-on review)

The WordPress `getarchives_join` filter accepts trusted SQL JOIN fragments.
When such a JOIN yields multiple matching metadata rows for a single post,
plain `COUNT(ID)` or `COUNT(*)` counts rows rather than distinct published posts.
Alpha/post-by-post archives can repeat the same permalink.

The development follow-on:
- Counts distinct post IDs in all five grouped archive modes.
- Uses `SELECT DISTINCT` for alphabetical and post-by-post link lists.
- Adds disposable WordPress test data with **two matching postmeta rows**
  and validates correct counts and unique links through the real filters.

This improves JOIN correctness. Large-corpus query plan benchmarking and
WordPress.org Plugin Check direct-SQL observations remain separate work.


## Official WordPress Plugin Check 2.2.0 result (2026-11-14)

[GitHub run 43552800337](https://github.com/aminostudio/chronoarchive/actions/runs/43552800337) identified **12 errors and 8 warnings**:

- Six `PluginCheck.Security.DirectDB.UnescapedDBParameter` errors.
- Six `WordPress.DB.PreparedSQL.NotPrepared` errors.
- Six `WordPress.DB.DirectDatabaseQuery.DirectQuery` warnings.
- One discouraged `load_plugin_textdomain()` warning.
- One unexpected root-level `NOTICE.md` warning.

The release-readiness workflow correctly blocks `2.1.0-beta1` as a prerelease. Only the legacy translation loader and notice-location warnings are addressed by the present cleanup.

**All twelve SQL errors and six SQL warnings remain explicitly OPEN.** No `phpcs:ignore`, blanket ignore codes, or artificial placeholder preparation has been added. The final SQL includes trusted WordPress hooks `getarchives_where` and `getarchives_join`. Replacing their SQL fragments with SQL value placeholders would break compatibility; a dedicated review of the trust boundary is required.

WordPress Plugin Check can also report false positives on prepared queries stored in intermediate variables, but that does not prove these dynamic queries secure. Consult the [WordPress prepare() API](https://developer.wordpress.org/reference/classes/wpdb/prepare/).

Next milestone: isolated large-corpus SQL query plans, hook-compatibility and cache invalidation tests, and source-level security rationale for each query.


## Automated large-corpus query-plan benchmark (CI only)

The stacked benchmark adds `tests/wp-performance.php`, guarded by **all three
conditions**: `GITHUB_ACTIONS=true`, the exact test-site title `chronoarchive-ci`,
and the disposable `/tmp/chrono-wp/` WordPress path. It **cannot run on the
LAB or a client WordPress installation**.

Only in the latest WordPress / PHP 8.4 integration job, CI inserts 3,000
synthetic published posts using batched, parameterized SQL. It then:

- Executes all seven archive modes after flushing cache, with a 24-row limit.
- Measures each cold-query elapsed time for **diagnostics only**, not an
  arbitrary timing pass/fail threshold.
- Validates a successful archive output and an identical warm output.
- Requires **no repeated aggregate SELECT** on the warm cache call.
- Captures the actual archive query from the WordPress SAVEQUERIES log.
- Runs MariaDB `EXPLAIN` on each SQL statement and prints selected index,
  estimated rows, join access type, and additional sorting/temporary-table
  operations.
- Uses an entirely disposable MariaDB database. No production site changes.

**Interpretation:** this is a reproducible smoke benchmark, not production
capacity planning. Query plans can differ with data distributions, MariaDB
versions and indexes; a `Using filesort` by itself is not a correctness failure.
Detailed CI timings and actual execution plans must be read from the workflow
logs after the job runs.
