<?php
/**
 * Read-only smoke tests, run from an isolated, populated WP test site:
 * wp eval-file scripts/qa-wordpress.php
 *
 * This is not an official Plugin Check run or a security audit.
 */
if ( ! defined( 'ABSPATH' ) ) {
    fwrite( STDERR, "Requires bootstrapped WordPress.\n" );
    exit( 1 );
}

$pass = 0;
$fail = 0;
$test = static function ( $name, $ok ) use ( &$pass, &$fail ) {
    if ( $ok ) {
        ++$pass;
        echo "PASS: {$name}\n";
    } else {
        ++$fail;
        echo "FAIL: {$name}\n";
    }
};

foreach ( array( 'chronoarchive', 'archives', 'arcpromat' ) as $name ) {
    $test( "Shortcode registered: {$name}", shortcode_exists( $name ) );
}

foreach ( array( 'yearly', 'monthly', 'weekly', 'daily', 'decade', 'alpha', 'postbypost' ) as $type ) {
    $output = do_shortcode( '[chronoarchive type="' . $type . '" limit="3"]' );
    $test( "{$type}: renders", is_string( $output ) && '' !== $output );
    $test( "{$type}: no script tag", false === stripos( $output, '<script' ) );
}

$bad_tag = do_shortcode( '[chronoarchive type="yearly" limit="2" tag="script"]' );
$test( 'Invalid tag sanitized', 0 === strpos( trim( $bad_tag ), '<ul>' ) );

$bad_type = do_shortcode( '[chronoarchive type="yearly" post_type="nonexistent_qa_type"]' );
$test( 'Unknown post type yields no links', false === strpos( $bad_type, '<a ' ) );

$select = do_shortcode( '[chronoarchive type="yearly" format="option" limit="2"]' );
$test( 'Dropdown has label', false !== strpos( $select, '<label' ) );
$test( 'Dropdown avoids inline onchange', false === stripos( $select, 'onchange=' ) );

$unsafe = do_shortcode( '[chronoarchive type="yearly" before="<span onclick=alert(1)>Before</span>" limit="2"]' );
$test( 'Unsafe event attributes stripped', false === stripos( $unsafe, 'onclick=' ) );

printf( "\nCompleted: %d passed, %d failed.\n", $pass, $fail );
if ( $fail ) {
    exit( 1 );
}
