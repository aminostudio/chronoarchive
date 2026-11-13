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

$assert( 'CSS invalid object rejected', '' === WP_Plugin_ChronoArchive::safe_custom_css( array( 'bad' ) ) );
$assert( 'Legacy CSS cannot end style tag', false === strpos(
    WP_Plugin_ChronoArchive::safe_custom_css( '.x{} </style><script>bad</script>' ), '<' ) );
$plugin_under_test->options['custom_css'] = '.ci{color:purple} </style><script>x</script>';
$plugin_under_test->plugin_enqueue_styles();
$inline_css = wp_styles()->get_data( 'chronoarchive-custom', 'after' );
$assert( 'Custom CSS enqueued through WP Styles', is_array( $inline_css ) && count( $inline_css ) > 0 );
$assert( 'Enqueued CSS cannot contain raw HTML tag', is_array( $inline_css ) &&
    false === strpos( implode( "\n", $inline_css ), '<' ) );

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
    ob_start();
    $plugin_under_test->options_page();
    $contrib_admin_html = ob_get_clean();
    $assert( 'Contributor cannot access options page', '' === $contrib_admin_html );
    wp_set_current_user( $old_user );
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
