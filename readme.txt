=== ChronoArchive ===
Contributors: aminostudio
Tags: archives, shortcode, widget, date archives, custom post types
Requires at least: 6.5
Tested up to: 7.1
Stable tag: 2.1.0-beta1
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Browse WordPress posts by year, month, week, day or decade, with shortcodes and a classic widget. Independent fork of Annual Archive.

== Description ==

**Development beta: not yet recommended for production sites.**

ChronoArchive creates chronological archive lists for WordPress posts and supported public custom post types. It is an independently maintained, GPL-licensed fork of *Annual Archive* by Twinpictures and its contributors.

Supported archive modes:

* Yearly, monthly, weekly and daily archives.
* Decade archives with explicit year ranges.
* Alphabetical and post-by-post archive lists.
* A dropdown selector or regular archive links.
* A classic WordPress widget and the Shortcode block.

The recommended shortcode is `[chronoarchive]`. The historical `[archives]` and `[arcpromat]` aliases remain available for compatibility.

Examples:

`[chronoarchive type="yearly" show_post_count="1"]`

`[chronoarchive type="decade" order="ASC"]`

`[chronoarchive type="weekly" limit="10"]`

`[chronoarchive type="monthly" format="option" select_text="Choose a month"]`

**Security and release status:** The imported Annual Archive code has undergone extensive hardening in development branches. Final SQL, localization and inherited-vulnerability reviews remain open. This beta has not been approved by WordPress.org and should be tested on an isolated WordPress site only.

Source code, release blockers and bug reports: https://github.com/aminostudio/chronoarchive

== Installation ==

ChronoArchive 2.1.0-beta1 is for **testing and review only**. Do not use it on an important live website.

1. Use an isolated WordPress test installation meeting the requirements above.
2. Install a development ZIP with a top-level `chronoarchive` directory, or copy its source files into `wp-content/plugins/chronoarchive/`.
3. Activate ChronoArchive in the WordPress Plugins screen.
4. Add a Shortcode block such as `[chronoarchive type="yearly"]` to a test page, or use the classic widget.
5. Check archive links, permalinks, counts, keyboard navigation and theme layout.
6. Review the release and security notes before upgrading an existing archive installation.

The public `main` branch currently contains the project landing page, **not a downloadable stable plugin**. Development snapshots should not be mistaken for releases.

== Frequently Asked Questions ==

= Is this plugin ready for production? =

No. Version 2.1.0-beta1 is a development build. Public stable release and WordPress.org review are pending, including the final security and SQL checks.

= Does ChronoArchive support old shortcodes? =

Yes. `[archives]` and `[arcpromat]` are retained. Prefer `[chronoarchive]` for new sites.

= What archive types can I request? =

Use `yearly`, `monthly`, `weekly`, `daily`, `decade`, `alpha` or `postbypost` in the `type` attribute.

= Can I use custom post types? =

Supported public custom post types can be selected with `post_type`. Test their permalinks and archive configuration in your theme: some custom post types do not expose a public archive base.

= How do I report a security vulnerability? =

Please report vulnerabilities privately through the maintainer's contact information at https://aminostudio.fr/ instead of posting exploit details in public issues. See SECURITY.md in the GitHub development branch.

= Who originally created Annual Archive? =

ChronoArchive is a fork of Annual Archive, originally by Twinpictures. Historical upstream authorship and licensing notices are preserved. This independent fork is not endorsed by the original authors.

== Changelog ==

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

= Earlier history =

ChronoArchive derives from Annual Archive. The original changelog and attribution are retained in the development repository's historical documentation.

== Upgrade Notice ==

= 2.1.0-beta1 =

Development beta only. Back up your site and test all custom archive links, widget settings and stored CSS on an isolated staging installation. Do not upgrade a production site to this build.
