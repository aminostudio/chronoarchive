=== ChronoArchive ===
Contributors: aminostudio
Tags: archives, shortcode, widget, date archives, custom post types
Requires at least: 6.5
Tested up to: 7.1
Stable tag: 2.1.0
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Browse WordPress posts by year, month, week, day or decade using shortcodes or a classic widget.

== Description ==

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

**Security:** ChronoArchive validates shortcode attributes, restricts custom CSS settings, escapes output and limits archive SQL to registered public post types. To report a possible security vulnerability, use the private maintainer contact below. Review your theme and existing archive URLs when upgrading.

Source code, release blockers and bug reports: https://github.com/aminostudio/chronoarchive

== Installation ==

ChronoArchive can be installed like any other WordPress plugin. Back up your site before upgrading or replacing a previous archive plugin.

1. Install the ChronoArchive plugin ZIP using **Plugins → Add New → Upload Plugin**.
2. Activate ChronoArchive.
3. Add a Shortcode block such as `[chronoarchive type="yearly"]`, or use the classic widget.
4. Open **Settings → ChronoArchive** to configure defaults and build a shortcode.
5. Verify archive links and permalinks on your theme before replacing a previous archive plugin.

== Frequently Asked Questions ==

= Does ChronoArchive work with recent WordPress versions? =

The plugin is tested against WordPress 6.5.5 and the most recent WordPress version in its automated matrix. Check the requirements above and keep your installation backed up.

= Does ChronoArchive support old shortcodes? =

Yes. `[archives]` and `[arcpromat]` are retained. Prefer `[chronoarchive]` for new sites.

= Should Annual Archive stay active after migrating? =

No. Both extensions register the `[archives]` and `[arcpromat]` shortcodes, so running them together can produce unpredictable results. Back up your site, check the archived pages and widgets, then deactivate Annual Archive. ChronoArchive displays an administrator-only warning when it detects that both are active. It never disables another plugin automatically.

= Do legacy getarchives_where and getarchives_join PHP filters still change ChronoArchive queries? =

No. ChronoArchive 2.1.0 no longer executes custom SQL fragments from these historic filters. For safety, a callback that changes either SQL fragment causes ChronoArchive to display no results rather than silently discard a potentially important content restriction. No-op callbacks remain compatible.

For customization, migrate to WordPress's supported `posts_where`, `posts_join` or `pre_get_posts` hooks using safe parameterization. Target only ChronoArchive queries by checking `$query->get( 'chronoarchive_origin' )` in your callback. ChronoArchive uses paginated `WP_Query` and groups public, published posts in PHP. On a site with many posts, check performance and object caching before deploying.

= What archive types can I request? =

Use `yearly`, `monthly`, `weekly`, `daily`, `decade`, `alpha` or `postbypost` in the `type` attribute.

= Can I use custom post types? =

Supported public custom post types can be selected with `post_type`. Test their permalinks and archive configuration in your theme: some custom post types do not expose a public archive base.

= How do I report a security vulnerability? =

Please report vulnerabilities privately through the maintainer's contact information at https://aminostudio.fr/ instead of posting exploit details in public issues. See SECURITY.md in the GitHub development branch.

= Who maintains ChronoArchive? =

Amino Studio develops and maintains ChronoArchive. The project repository contains its source code, technical documentation and release history.

== Changelog ==

= 2.1.0 =

* First stable-version release candidate with archive lists by year, month, week, day, decade and post title.
* Shortcode `[chronoarchive]`, compatibility aliases `[archives]` and `[arcpromat]`, and classic widget.
* Configurable defaults, administration dashboard, shortcode builder, CSS controls and public custom post types.
* Input validation, late escaping, archive link navigation and compatibility improvements.
* Moved grouped archives to native paginated WP_Query, replacing raw SQL aggregation. Custom legacy SQL-fragment filters now fail closed if they modify query fragments; migrate to scoped WordPress post query hooks.
* Verified reproducible WordPress.org ZIP packaging and real WordPress/PHP/browser test coverage.

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

= 2.1.0 =

Back up your site and verify archives, custom post types and widgets. Legacy getarchives_where/getarchives_join SQL filters must migrate to scoped posts_where/posts_join or pre_get_posts filters; altered SQL fragments now fail closed. Deactivate Annual Archive to avoid shortcode collisions.

= 2.1.0-beta3 =

New settings dashboard and shortcode configuration. Back up LAB before updating, verify saved settings, classic widgets, archive links, and the shortcode builder. Development beta only; not a production or WordPress.org release.

= 2.1.0-beta2 =

LAB-only candidate. Back up the plugin directory, database and stored settings before installing. Confirm shortcodes, widgets, navigation, dropdowns, custom post types and historical links. Never deploy this beta to production.

= 2.1.0-beta1 =

Development beta only. Back up your site and test all custom archive links, widget settings and stored CSS on an isolated staging installation. Do not upgrade a production site to this build.
