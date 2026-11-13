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
}

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
$select = do_shortcode( '[chronoarchive type="yearly" format="option" limit="2" select_text="Select archive"]' );
$assert( 'Selector has accessible label', false !== strpos( $select, '<label' ) );
$assert( 'No inline onchange', false === stripos( $select, 'onchange=' ) );
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
$GLOBALS['wp_the_query'] = $main_backup;

$query = new WP_Query();
$GLOBALS['wp_the_query'] = $query;
$query->parse_query( array( 'year' => 2025, 'decade' => '2025' ) );
$plugin_under_test->ChronoArchive_decade_filter( $query );
$assert( 'Invalid decade fails closed', array( 0 ) === $query->get( 'post__in' ) );
$GLOBALS['wp_the_query'] = $main_backup;

$assert( 'CSS invalid object rejected', '' === WP_Plugin_ChronoArchive::safe_custom_css( array( 'bad' ) ) );
$assert( 'Legacy CSS cannot end style tag', false === strpos(
    WP_Plugin_ChronoArchive::safe_custom_css( '.x{} </style><script>bad</script>' ), '<' ) );

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

$old_user = get_current_user_id();
wp_set_current_user( 0 );
ob_start();
$plugin_under_test->options_page();
$guest_page = ob_get_clean();
$assert( 'Settings page is not shown without capability', '' === $guest_page );
wp_set_current_user( $old_user );

foreach ( $fixture_ids as $id ) {
    wp_delete_post( $id, true );
}

echo "\nWordPress integration: {$passed} passed, {$failed} failed.\n";
exit( $failed ? 1 : 0 );
