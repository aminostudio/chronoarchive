# ChronoArchive: Chromium browser regression suite

**Target:** disposable WordPress/MariaDB GitHub Actions instance, not the protected Amino #LAB.  
**Workflow:** `.github/workflows/wordpress-integration.yml` (latest WordPress/PHP 8.4 job only).  
**Test:** `tests/browser-archive-qa.mjs`, Chromium via Playwright.

## Gap this test closes

The existing integration suite checks archive SQL, rendered shortcode HTML,
accessibility label syntax and real HTTP responses. None of those alone prove
that a browser executes `archive-dropdown.js`, that users can navigate using
an actual dropdown, or that the browser ignores an unsafe option URL.

The browser test creates a new WordPress page containing annual, decade and
weekly archive lists and three select menus through all three shortcode
aliases. It then tests real Chromium navigation on the disposable WordPress
site. Fixtures include published posts on December 31, 2019 and January 1,
2020, created by the preceding HTTP test step.

## Assertions

1. The three select menus have distinct IDs and each exposes the accessible
   `Choose archive` name through a corresponding `<label>`.
2. The plugin includes a single client-side archive-dropdown script.
3. The first dropdown is keyboard-focusable; its empty placeholder does not
   redirect the page.
4. Synthetic malicious option values pointing at an unrelated origin or the
   `javascript:` scheme cannot navigate or issue an off-site request.
5. Clicking the week link spanning 2019/2020 navigates and renders both
   published boundary fixtures.
6. Clicking the 2010 decade link includes the 2019 fixture and excludes 2020.
7. Selecting the 2019 option emits a real change event and opens the
   expected same-origin annual archive with its published article.
8. Invalid ISO week and non-boundary decade routes return HTTP 404.
9. No uncaught JavaScript exception occurs in Chromium.

The suite uses a pinned Playwright version, installs its browser runtime
on a disposable Ubuntu GitHub Actions worker and only permits loopback
`127.0.0.1` as the target origin. The script never receives credentials
for the real LAB or any production environment.

## Acceptance

- [ ] Latest WordPress/PHP 8.4 browser test passes in GitHub Actions.
- [ ] Existing WordPress integration matrix 4/4, static matrix 5/5,
      multisite 2/2 and 3,000-post benchmark remain green.
- [ ] Verify that any browser failure reveals either plugin behavior,
      fixture assumptions or the browser harness itself. Do not bypass
      failures merely to mark the workflow green.
- [ ] The real protected LAB still requires separate authenticated
      Firefox/Chromium navigation and Nginx/HTTP verification. A GitHub
      Actions browser pass does **not** complete that operator check.
- [ ] Stable publication is blocked by the outstanding SQL trust-boundary
      findings and the upstream CVE source-path review.

See [live beta2 QA record](LAB-BETA2-LIVE-QA.md) for which tests have
actually been performed against Amino #LAB.


## CI platform fix

The first workflow execution reached browser installation but
`playwright@1.56.1` rejected GitHub's new `ubuntu-latest` runner based
on Ubuntu 26.04 (unsupported Chromium host platform). This was an
**environment setup failure before executing browser assertions**, not a
plugin regression. To keep the browser installation reproducible and
without a moving compatibility target, this WordPress matrix is pinned
to `ubuntu-24.04`. Recheck the full matrix and browser test after pinning.


## First real browser run: navigation timing

The first browser execution on Ubuntu 24.04 passed dropdown accessiblity,
asset presence, focus and blocked external destinations. The cross-year
week click reached a matching URL, but its immediate post-navigation
`body.innerText()` assertion lacked the January fixture. The prior
`curl` HTTP integration test for this same week URL **did** return both
2019 and 2020 titles. We are not treating that as a passing browser test:
the test now explicitly waits for the page's `load` state and records the
actual destination URL plus response body excerpt if an archive fixture
still is absent. This distinguishes browser-navigation timing from a
possible real URL or rendering defect.
