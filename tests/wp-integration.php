<?php
/**
 * Real WordPress regression checks. Runs ONLY in disposable GitHub Actions WP.
 *
 * Run: wp eval-file wp-content/plugins/chronoarchive/tests/wp-integration.php
 *      --path=/tmp/chrono-wp
 *
 * @package ChronoArchive
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit( 1 );
}
global $wpdb;
// WP loads plugin sources inside its loader scope; their variables are not global.
$plugin_under_test = new WP_Plugin_ChronoArchive();

$passed = 0;
$failed = 0;
$fixture_ids = array();
$assert = static function ( $name, $condition ) use ( &$passed, &$failed ) {
    if ( $condition ) {
        ++$passed;
        echo "PASS: {$name}\n";
    } else {
        ++$failed;
        echo "FAIL: {$name}\n";
    }
};

// Independent site, never run this file against Amino #LAB or a client database.
if ( 'chronoarchive-ci' !== get_option( 'blogname' ) ) {
    fwrite( STDERR, "ABORT: site is not the disposable ChronoArchive CI instance.\n" );
    exit( 2 );
}

update_option( 'start_of_week', 1 ); // Monday, including cross-year weeks.
foreach ( range( 1997, 2026 ) as $year ) {
    $id = wp_insert_post( array(
        'post_title'   => 'CA-CI Fixture ' . $year,
        'post_content' => 'Synthetic archive content.',
        'post_date'    => sprintf( '%04d-01-02 12:00:00', $year ),
        'post_status'  => 'publish',
        'post_type'    => 'post',
    ), true );
    if ( is_wp_error( $id ) ) {
        throw new RuntimeException( 'Unable to create fixture for ' . $year . ': ' . $id->get_error_message() );
    }
    $fixture_ids[] = $id;
}
$boundary_ids = array();
foreach ( array( '2019-12-31 12:00:00', '2020-01-01 12:00:00' ) as $date ) {
    $id = wp_insert_post( array(
        'post_title' => 'CA-CI Boundary ' . $date,
        'post_date' => $date,
        'post_status' => 'publish',
        'post_type' => 'post',
    ), true );
    if ( is_wp_error( $id ) ) {
        throw new RuntimeException( 'Unable to create weekly boundary fixture' );
    }
    $fixture_ids[] = $id;
    $boundary_ids[] = $id;
}

// Exercise real MariaDB in strict grouping mode before the ordinary archive
// render suite. WordPress normally removes ONLY_FULL_GROUP_BY from its SQL
// session, so errors in grouping can otherwise remain invisible in CI.
$previous_sql_mode = (string) $wpdb->get_var( 'SELECT @@SESSION.sql_mode' );
$strict_query = $wpdb->query( "SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY'" );
$strict_enabled = false !== $strict_query &&
    false !== strpos( (string) $wpdb->get_var( 'SELECT @@SESSION.sql_mode' ), 'ONLY_FULL_GROUP_BY' );
$assert( 'MariaDB ONLY_FULL_GROUP_BY enabled for archive regression', $strict_enabled );
if ( $strict_enabled ) {
    wp_cache_flush();
    foreach ( array( 'yearly', 'monthly', 'daily', 'weekly', 'decade' ) as $bucket_type ) {
        $wpdb->last_error = '';
        $strict_html = WP_Plugin_ChronoArchive::wp_get_archives_advanced( array(
            'type' => $bucket_type, 'order' => 'ASC', 'limit' => 4, 'echo' => 0,
        ) );
        $assert( 'Strict SQL ' . $bucket_type . ': no database error', '' === $wpdb->last_error );
        $assert( 'Strict SQL ' . $bucket_type . ': archive links generated',
            is_string( $strict_html ) && false !== strpos( $strict_html, '<a ' ) );
    }
    // Grouped lists also need deterministic ordering and LIMIT semantics.
    $strict_year_desc = WP_Plugin_ChronoArchive::wp_get_archives_advanced( array(
        'type' => 'yearly', 'order' => 'DESC', 'limit' => 2, 'echo' => 0,
    ) );
    $assert( 'Strict SQL descending year order and limit',
        is_string( $strict_year_desc ) &&
        false !== strpos( $strict_year_desc, '2026' ) &&
        false !== strpos( $strict_year_desc, '2025' ) &&
        2 === substr_count( $strict_year_desc, '<li>' ) );
}
$restored_sql_mode = $wpdb->query( $wpdb->prepare( 'SET SESSION sql_mode = %s', $previous_sql_mode ) );
$assert( 'Original database session SQL mode restored', false !== $restored_sql_mode &&
    $previous_sql_mode === (string) $wpdb->get_var( 'SELECT @@SESSION.sql_mode' ) );

foreach ( array( 'archives', 'arcpromat', 'chronoarchive' ) as $alias ) {
    $assert( 'Alias registered ' . $alias, shortcode_exists( $alias ) );
}
$year_html = do_shortcode( '[chronoarchive type="yearly" order="ASC" show_post_count="1"]' );
$assert( 'Year archive contains 1997', false !== strpos( $year_html, '>1997</a>' ) );
$assert( 'Year archive contains 2026', false !== strpos( $year_html, '>2026</a>' ) );
$assert( 'Year list has at least 30 entries', substr_count( $year_html, '<li>' ) >= 30 );

$decade_html = do_shortcode( '[chronoarchive type="decade" order="ASC" show_post_count="1"]' );
$assert( 'Decades render 1990 to 2029', false !== strpos( $decade_html, '1990' ) &&
    false !== strpos( $decade_html, '2020' ) );
$assert( 'Four decades generated', 4 === substr_count( $decade_html, '<li>' ) );

foreach ( array( 'monthly', 'weekly', 'daily', 'alpha', 'postbypost' ) as $type ) {
    $html = do_shortcode( '[chronoarchive type="' . $type . '" limit="5"]' );
    $assert( 'Non-empty ' . $type . ' archive', is_string( $html ) && false !== strpos( $html, '<a ' ) );
    $assert( 'Limit 5 respected for ' . $type, substr_count( $html, '<li>' ) <= 5 );
}
$weekly = do_shortcode( '[chronoarchive type="weekly" limit="60"]' );
$assert( 'Cross-year week correctly linked', false !== strpos( $weekly, 'chrono_week=2019-12-30' ) );

$unsafe = do_shortcode( '[archives type="yearly" limit="2" tag="script" before="<span onclick=alert(1)>Prefix</span>"]' );
$assert( 'Invalid container falls back to UL', 0 === strpos( trim( $unsafe ), '<ul>' ) );
$assert( 'Shortcode event handler removed', false === stripos( $unsafe, 'onclick=' ) );

// CVE-2023-0178: WPScan's published, exact upstream proof of concept is
// [archives tag='ul onmouseover="alert(1)"']. Test this exact attacker
// attribute on all three inherited shortcode aliases, using the real
// WordPress shortcode parser rather than a manually constructed $atts array.
// Source: https://wpscan.com/vulnerability/cc308e15-7937-4d41-809d-74f8c13bee23/
foreach ( array( 'archives', 'arcpromat', 'chronoarchive' ) as $alias ) {
    $poc = '[' . $alias . ' tag=\'ul onmouseover="alert(1)"\' limit=\'2\']';
    $poc_html = do_shortcode( $poc );
    $assert( 'CVE-2023-0178 PoC rejects injected tag attribute (' . $alias . ')',
        0 === strpos( trim( $poc_html ), '<ul>' ) &&
        false === stripos( $poc_html, 'onmouseover=' ) &&
        false === stripos( $poc_html, 'alert(1)' ) &&
        false !== strpos( $poc_html, '<a ' ) );
}

// Annual Archive 1.6.0 used esc_attr($tag) as an HTML *tag name*. That
// does not strip an unquoted attribute from a tag (for example,
 // tag='ul onmouseover=alert(1)'). This is a source-derived variant,
 // not an attributed reproduction of CVE-2023-49847.
foreach ( array( 'archives', 'arcpromat', 'chronoarchive' ) as $alias ) {
    $variant = '[' . $alias . ' tag=\'ul onmouseover=alert(1)\' limit=\'2\']';
    $html_variant = do_shortcode( $variant );
    $assert( 'Annual Archive 1.6.0 unquoted tag handler prevented (' . $alias . ')',
        0 === strpos( trim( $html_variant ), '<ul>' ) &&
        false === stripos( $html_variant, 'onmouseover=' ) &&
        false !== strpos( $html_variant, '<a ' ) );
}

// The named shortcode_atts_archives hook may return malformed values.
// This is a typed-input resilience test using real WordPress functions.
$malformed_shortcode_filter = static function ( $attributes ) {
    $attributes['type'] = array( 'yearly' );
    $attributes['before'] = array( '<svg onload=alert(1)>' );
    $attributes['after'] = new stdClass();
    $attributes['tag'] = array( 'script' );
    $attributes['select_text'] = array( '<img src=x onerror=alert(1)>' );
    $attributes['order'] = array( 'DESC' );
    $attributes['limit'] = array( 2 );
    return $attributes;
};
add_filter( 'shortcode_atts_archives', $malformed_shortcode_filter, 10, 1 );
$filtered_error = '';
try {
    $filtered_shortcode = do_shortcode( '[chronoarchive type="yearly" limit="2"]' );
} catch ( Throwable $exception ) {
    $filtered_error = $exception->getMessage();
    $filtered_shortcode = '';
}
remove_filter( 'shortcode_atts_archives', $malformed_shortcode_filter, 10 );
$assert( 'Malformed shortcode filter values do not throw', '' === $filtered_error );
$assert( 'Malformed filtered tag renders allowed container', 0 === strpos( trim( $filtered_shortcode ), '<ul>' ) );
$assert( 'Filtered unsafe HTML cannot execute',
    false === stripos( $filtered_shortcode, '<svg' ) &&
    false === stripos( $filtered_shortcode, '<script' ) &&
    false === stripos( $filtered_shortcode, 'onerror=' ) );
$assert( 'Malformed filtered shortcode retains archive links', false !== strpos( $filtered_shortcode, '<a ' ) );

$object_shortcode_filter = static function () { return new stdClass(); };
add_filter( 'shortcode_atts_archives', $object_shortcode_filter, 10, 1 );
$filtered_default = do_shortcode( '[archives type="yearly" limit="2"]' );
remove_filter( 'shortcode_atts_archives', $object_shortcode_filter, 10 );
$assert( 'Non-array attribute filter returns safe default archive',
    0 === strpos( trim( $filtered_default ), '<ul>' ) && false !== strpos( $filtered_default, '<a ' ) );

$select = do_shortcode( '[chronoarchive type="yearly" format="option" limit="2" select_text="Select archive"]' );
$assert( 'Selector has accessible label', false !== strpos( $select, '<label' ) );
$assert( 'No inline onchange', false === stripos( $select, 'onchange=' ) );
ob_start();
WP_Plugin_ChronoArchive::wp_get_archives_advanced( array(
    'type' => 'yearly', 'limit' => 2, 'format' => 'option', 'echo' => 1,
) );
$rendered_options = ob_get_clean();
$assert( 'Direct API escapes HTML option format', substr_count( $rendered_options, '<option' ) === 2 );
ob_start();
WP_Plugin_ChronoArchive::wp_get_archives_advanced( array(
    'type' => 'yearly', 'limit' => 2, 'format' => 'link', 'echo' => 1,
) );
$rendered_links = ob_get_clean();
$assert( 'Direct API preserves sanitized LINK format', substr_count( $rendered_links, '<link' ) === 2 );
// WordPress's get_archives_link filter may provide additional HTML. Its result
// is untrusted for shortcode display, both when echoed and when returned.
$malformed_archives_link = static function ( $link ) {
    return $link . '<script>alert("archive")</script><img src=x onerror=alert(1)>';
};
add_filter( 'get_archives_link', $malformed_archives_link, 10, 1 );
$filtered_markup = do_shortcode( '[chronoarchive type="yearly" limit="1"]' );
$filtered_options = do_shortcode( '[chronoarchive type="yearly" format="option" limit="1"]' );
$filtered_links = WP_Plugin_ChronoArchive::wp_get_archives_advanced(
    array( 'type' => 'yearly', 'format' => 'link', 'limit' => 1, 'echo' => 0 )
);
remove_filter( 'get_archives_link', $malformed_archives_link, 10 );
$assert( 'Returned archive HTML removes active filtered markup',
    false === stripos( $filtered_markup, '<script' ) &&
    false === stripos( $filtered_markup, 'onerror=' ) &&
    false === stripos( $filtered_markup, 'onload=' ) &&
    false !== strpos( $filtered_markup, '<a ' ) );
$assert( 'Returned OPTION archive output preserves strict markup',
    2 === substr_count( $filtered_options, '<option value=' ) &&
    false === stripos( $filtered_options, '<img' ) &&
    false === stripos( $filtered_options, '<script' ) );
$assert( 'Returned LINK archive output strips filtered HTML tags',
    1 === substr_count( $filtered_links, '<link' ) &&
    false === stripos( $filtered_links, '<img' ) &&
    false === stripos( $filtered_links, '<script' ) );

// A cached empty archive is still a successful cache hit. The historical
// truthy/falsy check queried MariaDB again on every empty result.
register_post_type( 'ca_empty_cache', array( 'public' => true, 'has_archive' => false ) );
$cache_initial = $wpdb->num_queries;
$empty_first = WP_Plugin_ChronoArchive::wp_get_archives_advanced( array(
    'type' => 'yearly', 'post_type' => 'ca_empty_cache', 'echo' => 0,
) );
$cache_after_first = $wpdb->num_queries;
$empty_second = WP_Plugin_ChronoArchive::wp_get_archives_advanced( array(
    'type' => 'yearly', 'post_type' => 'ca_empty_cache', 'echo' => 0,
) );
$cache_after_second = $wpdb->num_queries;
$assert( 'Empty public CPT produces no archive links',
    '' === $empty_first && '' === $empty_second );
$assert( 'First empty archive is actually queried', $cache_after_first > $cache_initial );
$assert( 'Cached empty archive does not repeat its SQL query',
    $cache_after_second === $cache_after_first );
unregister_post_type( 'ca_empty_cache' );

// A database error must not poison the archive object cache. A failed
// get_results() returns null, whereas a valid empty result is an array.
// The invalid SQL below is generated exclusively by a disposable CI PHP
// callback, never a visitor input, and errors are suppressed for the test.
wp_cache_flush();
$malformed_sql = static function () { return 'WHERE ('; };
add_filter( 'getarchives_where', $malformed_sql, 10, 1 );
$previous_suppress = $wpdb->suppress_errors( true );
$failed_archive_queries_before = $wpdb->num_queries;
$failed_archive_first = WP_Plugin_ChronoArchive::wp_get_archives_advanced( array(
    'type' => 'yearly', 'post_type' => 'post', 'echo' => 0,
) );
$failed_archive_queries_after_first = $wpdb->num_queries;
$failed_archive_second = WP_Plugin_ChronoArchive::wp_get_archives_advanced( array(
    'type' => 'yearly', 'post_type' => 'post', 'echo' => 0,
) );
$failed_archive_queries_after_second = $wpdb->num_queries;
$wpdb->suppress_errors( $previous_suppress );
remove_filter( 'getarchives_where', $malformed_sql, 10 );
$assert( 'Failed archive SQL does not expose partial archive content',
    '' === $failed_archive_first && '' === $failed_archive_second );
$assert( 'Failed archive SQL is retried rather than negatively cached',
    $failed_archive_queries_after_first > $failed_archive_queries_before &&
    $failed_archive_queries_after_second > $failed_archive_queries_after_first );

// Publishing and deleting posts must invalidate an already warmed archive.
// WordPress clean_post_cache() updates the posts last_changed token.
wp_cache_flush();
$archive_pre_insert = WP_Plugin_ChronoArchive::wp_get_archives_advanced( array(
    'type' => 'yearly', 'echo' => 0, 'order' => 'ASC',
) );
$archive_pre_repeat = WP_Plugin_ChronoArchive::wp_get_archives_advanced( array(
    'type' => 'yearly', 'echo' => 0, 'order' => 'ASC',
) );
$assert( 'Cache returns identical warm archive before post changes',
    is_string( $archive_pre_insert ) && $archive_pre_insert === $archive_pre_repeat );
$pre_change_token = wp_cache_get_last_changed( 'posts' );
$cache_test_id = wp_insert_post( array(
    'post_title' => 'CA Cache Invalidation 1981',
    'post_date' => '1981-06-15 12:00:00',
    'post_status' => 'publish',
    'post_type' => 'post',
), true );
$cache_test_created = ! is_wp_error( $cache_test_id ) && (int) $cache_test_id > 0;
$assert( 'Archive invalidation fixture created', $cache_test_created );
if ( $cache_test_created ) {
    $after_insert_token = wp_cache_get_last_changed( 'posts' );
    $archive_post_insert = WP_Plugin_ChronoArchive::wp_get_archives_advanced( array(
        'type' => 'yearly', 'echo' => 0, 'order' => 'ASC',
    ) );
    $assert( 'Publishing an article invalidates posts cache generation',
        $pre_change_token !== $after_insert_token );
    $assert( 'Published article appears in refreshed historical archive',
        false === strpos( $archive_pre_insert, '1981' ) &&
        false !== strpos( $archive_post_insert, '1981' ) );
    wp_delete_post( (int) $cache_test_id, true );
    $archive_after_delete = WP_Plugin_ChronoArchive::wp_get_archives_advanced( array(
        'type' => 'yearly', 'echo' => 0, 'order' => 'ASC',
    ) );
    $assert( 'Deleted article disappears from refreshed archive',
        is_string( $archive_after_delete ) &&
        false === strpos( $archive_after_delete, '1981' ) );
}


// The legacy SQL filters are *trusted code extension points*, not user input.
// Non-string fragments must not become SQL, cause PHP warnings, or return
// private/unfiltered data. Check every mode and both filter hooks independently.
$invalid_where_values = array( array( 'WHERE post_status=publish' ), null, false, '' );
$invalid_join_values = array( array( 'JOIN invalid' ), new stdClass(), false );
foreach ( array( 'getarchives_where' => $invalid_where_values, 'getarchives_join' => $invalid_join_values ) as $hook => $bad_values ) {
    foreach ( $bad_values as $bad_value ) {
        $invalid_filter = static function () use ( $bad_value ) {
            return $bad_value;
        };
        add_filter( $hook, $invalid_filter, 10, 1 );
        foreach ( array( 'yearly', 'monthly', 'daily', 'weekly', 'decade', 'alpha', 'postbypost' ) as $mode ) {
            $before_queries = $wpdb->num_queries;
            $caught = '';
            $invalid_output = null;
            try {
                $invalid_output = WP_Plugin_ChronoArchive::wp_get_archives_advanced( array(
                    'type' => $mode, 'echo' => 0,
                ) );
            } catch ( Throwable $exception ) {
                $caught = $exception->getMessage();
            }
            $assert( 'Malformed ' . $hook . ' fails closed (' . $mode . ')',
                '' === $caught && '' === $invalid_output &&
                $before_queries === $wpdb->num_queries );
        }
        remove_filter( $hook, $invalid_filter, 10 );
    }
}
// Embedded NUL bytes should not be passed to MariaDB or used as cache keys.
foreach ( array( 'getarchives_where', 'getarchives_join' ) as $hook ) {
    $null_byte = static function ( $sql ) { return $sql . "\0"; };
    add_filter( $hook, $null_byte, 10, 1 );
    $before_queries = $wpdb->num_queries;
    $null_output = WP_Plugin_ChronoArchive::wp_get_archives_advanced( array(
        'type' => 'yearly', 'echo' => 0,
    ) );
    $assert( 'NUL byte in ' . $hook . ' fails closed without executing SQL',
        '' === $null_output && $before_queries === $wpdb->num_queries );
    remove_filter( $hook, $null_byte, 10 );
}


// A trusted getarchives_join filter can multiply each post into joined rows.
// The archive API must count unique posts rather than matching metadata rows.
$join_post = wp_insert_post( array(
    'post_title'  => 'CA CI JOIN Cardinality Fixture',
    'post_date'   => '2016-08-17 14:20:00',
    'post_status' => 'publish',
    'post_type'   => 'post',
), true );
$assert( 'JOIN cardinality fixture created', ! is_wp_error( $join_post ) );
if ( ! is_wp_error( $join_post ) ) {
    $fixture_ids[] = $join_post;
    add_post_meta( $join_post, 'ca_join_cardinality', 'first' );
    add_post_meta( $join_post, 'ca_join_cardinality', 'second' );
    $join_cardinality = static function ( $join ) use ( $wpdb ) {
        return $join . " INNER JOIN {$wpdb->postmeta} AS ca_join_meta ON ca_join_meta.post_id = {$wpdb->posts}.ID";
    };
    $where_cardinality = static function ( $where ) {
        return $where . " AND ca_join_meta.meta_key = 'ca_join_cardinality'";
    };
    add_filter( 'getarchives_join', $join_cardinality, 10, 1 );
    add_filter( 'getarchives_where', $where_cardinality, 10, 1 );

    foreach ( array( 'yearly', 'monthly', 'daily', 'weekly', 'decade' ) as $mode ) {
        $wpdb->last_error = '';
        $joined_markup = WP_Plugin_ChronoArchive::wp_get_archives_advanced( array(
            'type' => $mode, 'show_post_count' => true, 'echo' => 0,
        ) );
        $assert( 'JOIN ' . $mode . ' does not produce SQL errors', '' === $wpdb->last_error );
        $assert( 'JOIN ' . $mode . ' counts one post, not two matching metadata rows',
            is_string( $joined_markup ) &&
            1 === substr_count( $joined_markup, '<li>' ) &&
            1 === substr_count( $joined_markup, '(1)' ) &&
            false === strpos( $joined_markup, '(2)' ) );
    }
    foreach ( array( 'alpha', 'postbypost' ) as $mode ) {
        $joined_markup = WP_Plugin_ChronoArchive::wp_get_archives_advanced( array(
            'type' => $mode, 'echo' => 0,
        ) );
        $assert( 'JOIN ' . $mode . ' lists each post only once',
            is_string( $joined_markup ) && 1 === substr_count( $joined_markup, '<li>' ) );
    }
    remove_filter( 'getarchives_join', $join_cardinality, 10 );
    remove_filter( 'getarchives_where', $where_cardinality, 10 );
}


// wp_get_archives() passes an already-prepared WHERE to these legacy hooks.
// Verify the final SQL may be rebound without changing the hook-visible SQL,
// breaking legal percent signs (including the literal sequence "%s"), or
// losing support for trusted plugins that fully rewrite the WHERE clause.
$hook_seen = array();
$legacy_cases = array(
    'literal-percent' => array(
        'filter' => static function ( $where ) {
            return $where . " AND post_title LIKE 'CA-CI%'";
        },
        'expect_rows' => true,
    ),
    'prepared-percent' => array(
        'filter' => static function ( $where ) use ( $wpdb ) {
            return $where . $wpdb->prepare( ' AND post_title LIKE %s', 'CA-CI%' );
        },
        'expect_rows' => true,
    ),
    'reordered-where' => array(
        'filter' => static function ( $where ) {
            // Deliberately remove the canonical clause byte sequence.
            // This trusted full rewrite must remain backwards compatible.
            return "WHERE post_status = 'publish' AND post_type = 'post' AND post_title LIKE 'CA-CI%'";
        },
        'expect_rows' => true,
    ),
    'literal-placeholder-like' => array(
        'filter' => static function ( $where ) {
            // "%s" here is SQL LIKE text, not a wpdb::prepare() argument.
            return $where . " AND post_title LIKE 'CA-CI%suffix'";
        },
        'expect_rows' => false,
    ),
);
foreach ( $legacy_cases as $case => $spec ) {
    $wrapped_filter = static function ( $where, $args ) use ( &$hook_seen, $spec ) {
        $hook_seen[] = $where;
        return call_user_func( $spec['filter'], $where );
    };
    $hook_seen = array();
    add_filter( 'getarchives_where', $wrapped_filter, 10, 2 );
    foreach ( array( 'yearly', 'monthly', 'daily', 'weekly', 'decade', 'alpha', 'postbypost' ) as $mode ) {
        wp_cache_flush();
        $wpdb->last_error = '';
        $html = WP_Plugin_ChronoArchive::wp_get_archives_advanced( array(
            'type' => $mode, 'echo' => 0, 'limit' => 3,
        ) );
        $valid_result = is_string( $html ) && '' === $wpdb->last_error &&
            ( $spec['expect_rows'] ? false !== strpos( $html, '<li>' ) : '' === $html );
        $assert( 'Final archive SQL preserves ' . $case . ' filter (' . $mode . ')', $valid_result );
    }
    $assert( 'WordPress legacy WHERE filter sees prepared source clause (' . $case . ')',
        isset( $hook_seen[0] ) && false !== strpos(
            $hook_seen[0], "WHERE post_type = 'post' AND post_status = 'publish'"
        ) );
    remove_filter( 'getarchives_where', $wrapped_filter, 10 );
}
unset( $legacy_cases, $hook_seen );


// Native WP_Query listings must match the specialized SQL fallback in the
// absence of custom SQL fragments. The no-op archive hook forces fallback
// without changing its SQL, so output parity is checked for both sort
// directions and bounded/unbounded lists.
$noop_where = static function ( $where ) { return $where; };
$native_observed = 0;
$native_spy = static function ( $query ) use ( &$native_observed ) {
    if ( 'post' === $query->get( 'post_type' ) && ! $query->is_main_query() ) {
        ++$native_observed;
    }
};
add_action( 'pre_get_posts', $native_spy, 100, 1 );
foreach ( array( 'alpha', 'postbypost' ) as $listing_mode ) {
    foreach ( array( 'ASC', 'DESC' ) as $direction ) {
        foreach ( array( 7, 0 ) as $listing_limit ) {
            $listing_args = array(
                'type' => $listing_mode, 'echo' => 0,
                'limit' => $listing_limit, 'alpha_order' => $direction,
                'post_order' => $direction, 'post_type' => 'post',
            );
            wp_cache_flush();
            $native_before = $native_observed;
            $native_result = WP_Plugin_ChronoArchive::wp_get_archives_advanced( $listing_args );
            $assert( 'Native listing uses WP_Query (' . $listing_mode . '/' . $direction . '/' . $listing_limit . ')',
                $native_observed > $native_before );
            add_filter( 'getarchives_where', $noop_where, 10, 1 );
            $legacy_result = WP_Plugin_ChronoArchive::wp_get_archives_advanced( $listing_args );
            remove_filter( 'getarchives_where', $noop_where, 10 );
            $assert( 'Native listing matches trusted SQL fallback (' . $listing_mode . '/' . $direction . '/' . $listing_limit . ')',
                is_string( $native_result ) && $native_result === $legacy_result );
        }
    }
}
remove_action( 'pre_get_posts', $native_spy, 100 );

// Standard WP_Query SQL filters must remain active on the native listing
// path. WordPress VIP flags suppress_filters=true as a compatibility defect.
$posts_where_called = 0;
$native_where_spy = static function ( $where, $query ) use ( &$posts_where_called ) {
    if ( 'post' === $query->get( 'post_type' ) ) {
        ++$posts_where_called;
    }
    return $where;
};
add_filter( 'posts_where', $native_where_spy, 10, 2 );
WP_Plugin_ChronoArchive::wp_get_archives_advanced( array(
    'type' => 'alpha', 'post_type' => 'post', 'echo' => 0, 'limit' => 5,
) );
remove_filter( 'posts_where', $native_where_spy, 10 );
$assert( 'Native WP_Query listing preserves core posts_where filters',
    $posts_where_called > 0 );


foreach ( array( 'alpha', 'postbypost' ) as $listing_mode ) {
    $wpdb->last_error = '';
    $invalid_numeric = WP_Plugin_ChronoArchive::wp_get_archives_advanced( array(
        'type' => $listing_mode, 'limit' => 'not-a-number', 'echo' => 0,
    ) );
    $assert( 'Malformed nonnumeric listing limit fails closed (' . $listing_mode . ')',
        '' === $invalid_numeric && '' === $wpdb->last_error );
}

// Test with a public custom post type, including its two dated published
// fixtures, to protect non-blog archive support.
register_post_type( 'ca_native_list', array( 'public' => true, 'has_archive' => true ) );
foreach ( array(
    array( 'First CPT listing entry', '2018-01-04 10:30:00' ),
    array( 'Second CPT listing entry', '2022-05-06 14:00:00' ),
) as $details ) {
    $cpt_fixture = wp_insert_post( array(
        'post_title' => $details[0], 'post_date' => $details[1],
        'post_type' => 'ca_native_list', 'post_status' => 'publish',
    ), true );
    $assert( 'Public native listing CPT post inserted', ! is_wp_error( $cpt_fixture ) );
    if ( ! is_wp_error( $cpt_fixture ) ) {
        $fixture_ids[] = $cpt_fixture;
    }
}
foreach ( array( 'alpha', 'postbypost' ) as $listing_mode ) {
    $listing_args = array(
        'type' => $listing_mode, 'echo' => 0, 'post_type' => 'ca_native_list',
        'alpha_order' => 'ASC', 'post_order' => 'DESC',
    );
    wp_cache_flush();
    $native_result = WP_Plugin_ChronoArchive::wp_get_archives_advanced( $listing_args );
    add_filter( 'getarchives_where', $noop_where, 10, 1 );
    $legacy_result = WP_Plugin_ChronoArchive::wp_get_archives_advanced( $listing_args );
    remove_filter( 'getarchives_where', $noop_where, 10 );
    $assert( 'Public CPT native listing retains unique links (' . $listing_mode . ')',
        is_string( $native_result ) && 2 === substr_count( $native_result, '<li>' ) );
    $assert( 'Public CPT native listing matches legacy output (' . $listing_mode . ')',
        $native_result === $legacy_result );
}
unregister_post_type( 'ca_native_list' );


// Audit the final SQL at the wpdb::query boundary, not just rendered HTML.
// Canonical post_type values are bound via wpdb::prepare() before the legacy
// WordPress SQL hooks run, as in core wp_get_archives(). Only this disposable
// WordPress test instance is modified; SQL is inspected, never changed.
$sql_audit_seen = array();
$sql_audit_filter = static function ( $statement ) use ( &$sql_audit_seen, $wpdb ) {
    if ( 0 === stripos( ltrim( $statement ), 'SELECT ' ) &&
        false !== strpos( $statement, $wpdb->posts ) &&
        false !== strpos( $statement, 'COUNT(DISTINCT' ) ) {
        $sql_audit_seen[] = $statement;
    }
    return $statement;
};
add_filter( 'query', $sql_audit_filter, 10, 1 );
foreach ( array( 'yearly', 'monthly', 'daily', 'weekly', 'decade' ) as $sql_mode ) {
    wp_cache_flush();
    $sql_audit_seen = array();
    $wpdb->last_error = '';
    $sql_output = WP_Plugin_ChronoArchive::wp_get_archives_advanced( array(
        'type' => $sql_mode, 'post_type' => 'post', 'echo' => 0, 'limit' => 3,
    ) );
    $assert( 'Core-style prepared post type executes archive SQL (' . $sql_mode . ')',
        is_string( $sql_output ) && '' !== $sql_output &&
        '' === $wpdb->last_error && 1 === count( $sql_audit_seen ) );
    $assert( 'Archive SQL has validated published-only predicate (' . $sql_mode . ')',
        1 === count( $sql_audit_seen ) &&
        false !== strpos( $sql_audit_seen[0], "WHERE post_type = 'post' AND post_status = 'publish'" ) &&
        false === strpos( $sql_audit_seen[0], 'CHRONOARCHIVE_BOUND_POST_TYPE' ) );
}
// A hostile shortcode/direct API post_type must not become a SQL predicate.
foreach ( array( "post' OR 1=1 --", array( 'post' ) ) as $unsafe_type ) {
    $sql_audit_seen = array();
    $unsafe_result = WP_Plugin_ChronoArchive::wp_get_archives_advanced( array(
        'type' => 'yearly', 'post_type' => $unsafe_type, 'echo' => 0,
    ) );
    $assert( 'Unregistered or malformed post type never reaches MariaDB',
        '' === $unsafe_result && 0 === count( $sql_audit_seen ) );
}
// A hostile LIMIT/order value is normalized to a bounded, allowlisted SQL
// statement; the attacker-controlled SQL text never enters the query.
wp_cache_flush();
$sql_audit_seen = array();
$hostile_limit = WP_Plugin_ChronoArchive::wp_get_archives_advanced( array(
    'type' => 'yearly',
    'limit' => '1 UNION SELECT private_post_title',
    'order' => 'DESC; DELETE FROM wp_posts',
    'echo' => 0,
) );
$assert( 'Untrusted archive SQL fragments are normalized before query',
    is_string( $hostile_limit ) && 1 === count( $sql_audit_seen ) &&
    false !== strpos( $sql_audit_seen[0], ' LIMIT 1' ) &&
    false !== strpos( $sql_audit_seen[0], ' DESC ' ) &&
    false === strpos( $sql_audit_seen[0], 'UNION SELECT' ) &&
    false === strpos( $sql_audit_seen[0], 'DELETE FROM' ) );
remove_filter( 'query', $sql_audit_filter, 10 );

$assert( 'Unknown post type returns empty entries', false === strpos(
    do_shortcode( '[chronoarchive type="yearly" post_type="missing_ca_type"]' ), '<a ' ) );

$main_backup = $GLOBALS['wp_the_query'];
$query = new WP_Query();
$GLOBALS['wp_the_query'] = $query;
$query->parse_query( array( 'year' => 2019, 'chrono_week' => '2019-12-30' ) );
$plugin_under_test->ChronoArchive_decade_filter( $query );
$dates = $query->get( 'date_query' );
$assert( 'Week rewrites main archive query', is_array( $dates ) &&
    '2019-12-30' === ( $dates[0]['after'] ?? '' ) &&
    '2020-01-05' === ( $dates[0]['before'] ?? '' ) );
$assert( 'Year restriction cleared for cross-year range', empty( $query->get( 'year' ) ) );
$week_posts = $query->get_posts();
$week_ids = wp_list_pluck( $week_posts, 'ID' );
$assert( 'Cross-year query retrieves both Dec and Jan posts',
    in_array( $boundary_ids[0], $week_ids, true ) && in_array( $boundary_ids[1], $week_ids, true ) );
$GLOBALS['wp_the_query'] = $main_backup;

$query = new WP_Query();
$GLOBALS['wp_the_query'] = $query;
$query->parse_query( array( 'year' => 2025, 'decade' => '2025' ) );
$plugin_under_test->ChronoArchive_decade_filter( $query );
$assert( 'Invalid decade fails closed', array( 0 ) === $query->get( 'post__in' ) );
$assert( 'Invalid decade has no results', 0 === count( $query->get_posts() ) );
$GLOBALS['wp_the_query'] = $main_backup;

foreach ( array( '2019-12-31', '2024-02-31', 'not-a-date' ) as $bad_week ) {
    $invalid_week_query = new WP_Query();
    $GLOBALS['wp_the_query'] = $invalid_week_query;
    $invalid_week_query->parse_query( array( 'year' => 2024, 'chrono_week' => $bad_week ) );
    $plugin_under_test->ChronoArchive_decade_filter( $invalid_week_query );
    $assert( 'Invalid week input is rejected: ' . $bad_week,
        array( 0 ) === $invalid_week_query->get( 'post__in' ) );
}
$GLOBALS['wp_the_query'] = $main_backup;

$homepage_query = new WP_Query();
$GLOBALS['wp_the_query'] = $homepage_query;
$homepage_query->parse_query( array( 'chrono_week' => '2024-01-01' ) );
$plugin_under_test->ChronoArchive_decade_filter( $homepage_query );
$assert( 'Week selector cannot rewrite non-archive query',
    empty( $homepage_query->get( 'date_query' ) ) );
$GLOBALS['wp_the_query'] = $main_backup;

$decade_query = new WP_Query();
$GLOBALS['wp_the_query'] = $decade_query;
$decade_query->parse_query( array( 'year' => 2010, 'decade' => '2010' ) );
$plugin_under_test->ChronoArchive_decade_filter( $decade_query );
// Verify the entire decade, not just WordPress's default 10-post page.
$decade_query->set( 'posts_per_page', -1 );
$decade_posts = $decade_query->get_posts();
$assert( 'Valid decade query includes at least ten posts', count( $decade_posts ) >= 10 );
$years_found = array_map( static function( $post ) { return (int) substr( $post->post_date, 0, 4 ); }, $decade_posts );
$assert( 'Decade SQL respects both endpoints', in_array( 2010, $years_found, true ) &&
    in_array( 2019, $years_found, true ) && ! in_array( 2020, $years_found, true ) );
$GLOBALS['wp_the_query'] = $main_backup;


// WordPress get_year_link() uses ?m=YYYY for plain permalinks, not ?year.
// Test the EXACT format generated by the plugin's visible archive links;
// the previous year-only test missed the January boundary regression.
$plain_week = new WP_Query();
$GLOBALS['wp_the_query'] = $plain_week;
$plain_week->parse_query( array( 'm' => '2019', 'chrono_week' => '2019-12-30' ) );
$plugin_under_test->ChronoArchive_decade_filter( $plain_week );
$plain_week->set( 'posts_per_page', -1 );
$plain_week_ids = wp_list_pluck( $plain_week->get_posts(), 'ID' );
$assert( 'Plain-permalink weekly query clears inherited m=YYYY restriction',
    '' === $plain_week->get( 'm' ) &&
    in_array( $boundary_ids[0], $plain_week_ids, true ) &&
    in_array( $boundary_ids[1], $plain_week_ids, true ) );
$GLOBALS['wp_the_query'] = $main_backup;

$plain_decade = new WP_Query();
$GLOBALS['wp_the_query'] = $plain_decade;
$plain_decade->parse_query( array( 'm' => '2010', 'decade' => '2010' ) );
$plugin_under_test->ChronoArchive_decade_filter( $plain_decade );
$plain_decade->set( 'posts_per_page', -1 );
$plain_decade_years = array_map(
    static function( $post ) { return (int) substr( $post->post_date, 0, 4 ); },
    $plain_decade->get_posts()
);
$assert( 'Plain-permalink decade clears inherited m=YYYY restriction',
    '' === $plain_decade->get( 'm' ) &&
    in_array( 2010, $plain_decade_years, true ) &&
    in_array( 2019, $plain_decade_years, true ) &&
    ! in_array( 2020, $plain_decade_years, true ) );
$GLOBALS['wp_the_query'] = $main_backup;

$assert( 'CSS invalid object rejected', '' === WP_Plugin_ChronoArchive::safe_custom_css( array( 'bad' ) ) );
$assert( 'Legacy CSS cannot end style tag', false === strpos(
    WP_Plugin_ChronoArchive::safe_custom_css( '.x{} </style><script>bad</script>' ), '<' ) );
$plugin_under_test->options['custom_css'] = '.ci{color:purple} </style><script>x</script>';
$plugin_under_test->plugin_enqueue_styles();
$inline_css = wp_styles()->get_data( 'chronoarchive-custom', 'after' );
$assert( 'Custom CSS enqueued through WP Styles', is_array( $inline_css ) && count( $inline_css ) > 0 );
$assert( 'Enqueued CSS cannot contain raw HTML tag', is_array( $inline_css ) &&
    false === strpos( implode( "\n", $inline_css ), '<' ) );

// WordPress 4.9+ already implements shortcode handling for both visual
// and legacy Text widgets via widget_text_content. Registering the old
// widget_text/do_shortcode global filter duplicates that core behavior and
// can alter shortcode/paragraph rendering order across unrelated widgets.
$assert( 'Plugin does not re-register obsolete global Text-widget shortcode filter',
    false === has_filter( 'widget_text', 'do_shortcode' ) &&
    false !== has_filter( 'widget_text_content', 'do_shortcode' ) );
$text_widget = new WP_Widget_Text();
foreach ( array(
    'legacy' => array( 'text' => '[chronoarchive type="yearly" format="html" limit="2"]', 'filter' => false, 'visual' => false ),
    'visual' => array( 'text' => '[chronoarchive type="yearly" format="html" limit="2"]', 'filter' => true, 'visual' => true ),
) as $mode => $instance ) {
    ob_start();
    $text_widget->widget(
        array( 'before_widget' => '<aside>', 'after_widget' => '</aside>',
            'before_title' => '<h3>', 'after_title' => '</h3>' ),
        $instance
    );
    $rendered_text_widget = ob_get_clean();
    $assert( 'Core ' . $mode . ' Text widget still expands ChronoArchive shortcode',
        false !== strpos( $rendered_text_widget, '<aside>' ) &&
        false !== strpos( $rendered_text_widget, '<a ' ) &&
        false !== strpos( $rendered_text_widget, '2019' ) &&
        false === strpos( $rendered_text_widget, '[chronoarchive' ) );
}

$widget = new ChronoArchive_Widget();
$bad_instance = array( 'title' => array( 'bad' ), 'before' => array( 'bad' ),
    'type' => array( 'bad' ), 'format' => array( 'bad' ), 'post_type' => array( 'bad' ) );
$normalized = $widget->update( $bad_instance, array() );
$assert( 'Widget update rejects non-scalar fields', '' === $normalized['title'] &&
    'yearly' === $normalized['type'] && 'html' === $normalized['format'] );
ob_start();
$widget->form( $bad_instance );
$form = ob_get_clean();
$assert( 'Malformed historic widget form renders safely', false !== strpos( $form, '<input' ) );
ob_start();
$widget->widget( array( 'before_widget' => '<aside>', 'after_widget' => '</aside>' ),
    array( 'type' => 'yearly', 'limit' => 2 ) );
$widget_output = ob_get_clean();
$assert( 'Classic widget renders archive links', false !== strpos( $widget_output, '<a ' ) );
$assert( 'Trusted widget theme wrappers are preserved', false !== strpos( $widget_output, '<aside>' ) &&
    false !== strpos( $widget_output, '</aside>' ) );

// A classic WP_Widget is NOT the main plugin instance: $this->version
// would be an undefined property here on PHP 8.x. Deregister to prove
// that the widget itself supplies the correct JS version on first use.
wp_dequeue_script( 'chronoarchive-dropdown' );
wp_deregister_script( 'chronoarchive-dropdown' );
$widget_version_warnings = array();
$old_error_handler = set_error_handler(
    static function ( $severity, $message ) use ( &$widget_version_warnings ) {
        if ( false !== strpos( $message, 'version' ) ) {
            $widget_version_warnings[] = $message;
        }
        return false;
    }
);
ob_start();
$widget->widget( array( 'before_widget' => '<aside>', 'after_widget' => '</aside>' ),
    array( 'type' => 'yearly', 'format' => 'option', 'limit' => 3 ) );
$widget_option_html = ob_get_clean();
restore_error_handler();
$dropdown_registration = wp_scripts()->registered['chronoarchive-dropdown'] ?? null;
$assert( 'Classic widget dropdown registers plugin-versioned JavaScript',
    $dropdown_registration &&
    WP_Plugin_ChronoArchive::VERSION === $dropdown_registration->ver );
$assert( 'Classic widget does not access an undefined version property',
    array() === $widget_version_warnings );
$assert( 'Classic widget emits accessible archive select',
    false !== strpos( $widget_option_html, '<select' ) &&
    false !== strpos( $widget_option_html, 'screen-reader-text' ) );

// Each shortcode and widget dropdown must have a unique ID in one page,
// with a matching accessible label, even with repeated content.
$two_dropdowns = array(
    do_shortcode( '[chronoarchive type="yearly" format="option" limit="2"]' ),
    do_shortcode( '[archives type="monthly" format="option" limit="2"]' ),
    $widget_option_html,
);
$dropdown_ids = array();
$dropdowns_have_labels = true;
foreach ( $two_dropdowns as $dropdown_html ) {
    if ( preg_match( '/<select\b[^>]*\bid="([^"]+)"/', $dropdown_html, $matches ) ) {
        $dropdown_ids[] = $matches[1];
        if ( false === strpos( $dropdown_html, 'for="' . $matches[1] . '"' ) ) {
            $dropdowns_have_labels = false;
        }
    } else {
        $dropdowns_have_labels = false;
    }
}
$assert( 'Multiple widgets and shortcodes use unique select IDs',
    3 === count( $dropdown_ids ) && 3 === count( array_unique( $dropdown_ids ) ) );
$assert( 'Each archive select has its own matching accessible label',
    $dropdowns_have_labels );

// WordPress's edit_css meta capability maps to unfiltered_html and is
// restricted for multisite site administrators. Exercise both capability
// paths using a temporary mapping filter on disposable CI only.
$css_test_user = get_current_user_id();
wp_set_current_user( 1 );
$assert( 'Test administrator has edit_css privilege on a single site',
    current_user_can( 'edit_css' ) );
$approved_by_admin = $plugin_under_test->sanitize_settings( array(
    'custom_css' => '.approved{color:blue}</style><script>bad</script>',
    'use_cpt_archive' => '1',
) );
$assert( 'Authorized CSS settings receive explicit approval',
    '1' === $approved_by_admin['custom_css_approved'] &&
    false === strpos( $approved_by_admin['custom_css'], '<' ) );

// Emulate the WP multisite subsite-admin permission boundary without
// actually changing the user roles or network configuration.
$deny_edit_css = static function ( $caps, $cap ) {
    return 'edit_css' === $cap ? array( 'do_not_allow' ) : $caps;
};
update_option( 'WP_ChronoArchive_options', array(
    'custom_css' => '.old{color:green}',
    'use_cpt_archive' => '',
) );
add_filter( 'map_meta_cap', $deny_edit_css, 10, 2 );
$assert( 'Site administrator may manage options without edit_css',
    current_user_can( 'manage_options' ) && ! current_user_can( 'edit_css' ) );
$blocked_css = $plugin_under_test->sanitize_settings( array(
    'custom_css' => '.attacker{background:url(https://example.test/track)}',
    'custom_css_approved' => '1',
    'use_cpt_archive' => '1',
) );
$assert( 'Unprivileged CSS write and self-approval refused',
    '.old{color:green}' === $blocked_css['custom_css'] &&
    '' === $blocked_css['custom_css_approved'] &&
    '1' === $blocked_css['use_cpt_archive'] );
ob_start();
$plugin_under_test->options_page();
$read_only_css_admin = ob_get_clean();
$assert( 'Restricted administrator sees disabled CSS editor',
    false !== strpos( $read_only_css_admin, 'chronoarchive-custom-css' ) &&
    false !== strpos( $read_only_css_admin, 'disabled=' ) );

update_option( 'WP_ChronoArchive_options', $approved_by_admin );
$unchanged_css = $plugin_under_test->sanitize_settings( array(
    'custom_css' => '.overwrite{color:red}',
    'use_cpt_archive' => '',
) );
$assert( 'Non-edit_css save preserves previously approved CSS',
    $unchanged_css['custom_css'] === $approved_by_admin['custom_css'] &&
    '1' === $unchanged_css['custom_css_approved'] );
remove_filter( 'map_meta_cap', $deny_edit_css, 10 );
delete_option( 'WP_ChronoArchive_options' );
wp_set_current_user( $css_test_user );

// Global settings must apply to omitted attributes on all three shortcode
// aliases while each explicit shortcode attribute remains authoritative.
$validated_defaults = $plugin_under_test->sanitize_settings( array(
    'custom_css' => '',
    'default_type' => 'monthly',
    'default_format' => 'option',
    'default_order' => 'ASC',
    'default_limit' => '3',
    'default_show_post_count' => '1',
    'default_select_text' => 'Browse months',
) );
$assert( 'Admin default choices are validated and persisted',
    'monthly' === $validated_defaults['default_type'] &&
    'option' === $validated_defaults['default_format'] &&
    'ASC' === $validated_defaults['default_order'] &&
    '3' === $validated_defaults['default_limit'] &&
    '1' === $validated_defaults['default_show_post_count'] &&
    'Browse months' === $validated_defaults['default_select_text'] );
update_option( 'WP_ChronoArchive_options', $validated_defaults );
$plugin_under_test->_set_options();
foreach ( array( 'archives', 'arcpromat', 'chronoarchive' ) as $shortcode_alias ) {
    $default_output = do_shortcode( '[' . $shortcode_alias . ']' );
    $assert( 'Global dropdown defaults apply to ' . $shortcode_alias,
        false !== strpos( $default_output, '<select' ) &&
        false !== strpos( $default_output, '>Browse months</option>' ) &&
        3 === substr_count( $default_output, '<option value=' ) - 1 );
}
$override_output = do_shortcode( '[chronoarchive type="yearly" format="html" order="DESC" show_post_count="0" limit="2"]' );
$assert( 'Explicit shortcode attributes override global defaults',
    false === strpos( $override_output, '<select' ) &&
    2 === substr_count( $override_output, '<li>' ) &&
    false !== strpos( $override_output, '2026' ) &&
    false === strpos( $override_output, '&nbsp;(' ) );
$bad_defaults = $plugin_under_test->sanitize_settings( array(
    'default_type' => 'script',
    'default_format' => 'iframe',
    'default_order' => 'DROP TABLE',
    'default_limit' => '1 UNION SELECT',
    'default_show_post_count' => '',
    'default_select_text' => '<svg onload=alert(1)>',
) );
$assert( 'Malicious shortcode defaults fail closed',
    'yearly' === $bad_defaults['default_type'] &&
    'html' === $bad_defaults['default_format'] &&
    'DESC' === $bad_defaults['default_order'] &&
    '' === $bad_defaults['default_limit'] &&
    '' === $bad_defaults['default_show_post_count'] &&
    false === stripos( $bad_defaults['default_select_text'], '<svg' ) );
delete_option( 'WP_ChronoArchive_options' );
$plugin_under_test->_set_options();

// Render administrator settings with a malicious historic textarea value.
// WordPress esc_textarea() must keep the payload inside the textarea.
update_option( 'WP_ChronoArchive_options', array(
    'custom_css' => '</textarea><script>alert(1)</script>',
    'use_cpt_archive' => '1',
) );
$old_user = get_current_user_id();
wp_set_current_user( 1 );
ob_start();
$plugin_under_test->options_page();
$admin_html = ob_get_clean();
$assert( 'Admin settings screen renders', false !== strpos( $admin_html, 'chronoarchive-custom-css' ) );
$assert( 'Admin dashboard includes generator and shortcode examples',
    false !== strpos( $admin_html, 'chronoarchive-builder' ) &&
    false !== strpos( $admin_html, 'chronoarchive-generated' ) &&
    false !== strpos( $admin_html, '[chronoarchive type=&quot;monthly&quot; format=&quot;option&quot;]' ) &&
    6 === substr_count( $admin_html, 'class="ca-preset"' ) );
$assert( 'Global defaults form and CSS controls are accessible',
    false !== strpos( $admin_html, '[default_type]' ) &&
    false !== strpos( $admin_html, '[default_format]' ) &&
    false !== strpos( $admin_html, '[default_order]' ) &&
    false !== strpos( $admin_html, '[default_limit]' ) &&
    false !== strpos( $admin_html, '[default_show_post_count]' ) &&
    false !== strpos( $admin_html, '[default_select_text]' ) &&
    false !== strpos( $admin_html, '<details class="ca-advanced">' ) &&
    1 === substr_count( $admin_html, '<form ' ) );


$assert( 'Settings screen has no redundant fork or licensing footer',
    false === stripos( $admin_html, 'Fork of Annual Archive' ) &&
    false === stripos( $admin_html, 'distributed under the GPL' ) &&
    0 === preg_match( '/ChronoArchive [0-9]+\.[0-9]+\.[0-9]+-beta[0-9]+\./', $admin_html ) );
$assert( 'Admin textarea closes only once', 1 === substr_count( $admin_html, '</textarea>' ) );
$assert( 'Admin saved CSS cannot inject script', false === stripos( $admin_html, '<script>' ) );
$assert( 'Admin settings form includes WordPress nonce', false !== strpos( $admin_html, '_wpnonce' ) );
delete_option( 'WP_ChronoArchive_options' );
wp_set_current_user( 0 );
ob_start();
$plugin_under_test->options_page();
$guest_page = ob_get_clean();
$assert( 'Settings page is not shown without capability', '' === $guest_page );
wp_set_current_user( $old_user );

$editor_id = wp_insert_user( array(
    'user_login' => 'ca-ci-contributor',
    'user_pass' => wp_generate_password( 24 ),
    'user_email' => 'contributor@example.test',
    'role' => 'contributor',
) );
$assert( 'Contributor fixture created', ! is_wp_error( $editor_id ) );
if ( ! is_wp_error( $editor_id ) ) {
    wp_set_current_user( $editor_id );
    $contributor_html = do_shortcode(
        '[chronoarchive type="yearly" limit="2" before="<span onclick=alert(1)>Test</span>" after="<img src=x onerror=alert(1)>"]'
    );
    $assert( 'Contributor shortcode strips scriptable event handlers',
        false === stripos( $contributor_html, 'onclick=' ) &&
        false === stripos( $contributor_html, 'onerror=' ) );
    // CVE-2023-0178 requires only contributor access on the original plugin.
    // Check its published payload with the actual contributor identity and
    // all historic aliases; no permission-dependent rendering bypass allowed.
    foreach ( array( 'archives', 'arcpromat', 'chronoarchive' ) as $contributor_alias ) {
        $contributor_poc = '[' . $contributor_alias . ' tag=\'ul onmouseover="alert(1)"\' limit=\'2\']';
        $contributor_output = do_shortcode( $contributor_poc );
        $assert( 'Contributor role cannot inject shortcode tag attribute (' . $contributor_alias . ')',
            0 === strpos( trim( $contributor_output ), '<ul>' ) &&
            false !== strpos( $contributor_output, '<a ' ) &&
            false === stripos( $contributor_output, 'onmouseover=' ) &&
            false === stripos( $contributor_output, 'alert(1)' ) );
    }
    ob_start();
    $plugin_under_test->options_page();
    $contrib_admin_html = ob_get_clean();
    $assert( 'Contributor cannot access options page', '' === $contrib_admin_html );
    wp_set_current_user( $old_user );
}


// Regressions for the documented Annual Archive XSS advisory *classes*.
// These are additional controls for the fork, NOT proof that the advisory's
// exact original exploit paths have been exhaustively reproduced.
$cve_select = do_shortcode(
    '[chronoarchive type="yearly" format="option" limit="2" select_text="<img src=x onerror=alert(1)>"]'
);
$assert( 'CVE class: dropdown text never creates active image HTML',
    1 === substr_count( $cve_select, '<select' ) &&
    false === stripos( $cve_select, '<img' ) &&
    false === stripos( $cve_select, 'onerror=' ) );
$assert( 'CVE class: malicious dropdown keeps accessible text label',
    false !== strpos( $cve_select, 'screen-reader-text' ) &&
    false !== strpos( $cve_select, '<option value="">' ) );

$cve_direct = WP_Plugin_ChronoArchive::wp_get_archives_advanced( array(
    'type'   => 'yearly',
    'limit'  => 2,
    'echo'   => 0,
    'before' => '<span onclick="alert(1)">Start</span>',
    'after'  => '<a href="javascript:alert(1)">End</a>',
) );
$assert( 'CVE class: direct API output excludes HTML event handlers',
    is_string( $cve_direct ) && false !== strpos( $cve_direct, '<a ' ) &&
    false === stripos( $cve_direct, 'onclick=' ) );
$assert( 'CVE class: direct API output excludes javascript URL schemes',
    false === stripos( $cve_direct, 'javascript:' ) );

$cve_widget = $widget->update( array(
    'title'       => '"><img src=x onerror=alert(1)>',
    'type'        => 'yearly',
    'format'      => 'option',
    'select_text' => '</option><svg onload=alert(1)>',
    'before'      => '<span onclick=alert(1)>Before</span>',
    'after'       => '<img src=x onerror=alert(1)>',
), array() );
ob_start();
$widget->form( $cve_widget );
$cve_form = ob_get_clean();
$assert( 'CVE class: saved widget form escapes HTML injections',
    false === stripos( $cve_form, '<img' ) &&
    false === stripos( $cve_form, '<svg' ) &&
    false === stripos( $cve_form, 'onerror=' ) &&
    false === stripos( $cve_form, 'onload=' ) );
ob_start();
$widget->widget( array(
    'before_widget' => '<aside>', 'after_widget' => '</aside>',
    'before_title' => '<h3>', 'after_title' => '</h3>',
), $cve_widget );
$cve_widget_render = ob_get_clean();
$assert( 'CVE class: saved widget render neutralizes executable tags',
    false !== strpos( $cve_widget_render, '<select' ) &&
    false === stripos( $cve_widget_render, '<img' ) &&
    false === stripos( $cve_widget_render, '<svg' ) &&
    false === stripos( $cve_widget_render, '<script' ) &&
    false === stripos( $cve_widget_render, 'onclick=' ) &&
    false === stripos( $cve_widget_render, 'onerror=' ) );

// Annual Archive 1.6.0 persisted widget options with an unsafe array merge.
// During an upgrade, existing database values can bypass our new update()
// sanitizer. Assert the direct form and public render sinks are still safe.
$legacy_raw_instance = array(
    'title'       => '"><img id="legacy-title-xss" src=x onerror=alert(1)>',
    'format'      => 'option',
    'type'        => 'yearly',
    'select_text' => '</option><svg id="legacy-select-xss" onload=alert(1)>',
    'before'      => '<script id="legacy-before-xss">alert(1)</script>',
    'after'       => '<img id="legacy-after-xss" src=x onerror=alert(1)>',
    'limit'       => '2',
);
ob_start();
$widget->form( $legacy_raw_instance );
$legacy_form_html = ob_get_clean();
// The harmless *text* "onerror=" may still appear inside an HTML-escaped
// input value; assert no active tags. Treating that text as a live event
// attribute would produce a false positive in string-based scanning.
$assert( 'Annual Archive 1.6.0 raw saved widget never inserts active admin tags',
    false === stripos( $legacy_form_html, '<img' ) &&
    false === stripos( $legacy_form_html, '<svg' ) &&
    false === stripos( $legacy_form_html, '<script' ) );
foreach ( array( 'option', 'html' ) as $legacy_format ) {
    $legacy_raw_instance['format'] = $legacy_format;
    ob_start();
    $widget->widget( array(
        'before_widget' => '<aside>', 'after_widget' => '</aside>',
        'before_title' => '<h3>', 'after_title' => '</h3>',
    ), $legacy_raw_instance );
    $legacy_render_html = ob_get_clean();
    $assert( 'Annual Archive 1.6.0 raw widget values safe in ' . $legacy_format,
        false !== strpos( $legacy_render_html, '<a ' ) ||
        'option' === $legacy_format && false !== strpos( $legacy_render_html, '<select' ) );
    $assert( 'Annual Archive 1.6.0 raw widget has no executable nodes in ' . $legacy_format,
        false === stripos( $legacy_render_html, '<script' ) &&
        false === stripos( $legacy_render_html, '<svg' ) &&
        0 === preg_match( '/<[^>]+\son(?:load|error|click|mouseover)\s*=/i', $legacy_render_html ) &&
        false === stripos( $legacy_render_html, 'href="javascript:' ) );
}

register_post_type( 'ca_qa_public', array( 'public' => true, 'has_archive' => false ) );
$cpt_id = wp_insert_post( array(
    'post_type' => 'ca_qa_public', 'post_status' => 'publish',
    'post_date' => '2020-06-15 12:00:00', 'post_title' => 'CA-CI Public CPT',
), true );
$assert( 'Public CPT fixture created', ! is_wp_error( $cpt_id ) );
if ( ! is_wp_error( $cpt_id ) ) {
    $fixture_ids[] = $cpt_id;
    $cpt_html = do_shortcode( '[chronoarchive type="yearly" post_type="ca_qa_public" use_cpt_archive="1"]' );
    $assert( 'CPT without archive gets safe fallback URL',
        false !== strpos( $cpt_html, 'post_type=ca_qa_public' ) );
}
unregister_post_type( 'ca_qa_public' );

foreach ( $fixture_ids as $id ) {
    wp_delete_post( $id, true );
}

echo "\nWordPress integration: {$passed} passed, {$failed} failed.\n";
exit( $failed ? 1 : 0 );
