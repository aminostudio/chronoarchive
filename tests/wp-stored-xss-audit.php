<?php
/**
 * Independent stored-XSS path audit; source-derived, not confidential CVE PoCs.
 * Run with WP-CLI on the disposable chronoarchive-ci site only.
 *
 * @package ChronoArchive
 */
if ( ! defined( 'ABSPATH' ) || 'chronoarchive-ci' !== get_option( 'blogname' ) ) {
    exit( 2 );
}

$audit_passed = 0;
$audit_failed = 0;
$audit_check = static function ( $name, $ok ) use ( &$audit_passed, &$audit_failed ) {
    $ok ? ++$audit_passed : ++$audit_failed;
    echo ( $ok ? 'PASS: ' : 'FAIL: ' ) . $name . "\n";
};
// Parse emitted HTML, rather than mistaking harmless escaped payload text for
// executable markup. This is an HTML-sink check, not a complete browser model.
$audit_safe_dom = static function ( $html ) {
    $doc = new DOMDocument();
    $previous = libxml_use_internal_errors( true );
    $doc->loadHTML( '<!doctype html><html><body>' . $html . '</body></html>' );
    libxml_clear_errors();
    libxml_use_internal_errors( $previous );
    foreach ( $doc->getElementsByTagName( '*' ) as $node ) {
        if ( in_array( strtolower( $node->nodeName ), array( 'script', 'iframe', 'object', 'embed', 'svg', 'math' ), true ) ) {
            return false;
        }
        foreach ( $node->attributes as $attribute ) {
            $name = strtolower( $attribute->name );
            if ( 0 === strpos( $name, 'on' ) || 'srcdoc' === $name ) {
                return false;
            }
            if ( in_array( $name, array( 'href', 'src', 'action', 'value' ), true ) &&
                preg_match( '/^(?:javascript|vbscript|data):/i', preg_replace( '/[\x00-\x20]/', '', $attribute->value ) ) ) {
                return false;
            }
        }
    }
    return true;
};
$audit_check( 'DOM checker rejects an active upstream-style tag sink',
    ! $audit_safe_dom( '<ul onmouseover=alert(1)><li>archive</li></ul>' ) );
$audit_check( 'DOM checker accepts escaped inert payload text',
    $audit_safe_dom( '<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>' ) );

