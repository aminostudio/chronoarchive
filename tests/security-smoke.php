<?php
/**
 * Narrow security-contract tests using WordPress API stubs.
 *
 * Not a substitute for real WordPress integration tests or Plugin Check.
 * These intentionally make get_archives_link() leave link labels unescaped,
 * mirroring core WordPress, so ChronoArchive must escape at its own boundary.
 */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ . '/../' );

class WP_Widget {
    public $id_base = 'chronoarchive_widget';
    public function __construct( $id = '', $name = '', $options = array() ) {}
    public function get_field_id( $field ) { return 'mock-' . $field; }
    public function get_field_name( $field ) { return 'mock[' . $field . ']'; }
}
class QA_DB {
    public $posts = 'wp_posts';
    public $queries = array();
    public function prepare( $sql, $post_type ) {
        return str_replace( '%s', "'" . addslashes( $post_type ) . "'", $sql );
    }
    public function get_results( $sql ) {
        $this->queries[] = $sql;
        if ( false !== strpos( $sql, 'ORDER BY post_title' ) ||
            false !== strpos( $sql, 'SELECT ID, post_title, post_date' ) ) {
            return array( (object) array(
                'ID' => 101, 'post_title' => 'Story <svg onload=alert(1)> & company',
                'post_date' => '2026-11-01 10:00:00',
            ) );
        }
        return array( (object) array(
            'year' => 2026, 'posts' => 2,
            'month' => 11, 'dayofmonth' => 1,
            'decade' => 2020, 'end_year' => 2029,
            'week_start' => '2026-10-26',
        ) );
    }
}
class QA_Locale {
    public function get_month( $num ) { return 'November'; }
}
class QA_Query {
    public $vars;
    public $archive;
    public function __construct( $vars = array(), $archive = true ) {
        $this->vars = $vars;
        $this->archive = $archive;
    }
    public function is_main_query() { return true; }
    public function is_archive() { return $this->archive; }
    public function get( $key ) { return isset( $this->vars[ $key ] ) ? $this->vars[ $key ] : null; }
    public function set( $key, $value ) { $this->vars[ $key ] = $value; }
}
$wpdb = new QA_DB();
$wp_locale = new QA_Locale();
$registered = array();
function add_action() {}
function add_filter() {}
function register_widget() {}
function add_shortcode( $name, $callback ) { $GLOBALS['registered'][ $name ] = $callback; }
function plugin_basename( $path ) { return basename( $path ); }
function get_option( $name, $default = false ) {
    if ( 'start_of_week' === $name ) { return 1; }
    if ( 'date_format' === $name ) { return 'Y-m-d'; }
    return $default;
}
function wp_parse_args( $arguments, $defaults = array() ) {
    return array_merge( $defaults, is_array( $arguments ) ? $arguments : array() );
}
function shortcode_atts( $defaults, $attributes, $tag = '' ) {
    return array_merge( $defaults, array_intersect_key( $attributes, $defaults ) );
}
function sanitize_key( $text ) {
    return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $text ) );
}
function absint( $value ) { return abs( (int) $value ); }
function sanitize_text_field( $text ) { return strip_tags( (string) $text ); }
function wp_strip_all_tags( $text ) { return strip_tags( (string) $text ); }
function wp_kses_post( $text ) {
    $text = preg_replace( '/<\/?(?:script|svg|img)[^>]*>/i', '', (string) $text );
    return preg_replace( '/\s+on[a-z]+\s*=\s*(?:"[^"]*"|\x27[^\x27]*\x27|[^\s>]+)/i', '', $text );
}
function wp_kses_allowed_html( $context ) { return array( 'a' => array( 'href' => true ), 'li' => array(), 'ul' => array() ); }
function wp_kses( $text, $allowed_html ) { return $text; } // Stub: only real-WP tests validate final HTML filtering.
function esc_html( $value ) {
    return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false );
}
function esc_attr( $value ) { return esc_html( $value ); }
function esc_textarea( $value ) { return esc_html( $value ); }
function settings_fields( $group ) { echo '<input type="hidden" name="option_page" value="' . esc_attr( $group ) . '">'; }
function submit_button() { echo '<input type="submit" value="Save Changes">'; }
function __( $text, $domain = '' ) { return $text; }
function _e( $text, $domain = '' ) { echo $text; }
function esc_html_e( $text, $domain = '' ) { echo esc_html( $text ); }
function checked( $actual, $expected = true, $echo = true ) { return $actual == $expected ? 'checked' : ''; }
function selected( $actual, $expected = true, $echo = true ) { return $actual == $expected ? 'selected' : ''; }
function current_user_can( $cap ) { return true; }
function is_admin() { return false; }
function wp_timezone() { return new DateTimeZone( 'UTC' ); }
function get_query_var( $key ) { return ''; }
function wp_date( $format, $time ) { return gmdate( $format, $time ); }
function get_post_type_object( $type ) { return 'post' === $type ? (object) array( 'name' => 'post' ) : null; }
function is_post_type_viewable( $object ) { return is_object( $object ); }
function wp_cache_get_last_changed( $group ) { return 'one'; }
function wp_cache_get( $key, $group ) { return false; }
function wp_cache_set( $key, $value, $group ) { return true; }
function apply_filters( $tag, $value ) { return $value; }
function get_year_link( $year ) { return 'https://example.org/' . (int) $year . '/'; }
function get_month_link( $year, $month ) { return get_year_link( $year ) . (int) $month . '/'; }
function get_day_link( $year, $month, $day ) { return get_month_link( $year, $month ) . (int) $day . '/'; }
function get_permalink( $id ) { return 'https://example.org/post/' . (int) $id; }
function get_post_type_archive_link( $type ) { return false; }
function add_query_arg( $key, $value = null, $url = null ) { return is_array( $key ) ? (string) $value : (string) $url; }
function wp_unique_id( $prefix = '' ) { return $prefix . 'qa-1'; }
function plugins_url( $path, $source = '' ) { return 'https://example.org/' . $path; }
function wp_enqueue_script() {}
function get_post_types() { return array( 'post' ); }
function get_archives_link( $url, $text, $format = 'html', $before = '', $after = '' ) {
    // Matches WordPress's escape boundary: URL escaped; TEXT is not escaped.
    $url = htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' );
    if ( 'option' === $format ) {
        return '<option value="' . $url . '">' . $before . $text . $after . '</option>';
    }
    return '<li>' . $before . '<a href="' . $url . '">' . $text . '</a>' . $after . '</li>';
}

