# GitHub Actions policy and notification hygiene

**Status:** Pre-release development. The first stable version of ChronoArchive has not been approved, tagged or submitted to WordPress.org.

## What executes during development

| Workflow | Draft PR | Ready-for-review PR | Tag `v*` / manual review |
| --- | --- | --- | --- |
| Static checks (PHP lint, PHP security mocks, JS syntax) | **Required**, failures block | Required | On main; manual checks can be run locally |
| Real WordPress + HTTP archive matrix | **Required**, failures block | Required | On main |
| Genuine multisite capability matrix | **Required**, failures block | Required | Manual workflow available |
| Official WordPress Plugin Check | **Skipped while draft** | **Blocking** | **Blocking** on tag/manual/main |
| WordPress.org release metadata | **Skipped while draft** | **Blocking** | **Blocking** on tag/manual |

Plugin Check is **not silenced or configured with `continue-on-error`**. It is intentionally deferred during incomplete draft iterations to prevent repeated expected failures from generating workflow notifications on every commit. Before a PR is marked ready for review, the full audit must run and pass. A skipped gate is *not* a successful audit.

The WordPress.org metadata gate was moved from the routine static-check workflow into `.github/workflows/release-readiness.yml`. CI job names now distinguish code regression failures from release policy blockers.

The WordPress integration and multisite workflows use `concurrency.cancel-in-progress` so a new commit supersedes the same PR's obsolete in-flight jobs. Completed historical runs remain visible in GitHub Actions for audit purposes.

## What is still blocked

The inherited baseline has official Plugin Check findings covering localization, distribution metadata and SQL construction. They remain open under [#3](https://github.com/aminostudio/chronoarchive/issues/3) and [#4](https://github.com/aminostudio/chronoarchive/issues/4). The security issue [#2](https://github.com/aminostudio/chronoarchive/issues/2) remains open until CVE path mapping, reviews and appropriate regressions are completed.

Never interpret a green PHP matrix as proof of XSS security, a passed Plugin Check, or WordPress.org approval.

## Manual run & notification preferences

When a release candidate is ready, use **Actions → WordPress Plugin Check → Run workflow** and **Actions → WordPress.org release readiness → Run workflow**. When a PR leaves draft mode, both release gates run automatically; subsequent ready-PR updates rerun them.

To reduce notifications without losing CI evidence, GitHub provides **Settings → Notifications → System → Actions → Only notify for failed workflows** (delivery method may vary). Completed workflow runs remain under the repository's Actions tab even when a user marks a notification as Done.

Reference: https://docs.github.com/en/subscriptions-and-notifications/how-tos/managing-github-actions-notifications
