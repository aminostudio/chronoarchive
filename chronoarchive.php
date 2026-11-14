<?php
/*
Plugin Name: ChronoArchive
Plugin URI: https://github.com/aminostudio/chronoarchive
Description: Create daily, weekly, monthly, yearly, decade and alphabetical archives using a shortcode or classic widget.
Version: 2.1.0-beta2
Requires at least: 6.5
Requires PHP: 7.2
Author: Amino Studio
Author URI: https://aminostudio.fr/
Text Domain: chronoarchive
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Based on: Annual Archive by Twinpictures (https://www.twinpictures.de/)
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Plugin_ChronoArchive
 *
 * This plugin is a fork of Annual Archive by Twinpictures.
 * It displays various archive lists via shortcode or widget.
 */
class WP_Plugin_ChronoArchive {

	/**
	 * Plugin variables.
	 *
	 * @var string
	 */
	var $plugin_name = 'ChronoArchive';
	var $version = '2.1.0-beta2';
	// Historical Settings API group, not the translation text domain.
	// Preserve the original value for existing settings and fallback options.
	var $domain = 'ChronoArchive';

	/**
	 * Options page information.
	 *
	 * @var string
	 */
	var $plugin_options_page_title = 'ChronoArchive Options';
	var $plugin_options_menu_title = 'ChronoArchive';
	var $plugin_options_slug = 'ChronoArchive-options';

	/**
	 * Option name in the database.
	 *
	 * @var string
	 */
	var $options_name = 'WP_ChronoArchive_options';

	/**
	 * Plugin options.
	 *
	 * @var array
	 */
	var $options = array(
		'custom_css' => '',
		// Explicitly approved by an edit_css-capable user. Legacy multisite
		// values are not trusted implicitly when upgrading this plugin.
		'custom_css_approved' => '',
		'use_cpt_archive' => '',
	);

	/**
	 * Constructor.
	 */
	function __construct() {
		// Set option values.
		$this->_set_options();

		// Add actions.
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'plugin_action_links_' . plugin_basename(__FILE__), array( $this, 'plugin_actions' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'archive_admin_js_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'plugin_enqueue_styles' ) );

		// Register shortcodes.
		add_shortcode('archives', array($this, 'shortcode'));
		add_shortcode('arcpromat', array($this, 'shortcode'));
		add_shortcode('chronoarchive', array($this, 'shortcode'));

		// Add filters.
		add_filter( 'widget_text', 'do_shortcode' );
		add_filter( 'query_vars', array($this, 'ChronoArchive_query_vars') );
		add_filter( 'pre_get_posts', array($this, 'ChronoArchive_decade_filter') );
		add_filter( 'get_the_archive_title', array($this, 'ChronoArchive_decade_title') );
	}

	/**
	 * Add decade query variable.
	 *
	 * @param array $vars Existing query vars.
	 * @return array Modified query vars.
	 */
	function ChronoArchive_query_vars($vars) {
		$vars[] = 'decade';
		$vars[] = 'chrono_week';
		return $vars;
	}

	/**
	 * Check for decade query var and modify the main query.
	 *
	 * @param WP_Query $query The main query.
	 */
	function ChronoArchive_decade_filter( $query ) {
		// Never alter admin queries or secondary loops.
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		// Group by the actual beginning of a calendar week. Unlike ?w=0 and
		// ?m=YYYY&w=NN, this correctly handles weeks crossing a year boundary.
		$week = $query->get( 'chrono_week' );
		if ( null !== $week && '' !== $week ) {
			// A week selector must never rewrite the homepage, search or a single post.
			if ( ! $query->is_archive() ) {
				return;
			}
			$week = is_string( $week ) ? $week : '';
			$start = preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $week )
				? DateTimeImmutable::createFromFormat( '!Y-m-d', $week, wp_timezone() ) : false;
			if ( ! $start || $start->format( 'Y-m-d' ) !== $week ||
				(int) $start->format( 'w' ) !== (int) get_option( 'start_of_week', 1 ) ) {
				// Invalid archive dates must not silently expose the complete post list.
				$query->set( 'post__in', array( 0 ) );
				return;
			}
			$query->set( 'year', '' ); // Weekly ranges can span two calendar years.
			$query->set( 'date_query', array(
				array(
					'after'     => $week,
					'before'    => $start->modify( '+6 days' )->format( 'Y-m-d' ),
					'inclusive' => true,
				),
			) );
			$query->set( 'ignore_sticky_posts', true );
			return;
		}

		if ( $query->is_archive() ) {
			$decade = $query->get( 'decade' );
			$decade = is_scalar( $decade ) ? (string) $decade : '';
			if ( '' !== $decade && ( ! preg_match( '/^[1-9][0-9]{3}$/D', $decade ) || 0 !== ( (int) $decade % 10 ) ) ) {
				$query->set( 'post__in', array( 0 ) );
				return;
			}
			if ( '' !== $decade ) {
				$start = $decade . '-01-01';
				$end   = ( $decade + 9 ) . '-12-31';
				$query->set( 'year', '' );
				$query->set( 'date_query', array(
					array( 'after' => $start, 'before' => $end, 'inclusive' => true ),
				) );
			}
		}
	}