$audit_original_user = get_current_user_id();
$audit_original_options = get_option( 'WP_ChronoArchive_options', null );
$audit_fixture_ids = array();
$audit_user_id = wp_insert_user( array(
    'user_login' => 'ca-audit-' . wp_generate_password( 10, false ),
    'user_pass' => wp_generate_password( 32 ),
    'role' => 'contributor',
) );
if ( is_wp_error( $audit_user_id ) ) {
    throw new RuntimeException( $audit_user_id->get_error_message() );
}
$audit_plugin = new WP_Plugin_ChronoArchive();
$audit_widget = new ChronoArchive_Widget();
try {
    wp_set_current_user( $audit_user_id );
    $audit_check( 'Attacker contributor has neither unfiltered HTML nor settings capabilities',
        ! current_user_can( 'unfiltered_html' ) && ! current_user_can( 'manage_options' ) && ! current_user_can( 'publish_posts' ) );
    foreach ( array( 'archives', 'arcpromat', 'chronoarchive' ) as $alias ) {
        foreach ( array( 'ul onmouseover=alert(1)', 'img/src=x/onerror=alert(1)', 'svg/onload=alert(1)' ) as $tag ) {
            $content = '[' . $alias . ' tag="' . $tag . '" type="yearly" limit="2"]';
            $id = wp_insert_post( array(
                'post_title' => 'Audit contributor shortcode', 'post_type' => 'post',
                'post_status' => 'pending', 'post_author' => $audit_user_id,
                'post_content' => $content,
            ), true );
            if ( is_wp_error( $id ) ) {
                throw new RuntimeException( $id->get_error_message() );
            }
            $audit_fixture_ids[] = $id;
            $stored = get_post_field( 'post_content', $id );
            $audit_check( 'Payload survives contributor storage (' . $alias . '/' . $tag . ')',
                $content === $stored );
            // Publication requires a separate privileged actor; rendering is
            // tested as a guest, not as the contributor who saved the input.
            wp_set_current_user( 1 );
            wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
            wp_set_current_user( 0 );
            $html = do_shortcode( get_post_field( 'post_content', $id ) );
            $audit_check( 'Stored payload is inert for guest (' . $alias . '/' . $tag . ')',
                0 === strpos( trim( $html ), '<ul>' ) && false !== strpos( $html, '<a ' ) && $audit_safe_dom( $html ) );
            wp_set_current_user( $audit_user_id );
        }
        foreach ( array( 'html', 'option', 'link', 'custom' ) as $format ) {
            $html = do_shortcode( '[' . $alias . ' format="' . $format . '" limit="2"'
                . ' before=\'<img src=x onerror=alert(1)>\' after=\'<svg onload=alert(1)>\''
                . ' select_text=\'</option></select><script>alert(1)</script>\']' );
            $audit_check( 'All shortcode HTML inputs are inert (' . $alias . '/' . $format . ')',
                '' !== $html && $audit_safe_dom( $html ) );
        }
    }
    wp_set_current_user( 1 );
    // Emulate unsanitized values inherited from a predecessor installation.
    // Do not call update(): late output defenses must work independently.
    $legacy_instance = array(
        'title' => '"><svg onload=alert(1)>', 'type' => 'yearly', 'limit' => 2,
        'select_text' => '</option></select><script>alert(1)</script>',
        'before' => '<img src=x onerror=alert(1)>',
        'after' => '<a href="javascript:alert(1)">end</a>',
    );
    foreach ( array( 'html', 'option', 'link', 'custom' ) as $format ) {
        $legacy_instance['format'] = $format;
        ob_start();
        $audit_widget->widget( array( 'before_widget' => '<aside>', 'after_widget' => '</aside>' ), $legacy_instance );
        $html = ob_get_clean();
        $audit_check( 'Raw inherited widget is inert (' . $format . ')',
            false !== strpos( $html, '<aside>' ) && $audit_safe_dom( $html ) );
    }
    ob_start();
    $audit_widget->form( $legacy_instance );
    $audit_check( 'Raw inherited widget admin fields are inert', $audit_safe_dom( ob_get_clean() ) );

    foreach ( array(
        '</style><script>alert(1)</script>',
        '</StYlE ><img src=x onerror=alert(1)>',
        '<!--</style><svg/onload=alert(1)>',
        '</textarea><script>alert(1)</script>',
        '&lt;/style&gt;&lt;script&gt;alert(1)&lt;/script&gt;',
        '\\3c /style\\3e \\3c script\\3e alert(1)',
    ) as $index => $css ) {
        update_option( 'WP_ChronoArchive_options', array( 'custom_css' => $css, 'custom_css_approved' => '1' ) );
        $audit_plugin->_set_options();
        $safe_css = WP_Plugin_ChronoArchive::safe_custom_css( $css );
        $audit_check( 'Legacy CSS cannot break out of HTML style (' . $index . ')',
            false === strpos( $safe_css, '<' ) && $audit_safe_dom( '<style>' . $safe_css . '</style>' ) );
        ob_start();
        $audit_plugin->options_page();
        $settings_html = ob_get_clean();
        $audit_check( 'Stored CSS cannot break out of settings textarea (' . $index . ')',
            1 === substr_count( $settings_html, '</textarea>' ) && $audit_safe_dom( $settings_html ) );
    }
    // A restricted single-site administrator models DISALLOW_UNFILTERED_HTML
    // without redefining global constants. Multisite is tested separately.
    wp_set_current_user( $audit_user_id );
    $restricted_admin = new WP_User( $audit_user_id );
    $restricted_admin->set_role( 'administrator' );
    $restricted_admin->add_cap( 'unfiltered_html', false );
    wp_set_current_user( 0 );
    wp_set_current_user( $audit_user_id );
    $audit_check( 'Restricted administrator can manage settings but cannot edit CSS',
        current_user_can( 'manage_options' ) && ! current_user_can( 'edit_css' ) );
    update_option( 'WP_ChronoArchive_options', array( 'custom_css' => '.trusted{color:green}', 'custom_css_approved' => '' ) );
    $saved = $audit_plugin->sanitize_settings( array(
        'custom_css' => '</style><script>alert(1)</script>', 'custom_css_approved' => '1',
    ) );
    $audit_check( 'Restricted administrator cannot overwrite CSS or forge approval',
        '.trusted{color:green}' === $saved['custom_css'] && '' === $saved['custom_css_approved'] );
} finally {
    wp_set_current_user( 1 );
    foreach ( $audit_fixture_ids as $id ) {
        wp_delete_post( $id, true );
    }
    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user( $audit_user_id );
    if ( null === $audit_original_options ) {
        delete_option( 'WP_ChronoArchive_options' );
    } else {
        update_option( 'WP_ChronoArchive_options', $audit_original_options );
    }
    wp_set_current_user( $audit_original_user );
}
echo "Stored-XSS path audit: {$audit_passed} passed, {$audit_failed} failed.\n";
if ( $audit_failed ) {
    exit( 1 );
}
