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
    public $id_base = 'chronoarchive_widget';
    public function __construct() {}
    public function get_field_id( $name ) { return 'ca-test-' . $name; }
    public function get_field_name( $name ) { return 'widget-chronoarchive[' . $name . ']'; }
}
class CA_Fake_Query {
    public $values = array();
    public $archive = false;
    public function __construct( $values, $is_archive ) {
        $this->values = $values;
        $this->archive = $is_archive;
    }
    public function is_main_query() { return true; }
    public function is_archive() { return $this->archive; }
    public function get( $key ) {
        return isset( $this->values[ $key ] ) ? $this->values[ $key ] : null;
    }
    public function set( $key, $value ) { $this->values[ $key ] = $value; }
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

function sanitize_text_field( $value ) { return strip_tags( (string) $value ); }
function get_option( $name, $default = false ) {
    return isset( $GLOBALS['ca_mock_options'][ $name ] ) ? $GLOBALS['ca_mock_options'][ $name ] : $default;
}
function add_action() {}
function add_filter() {}
function add_shortcode() {}
function shortcode_atts( $pairs, $atts, $shortcode = '' ) {
    $out = array();
    foreach ( $pairs as $key => $default ) {
        $out[ $key ] = isset( $atts[ $key ] ) ? $atts[ $key ] : $default;
    }
    return apply_filters( 'shortcode_atts_' . $shortcode, $out );
}
function plugin_basename( $path ) { return basename( $path ); }
function __($s) { return $s; }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_attr($s) { return esc_html( $s ); }
function esc_html_e($s) { echo esc_html( $s ); }
function _e($s) { echo esc_html( $s ); }
function selected( $a, $b, $echo = true ) {
    $text = (string) $a === (string) $b ? ' selected="selected"' : '';
    if ( $echo ) { echo $text; }
    return $text;
}
function checked( $a, $b, $echo = true ) {
    $text = (string) $a === (string) $b ? ' checked="checked"' : '';
    if ( $echo ) { echo $text; }
    return $text;
}
function get_post_types( $args ) { return array( 'post' ); }
function wp_strip_all_tags( $html ) { return strip_tags( (string) $html ); }
function wp_unslash( $text ) { return stripslashes( $text ); }
function wp_register_style( $handle, $src, $deps, $version ) { $GLOBALS['ca_styles']['registered'][] = $handle; }
function wp_enqueue_style( $handle ) { $GLOBALS['ca_styles']['enqueued'][] = $handle; }
function wp_add_inline_style( $handle, $css ) { $GLOBALS['ca_styles']['css'][] = $css; }
function apply_filters( $name, $value ) {
    return isset( $GLOBALS['ca_mock_filters'][ $name ] ) ? $GLOBALS['ca_mock_filters'][ $name ] : $value;
}
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, (array) $args ); }
function sanitize_key( $key ) {
    return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) );
}
function wp_kses_post( $text ) {
    $text = preg_replace( '/\s+on[a-z]+\s*=\s*("[^"]*"|\x27[^\x27]*\x27|[^\s>]+)/i', '', (string) $text );
    return strip_tags( $text, '<span><strong><em><a><aside><section><div><h2><ul><li>' );
}
function wp_kses( $text, $allowed ) {
    $tags = '<' . implode( '><', array_keys( $allowed ) ) . '>';
    return strip_tags( (string) $text, $tags );
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
    return apply_filters( 'get_archives_link', '<li>' . $before .
        '<a href="' . htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' ) .
        '">' . esc_html( $text ) . '</a>' . $after . '</li>' );
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

$home = new CA_Fake_Query( array( 'chrono_week' => '2025-12-29' ), false );
$WP_Plugin_ChronoArchive->ChronoArchive_decade_filter( $home );
ca_assert( 'Week query does not change homepage', ! isset( $home->values['date_query'] ) );

$bad_decade = new CA_Fake_Query( array( 'decade' => '2025' ), true );
$WP_Plugin_ChronoArchive->ChronoArchive_decade_filter( $bad_decade );
ca_assert( 'Invalid decade fails closed', isset( $bad_decade->values['post__in'] ) &&
    array( 0 ) === $bad_decade->values['post__in'] );

// Admin CSS comes from both sanitized and historical untrusted options.
ca_assert( 'CSS helper ignores invalid stored types', '' === WP_Plugin_ChronoArchive::safe_custom_css( array( '</style>' ) ) );
$bad_css = '.test { color: red; } </style><script>alert(1)</script>';
$safe_css = WP_Plugin_ChronoArchive::safe_custom_css( $bad_css );
ca_assert( 'CSS cannot close an HTML style tag', false === stripos( $safe_css, '</style' ) );
ca_assert( 'CSS cannot emit a raw opening HTML tag', false === strpos( $safe_css, '<' ) );

$GLOBALS['ca_mock_options']['WP_ChronoArchive_options'] = array(
    'custom_css' => array( 'malformed' ),
    'use_cpt_archive' => array( 'malformed' ),
);
$WP_Plugin_ChronoArchive->_set_options();
ca_assert( 'Malformed legacy option ignored', '' === $WP_Plugin_ChronoArchive->options['custom_css'] );
$GLOBALS['ca_styles'] = array( 'registered' => array(), 'enqueued' => array(), 'css' => array() );
$WP_Plugin_ChronoArchive->plugin_enqueue_styles();
ca_assert( 'Malformed CSS enqueues no styles', empty( $GLOBALS['ca_styles']['css'] ) );

$GLOBALS['ca_mock_options']['WP_ChronoArchive_options'] = array(
    'custom_css' => '.example{color:purple} </style><script>x</script>',
    'use_cpt_archive' => '1',
);
$WP_Plugin_ChronoArchive->_set_options();
$WP_Plugin_ChronoArchive->plugin_enqueue_styles();
ca_assert( 'Valid legacy CSS uses WP Styles API', count( $GLOBALS['ca_styles']['css'] ) === 1 );
ca_assert( 'Legacy CSS output contains no HTML tag', false === strpos( $GLOBALS['ca_styles']['css'][0], '<' ) );

$broken = array(
    'title' => array( 'invalid' ),
    'type' => array( 'invalid' ),
    'format' => array( 'invalid' ),
    'order' => array( 'invalid' ),
    'post_type' => array( 'invalid' ),
    'before' => array( 'invalid' ),
    'select_text' => array( 'invalid' ),
);
$saved = $widget->update( $broken, array() );
ca_assert( 'Widget update refuses invalid array title', '' === $saved['title'] );
ca_assert( 'Widget update defaults invalid archive type', 'yearly' === $saved['type'] );
ca_assert( 'Widget update defaults invalid format', 'html' === $saved['format'] );
ca_assert( 'Widget update keeps scalar-safe order', 'DESC' === $saved['order'] );
ob_start();
$widget->form( $broken );
$widget_form_html = ob_get_clean();
ca_assert( 'Legacy widget form renders without fatal errors', false !== strpos( $widget_form_html, 'widget-chronoarchive' ) );
ca_assert( 'Widget form does not render array marker', false === strpos( $widget_form_html, 'Array' ) );

$GLOBALS['ca_mock_filters']['widget_before'] = array( 'malformed' );
ob_start();
$widget->widget( array( 'before_widget' => '<aside>' ), array( 'type' => 'yearly', 'before' => '<em>Test</em>' ) );
$filtered_widget_html = ob_get_clean();
ca_assert( 'Malformed wrapper filter is ignored', false === strpos( $filtered_widget_html, 'Array' ) && false !== strpos( $filtered_widget_html, '2026' ) );
unset( $GLOBALS['ca_mock_filters']['widget_before'] );

// A trusted WordPress shortcode_atts filter may unexpectedly return malformed
// data. The plugin must not emit PHP type warnings or unsafe markup.
$GLOBALS['ca_mock_filters']['shortcode_atts_archives'] = array(
    'type' => array( 'yearly' ),
    'limit' => array( 2 ),
    'format' => array( 'html' ),
    'before' => array( '<svg onload=alert(1)>' ),
    'after' => new stdClass(),
    'show_post_count' => array( 'yes' ),
    'showcount' => array( 'yes' ),
    'tag' => array( 'script' ),
    'order' => array( 'DROP' ),
    'alpha_order' => array( 'DESC' ),
    'post_order' => array( 'ASC' ),
    'select_text' => array( '<img onerror=alert(1)>' ),
    'post_type' => array( 'post' ),
    'use_cpt_archive' => array( true ),
);
$warnings = 0;
set_error_handler( function ( $severity, $message ) use ( &$warnings ) {
    ++$warnings;
    throw new ErrorException( $message, 0, $severity );
} );
$render_error = null;
$filtered = '';
try {
    $filtered = $WP_Plugin_ChronoArchive->shortcode( array( 'type' => 'yearly' ) );
} catch ( Throwable $error ) {
    $render_error = $error->getMessage();
}
restore_error_handler();
ca_assert( 'Malformed shortcode filter output does not throw', null === $render_error );
ca_assert( 'Malformed shortcode filter output causes no PHP warnings', 0 === $warnings );
ca_assert( 'Malformed shortcode tag falls back to UL', 0 === strpos( trim( $filtered ), '<ul>' ) );
ca_assert( 'Malformed shortcode filter cannot insert active SVG or script', false === stripos( $filtered, '<svg' ) && false === stripos( $filtered, '<script' ) );
ca_assert( 'Malformed shortcode filter preserves archive listing', false !== strpos( $filtered, '2026' ) );

$GLOBALS['ca_mock_filters']['shortcode_atts_archives'] = new stdClass();
$render_error = null;
$filtered = '';
try {
    $filtered = $WP_Plugin_ChronoArchive->shortcode( array() );
} catch ( Throwable $error ) {
    $render_error = $error->getMessage();
}
ca_assert( 'Non-array shortcode filter return is not fatal', null === $render_error );
ca_assert( 'Non-array shortcode filter return uses safe defaults', false !== strpos( $filtered, '2026' ) );
unset( $GLOBALS['ca_mock_filters']['shortcode_atts_archives'] );

// Ensure direct return-mode archive output is escaped, including the
// get_archives_link filter, not only output echoed directly by the plugin.
$GLOBALS['ca_mock_filters']['get_archives_link'] =
    '<li><a href="https://example.test/">Year</a></li><script>alert(1)</script>';
$returned = WP_Plugin_ChronoArchive::wp_get_archives_advanced(
    array( 'type' => 'yearly', 'echo' => 0, 'limit' => 1 )
);
ca_assert( 'Returned archive links filter executable HTML', false === stripos( $returned, '<script' ) );
ca_assert( 'Returned archive links preserve normal list items', false !== strpos( $returned, '<li>' ) );
$GLOBALS['ca_mock_filters']['get_archives_link'] =
    '<option value="https://example.test/2026/">2026</option><script>alert(1)</script>';
$option_output = WP_Plugin_ChronoArchive::wp_get_archives_advanced(
    array( 'type' => 'yearly', 'format' => 'option', 'echo' => 0, 'limit' => 1 )
);
ca_assert( 'Returned option format rejects executable HTML',
    false !== strpos( $option_output, '<option' ) && false === stripos( $option_output, '<script' ) );
$GLOBALS['ca_mock_filters']['get_archives_link'] =
    '<link rel="archives" href="https://example.test/2026/"><script>alert(1)</script>';
$link_output = WP_Plugin_ChronoArchive::wp_get_archives_advanced(
    array( 'type' => 'yearly', 'format' => 'link', 'echo' => 0, 'limit' => 1 )
);
ca_assert( 'Returned LINK format rejects executable HTML',
    false !== strpos( $link_output, '<link' ) && false === stripos( $link_output, '<script' ) );
unset( $GLOBALS['ca_mock_filters']['get_archives_link'] );

echo "\nMock security checks: {$passes} passed, {$fails} failed.\n";
exit( $fails ? 1 : 0 );