require_once dirname( __DIR__ ) . '/chronoarchive.php';
$fail = 0;
$pass = 0;
$check = static function ( $label, $assert ) use ( &$fail, &$pass ) {
    if ( $assert ) {
        ++$pass;
        echo "PASS: {$label}\n";
    } else {
        ++$fail;
        echo "FAIL: {$label}\n";
    }
};
$plugin = $GLOBALS['WP_Plugin_ChronoArchive'];
$check( 'Shortcodes registered', isset( $GLOBALS['registered']['chronoarchive'] ) &&
    isset( $GLOBALS['registered']['archives'] ) && isset( $GLOBALS['registered']['arcpromat'] ) );
$out = $plugin->shortcode( array( 'type' => 'yearly', 'tag' => 'script', 'limit' => '1' ) );
$check( 'Unsafe shortcode tag falls back', 0 === strpos( $out, '<ul>' ) && false === stripos( $out, '<script' ) );
$check( 'Unexpected shortcode object is harmless', is_string( $plugin->shortcode( (object) array( 'type' => 'yearly' ) ) ) );
$out = $plugin->shortcode( array( 'type' => array( 'invalid' ), 'limit' => array( 1 ),
    'before' => array( 'bad' ), 'tag' => array( 'script' ) ) );
$check( 'Malformed shortcode arrays do not trigger PHP type errors', is_string( $out ) && false === stripos( $out, '<script' ) );
$out = WP_Plugin_ChronoArchive::wp_get_archives_advanced( array(
    'type' => 'postbypost', 'echo' => 0, 'post_type' => 'post',
) );
$check( 'Post title HTML is not rendered', false === strpos( $out, '<svg' ) &&
    false !== strpos( $out, 'Story' ) && false !== strpos( $out, '&amp; company' ) );
$out = WP_Plugin_ChronoArchive::wp_get_archives_advanced( array(
    'type' => 'yearly', 'echo' => 0,
    'format' => array( 'bad' ), 'before' => array( 'bad' ), 'order' => "ASC; DROP TABLE posts",
    'post_type' => 'post',
) );
$check( 'Direct API rejects malformed arguments', false !== strpos( $out, '<li>' ) &&
    false === strpos( end( $wpdb->queries ), 'DROP TABLE' ) );
$out = WP_Plugin_ChronoArchive::wp_get_archives_advanced( array(
    'type' => 'yearly', 'echo' => 0, 'format' => 'option',
    'before' => '<span>Plain</span>', 'after' => '<img src=x onerror=alert(1)>',
) );
$check( 'Select option context does not contain user markup', false !== strpos( $out, 'Plain' ) &&
    false === strpos( $out, '<span>' ) && false === strpos( $out, '<img' ) );
$out = WP_Plugin_ChronoArchive::wp_get_archives_advanced( array(
    'post_type' => 'private_custom', 'echo' => 0,
) );
$check( 'Non-viewable post type cannot be listed', empty( $out ) );
$widget = new ChronoArchive_Widget();
$updated = $widget->update( array( 'title' => array( 'wrong' ), 'before' => array( 'bad' ),
    'type' => array( 'bad' ), 'post_type' => array( 'private' ) ), array() );