	/**
	 * Modify the archive title when using the decade filter.
	 *
	 * @param string $title The original title.
	 * @return string Modified title.
	 */
	function ChronoArchive_decade_title( $title ) {
		$week = get_query_var( 'chrono_week' );
		if ( is_string( $week ) && preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $week ) ) {
			$start = DateTimeImmutable::createFromFormat( '!Y-m-d', $week, wp_timezone() );
			if ( $start && $start->format( 'Y-m-d' ) === $week ) {
				$end = $start->modify( '+6 days' );
				return wp_date( get_option( 'date_format' ), $start->getTimestamp() ) . ' – ' . wp_date( get_option( 'date_format' ), $end->getTimestamp() );
			}
		}
		$decade = get_query_var( 'decade' );
		$decade = is_scalar( $decade ) ? (string) $decade : '';
		if ( preg_match( '/^[1-9][0-9]{3}$/', $decade ) && 0 === ( (int) $decade % 10 ) ) {
			$title = sprintf( '%d–%d', (int) $decade, (int) $decade + 9 );
		}
		return $title;
	}

	/**
	 * Prepare administrator CSS for an HTML-safe style element.
	 *
	 * This is a CSS text boundary, not a full CSS validator. Administrators
	 * retain CSS capabilities but a legacy option cannot close the style tag.
	 *
	 * @param mixed $raw CSS from stored settings.
	 * @return string
	 */
	public static function safe_custom_css( $raw ) {
		if ( ! is_string( $raw ) ) {
			return '';
		}
		$raw = substr( $raw, 0, 20000 );
		$raw = wp_strip_all_tags( $raw );
		// Prevent the HTML parser from recognizing a closing style tag,
		// including values loaded from unsanitized legacy options.
		return str_replace( '<', '\\3c ', $raw );
	}

	/**
	 * Enqueue custom styling using the WordPress Styles API.
	 *
	 * The handle has no external file and is enqueued only with real CSS.
	 * No raw style elements are echoed by this plugin.
	 */
	function plugin_enqueue_styles() {
		// Site administrators in a multisite network normally cannot use
		// unfiltered CSS. Previously saved values must be approved again by
		// a super admin before they can affect public output.
		if ( is_multisite() && '1' !== $this->options['custom_css_approved'] ) {
			return;
		}
		$css = self::safe_custom_css( $this->options['custom_css'] );
		if ( '' === trim( $css ) ) {
			return;
		}
		wp_register_style( 'chronoarchive-custom', false, array(), $this->version );
		wp_enqueue_style( 'chronoarchive-custom' );
		wp_add_inline_style( 'chronoarchive-custom', $css );
	}

	/**
	 * Add options page to the admin menu.
	 */
	function admin_menu() {
		if ( function_exists( 'add_options_page' ) && current_user_can( 'manage_options' ) ) {
			add_options_page(
				$this->plugin_options_page_title,
				$this->plugin_options_menu_title,
				'manage_options',
				$this->plugin_options_slug,
				array( $this, 'options_page' )
			);
		}
	}

	/**
	 * Register plugin settings.
	 */
	function admin_init() {
		register_setting(
			$this->domain,
			$this->options_name,
			array( 'sanitize_callback' => array( $this, 'sanitize_settings' ) )
		);
	}

	/**
	 * Validate settings before saving. Existing stored values are not migrated.
	 *
	 * @param mixed $input Form submission.
	 * @return array<string, string>
	 */
	function sanitize_settings( $input ) {
		$input = is_array( $input ) ? $input : array();
		$previous = get_option( $this->options_name, array() );
		$previous = is_array( $previous ) ? $previous : array();

		// manage_options may be held by a multisite subsite administrator,
		// who intentionally lacks the edit_css (unfiltered_html) capability.
		// Preserve existing settings without allowing that user to replace
		// arbitrary CSS or self-approve an untrusted legacy value.
		if ( current_user_can( 'edit_css' ) ) {
			$css = isset( $input['custom_css'] ) && is_string( $input['custom_css'] )
				? substr( wp_strip_all_tags( wp_unslash( $input['custom_css'] ) ), 0, 20000 )
				: '';
			$approved = '1';
		} else {
			$css = isset( $previous['custom_css'] ) && is_string( $previous['custom_css'] )
				? substr( $previous['custom_css'], 0, 20000 )
				: '';
			$approved = isset( $previous['custom_css_approved'] ) &&
				'1' === $previous['custom_css_approved'] ? '1' : '';
		}

		return array(
			'custom_css' => $css,
			'custom_css_approved' => $approved,
			'use_cpt_archive' => ! empty( $input['use_cpt_archive'] ) ? '1' : '',
		);
	}

	/**
	 * Load widget admin JavaScript.
	 *
	 * @param string $hook Current admin page.
	 */
	function archive_admin_js_scripts( $hook ) {
		global $pagenow;
		if ( $hook == 'widgets.php' && $pagenow != 'customize.php' ) {
			$plugin_url = plugins_url() . '/' . dirname( plugin_basename(__FILE__) );
			wp_register_script( 'archive-admin-script', $plugin_url . '/js/widget_form.js', array( 'jquery' ), '0.2', true );
			wp_enqueue_script( 'archive-admin-script' );
		}
	}


	/**
	 * Execute and cache an archive statement with a pre-prepared predicate.
	 *
	 * Keep the six inherited aggregate queries behind ONE audited SQL sink.
	 * Their SQL includes trusted WordPress getarchives_where/getarchives_join
	 * extension fragments, which remain the responsibility of the registering
	 * PHP callback. Passing a whole fragment as an SQL string value to
	 * wpdb::prepare() would be incorrect.
	 *
	 * Cache keys intentionally preserve the historical format so an empty
	 * result is a valid cache hit, and a WordPress posts last_changed update
	 * invalidates the archive without manual cache deletion.
	 *
	 * @param string $query        SQL including the prepared core WHERE predicate.
	 * @param string $last_changed WordPress posts cache-generation token.
	 * @return array|null Archive rows or empty/null on database failure.
	 */
	private static function cached_archive_rows( $query, $last_changed ) {
		global $wpdb;

		$key = 'wp_get_archives_advanced:' . md5( $query ) . ':' . $last_changed;
		$results = wp_cache_get( $key, 'posts' );

		if ( false === $results ) {
			$results = $wpdb->get_results( $query );
			wp_cache_set( $key, $results, 'posts' );
		}

		return $results;
	}

	/**
	 * Advanced wp_get_archives.
	 *
	 * Adds ordering and decade filtering capabilities.
	 *
	 * @param array|string $args Arguments for retrieving archives.
	 * @return void|string Archive links output or return.
	 */
	static function wp_get_archives_advanced( $args = '' ) {
		global $wpdb, $wp_locale;

		$defaults = array(
			'type'            => 'yearly',
			'limit'           => '',
			'format'          => 'html',
			'before'          => '',
			'after'           => '',
			'show_post_count' => false,
			'showcount'       => false,
			'echo'            => 1,
			'order'           => 'DESC',
			'alpha_order'     => 'ASC',
			'post_order'      => 'DESC',
			'post_type'       => 'post',
			'use_cpt_archive' => '',
		);

		$r = wp_parse_args( $args, $defaults );

		// Validate direct API and legacy-widget calls, which skip shortcode_atts().
		$type = is_string( $r['type'] ) ? sanitize_key( $r['type'] ) : '';
		$r['type'] = in_array( $type, array( 'daily', 'weekly', 'monthly', 'yearly', 'decade', 'alpha', 'postbypost' ), true ) ? $type : 'yearly';
		$format = is_string( $r['format'] ) ? sanitize_key( $r['format'] ) : '';
		$r['format'] = in_array( $format, array( 'html', 'option', 'link', 'custom' ), true ) ? $format : 'html';
		$r['before'] = wp_kses_post( is_scalar( $r['before'] ) ? (string) $r['before'] : '' );
		$r['after'] = wp_kses_post( is_scalar( $r['after'] ) ? (string) $r['after'] : '' );
		$r['order'] = 'ASC' === strtoupper( is_string( $r['order'] ) ? $r['order'] : '' ) ? 'ASC' : 'DESC';
		$r['alpha_order'] = 'DESC' === strtoupper( is_string( $r['alpha_order'] ) ? $r['alpha_order'] : '' ) ? 'DESC' : 'ASC';
		$r['post_order'] = 'ASC' === strtoupper( is_string( $r['post_order'] ) ? $r['post_order'] : '' ) ? 'ASC' : 'DESC';
		$r['limit'] = is_scalar( $r['limit'] ) ? $r['limit'] : '';
		$r['post_type'] = is_string( $r['post_type'] ) ? sanitize_key( $r['post_type'] ) : '';

		$post_type_object = get_post_type_object( $r['post_type'] );
		if ( ! is_post_type_viewable( $post_type_object ) ) {
			return '';
		}
		$r['post_type'] = $post_type_object->name;

		if ( '' == $r['type'] ) {
			$r['type'] = 'yearly';
		}

		// A zero/omitted limit means unlimited. Never append a bare "0"
		// after ORDER BY: the legacy path produced invalid SQL for limit=0.
		// Invalid nonempty values preserve a fail-closed SQL LIMIT 0.
		$limit_present = ! empty( $r['limit'] );
		$listing_limit = $limit_present ? absint( $r['limit'] ) : 0;
		$invalid_listing_limit = $limit_present && 0 === $listing_limit;
		$r['limit'] = $limit_present ? ' LIMIT ' . $listing_limit : '';

		$order = strtoupper( $r['order'] );
		if ( $order !== 'ASC' ) {
			$order = 'DESC';
		}

		if ( empty( $r['show_post_count'] ) && ! empty( $r['showcount'] ) ) {
			$r['show_post_count'] = $r['showcount'];
		}

		// Separator for weekly archives.
		$archive_week_separator = '&#8211;';

		$sql_where = $wpdb->prepare( "WHERE post_type = %s AND post_status = 'publish'", $r['post_type'] );

		$where = apply_filters( 'getarchives_where', $sql_where, $r );
		$join  = apply_filters( 'getarchives_join', '', $r );

		// Preserve the legacy SQL-fragment hook contract for trusted plugin
		// callbacks. Malformed filter return values must never be coerced into
		// SQL or trigger a fatal error in a public shortcode.
		// SQL fragments from PHP callbacks are *not* user-input parameters:
		// wpdb::prepare() cannot quote a JOIN or WHERE clause as a value.
		// Code registering these filters remains responsible for its own
		// SQL parameterization. Fail closed when the contract is violated.
		if (
			! is_string( $where ) ||
			! is_string( $join ) ||
			'' === trim( $where ) ||
			false !== strpos( $where, "\0" ) ||
			false !== strpos( $join, "\0" )
		) {
			return '';
		}

		// WordPress core passes this pre-prepared WHERE clause to the legacy
		// SQL-fragment hooks. Keep their returned SQL intact, including
		// literal percent signs, prepared LIKE patterns and custom JOINs.
		// Only installed PHP callbacks can add these SQL fragments.

		$output = '';

		$last_changed = wp_cache_get_last_changed( 'posts' );
		$limit = $r['limit'];

		// JOIN filters may multiply posts into matching metadata rows. Archive
		// counts and individual links must represent unique published posts.
		// Grouped archives must sort on their grouped date buckets, not raw
		// post_date. The latter is ambiguous and can fail ONLY_FULL_GROUP_BY.
		// Sort directions are already allowlisted to ASC/DESC above.
		// The validated post type is prepared in the canonical WHERE clause
		// before the WordPress hooks run. Trusted PHP filters can supply their
		// own parameterized JOIN/WHERE fragments as in wp_get_archives().
		// Monthly archives.
		if ( 'monthly' == $r['type'] ) {
			$query = "SELECT YEAR(post_date) AS `year`, MONTH(post_date) AS `month`, COUNT(DISTINCT $wpdb->posts.ID) AS posts FROM $wpdb->posts $join $where GROUP BY YEAR(post_date), MONTH(post_date) ORDER BY `year` $order, `month` $order $limit";
			$results = self::cached_archive_rows( $query, $last_changed );
			if ( $results ) {
				$after = $r['after'];
				foreach ( (array) $results as $result ) {
					$url = get_month_link( $result->year, $result->month );
					if ( 'post' !== $r['post_type'] ) {
						if ( ! empty( $r['use_cpt_archive'] ) ) {
							$base = get_post_type_archive_link( $r['post_type'] );
							if ( $base ) {
								$url = add_query_arg( array(
									'year'     => $result->year,
									'monthnum' => $result->month,
								), $base );
							} else {
								$url = add_query_arg( 'post_type', $r['post_type'], $url );
							}
						} else {
							$url = add_query_arg( 'post_type', $r['post_type'], $url );
						}
					}
					/* translators: 1: Localized month name, 2: four-digit year. */
					$text = sprintf( __( '%1$s %2$d', 'chronoarchive' ), $wp_locale->get_month( $result->month ), $result->year );
					if ( $r['show_post_count'] ) {
						$r['after'] = '&nbsp;(' . $result->posts . ')' . $after;
					}
					$output .= get_archives_link( $url, $text, $r['format'], $r['before'], $r['after'] );
				}
			}
		// Yearly archives: grouped SQL and object-cache lookup.
		} elseif ( 'yearly' == $r['type'] ) {
			$query = "SELECT YEAR(post_date) AS `year`, COUNT(DISTINCT $wpdb->posts.ID) AS posts FROM $wpdb->posts $join $where GROUP BY YEAR(post_date) ORDER BY `year` $order $limit";
			$results = self::cached_archive_rows( $query, $last_changed );
			if ( $results ) {
				$after = $r['after'];
				foreach ( (array) $results as $result ) {
					$url = get_year_link( $result->year );
					if ( 'post' !== $r['post_type'] ) {
						if ( ! empty( $r['use_cpt_archive'] ) ) {
							$base = get_post_type_archive_link( $r['post_type'] );
							if ( $base ) {
								$url = add_query_arg( 'year', $result->year, $base );
							} else {
								$url = add_query_arg( 'post_type', $r['post_type'], $url );
							}
						} else {
							$url = add_query_arg( 'post_type', $r['post_type'], $url );
						}
					}
					$text = sprintf( '%d', $result->year );
					if ( $r['show_post_count'] ) {
						$r['after'] = '&nbsp;(' . $result->posts . ')' . $after;
					}
					$output .= get_archives_link( $url, $text, $r['format'], $r['before'], $r['after'] );
				}
			}
		// Daily archives: grouped SQL and object-cache lookup.
		} elseif ( 'daily' == $r['type'] ) {
			$query = "SELECT YEAR(post_date) AS `year`, MONTH(post_date) AS `month`, DAYOFMONTH(post_date) AS `dayofmonth`, COUNT(DISTINCT $wpdb->posts.ID) AS posts FROM $wpdb->posts $join $where GROUP BY YEAR(post_date), MONTH(post_date), DAYOFMONTH(post_date) ORDER BY `year` $order, `month` $order, `dayofmonth` $order $limit";
			$results = self::cached_archive_rows( $query, $last_changed );
			if ( $results ) {
				$after = $r['after'];
				foreach ( (array) $results as $result ) {
					$url = get_day_link( $result->year, $result->month, $result->dayofmonth );
					if ( 'post' !== $r['post_type'] ) {
						$url = add_query_arg( 'post_type', $r['post_type'], $url );
					}
					$date = sprintf( '%1$d-%2$02d-%3$02d 00:00:00', $result->year, $result->month, $result->dayofmonth );
					$text = mysql2date( get_option( 'date_format' ), $date );
					if ( $r['show_post_count'] ) {
						$r['after'] = '&nbsp;(' . $result->posts . ')' . $after;
					}
					$output .= get_archives_link( $url, $text, $r['format'], $r['before'], $r['after'] );
				}
			}
		// Weekly archives: grouped SQL and object-cache lookup.
		} elseif ( 'weekly' == $r['type'] ) {
			// start_of_week is a validated WordPress option (0=Sunday ... 6=Saturday).
			$start_day = (int) get_option( 'start_of_week', 1 );
			$week_start_sql = "DATE_SUB(DATE(post_date), INTERVAL MOD(DAYOFWEEK(post_date) - 1 - $start_day + 7, 7) DAY)";
			$query = "SELECT DATE_FORMAT($week_start_sql, '%Y-%m-%d') AS week_start, COUNT(DISTINCT $wpdb->posts.ID) AS posts FROM $wpdb->posts $join $where GROUP BY week_start ORDER BY week_start $order $limit";
			$results = self::cached_archive_rows( $query, $last_changed );
			if ( $results ) {
				$after = $r['after'];
				foreach ( (array) $results as $result ) {
					$range = get_weekstartend( $result->week_start, $start_day );
					$start_label = date_i18n( get_option( 'date_format' ), $range['start'] );
					$end_label   = date_i18n( get_option( 'date_format' ), $range['end'] );
					$args = array( 'chrono_week' => $result->week_start );
					if ( 'post' !== $r['post_type'] ) {
						$args['post_type'] = $r['post_type'];
					}
					$url  = add_query_arg( $args, get_year_link( (int) substr( $result->week_start, 0, 4 ) ) );
					$text = $start_label . $archive_week_separator . $end_label;
					$r['after'] = $r['show_post_count'] ? '&nbsp;(' . (int) $result->posts . ')' . $after : $after;
					$output .= get_archives_link( $url, $text, $r['format'], $r['before'], $r['after'] );
				}
			}
		// Decade archives: grouped SQL and object-cache lookup.
		} elseif ( 'decade' == $r['type'] ) {
			$query = "SELECT COUNT(DISTINCT archive_post_id) AS posts, decade, MAX(decade) + 9 AS end_year FROM (SELECT $wpdb->posts.ID AS archive_post_id, FLOOR(YEAR(post_date) / 10) * 10 AS decade FROM $wpdb->posts $join $where) t GROUP BY decade ORDER BY decade $order $limit";
			$results = self::cached_archive_rows( $query, $last_changed );
			if ( $results ) {
				$after = $r['after'];
				foreach ( (array) $results as $result ) {
					$url = add_query_arg( 'decade', absint( $result->decade ), get_year_link( absint( $result->decade ) ) );
					if ( 'post' !== $r['post_type'] ) {
						$url = add_query_arg( 'post_type', $r['post_type'], $url );
					}
					$text = sprintf( '%d–%d', (int) $result->decade, (int) $result->end_year );
					if ( $r['show_post_count'] ) {
						$r['after'] = '&nbsp;(' . $result->posts . ')' . $after;
					}
					$output .= get_archives_link( $url, $text, $r['format'], $r['before'], $r['after'] );
				}
			}
		// Post-by-post or Alpha archives.
		} elseif ( 'postbypost' == $r['type'] || 'alpha' == $r['type'] ) {
			$alpha_order = strtoupper( $r['alpha_order'] );
			if ( $alpha_order !== 'ASC' ) {
				$alpha_order = 'DESC';
			}
			$post_order = strtoupper( $r['post_order'] );
			if ( $post_order !== 'DESC' ) {
				$post_order = 'ASC';
			}
			// Use WordPress's structured query API when there are no SQL
			// archive hooks. It handles native post queries and their cache,
			// rather than interpolating SQL for ordinary public listings.
			// Callbacks on either legacy SQL hook retain their original
			// behavior and use the specialized query below.
			if ( ! $invalid_listing_limit && ! has_filter( 'getarchives_where' ) && ! has_filter( 'getarchives_join' ) ) {
				$query_args = array(
					'post_type'              => $r['post_type'],
					'post_status'            => 'publish',
					'posts_per_page'         => $listing_limit > 0 ? $listing_limit : -1,
					'ignore_sticky_posts'    => true,
					'no_found_rows'          => true,
					'cache_results'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					// Keep normal WP_Query filters enabled, as expected
					// by WordPress plugins and the Plugin Check VIP rules.
					'orderby'                => 'alpha' === $r['type']
						? 'title' : array( 'date' => $post_order, 'ID' => 'DESC' ),
					'order'                  => $alpha_order,
				);
				$native_query = new WP_Query( $query_args );
				$results = $native_query->posts;
			} else {
				$orderby = ( 'alpha' == $r['type'] )
					? 'post_title ' . $alpha_order
					: 'post_date ' . $post_order . ', ID DESC';
				$query = "SELECT DISTINCT $wpdb->posts.ID AS ID, post_title, post_date FROM $wpdb->posts $join $where ORDER BY $orderby $limit";
				$results = self::cached_archive_rows( $query, $last_changed );
			}
			if ( $results ) {
				foreach ( (array) $results as $result ) {
					if ( $result->post_date != '0000-00-00 00:00:00' ) {
						$url = get_permalink( (int) $result->ID );
						$filtered_title = $result->post_title ? apply_filters( 'the_title', $result->post_title, $result->ID ) : $result->ID;
						$text = is_scalar( $filtered_title ) ? wp_strip_all_tags( (string) $filtered_title ) : (string) $result->ID;
						$output .= get_archives_link( $url, $text, $r['format'], $r['before'], $r['after'] );
					}
				}
			}
		}
		if ( $r['echo'] ) {
			// Archive links originate from WordPress get_archives_link(), but
			// different formats require distinct HTML allowlists at the sink.
			if ( 'option' === $r['format'] ) {
				echo wp_kses( $output, array(
					'option' => array( 'value' => true ),
				) );
			} elseif ( 'link' === $r['format'] ) {
				echo wp_kses( $output, array(
					'link' => array( 'rel' => true, 'title' => true, 'href' => true ),
				) );
			} else {
				echo wp_kses_post( $output );
			}
		} else {
			// Return-mode output is rendered by shortcodes and direct callers.
			// Apply the same late-escaping policy as echo-mode, including markup
			// modified by the WordPress get_archives_link filter.
			if ( 'option' === $r['format'] ) {
				return wp_kses( $output, array(
					'option' => array( 'value' => true ),
				) );
			}
			if ( 'link' === $r['format'] ) {
				return wp_kses( $output, array(
					'link' => array( 'rel' => true, 'title' => true, 'href' => true ),
				) );
			}
			return wp_kses_post( $output );
		}
	}

	/**
	 * Shortcode callback.
	 *
	 * Sanitizes and assigns shortcode attributes.
	 *
	 * @param array  $atts Shortcode attributes.
	 * @param string $content Content between shortcode tags.
	 * @return string Archive output.
	 */
	function shortcode( $atts, $content = null ) {
		$defaults = array(
			'type'            => 'yearly',
			'limit'           => '',
			'format'          => 'html', // html, option, link
			'before'          => '',
			'after'           => '',
			'show_post_count' => '0',
			'showcount'       => '0',
			'tag'             => 'ul',
			'order'           => 'DESC',
			'alpha_order'     => 'ASC',
			'post_order'      => 'DESC',
			'select_text'     => '',
			'post_type'       => 'post',
			'use_cpt_archive' => $this->options['use_cpt_archive']
		);
		$atts = shortcode_atts( $defaults, is_array( $atts ) ? $atts : array(), 'archives' );
		// shortcode_atts() exposes the shortcode_atts_archives filter. A
		// third-party filter can return unexpected types, including arrays.
		// Normalize every field before WordPress string/escaping functions.
		$atts = is_array( $atts ) ? wp_parse_args( $atts, $defaults ) : $defaults;
		foreach ( $defaults as $key => $default ) {
			if ( ! is_scalar( $atts[ $key ] ) ) {
				$atts[ $key ] = $default;
			}
			$atts[ $key ] = (string) $atts[ $key ];
		}

		$type            = sanitize_key( $atts['type'] );
		$type            = in_array( $type, array( 'daily', 'weekly', 'monthly', 'yearly', 'decade', 'alpha', 'postbypost' ), true ) ? $type : 'yearly';
		$limit           = '' !== $atts['limit'] ? absint( $atts['limit'] ) : '';
		$format          = sanitize_key( $atts['format'] );
		$format          = in_array( $format, array( 'html', 'option', 'link', 'custom' ), true ) ? $format : 'html';
		$before          = wp_kses_post( $atts['before'] );
		$after           = wp_kses_post( $atts['after'] );
		$show_post_count = in_array( strtolower( $atts['show_post_count'] ), array( '1', 'true', 'yes', 'on' ), true )
			|| in_array( strtolower( $atts['showcount'] ), array( '1', 'true', 'yes', 'on' ), true ) ? 1 : 0;
		$tag             = sanitize_key( $atts['tag'] );
		$tag             = in_array( $tag, array( 'ul', 'ol', 'div', 'nav', 'section', 'span' ), true ) ? $tag : 'ul';
		$order           = 'ASC' === strtoupper( $atts['order'] ) ? 'ASC' : 'DESC';
		$alpha_order     = 'ASC' === strtoupper( $atts['alpha_order'] ) ? 'ASC' : 'DESC';
		$post_order      = 'ASC' === strtoupper( $atts['post_order'] ) ? 'ASC' : 'DESC';
		$select_text     = sanitize_text_field( $atts['select_text'] );
		$post_type       = sanitize_key( $atts['post_type'] );
		// Preserve legacy truthy values accepted by the original shortcode.
		$use_cpt_archive = '' !== $atts['use_cpt_archive'] && '0' !== $atts['use_cpt_archive'] ? '1' : '';

		if ( $format == 'option' ) {
			$dtitle = ! empty( $select_text )
				? $select_text
				: ( $type == 'monthly'
					? __( 'Select Month', 'chronoarchive' )
					: ( $type == 'weekly'
						? __( 'Select Week', 'chronoarchive' )
						: ( $type == 'daily'
							? __( 'Select Day', 'chronoarchive' )
							: ( $type == 'postbypost' || $type == 'alpha'
								? __( 'Select Post', 'chronoarchive' )
								: __( 'Select Year', 'chronoarchive' )
							)
						)
					)
				);
			$select_id = wp_unique_id( 'chronoarchive-select-' );
			wp_enqueue_script( 'chronoarchive-dropdown', plugins_url( 'assets/js/archive-dropdown.js', __FILE__ ), array(), $this->version, true );
			$arc  = '<label for="' . esc_attr( $select_id ) . '" class="screen-reader-text">' . esc_html__( 'Choose archive', 'chronoarchive' ) . '</label>';
			$arc .= '<select class="chronoarchive-select" name="archive-dropdown" id="' . esc_attr( $select_id ) . '">';
			$arc .= '<option value="">' . esc_html( $dtitle ) . '</option>';
			$arc .= WP_Plugin_ChronoArchive::wp_get_archives_advanced( array(
				'type'            => $type,
				'limit'           => $limit,
				'format'          => 'option',
				'show_post_count' => $show_post_count,
				'post_type'       => $post_type,
				'order'           => $order,
				'alpha_order'     => $alpha_order,
				'post_order'      => $post_order,
				'use_cpt_archive' => $use_cpt_archive,
				'echo'            => 0
			) );
			$arc .= '</select>';
		} else {
			$arc  = '<' . $tag . '>';
			$arch_arr = array(
				'type'            => $type,
				'limit'           => $limit,
				'format'          => $format,
				'before'          => $before,
				'after'           => $after,
				'show_post_count' => $show_post_count,
				'post_type'       => $post_type,
				'order'           => $order,
				'alpha_order'     => $alpha_order,
				'post_order'      => $post_order,
				'use_cpt_archive' => $use_cpt_archive,
				'echo'            => 0
			);

			$arc .= WP_Plugin_ChronoArchive::wp_get_archives_advanced( $arch_arr );
			$arc .= '</' . $tag . '>';
		}
		return $arc;
	}

	/**
	 * Add a link to the options page from the plugin list.
	 *
	 * @param array $links Existing plugin action links.
	 * @return array Modified plugin action links.
	 */
	function plugin_actions( $links ) {
		$new_links = array();
		$new_links[] = '<a href="options-general.php?page=' . $this->plugin_options_slug . '">' . __( 'Settings', 'chronoarchive' ) . '</a>';
		return array_merge( $new_links, $links );
	}

	/**
	 * Options page callback.
	 */
	function options_page() {
		// The settings page must never render for unprivileged users, even when
		// invoked directly outside its capability-gated admin_menu registration.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$this->_set_options();
		$options = $this->options;
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $this->plugin_name ); ?></h1>
			<p><?php esc_html_e( 'Display chronological archives with shortcodes or a classic widget.', 'chronoarchive' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( $this->domain ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="chronoarchive-custom-css"><?php esc_html_e( 'Custom CSS', 'chronoarchive' ); ?></label>
						</th>
						<td>
							<textarea id="chronoarchive-custom-css"
								name="<?php echo esc_attr( $this->options_name ); ?>[custom_css]"
								rows="7" cols="70" class="large-text code" <?php disabled( ! current_user_can( 'edit_css' ), true ); ?>><?php echo esc_textarea( $options['custom_css'] ); ?></textarea>
							<p class="description">
								<?php esc_html_e( 'Only users with the WordPress edit_css capability may change custom CSS. Previously saved multisite CSS requires approval by an authorized administrator.', 'chronoarchive' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Custom post type archive links', 'chronoarchive' ); ?></th>
						<td>
							<label for="chronoarchive-cpt-archive">
								<input type="checkbox" id="chronoarchive-cpt-archive"
									name="<?php echo esc_attr( $this->options_name ); ?>[use_cpt_archive]"
									value="1" <?php checked( $options['use_cpt_archive'], '1' ); ?> />
								<?php esc_html_e( 'Prefer registered custom post type archive URLs when available', 'chronoarchive' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Post types without a public archive base fall back to the normal WordPress archive URL with a post_type parameter.', 'chronoarchive' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
			<p class="description">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: plugin version. */
						__( 'ChronoArchive %s. Fork of Annual Archive by Twinpictures, distributed under the GPL.', 'chronoarchive' ),
						$this->version
					)
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Set options from saved values or defaults.
	 */
	function _set_options() {
		$saved_options = get_option( $this->options_name );
		// Preserve settings from historic installations without trusting types.
		if ( ! is_array( $saved_options ) || empty( $saved_options ) ) {
			$saved_options = get_option( $this->domain . 'options' );
		}
		if ( ! is_array( $saved_options ) ) {
			return;
		}
		$this->options['custom_css'] = isset( $saved_options['custom_css'] ) &&
			is_string( $saved_options['custom_css'] )
			? substr( $saved_options['custom_css'], 0, 20000 )
			: '';
		$this->options['custom_css_approved'] = isset( $saved_options['custom_css_approved'] ) &&
			is_string( $saved_options['custom_css_approved'] ) &&
			'1' === $saved_options['custom_css_approved'] ? '1' : '';
		$this->options['use_cpt_archive'] = ! empty( $saved_options['use_cpt_archive'] ) &&
			is_scalar( $saved_options['use_cpt_archive'] )
			? '1'
			: '';
	}

} // end class WP_Plugin_ChronoArchive

/**
 * Instantiate the ChronoArchive plugin.
 */
$WP_Plugin_ChronoArchive = new WP_Plugin_ChronoArchive();

/**
 * ChronoArchive Widget Class.
 */
class ChronoArchive_Widget extends WP_Widget {

	/**
	 * Read a scalar from legacy widget storage, avoiding PHP type errors.
	 *
	 * @param array  $instance Stored widget settings.
	 * @param string $key      Option name.
	 * @param string $default  Value if absent or malformed.
	 * @return string
	 */
	private static function legacy_text( $instance, $key, $default = '' ) {
		$value = isset( $instance[ $key ] ) ? $instance[ $key ] : $default;
		return is_scalar( $value ) ? stripslashes( (string) $value ) : $default;
	}

	/**
	 * Constructor.
	 */
	function __construct() {
		$widget_ops = array(
			'classname'   => 'ChronoArchive_Widget',
			'description' => __( 'Display daily, weekly, monthly or yearly archives with a sidebar widget or shortcode', 'chronoarchive' )
		);
		parent::__construct( 'ChronoArchive_Widget', __( 'ChronoArchive', 'chronoarchive' ), $widget_ops );
	}

	/**
	 * Front-end display of widget.
	 *
	 * @param array $args Widget arguments.
	 * @param array $instance Instance settings.
	 */
	function widget( $args, $instance ) {
		// Explicitly read only trusted WordPress theme wrappers. Never extract()
		// arbitrary keys into local variables, where they could overwrite state.
		$before_widget = isset( $args['before_widget'] ) && is_string( $args['before_widget'] ) ? $args['before_widget'] : '';
		$after_widget  = isset( $args['after_widget'] ) && is_string( $args['after_widget'] ) ? $args['after_widget'] : '';
		$before_title  = isset( $args['before_title'] ) && is_string( $args['before_title'] ) ? $args['before_title'] : '';
		$after_title   = isset( $args['after_title'] ) && is_string( $args['after_title'] ) ? $args['after_title'] : '';
		$instance = is_array( $instance ) ? $instance : array();

		$format         = empty( $instance['format'] ) ? 'html' : apply_filters( 'widget_format', $instance['format'] );
		$format         = is_string( $format ) && in_array( $format, array( 'html', 'option', 'link', 'custom' ), true ) ? $format : 'html';
		$type           = empty( $instance['type'] ) ? 'yearly' : apply_filters( 'widget_type', $instance['type'] );
		$before_raw    = empty( $instance['before'] ) ? '' : apply_filters( 'widget_before', $instance['before'] );
		$after_raw     = empty( $instance['after'] ) ? '' : apply_filters( 'widget_after', $instance['after'] );
		$before         = wp_kses_post( is_scalar( $before_raw ) ? (string) $before_raw : '' );
		$after          = wp_kses_post( is_scalar( $after_raw ) ? (string) $after_raw : '' );
		$limit          = apply_filters( 'widget_limit', isset( $instance['limit'] ) ? $instance['limit'] : '' );
		$title          = apply_filters( 'widget_title', self::legacy_text( $instance, 'title' ), $instance, $this->id_base );
		$show_post_count= empty( $instance['show_post_count'] ) ? 0 : $instance['show_post_count'];
		$order          = empty( $instance['order'] ) ? 'DESC' : apply_filters( 'widget_order', $instance['order'] );
		$alpha_order    = empty( $instance['alpha_order'] ) ? 'ASC' : apply_filters( 'widget_alpha_order', $instance['alpha_order'] );
		$post_order     = empty( $instance['post_order'] ) ? 'DESC' : apply_filters( 'widget_post_order', $instance['post_order'] );
		$select_text    = empty( $instance['select_text'] ) ? '' : apply_filters( 'widget_slelect_text', $instance['select_text'] );
		$select_text    = is_scalar( $select_text ) ? sanitize_text_field( (string) $select_text ) : '';
		$post_type      = empty( $instance['post_type'] ) ? 'post' : apply_filters( 'widget_post_type', $instance['post_type'] );

		if ( empty( $show_post_count ) && ! empty( $instance['showcount'] ) ) {
			$show_post_count = $instance['showcount'];
		}

		echo wp_kses_post( $before_widget );
		if ( is_scalar( $title ) && '' !== (string) $title ) {
			echo wp_kses_post( $before_title ) . esc_html( (string) $title ) . wp_kses_post( $after_title );
		}

		if ( $format == 'option' ) {
			$select_id = wp_unique_id( 'chronoarchive-select-' );
			wp_enqueue_script( 'chronoarchive-dropdown', plugins_url( 'assets/js/archive-dropdown.js', __FILE__ ), array(), $this->version, true );
			$dtitle = $select_text ? $select_text : ( $type == 'monthly' ? __( 'Select Month', 'chronoarchive' ) : ( $type == 'weekly' ? __( 'Select Week', 'chronoarchive' ) : ( $type == 'daily' ? __( 'Select Day', 'chronoarchive' ) : ( $type == 'postbypost' || $type == 'alpha' ? __( 'Select Post', 'chronoarchive' ) : __( 'Select Year', 'chronoarchive' ) ) ) ) );
			?>
			<label for="<?php echo esc_attr( $select_id ); ?>" class="screen-reader-text"><?php esc_html_e( 'Choose archive', 'chronoarchive' ); ?></label>
			<select name="archive-dropdown" id="<?php echo esc_attr( $select_id ); ?>" class="chronoarchive-select">
				<option value=""><?php echo esc_html( $dtitle ); ?></option>
				<?php WP_Plugin_ChronoArchive::wp_get_archives_advanced( apply_filters( 'widget_archive_dropdown_args', array(
					'type'            => $type,
					'format'          => 'option',
					'show_post_count' => $show_post_count,
					'post_type'       => $post_type,
					'limit'           => $limit,
					'order'           => $order,
					'alpha_order'     => $alpha_order,
					'post_order'      => $post_order
				) ) ); ?>
			</select>
			<?php
		} else {
			?>
			<ul>
				<?php WP_Plugin_ChronoArchive::wp_get_archives_advanced( apply_filters( 'widget_archive_args', array(
					'type'            => $type,
					'limit'           => $limit,
					'format'          => $format,
					'before'          => $before,
					'after'           => $after,
					'show_post_count' => $show_post_count,
					'post_type'       => $post_type,
					'order'           => $order,
					'alpha_order'     => $alpha_order,
					'post_order'      => $post_order,
					'select_text'     => $select_text
				) ) ); ?>
			</ul>
			<?php
		}

		echo wp_kses_post( $after_widget );
	}

	/**
	 * Update widget settings.
	 *
	 * @param array $new_instance New settings.
	 * @param array $old_instance Previous settings.
	 * @return array Updated settings.
	 */
	function update( $new_instance, $old_instance ) {
		$instance = is_array( $old_instance ) ? $old_instance : array();
		$new_instance = is_array( $new_instance ) ? $new_instance : array();
		$instance['title'] = sanitize_text_field( self::legacy_text( $new_instance, 'title' ) );
		$instance['before'] = wp_kses_post( self::legacy_text( $new_instance, 'before' ) );
		$instance['after'] = wp_kses_post( self::legacy_text( $new_instance, 'after' ) );
		$instance['select_text'] = sanitize_text_field( self::legacy_text( $new_instance, 'select_text' ) );
		$instance['limit'] = empty( $new_instance['limit'] ) ? '' : absint( self::legacy_text( $new_instance, 'limit' ) );
		$instance['show_post_count'] = ! empty( $new_instance['show_post_count'] ) ? 1 : 0;
		$instance['post_type'] = sanitize_key( self::legacy_text( $new_instance, 'post_type', 'post' ) );
		$type = sanitize_key( self::legacy_text( $new_instance, 'type', 'yearly' ) );
		$instance['type'] = in_array( $type, array( 'daily', 'weekly', 'monthly', 'yearly', 'decade', 'alpha', 'postbypost' ), true ) ? $type : 'yearly';
		$format = sanitize_key( self::legacy_text( $new_instance, 'format', 'html' ) );
		$instance['format'] = in_array( $format, array( 'html', 'option', 'link', 'custom' ), true ) ? $format : 'html';
		foreach ( array( 'order' => 'DESC', 'alpha_order' => 'ASC', 'post_order' => 'DESC' ) as $field => $default ) {
			$value = strtoupper( self::legacy_text( $new_instance, $field, $default ) );
			$instance[ $field ] = in_array( $value, array( 'ASC', 'DESC' ), true ) ? $value : $default;
		}
		return $instance;
	}

	/**
	 * Widget settings form.
	 *
	 * @param array $instance Current settings.
	 */
	function form( $instance ) {
		$instance = is_array( $instance ) ? $instance : array();
		$title = self::legacy_text( $instance, 'title' );
		$show_post_count = empty( $instance['show_post_count'] ) ? 0 : $instance['show_post_count'];
		$format = self::legacy_text( $instance, 'format' );
		$before = self::legacy_text( $instance, 'before' );
		$after = self::legacy_text( $instance, 'after' );
		$type = wp_strip_all_tags( self::legacy_text( $instance, 'type' ) );
		$limit = self::legacy_text( $instance, 'limit' );
		$post_type = self::legacy_text( $instance, 'post_type', 'post' );
		$order = self::legacy_text( $instance, 'order', 'DESC' );
		$alpha_order = self::legacy_text( $instance, 'alpha_order', 'ASC' );
		$post_order = self::legacy_text( $instance, 'post_order', 'DESC' );
		$select_text = self::legacy_text( $instance, 'select_text' );

		if ( empty( $show_post_count ) && ! empty( $instance['showcount'] ) ) {
			$show_post_count = $instance['showcount'];
		}
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
				<?php esc_html_e( 'Title:', 'chronoarchive' ); ?>
				<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
			</label>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_post_count' ) ); ?>">
				<input type="checkbox" id="<?php echo esc_attr( $this->get_field_id( 'show_post_count' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'show_post_count' ) ); ?>" value="1" <?php checked( $show_post_count, 1 ); ?> />
				&nbsp;&nbsp;<?php esc_html_e( 'Show post counts', 'chronoarchive' ); ?>
			</label>
		</p>
		<p>
			<label><?php esc_html_e( 'Archive type:', 'chronoarchive' ); ?>
				<select name="<?php echo esc_attr( $this->get_field_name( 'type' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'type' ) ); ?>" class="annual_archive_type_select">
					<?php
						$types_arr = array(
							'daily'     => __( 'Daily', 'chronoarchive' ),
							'weekly'    => __( 'Weekly', 'chronoarchive' ),
							'monthly'   => __( 'Monthly', 'chronoarchive' ),
							'yearly'    => __( 'Yearly', 'chronoarchive' ),
							'decade'    => __( 'Decade', 'chronoarchive' ),
							'alpha'     => __( 'Alpha', 'chronoarchive' ),
							'postbypost'=> __( 'Post By Post', 'chronoarchive' )
						);

						$order_style = '';
						$alpha_style = 'style="display: none;"';
						$post_style  = 'style="display: none;"';

						foreach ( $types_arr as $key => $value ) {
							// WordPress core selected() will print the safe selected attribute.
							// Set data attribute for ordering fields.
							$data_att = 'wrap_' . $this->get_field_id( 'order' );
							if ( $key == 'alpha' ) {
								$data_att = 'wrap_' . $this->get_field_id( 'alpha_order' );
								if ( $key == $type ) {
									$order_style = 'style="display: none;"';
									$alpha_style = '';
								}
							}
							if ( $key == 'postbypost' ) {
								$data_att = 'wrap_' . $this->get_field_id( 'post_order' );
								if ( $key == $type ) {
									$order_style = 'style="display: none;"';
									$post_style  = '';
								}
							}
							echo '<option value="' . esc_attr( $key ) . '" data-orderfield="' . esc_attr( $data_att ) . '"' . selected( $key, $type ? $type : 'yearly', false ) . '>' . esc_html( $value ) . '</option>';
						}
					?>
				</select>
			</label>
		</p>
		<p>
			<label><?php esc_html_e( 'Format:', 'chronoarchive' ); ?>
				<select name="<?php echo esc_attr( $this->get_field_name( 'format' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'format' ) ); ?>">
					<?php
						$format_arr = array(
							'html'   => __( 'HTML', 'chronoarchive' ),
							'option' => __( 'Option', 'chronoarchive' ),
							'link'   => __( 'Link', 'chronoarchive' ),
							'custom' => __( 'Custom', 'chronoarchive' )
						);
						foreach ( $format_arr as $key => $value ) {
							// WordPress core selected() handles safe selected attribute.
							echo '<option value="' . esc_attr( $key ) . '"' . selected( $key, $format ? $format : 'html', false ) . '>' . esc_html( $value ) . '</option>';
						}
					?>
				</select>
			</label>
			<br/>
			<span class="description">
				<a href="https://developer.wordpress.org/reference/functions/wp_get_archives/#parameters" target="_blank"><?php esc_html_e( 'Format details', 'chronoarchive' ); ?></a>
			</span>
		</p>
		<p>
			<label><?php esc_html_e( 'Post Type:', 'chronoarchive' ); ?>
				<select name="<?php echo esc_attr( $this->get_field_name( 'post_type' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'post_type' ) ); ?>">
					<?php
						$pt_args = array( 'public' => true );
						$post_types = get_post_types( $pt_args );
						foreach ( $post_types as $type ) {
							// Keep the post type name as data until late escaping.
							echo '<option value="' . esc_attr( $type ) . '"' . selected( $type, $post_type, false ) . '>' . esc_html( $type ) . '</option>';
						}
					?>
				</select>
			</label>
			<br/>
			<span class="description">
				<a href="https://developer.wordpress.org/reference/functions/wp_get_archives/#parameters" target="_blank"><?php esc_html_e( 'Post Type', 'chronoarchive' ); ?></a>
			</span>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'before' ) ); ?>"><?php esc_html_e( 'Text Before Link:', 'chronoarchive' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'before' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'before' ) ); ?>" type="text" value="<?php echo esc_attr( $before ); ?>" />
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'after' ) ); ?>"><?php esc_html_e( 'Text After Link:', 'chronoarchive' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'after' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'after' ) ); ?>" type="text" value="<?php echo esc_attr( $after ); ?>" />
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'select_text' ) ); ?>"><?php esc_html_e( 'Select Text:', 'chronoarchive' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'select_text' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'select_text' ) ); ?>" type="text" value="<?php echo esc_attr( $select_text ); ?>" />
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>"><?php esc_html_e( 'Number of archives to display:', 'chronoarchive' ); ?></label>
			<input class="widefat" style="width: 50px;" id="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'limit' ) ); ?>" type="text" value="<?php echo esc_attr( $limit ); ?>" />
		</p>
		<p id="wrap_<?php echo esc_attr( $this->get_field_id( 'order' ) ); ?>" class="order_<?php echo esc_attr( $this->get_field_id( 'type' ) ); ?>" <?php if ( '' !== $order_style ) : ?>style="display: none;"<?php endif; ?>>
			<label for="<?php echo esc_attr( $this->get_field_id( 'order' ) ); ?>"><?php esc_html_e( 'Sort order:', 'chronoarchive' ); ?></label>
			<input id="<?php echo esc_attr( $this->get_field_id( 'order' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'order' ) ); ?>" type="radio" value="DESC" <?php checked( $order, 'DESC' ); ?> /> DESC
			<input name="<?php echo esc_attr( $this->get_field_name( 'order' ) ); ?>" type="radio" value="ASC" <?php checked( $order, 'ASC' ); ?> /> ASC
		</p>
		<p id="wrap_<?php echo esc_attr( $this->get_field_id( 'alpha_order' ) ); ?>" class="order_<?php echo esc_attr( $this->get_field_id( 'type' ) ); ?>" <?php if ( '' !== $alpha_style ) : ?>style="display: none;"<?php endif; ?>>
			<label for="<?php echo esc_attr( $this->get_field_id( 'alpha_order' ) ); ?>"><?php esc_html_e( 'Alpha order:', 'chronoarchive' ); ?></label>
			<input id="<?php echo esc_attr( $this->get_field_id( 'alpha_order' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'alpha_order' ) ); ?>" type="radio" value="DESC" <?php checked( $alpha_order, 'DESC' ); ?> /> DESC
			<input name="<?php echo esc_attr( $this->get_field_name( 'alpha_order' ) ); ?>" type="radio" value="ASC" <?php checked( $alpha_order, 'ASC' ); ?> /> ASC
		</p>
		<p id="wrap_<?php echo esc_attr( $this->get_field_id( 'post_order' ) ); ?>" class="order_<?php echo esc_attr( $this->get_field_id( 'type' ) ); ?>" <?php if ( '' !== $post_style ) : ?>style="display: none;"<?php endif; ?>>
			<label for="<?php echo esc_attr( $this->get_field_id( 'post_order' ) ); ?>"><?php esc_html_e( 'Post order:', 'chronoarchive' ); ?></label>
			<input id="<?php echo esc_attr( $this->get_field_id( 'post_order' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'post_order' ) ); ?>" type="radio" value="DESC" <?php checked( $post_order, 'DESC' ); ?> /> DESC
			<input name="<?php echo esc_attr( $this->get_field_name( 'post_order' ) ); ?>" type="radio" value="ASC" <?php checked( $post_order, 'ASC' ); ?> /> ASC
		</p>
		<?php
	}
} // end class ChronoArchive_Widget

/**
 * Register ChronoArchive_Widget.
 */
function ChronoArchive_register_widget() {
	register_widget( 'ChronoArchive_Widget' );
}
add_action( 'widgets_init', 'ChronoArchive_register_widget' );

