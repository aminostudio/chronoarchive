<?php
/**
 * Genuine WordPress multisite authorization regression, run on disposable CI.
 *
 * Usage:
 * wp eval-file wp-content/plugins/chronoarchive/tests/multisite-security.php
 *
 * @package ChronoArchive
 */
if ( ! defined( 'ABSPATH' ) || ! is_multisite() ) {
    fwrite( STDERR, "ABORT: WordPress multisite required.\n" );
    exit( 2 );
}

if ( 'chronoarchive-multisite-ci' !== get_blog_option( get_main_site_id(), 'blogname' ) ) {
    fwrite( STDERR, "ABORT: site is not isolated CI WordPress.\n" );
    exit( 2 );
}

$passes = 0;
$failures = 0;
$check = static function ( $name, $condition ) use ( &$passes, &$failures ) {
    if ( $condition ) {
        ++$passes;
        echo "PASS: {$name}\n";
    } else {
        ++$failures;
        echo "FAIL: {$name}\n";
    }
};

$sites = get_sites( array( 'number' => 20 ) );
$main_site = get_main_site_id();
$subsite_id = null;
foreach ( $sites as $site ) {
    if ( (int) $site->blog_id !== (int) $main_site ) {
        $subsite_id = (int) $site->blog_id;
        break;
    }
}
$check( 'Secondary site exists', null !== $subsite_id );
if ( null === $subsite_id ) {
    exit( 1 );
}

$super_id = 1;
$check( 'Network super administrator is configured', is_super_admin( $super_id ) );

$subadmin = wp_insert_user( array(
    'user_login' => 'ca_ci_subadmin',
    'user_pass' => wp_generate_password( 36 ),
    'user_email' => 'ca-subadmin@example.test',
    'role' => 'subscriber',
) );
if ( is_wp_error( $subadmin ) ) {
    fwrite( STDERR, "ABORT: cannot create disposable user.\n" );
    exit( 1 );
}
add_user_to_blog( $subsite_id, $subadmin, 'administrator' );
$check( 'Subsite administrator is not super administrator', ! is_super_admin( $subadmin ) );

switch_to_blog( $subsite_id );
wp_set_current_user( $subadmin );
$check( 'Subsite administrator can manage options', current_user_can( 'manage_options' ) );
$check( 'Subsite administrator cannot edit unfiltered CSS', ! current_user_can( 'edit_css' ) );

$legacy_css = '.legacy{color:teal}';
update_option( 'WP_ChronoArchive_options', array(
    'custom_css' => $legacy_css,
    'use_cpt_archive' => '1',
) );
$plugin = new WP_Plugin_ChronoArchive();
$plugin->plugin_enqueue_styles();
$check( 'Legacy unapproved network CSS is not loaded',
    ! wp_style_is( 'chronoarchive-custom', 'enqueued' ) );

$submitted = $plugin->sanitize_settings( array(
    'custom_css' => '.untrusted{background:url(https://other.invalid/path)}',
    'custom_css_approved' => '1',
    'use_cpt_archive' => '',
) );
$check( 'Subsite admin cannot change previously stored CSS',
    $legacy_css === $submitted['custom_css'] );
$check( 'Subsite admin cannot forge CSS approval',
    '' === $submitted['custom_css_approved'] );
$check( 'Non-CSS options remain editable',
    '' === $submitted['use_cpt_archive'] );

ob_start();
$plugin->options_page();
$settings = ob_get_clean();
$check( 'Subsite admin sees CSS setting disabled',
    false !== strpos( $settings, 'chronoarchive-custom-css' ) &&
    false !== strpos( $settings, 'disabled=' ) );

wp_set_current_user( $super_id );
$check( 'Super admin may edit CSS', current_user_can( 'edit_css' ) );
$approved = $plugin->sanitize_settings( array(
    'custom_css' => '.reviewed{color:navy}',
    'use_cpt_archive' => '1',
) );
$check( 'Super admin authorizes reviewed CSS', '1' === $approved['custom_css_approved'] );
update_option( 'WP_ChronoArchive_options', $approved );
$plugin->_set_options();
$plugin->plugin_enqueue_styles();
$css = wp_styles()->get_data( 'chronoarchive-custom', 'after' );
$check( 'Approved network CSS is delivered via the Styles API',
    is_array( $css ) && false !== strpos( implode( "\n", $css ), '.reviewed' ) );

wp_set_current_user( $subadmin );
$forged = $plugin->sanitize_settings( array(
    'custom_css' => '.override{color:red}',
    'custom_css_approved' => '',
    'use_cpt_archive' => '',
) );
$check( 'Later restricted save preserves CSS and approval',
    $approved['custom_css'] === $forged['custom_css'] &&
    '1' === $forged['custom_css_approved'] );

delete_option( 'WP_ChronoArchive_options' );
restore_current_blog();

printf( "\nMultisite authorization: %d passed, %d failed.\n", $passes, $failures );
exit( $failures ? 1 : 0 );
