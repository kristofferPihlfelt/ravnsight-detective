<?php
/**
 * Attribution exit test (Fas 9): fixture errors must attribute to the right
 * plugin in >= 90 % of rows. Run: wp eval-file tools/fixtures/run-attribution-test.php
 *
 * @package Ravnsight\Detective
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

global $wpdb;
$table = $wpdb->prefix . 'ravndet_signals';
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- test harness.
$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $table ) );

require_once WP_PLUGIN_DIR . '/broken-warnings/broken-warnings.php';
require_once WP_PLUGIN_DIR . '/broken-fatal/broken-fatal.php';
require_once WP_PLUGIN_DIR . '/broken-deprecated/broken-deprecated.php';

brokw_noise( 5000 );
brokd_old( 200 );

$rows    = $wpdb->get_results( $wpdb->prepare( 'SELECT type, component_type, component_id, count FROM %i', $table ) );
$total   = 0;
$correct = 0;
$grouped = true;
foreach ( $rows as $r ) {
	++$total;
	if ( 'plugin' === $r->component_type && str_starts_with( (string) $r->component_id, 'broken-' ) ) {
		++$correct;
	}
	if ( (int) $r->count > 1 && (int) $r->count < 100 ) {
		$grouped = false;
	}
}
// phpcs:enable
printf( "rows: %d (5200 raw errors)\n", $total );
printf( "attribution: %d/%d = %.0f%%\n", $correct, $total, $total ? $correct / $total * 100 : 0 );
printf( "grouping: %s\n", $grouped && $total <= 4 ? 'OK (mass duplicates collapsed)' : 'CHECK MANUALLY' );
echo ( $total > 0 && $correct / $total >= 0.9 && $total <= 4 ) ? "EXIT CRITERIA MET\n" : "EXIT CRITERIA NOT MET\n";
