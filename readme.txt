=== ChronoArchive ===
Contributors: aminostudio, twinpictures, baden03
Donate link: https://aminostudio.fr/donate/
Tags: archive, archives, daily, weekly, monthly, yearly, decade, shortcode, widget, chronoarchive
Requires at least: 6.5
Tested up to: 7.1
Stable tag: 2.1.0-beta1
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Description
-----------
ChronoArchive is a rebranded and improved version of the original Annual Archive plugin by Twinpictures. It displays daily, weekly, monthly, yearly, and decade archives as well as post-by-post and alpha archives via a simple shortcode or a sidebar widget. The 2.1 beta improves calendar-week archives, CPT URL fallback, and accessible dropdown navigation. This is a LAB/testing build; production compatibility is not yet confirmed.

Installation
------------
1. Upload the `chronoarchive` folder to your `/wp-content/plugins/` directory via FTP.
2. Activate the plugin through the WordPress Plugins menu.
3. Configure the plugin settings via the ChronoArchive options page.
4. Add the ChronoArchive widget to your sidebar or use the provided shortcode in your posts/pages.
5. Test all archive links and layouts before using on a live site.

Frequently Asked Questions
--------------------------
= What are the shortcode options? =
A complete list of shortcode options and demos is provided in the documentation:
https://aminostudio.fr/chronoarchive/documentation/

= Where can I leave feedback, bug reports, or request features? =
You can leave your feedback on the support forum on WordPress.org or contact us directly via https://aminostudio.fr

= How can I translate this plugin into my language? =
Use the open community translation tool available at:
https://translate.wordpress.org/projects/wp-plugins/chronoarchive

= Do you have a Facebook page? =
Yes, follow Amino Studio on Facebook at:
https://www.facebook.com/aminostudio

= Does Amino Studio have a Twitter account? =
Yes, follow us on Twitter: @aminostudio
https://twitter.com/aminostudio

= Why the new name ChronoArchive? =
The new name reflects the plugin's enhanced focus on time-based archives and a modern approach, along with significant security and code improvements.

== Screenshots ==
1. ChronoArchive widget settings page.
2. Sidebar widget displaying daily archives.
3. Sidebar widget displaying weekly archives.
4. Sidebar widget displaying monthly archives.
5. Sidebar widget displaying yearly archives.
6. Sidebar widget displaying post-by-post archives.
7. Sidebar widget displaying alpha archives.

== Changelog ==

= 2.1.0-beta1 =
* Weekly archives grouped by actual week start, preserving ranges that span years.
* New chrono_week query variable for robust weekly archive URLs.
* Decade labels use explicit year range (2020–2029).
* CPT archive links fall back safely when a post type has_archive=false.
* Accessible, labelled dropdowns without inline JavaScript (vanilla JS loaded on demand).
* Registered explicit [chronoarchive] shortcode alongside [archives] and [arcpromat].
* Verified against PHP 8.4 on Amino #LAB. This is a beta, not a production release.


= 2.0.1 =
* Security: Validate shortcode tag, archive format and period against allowed values.
* Security: Sanitize stored settings and legacy widget fields; escape HTML attributes and widget title.
* Compatibility: Support historical boolean shortcode counts and pass post type into widget dropdowns.
* Fix: Return article counts for decade archives and sort decades deterministically.
* Fix: Use WordPress monthnum query parameter for custom post type monthly archives.
* Performance: Retrieve only required columns for alpha and post-by-post listings.
* Technical: Correct displayed plugin version to match the plugin header.
* Scope: This is a laboratory hotfix; full regression and accessibility tests are still required.



= 2.0 =
* Major Rebranding: Plugin renamed to ChronoArchive; file names, text domain, and classes updated.
* Security Enhancements: 
  - All shortcode attributes are now sanitized.
  - Custom CSS injected into the head is escaped using esc_html() to prevent code injection.
* Improved Code Quality: 
  - Removal of extract() in favor of explicit variable assignments.
  - All filters and actions are now organized within the plugin class.
* Enhanced Compatibility: 
  - Updated support for custom post type archive links using get_post_type_archive_link().
* General Refactoring: Codebase refactored for improved maintainability and performance.

= 1.6.0 =
* Option to use get_post_type_archive_link for custom post types.
* Fully tested with WordPress 6.1.
* Escaped shortcode attributes.

= 1.5.5 =
* Addressed issues from previous support topics.
* Tested with WordPress 5.8.1 and 5.9 alphas.
* Updated links to new documentation.
* Escaped plugin option page fields.

= 1.5.4 =
* Renamed showcount to show_post_count.
* Added missing select_text field in the widget.
* Included a notice about new features in the pro version.

= 1.5.3 =
* Changed default archive type from monthly to yearly.
* Cleaned up the plugin options page.
* Widget title can now be left blank.
* Adjusted text domain loading for better integration with translation plugins.

= 1.5.2 =
* Fixed an issue with some themes causing errors in object methods.

= 1.5.1 =
* Improved caching in wp_get_archives_advanced function.
* Moved all filters inside the main plugin class.
* Fixed an issue with alpha order not saving in the widget.
* Bound jQuery order swap to the element for correct behavior after saving the widget.

= 1.5.0 =
* Added new wp_get_archives_advanced function.
* Enabled ordering for alpha and post-by-post archives.
* Introduced archive by decade feature.
* Tested with WordPress 4.9.3.

= 1.4.10 =
* Tested with WordPress 4.8.

= 1.4.9 =
* Tested with WordPress 4.7.3.
* Updated links to documentation.

= 1.4.8 =
* Added post_type filter.
* Tested with WordPress 4.7.

= 1.4.7 =
* Added backup shortcode ‘arcpromat’ to avoid conflicts.
* Fully tested with WordPress 4.5.
* Now fully supports WordPress Language Packs.

= 1.4.6 =
* Corrected spelling errors.
* Tested with WordPress 4.4.

= 1.4.5 =
* Updated widget constructor to PHP5 standards.
* Updated language text domain for compatibility with the new WordPress language system.

= 1.4.4 =
* Fixed order attribute issue when using the dropdown select element.

= 1.4.3 =
* Updated for WordPress 4.1.

= 1.4.2 =
* Added WP4.0 Icons.

= 1.4.1 =
* Added option to change sort order.
* Introduced select_text to customize default dropdown text.

= 1.4 =
* Completely rebuilt the plugin using classes.
* Added an options page.
* Introduced information about Archive-Pro-Matic for extended features.
* Updated translation files.

= 1.3 =
* Added support for all wp_get_archives formats.
* Added before and after text inputs.

= 1.2 =
* Added translation files.
* Included English and German translations.

= 1.1.1 =
* Updated screenshots.
* Moved plugin to its new home on Plugin Oven.

= 1.1 =
* Added additional archive types and shortcode functionality.

= 1.0 =
* Initial release.

== Upgrade Notice ==
Upgrade to 2.0.1 for initial security and compatibility fixes. Make a complete backup and test on staging before deployment to production.

