<?php
/**
 * Disposable CI-only PHP server router.
 *
 * Unlike routing every request to index.php, returning false for a real
 * file permits the built-in server to serve JS/CSS with correct MIME types.
 * Keep the path canonicalized inside the isolated WordPress docroot.
 */
if ( 'cli-server' !== PHP_SAPI ) {
	http_response_code( 403 );
	exit;
}

$root = realpath( $_SERVER['DOCUMENT_ROOT'] );
$path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
$file = $root && is_string( $path ) ? realpath( $root . $path ) : false;
if (
	false !== $file &&
	is_file( $file ) &&
	0 === strpos( $file, $root . DIRECTORY_SEPARATOR )
) {
	return false;
}

require $root . '/index.php';