$check( 'Legacy widget rejects malformed update values',
    '' === $updated['title'] && '' === $updated['before'] && 'yearly' === $updated['type'] );
ob_start();
$widget->form( array( 'title' => array( 'bad' ), 'type' => array( 'bad' ),
    'select_text' => '<svg onload=alert(1)>' ) );
$form_html = ob_get_clean();
$check( 'Legacy form handles malformed stored settings',
    false === strpos( $form_html, '<svg' ) && false === strpos( $form_html, 'onload=' ) );
ob_start();
$widget->widget( array( 'before_widget' => '<div>', 'after_widget' => '</div>' ),
    array( 'title' => '<img src=x onerror=alert(1)>', 'format' => 'html' ) );
$widget_html = ob_get_clean();
$check( 'Widget title escaped', false === strpos( $widget_html, '<img' ) &&
    false !== strpos( $widget_html, '&lt;img' ) );
$q = new QA_Query( array( 'decade' => '2011' ) );
$plugin->ChronoArchive_decade_filter( $q );
$check( 'Malformed decade cannot broaden archive results', isset( $q->vars['post__in'] ) &&
    array( 0 ) === $q->vars['post__in'] );
$q = new QA_Query( array( 'chrono_week' => '2020-01-04' ) );
$plugin->ChronoArchive_decade_filter( $q );
$check( 'Malformed week cannot broaden archive results', isset( $q->vars['post__in'] ) &&
    array( 0 ) === $q->vars['post__in'] );
$q = new QA_Query( array( 'chrono_week' => '2019-12-30', 'year' => 2019 ) );
$plugin->ChronoArchive_decade_filter( $q );
$check( 'Valid cross-year week sets bounded query', isset( $q->vars['date_query'] ) &&
    '2020-01-05' === $q->vars['date_query'][0]['before'] && '' === $q->vars['year'] );
$q = new QA_Query( array( 'chrono_week' => '2019-12-30' ), false );
$plugin->ChronoArchive_decade_filter( $q );
$check( 'Non-archive page query unaffected', ! isset( $q->vars['date_query'] ) );
$out = $plugin->shortcode( array(
    'type' => 'yearly', 'limit' => 1,
    'before' => '<img src=x onerror=alert(1)>',
    'after' => '<svg onload=alert(1)>',
) );
$check( 'Shortcode prefixes and suffixes cannot render event handlers',
    false === strpos( $out, '<img' ) &&
    false === strpos( $out, '<svg' ) && false === strpos( $out, 'onerror=' ) );

ob_start();
$widget->widget( array(), array(
    'type' => 'yearly', 'show_post_count' => '0', 'showcount' => '1',
) );
$legacy_counts = ob_get_clean();
$check( 'Historical showcount alias survives widget hardening',
    false !== strpos( $legacy_counts, '(2)' ) );

$plugin->options['custom_css'] = 'body { color: red; }</style><svg onload=alert(1)>';
ob_start();
$plugin->plugin_head_inject();
$custom_css = ob_get_clean();
$check( 'Stored CSS cannot close a style tag to inject HTML',
    false === strpos( $custom_css, '<svg' ) &&
    false !== strpos( $custom_css, '&lt;/style&gt;' ) );
$plugin->options['custom_css'] = array( 'invalid' );
ob_start();
$plugin->plugin_head_inject();
$invalid_css = ob_get_clean();
$check( 'Malformed stored CSS option produces no output',
    '' === $invalid_css );

$plugin->options['custom_css'] = '</textarea><svg onload=alert(1)>';
ob_start();
$plugin->options_page();
$admin_settings = ob_get_clean();
$check( 'Legacy admin form escapes persisted HTML',
    false !== strpos( $admin_settings, '&lt;/textarea&gt;' ) &&
    false === strpos( $admin_settings, '<svg' ) &&
    false !== strpos( $admin_settings, '[custom_css]' ) &&
    false !== strpos( $admin_settings, '[use_cpt_archive]' ) &&
    false === strpos( $admin_settings, 'pluginoven.com' ) );

ob_start();
$widget->widget( array(
    'before_widget' => '<div class="legacy"><script>alert(1)</script>',
    'after_widget' => '</div>',
), array( 'title' => 'Safe widget' ) );
$wrapper = ob_get_clean();
$check( 'Widget wrappers filter unexpected script tags',
    false === strpos( $wrapper, '<script' ) &&
    false !== strpos( $wrapper, 'Safe widget' ) );

printf( "\nSecurity contracts: %d passed, %d failed.\n", $pass, $fail );
exit( $fail ? 1 : 0 );
