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
