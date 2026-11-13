<?php
/**
 * Ephemeral WordPress integration checks.
 *
 * Execute only inside a disposable WordPress environment:
 *   wp eval-file wp-content/plugins/chronoarchive/tests/wp-security-integration.php
 *
 * Creates four temporary public posts and deletes them in a finally block.
 * Never run on a production site.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit( 1 );
}

$passed = 0;
$failed = 0;
$check = static function ( $title, $good ) use ( &$passed, &$failed ) {
    if ( $good ) {
        ++$passed;
        echo "PASS: {$title}\n";
    } else {
        ++$failed;
        echo "FAIL: {$title}\n";
    }
};

$ids = array();
$fixture_dates = array( '1999-12-31 12:00:00', '2000-01-01 12:00:00',
    '2019-12-31 12:00:00', '2020-01-01 12:00:00' );

try {
    foreach ( $fixture_dates as $index => $date ) {
        $id = wp_insert_post( array(
            'post_type'   => 'post',
            'post_status' => 'publish',
            'post_title'  => 'ChronoArchive CI QA ' . $index . ' & project',
            'post_date'   => $date,
            'post_content' => 'Temporary integration fixture. Deleted after tests.',
        ), true );
        if ( is_wp_error( $id ) || ! $id ) {
            throw new RuntimeException( 'Unable to insert QA fixture for ' . $date );
        }
        $ids[] = (int) $id;
    }

    $check( 'Three shortcode aliases available',
        shortcode_exists( 'chronoarchive' ) &&
        shortcode_exists( 'archives' ) &&
        shortcode_exists( 'arcpromat' ) );

    $html = do_shortcode( '[chronoarchive type="yearly" show_post_count="1"]' );
    $check( 'Yearly archives include historical fixture years',
        false !== strpos( $html, '>1999<' ) &&
        false !== strpos( $html, '>2000<' ) &&
        false !== strpos( $html, '>2019<' ) &&
        false !== strpos( $html, '>2020<' ) );

    $decades = do_shortcode( '[chronoarchive type="decade" show_post_count="1"]' );
    $check( 'Decade archive ranges render',
        false !== strpos( $decades, '1990–1999' ) &&
        false !== strpos( $decades, '2000–2009' ) &&
        false !== strpos( $decades, '2010–2019' ) &&
        false !== strpos( $decades, '2020–2029' ) );

    $weeks = do_shortcode( '[chronoarchive type="weekly" show_post_count="1"]' );
    $check( 'Cross-year weeks have bounded chrono_week links',
        false !== strpos( $weeks, 'chrono_week=2019-12-30' ) &&
        false !== strpos( $weeks, 'chrono_week=1999-12-27' ) );

    $select = do_shortcode( '[chronoarchive type="yearly" format="option"]' );
    $check( 'Accessible dropdown contains no inline event handler',
        false !== strpos( $select, '<label' ) &&
        false !== strpos( $select, '<select' ) &&
        false === stripos( $select, 'onchange=' ) );

    $prefix = do_shortcode( '[chronoarchive type="yearly" limit="2" before="<svg onload=alert(1)>" after="<img src=x onerror=alert(1)>"]' );
    $check( 'WordPress KSES removes disallowed elements and event handlers',
        false === strpos( $prefix, '<svg' ) &&
        false === stripos( $prefix, 'onload=' ) &&
        false === stripos( $prefix, 'onerror=' ) );

    $bad_tag = do_shortcode( '[chronoarchive type="yearly" tag="script" limit="2"]' );
    $check( 'Invalid tag falls back to list', 0 === strpos( trim( $bad_tag ), '<ul>' ) );

    // The core get_archives_link() does not escape display text.
    $posts = WP_Plugin_ChronoArchive::wp_get_archives_advanced( array(
        'type' => 'postbypost', 'limit' => 6, 'echo' => 0,
    ) );
    $check( 'Post title ampersand is escaped',
        false !== strpos( $posts, '&amp; project' ) );

    $no_private = WP_Plugin_ChronoArchive::wp_get_archives_advanced( array(
        'type' => 'yearly', 'post_type' => 'chrono_private_qa', 'echo' => 0,
    ) );
    $check( 'Invalid post types render no archive links', empty( $no_private ) );

    $bad_input = WP_Plugin_ChronoArchive::wp_get_archives_advanced( array(
        'type' => array( 'malformed' ), 'format' => array( 'malformed' ),
        'before' => array( '<script>' ), 'post_type' => 'post',
        'order' => 'DESC; DELETE FROM wp_posts', 'echo' => 0,
    ) );
    $check( 'Malformed direct arguments fail safely', is_string( $bad_input ) &&
        false !== strpos( $bad_input, '<li>' ) &&
        false === strpos( $bad_input, '<script>' ) );

    $widget = new ChronoArchive_Widget();
    $safe_widget = $widget->update( array( 'title' => array( '<svg>' ),
        'before' => array( '<img>' ), 'type' => array( 'daily' ) ), array() );
    $check( 'Malformed stored widget values cannot bypass sanitizer',
        '' === $safe_widget['title'] && '' === $safe_widget['before'] &&
        'yearly' === $safe_widget['type'] );

    ob_start();
    $widget->form( array( 'title' => '<svg onload=alert(1)>', 'select_text' => array( 'bad' ) ) );
    $form = ob_get_clean();
    $check( 'Legacy admin widget fields are escaped',
        false === strpos( $form, '<svg' ) &&
        false === stripos( $form, 'onload=' ) );

    $plugin = ( new ReflectionClass( 'WP_Plugin_ChronoArchive' ) )->newInstanceWithoutConstructor();
    $old_main = $GLOBALS['wp_the_query'];
    $week_query = new WP_Query();
    $week_query->parse_query( array( 'year' => 2020, 'chrono_week' => '2019-12-30' ) );
    $GLOBALS['wp_the_query'] = $week_query;
    try {
        $plugin->ChronoArchive_decade_filter( $week_query );
        $query_dates = $week_query->get( 'date_query' );
        $check( 'Actual WP_Query week spans New Year',
            '' === $week_query->get( 'year' ) &&
            is_array( $query_dates ) && isset( $query_dates[0]['before'] ) &&
            '2020-01-05' === $query_dates[0]['before'] );
    } finally {
        $GLOBALS['wp_the_query'] = $old_main;
    }

    $old_main = $GLOBALS['wp_the_query'];
    $bad_query = new WP_Query();
    $bad_query->parse_query( array( 'year' => 2010, 'decade' => '2011' ) );
    $GLOBALS['wp_the_query'] = $bad_query;
    try {
        $plugin->ChronoArchive_decade_filter( $bad_query );
        $check( 'Actual WP_Query rejects invalid decade',
            array( 0 ) === $bad_query->get( 'post__in' ) );
    } finally {
        $GLOBALS['wp_the_query'] = $old_main;
    }
} finally {
    foreach ( $ids as $id ) {
        wp_delete_post( $id, true );
    }
}

printf( "\nWordPress integration: %d passed, %d failed.\n", $passed, $failed );
if ( $failed > 0 ) {
    exit( 1 );
}
