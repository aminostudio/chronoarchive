=== ChronoArchive ===
Contributors: aminostudio
Tags: archives, shortcode, widget, date archives, custom post types
Requires at least: 6.5
Tested up to: 7.1
Stable tag: 2.1.0-beta3
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Browse WordPress posts by year, month, week, day or decade using shortcodes or a classic widget.

== Description ==

**Development beta: not yet recommended for production sites.**

ChronoArchive creates chronological archive lists for WordPress posts and supported public custom post types. Developed and maintained by Amino Studio.

Supported archive modes:

* Yearly, monthly, weekly and daily archives.
* Decade archives with explicit year ranges.
* Alphabetical and post-by-post archive lists.
* A dropdown selector or regular archive links.
* A classic WordPress widget and the Shortcode block.

The recommended shortcode is `[chronoarchive]`. The historical `[archives]` and `[arcpromat]` aliases remain available for compatibility.

**Settings & shortcode builder:** In WordPress, open **Settings → ChronoArchive** to configure the default archive type, display (links or dropdown), sort direction, maximum entries, post counts and dropdown placeholder. These defaults apply only when the shortcode does not specify an attribute. The same screen provides a live shortcode generator, copy buttons, six examples, public post type selection, a complete attribute reference and advanced CSS settings.

The generator emits explicit attributes, so a copied shortcode remains consistent if the site-wide defaults change later. The classic widget retains its own per-widget options.

Examples:

`[chronoarchive type="yearly" show_post_count="1"]`

`[chronoarchive type="decade" order="ASC"]`

`[chronoarchive type="weekly" limit="10"]`

`[chronoarchive type="monthly" format="option" select_text="Choose a month"]`

**Security and release status:** Development security hardening and compatibility tests are ongoing. Final SQL and inherited-vulnerability reviews remain open. This beta has not been approved by WordPress.org and should be tested on an isolated WordPress site only.

Source code, release blockers and bug reports: https://github.com/aminostudio/chronoarchive

== Installation ==

ChronoArchive 2.1.0-beta3 is for **testing and review only**. Do not use it on an important live website.

1. Use an isolated WordPress test installation meeting the requirements above.
2. Install a development ZIP with a top-level `chronoarchive` directory, or copy its source files into `wp-content/plugins/chronoarchive/`.
3. Activate ChronoArchive in the WordPress Plugins screen.
4. Add a Shortcode block such as `[chronoarchive type="yearly"]` to a test page, or use the classic widget.
5. Check archive links, permalinks, counts, keyboard navigation and theme layout.
6. Review the release and security notes before upgrading an existing archive installation.

The public `main` branch currently contains the project landing page, **not a downloadable stable plugin**. Development snapshots should not be mistaken for releases.

== Frequently Asked Questions ==

= Is this plugin ready for production? =

No. Version 2.1.0-beta3 is a development build. Public stable release and WordPress.org review are pending, including the final security and SQL checks.

= Does ChronoArchive support old shortcodes? =

Yes. `[archives]` and `[arcpromat]` are retained. Prefer `[chronoarchive]` for new sites.

= Should Annual Archive stay active after migrating? =

No. Both extensions register the `[archives]` and `[arcpromat]` shortcodes, so running them together can produce unpredictable results. Back up your site, check the archived pages and widgets, then deactivate Annual Archive. ChronoArchive displays an administrator-only warning when it detects that both are active. It never disables another plugin automatically.

= What archive types can I request? =

Use `yearly`, `monthly`, `weekly`, `daily`, `decade`, `alpha` or `postbypost` in the `type` attribute.

= Can I use custom post types? =

Supported public custom post types can be selected with `post_type`. Test their permalinks and archive configuration in your theme: some custom post types do not expose a public archive base.

= How do I report a security vulnerability? =

Please report vulnerabilities privately through the maintainer's contact information at https://aminostudio.fr/ instead of posting exploit details in public issues. See SECURITY.md in the GitHub development branch.

= Who maintains ChronoArchive? =

Amino Studio develops and maintains ChronoArchive. The project repository contains its source code, technical documentation and release history.

== Changelog ==

= 2.1.0-beta3 =

* Live shortcode builder and fully redesigned responsive settings dashboard.
* Six validated global defaults, shortcode preset library and quick-copy controls.
* Explicit shortcode attributes remain authoritative, with legacy aliases and widgets preserved.
* Browser-tested Settings API persistence and restored values after reload.
* Improved asset packaging and a distinct plugin version for safe staging updates.
* Development-only: final SQL and inherited-vulnerability review are still open.

= 2.1.0-beta2 =

* Security hardening across archive inputs, shortcodes, widget settings, multisite CSS and output escaping.
* Strict SQL grouping, distinct post counts for custom JOINs, and correct cache invalidation.
* Native WP_Query for unfiltered alphabetical and post-by-post listings, retaining the legacy SQL hooks.
* Reproducible WordPress, PHP and MariaDB integration and performance tests.
* New WordPress admin dashboard with secure global shortcode defaults, live shortcode generator, quick-copy examples and scoped responsive assets.
* Development-only. SQL human review, inherited-vulnerability review and WordPress.org approval remain open.

= 2.1.0-beta1 =

* Development branch: improved week archives that span calendar years.
* Decade labels with explicit start and end year.
* Archive fallback links for custom post types without their own archive URL.
* Labeled dropdowns using progressive enhancement and JavaScript without inline event handlers.
* New `[chronoarchive]` shortcode alongside the original aliases.
* Security, permissions and WordPress compatibility fixes are being reviewed and tested before a stable release.

= 2.0.1 =

* Laboratory fixes to input validation, historical shortcode handling and decade counts.
* Preliminary legacy widget improvements and output escaping.
* This development version was not a public security-certified release.

== Upgrade Notice ==

= 2.1.0-beta3 =

New settings dashboard and shortcode configuration. Back up LAB before updating, verify saved settings, classic widgets, archive links, and the shortcode builder. Development beta only; not a production or WordPress.org release.

= 2.1.0-beta2 =

LAB-only candidate. Back up the plugin directory, database and stored settings before installing. Confirm shortcodes, widgets, navigation, dropdowns, custom post types and historical links. Never deploy this beta to production.

= 2.1.0-beta1 =

Development beta only. Back up your site and test all custom archive links, widget settings and stored CSS on an isolated staging installation. Do not upgrade a production site to this build.
