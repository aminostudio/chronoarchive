<?php
/**
 * Isolated mock regression harness for direct-call and widget input handling.
 * This harness is NOT a substitute for a bootstrapped WordPress integration test.
 *
 * Run: php tests/security-smoke.php
 */
error_reporting( E_ALL );
define( 'ABSPATH', '/' );

$GLOBALS['ca_mock_filters'] = array();
$GLOBALS['ca_mock_options'] = array();
$GLOBALS['ca_fake_query'] = '';

class WP_Widget {
    public function __construct() {}
}
class CA_Fake_DB {
    public $posts = 'wp_posts';

    public function prepare( $sql, $argument ) {
        return str_replace( '%s', "'" . str_replace( "'", "''", $argument ) . "'", $sql );
    }
    public function get_results( $sql ) {
        $GLOBALS['ca_fake_query'] = $sql;
        if ( strpos( $sql, 'GROUP BY YEAR(post_date)' ) !== false ) {
            return array( (object) array( 'year' => 2026, 'posts' => 1 ) );
        }
        return array();
    }
}
$GLOBALS['wpdb'] = new CA_Fake_DB();

function get_option( $name, $default = false ) {
    return isset( $GLOBALS['ca_mock_options'][ $name ] ) ? $GLOBALS['ca_mock_options'][ $name ] : $default;
}
function add_action() {}
function add_filter() {}
function add_shortcode() {}
function __($s) { return $s; }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function apply_filters( $name, $value ) {
    return isset( $GLOBALS['ca_mock_filters'][ $name ] ) ? $GLOBALS['ca_mock_filters'][ $name ] : $value;
}
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, (array) $args ); }
function sanitize_key( $key ) {
    return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) );
}
function wp_kses_post( $text ) {
    $text = preg_replace( '/\s+on[a-z]+\s*=\s*("[^"]*"|\x27[^\x27]*\x27|[^\s>]+)/i', '', (string) $text );
    return strip_tags( $text, '<span><strong><em><a>' );
}
function get_post_type_object( $name ) {
    return 'post' === $name ? (object) array( 'name' => 'post' ) : null;
}
function is_post_type_viewable( $obj ) { return is_object( $obj ); }
function absint( $n ) { return abs( (int) $n ); }
function wp_cache_get_last_changed( $group ) { return 'mock'; }
function wp_cache_get( $key, $group ) { return false; }
function wp_cache_set( $key, $value, $group ) {}
function get_year_link( $year ) { return 'https://example.test/' . (int) $year . '/'; }
function get_archives_link( $url, $text, $format, $before = '', $after = '' ) {
    return '<li>' . $before . '<a href="' . htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' ) .
        '">' . esc_html( $text ) . '</a>' . $after . '</li>';
}
function is_admin() { return false; }

require dirname( __DIR__ ) . '/chronoarchive.php';

$passes = 0;
$fails = 0;
function ca_assert( $name, $condition ) {
    global $passes, $fails;
    if ( $condition ) {
        ++$passes;
        echo "PASS: {$name}\n";
    } else {
        ++$fails;
        echo "FAIL: {$name}\n";
    }
}

$invalid_post_type = WP_Plugin_ChronoArchive::wp_get_archives_advanced(
    array( 'post_type' => 'INVALID<>', 'echo' => 0 )
);
ca_assert( 'Invalid direct post type returns empty string', '' === $invalid_post_type );

$yearly = WP_Plugin_ChronoArchive::wp_get_archives_advanced(
    array(
        'type' => array( 'yearly' ),
        'format' => array( 'custom' ),
        'order' => array( 'DESC' ),
        'limit' => array( 500 ),
        'before' => '<span onclick="alert(1)">Prefix</span>',
        'echo' => 0,
    )
);
ca_assert( 'Malformed direct arguments do not throw', is_string( $yearly ) );
ca_assert( 'Invalid HTML attribute stripped', false === strpos( $yearly, 'onclick=' ) );
ca_assert( 'Yearly fallback renders', false !== strpos( $yearly, '2026' ) );
ca_assert( 'Bad sort falls back to DESC', false !== strpos( $GLOBALS['ca_fake_query'], 'ORDER BY post_date DESC' ) );
ca_assert( 'Bad limit is ignored', false === strpos( $GLOBALS['ca_fake_query'], 'LIMIT' ) );

$GLOBALS['ca_mock_filters']['widget_title'] = array( 'not a scalar' );
$widget = new ChronoArchive_Widget();
ob_start();
$widget->widget(
    array(
        'before_widget' => '<aside>',
        'after_widget' => '</aside>',
        'format' => 'malicious-arg-overwrite',
        'instance' => array( 'title' => 'overridden' ),
    ),
    array( 'type' => 'yearly', 'title' => 'Test', 'limit' => 2 )
);
$widget_html = ob_get_clean();
ca_assert( 'Widget wrapper remains intact', false !== strpos( $widget_html, '<aside>' ) );
ca_assert( 'Widget title filter returning array is not fatal', false === strpos( $widget_html, 'Array' ) );
ca_assert( 'Unexpected widget args do not overwrite state', false !== strpos( $widget_html, '2026' ) );

echo "\nMock security checks: {$passes} passed, {$fails} failed.\n";
exit( $fails ? 1 : 0 );
