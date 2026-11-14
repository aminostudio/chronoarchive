# ChronoArchive: integrated beta2 review

**One review surface:** branch `integration/2.1.0-beta2-lab`, based on the
latest validated PR #20 source, intended for comparison against
`develop/import-lab-beta1`. This keeps the public landing `main` untouched.

## Why consolidate?

The development history consists of independently reviewable security,
metadata, SQL and WordPress compatibility PRs. Their changes are **stacked**.
GitHub's comparison of the original LAB import to PR #20 found **63 commits,
zero commits behind**, and changes spanning PHP, tests, workflows, metadata and
documentation. Asking a reviewer to open every stacked draft PR is unnecessary.

The integration PR provides a single end-to-end diff and a reproducible beta
package. The previous PRs remain as an audit trail until the integration
review is complete; nothing is merged to `main` by creating this branch.

## Main checkpoints

- Security hardening: direct archive APIs, shortcodes, widget settings,
  output escaping, multisite custom-CSS capability checks.
- WordPress: old shortcode aliases, CPTs, cross-year weeks, decades,
  standard `WP_Query` for ordinary post listings.
- SQL: `ONLY_FULL_GROUP_BY` support, duplicate JOIN cardinality, trusted
  hook fail-closed behavior, one cache-aware execution site.
- CI: PHP 7.2, 8.2, 8.4, WordPress 6.5.5 and latest, multisite security,
  3,000-post disposable MariaDB plans, browser HTTP archive navigation.
- Packaging: versioned LAB-only ZIP, reproducible bytes, runtime allowlist,
  correct `chronoarchive/` root, GPL attribution retained.

## Explicitly NOT passed

- Official Plugin Check: two SQL errors and one warning as of
  [run 43560340374](https://github.com/aminostudio/chronoarchive/actions/runs/43560340374).
  A human WordPress.org reviewer needs to assess the legacy SQL hooks.
- The upstream Annual Archive CVE paths and a complete security audit.
- Real LAB beta2 installation and end-user interaction tests.
- The WordPress.org stable-release metadata gate: beta versions should fail
  this gate, by design.

## Review and eventual disposition

1. Validate this branch's new CI and package artifact.
2. Record WordPress.org/Plugin Check review and any required code changes.
3. Test the beta2 ZIP on the protected LAB with a full rollback plan.
4. Only after integration acceptance, decide how to merge feature work into
   a long-lived development branch and close superseded stacked PRs.
5. Do **not** merge an unreviewed beta into public `main` or publish a stable
   GitHub/WordPress.org release.

References: [SQL dossier](SQL-REVIEW-REQUEST.md),
[LAB acceptance](BETA2-LAB-PLAN.md), [release gates](RELEASE-GATES.md),
[history](ISSUE-4-SQL-REVIEW.md).
