# Security review backlog

This file is a **review plan**, not a completed vulnerability audit.

## Upstream XSS history

Annual Archive, the predecessor project, has published security advisories including `CVE-2023-0178` and `CVE-2024-33598`. Before release, fetch original advisories and identify vulnerable parameters, attacker capabilities, the exact code paths and upstream fixes. Test whether inherited ChronoArchive paths are affected. Do not infer safety merely from changed version numbers.

## Inputs and sinks to review

- Shortcode `type`, `limit`, `format`, `tag`, `post_type`, `select_text`, `before`, `after` and legacy count arguments.
- Saved options `custom_css` and `use_cpt_archive`.
- Widget fields (`before`, `after`, `title`, `format`, `post_type`, ordering) and fallback HTML.
- `get_archives_link` filters, WordPress post titles and third-party content.
- SQL fragments `getarchives_where` / `getarchives_join` provided by filters (trusted extension integration only), post type lookup and limit.
- `chrono_week` / `decade` query variables, invalid dates and query broadening.
- The admin settings screen: capability checks, nonce, escaping and obsolete external links.

## Particular questions

- Does custom CSS printing use a suitable safe model and avoid unexpected content?
- Does HTML escaping occur at the correct sink, rather than double-escaping functional markup?
- Do weekly group links return exactly the expected results across 31 December / 1 January?
- Could plugins alter shortcode output via WordPress filters, and what trust boundary applies?
- Is `only_full_group_by` SQL mode compatible with every group query?
- Can any invalid post-type choice produce a database error or reveal private posts?

## Evidence to collect

Store a minimal test case, before/after output, regression test and environment for every fix. Re-run the full QA suite and Plugin Check on a release candidate. Do not publish exploit details before a fix.
