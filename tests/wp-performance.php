<?php
/**
 * Disposable CI-only archive query-plan benchmark.
 *
 * Runs only under GitHub Actions in /tmp/chrono-wp, never on a real WP site.
 * Creates 3,000 synthetic posts, inspects cold/warm archive SQL and EXPLAIN.
 * No hard duration threshold: runner variability must not create false passes.
 *
 * Usage: wp eval-file .../tests/wp-performance.php --path=/tmp/chrono-wp
 *
 * @package ChronoArchive
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit( 2 );
}
if ( 'true' !== getenv( 'GITHUB_ACTIONS' ) ||
    'chronoarchive-ci' !== get_option( 'blogname' ) ||
    0 !== strpos( wp_normalize_path( ABSPATH ), '/tmp/chrono-wp/' ) ) {
    fwrite( STDERR, "ABORT: benchmark requires disposable GitHub CI WordPress.\n" );
    exit( 2 );
}
if ( ! defined( 'SAVEQUERIES' ) || ! SAVEQUERIES ) {
    fwrite( STDERR, "ABORT: enable SAVEQUERIES in disposable WordPress config.\n" );
    exit( 2 );
}

global $wpdb;
$rows = 3000;
$batch_size = 200;
$inserted = 0;

for ( $base = 0; $base < $rows; $base += $batch_size ) {
    $placeholders = array();
    $args = array();
    for ( $i = $base; $i < min( $rows, $base + $batch_size ); ++$i ) {
        $date = sprintf(
            '%04d-%02d-%02d 12:00:00',
            2004 + ( $i % 21 ),
            1 + ( (int) floor( $i / 21 ) % 12 ),
            1 + ( $i % 27 )
        );
        $placeholders[] = "(%s, %s, %s, %s, %s, 'publish', 'post')";
        array_push( $args, $date, $date, $date, $date, sprintf( 'CA-PERF-%05d', $i ) );
    }
    $sql = 'INSERT INTO ' . $wpdb->posts .
        ' (post_date, post_date_gmt, post_modified, post_modified_gmt, post_title, post_status, post_type) VALUES ' .
        implode( ', ', $placeholders );
    $result = $wpdb->query( $wpdb->prepare( $sql, $args ) );
    if ( false === $result || count( $placeholders ) !== $result ) {
        fwrite( STDERR, "FAIL: bulk fixture insert at offset {$base}: {$wpdb->last_error}\n" );
        exit( 1 );
    }
    $inserted += (int) $result;
}
wp_cache_flush();
echo "BENCHMARK FIXTURE: {$inserted} additional public posts (disposable CI only)\n";

$failures = 0;
foreach ( array( 'yearly', 'monthly', 'daily', 'weekly', 'decade', 'alpha', 'postbypost' ) as $mode ) {
    wp_cache_flush();
    $before = count( $wpdb->queries );
    $start = microtime( true );
    $html = WP_Plugin_ChronoArchive::wp_get_archives_advanced( array(
        'type' => $mode, 'limit' => 24, 'echo' => 0, 'show_post_count' => 1,
    ) );
    $duration = ( microtime( true ) - $start ) * 1000;
    $cold_queries = array_slice( $wpdb->queries, $before );
    $query = null;
    // Grouped archives use SQL aggregates; native alpha/post-by-post now
    // use WP_Query, which selects posts with a structured WHERE instead.
    $is_archive_select = static function ( $statement ) use ( $wpdb ) {
        if ( 0 !== stripos( ltrim( $statement ), 'SELECT ' ) ||
            false === strpos( $statement, $wpdb->posts ) ) {
            return false;
        }
        return false !== strpos( $statement, 'GROUP BY' ) ||
            false !== strpos( $statement, 'SELECT DISTINCT' ) ||
            ( false !== strpos( $statement, 'post_type' ) &&
              false !== strpos( $statement, 'post_status' ) );
    };
    foreach ( $cold_queries as $row ) {
        if ( $is_archive_select( $row[0] ) ) {
            $query = $row[0];
            break;
        }
    }
    $before_warm = count( $wpdb->queries );
    $repeat = WP_Plugin_ChronoArchive::wp_get_archives_advanced( array(
        'type' => $mode, 'limit' => 24, 'echo' => 0, 'show_post_count' => 1,
    ) );
    $warm_queries = array_slice( $wpdb->queries, $before_warm );
    $warm_archive_sql = array_filter( $warm_queries, static function ( $row ) use ( $is_archive_select ) {
        return $is_archive_select( $row[0] );
    } );
    if ( ! is_string( $html ) || '' === $html || $repeat !== $html || null === $query || $warm_archive_sql ) {
        ++$failures;
        echo "FAIL: {$mode} returned empty/mismatched output, no SQL captured, or re-queried warmed archives\n";
        continue;
    }
    $wpdb->last_error = '';
    $plan = $wpdb->get_results( 'EXPLAIN ' . $query, ARRAY_A );
    if ( ! is_array( $plan ) || ! $plan || '' !== $wpdb->last_error ) {
        ++$failures;
        echo "FAIL: {$mode} EXPLAIN returned error: {$wpdb->last_error}\n";
        continue;
    }
    $access = array();
    foreach ( $plan as $row ) {
        $access[] = ( isset( $row['table'] ) ? $row['table'] : '?' ) .
            ':type=' . ( isset( $row['type'] ) ? $row['type'] : '?' ) .
            ',key=' . ( ! empty( $row['key'] ) ? $row['key'] : '(none)' ) .
            ',rows=' . ( isset( $row['rows'] ) ? $row['rows'] : '?' ) .
            ',extra=' . ( isset( $row['Extra'] ) ? $row['Extra'] : '' );
    }
    echo sprintf(
        "BENCH %-11s cold_ms=%.2f sql_cold=%d sql_warm=%d links=%d PLAN %s\n",
        $mode, $duration, count( array_filter( $cold_queries, static function ( $row ) use ( $is_archive_select ) { return $is_archive_select( $row[0] ); } ) ), count( $warm_archive_sql ), substr_count( $html, '<li>' ), implode( ' | ', $access )
    );
}
echo "BENCHMARK RESULT: " . ( $failures ? $failures . ' FAILED' : '7/7 modes passed' ) . "\n";
exit( $failures ? 1 : 0 );
