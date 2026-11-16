# ChronoArchive protected LAB acceptance | 16 November 2026

**Scope:** Authenticated, **read-only** inspection of the existing WordPress test
site, performed through its connected WordPress administration API. This is not
a new plugin installation, a published frontend inspection or a completed
manual release sign-off.

## Confirmed without changing LAB

| Check | Observation | Status |
| --- | --- | --- |
| WordPress / PHP | WordPress **7.1.4**, PHP **8.4.27** | Verified |
| Active plugin | ChronoArchive **2.1.0-beta3** | Verified |
| Plugin basename | `ChronoArchive/chronoarchive.php`, no alternate lowercase instance among installed plugins | Verified |
| Theme | Twenty Twenty-Five | Verified |
| Public QA fixture | Post **#70**, `lab-chronoarchive-qa`, is published and contains archive samples using both `[archives]` and `[chronoarchive]` | Verified |
| Security QA fixture | Post **#75**, `lab-chronoarchive-security-tests`, remains a **draft** | Verified |
| Visitor protection | Under Construction plugin is active and maintenance status reports its holding page | Verified |
| Plugin settings | `WP_ChronoArchive_options` option is **not present**; plugin therefore uses defaults | Verified |

The existing QA content includes yearly/monthly/daily/weekly/decade/alpha/post-by-post
shortcodes, a dropdown selector, an invalid-type fallback example and cross-year
weekly archive navigation. The draft security page includes unsafe tag, event
attribute, dropdown placeholder and invalid post-type fixtures; **do not publish
it**.

## Release checks that remain open

- [ ] Authenticated browser view of QA page #70, including real clicks through
  weekly/decade links, visual appearance and mobile navigation, without removing
  Under Construction.
- [ ] Save real settings via the admin Settings API, reload and confirm their
  persisted state. The options row is currently absent; **do not imply that
  persistence has been tested on LAB**. The independent disposable CI WordPress
  install does test real Settings API saving.
- [ ] Verify classic widget rendering on LAB if one is configured. The automated
  WordPress matrix exercises a test widget, but that is **not** the LAB site.
- [ ] Confirm a recent **restorable** backup and a rollback path. UpdraftPlus is
  installed and active, but backup freshness was not verified.
- [ ] Complete SQL scanner / inherited-CVE reviewer decisions before release.

**No LAB data or settings were written, no plugin was reinstalled, and the
publicly accessible Under Construction boundary was not disabled.**

For independent automated test evidence see the draft
[PR #28](https://github.com/aminostudio/chronoarchive/pull/28).
