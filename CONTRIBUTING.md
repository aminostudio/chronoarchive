# Contributing

Thank you for helping improve ChronoArchive. This fork is in **beta stabilization**.

- Use a feature branch based on the latest development branch. Submit changes through a pull request.
- Open public issues for ordinary bugs, compatibility reports and enhancements. **Report security vulnerabilities privately** as described in [SECURITY.md](SECURITY.md).
- Describe the shortcode, date ranges, permalink configuration, post types, WordPress/PHP versions and expected behavior.
- Preserve both modern `[chronoarchive]` behavior and the `[archives]` / `[arcpromat]` aliases.
- Do not delete upstream licenses, author attribution or the `js/widget_form.js` copyright notice.
- Run `bash scripts/check-static.sh` and WordPress integration tests on an isolated site. Include test outcomes in your PR.
- For changes to SQL, query vars, widget settings, URL handling, XSS-sensitive output or accessibility, describe the security/compatibility impact and rollback plan.

See [release gates](docs/RELEASE-GATES.md). No proposed change is considered released until reviewed and tested.
