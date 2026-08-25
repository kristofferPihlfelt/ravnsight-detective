<?php
/**
 * Uninstall: data is KEPT unless the user opted in to deletion.
 *
 * @package Ravnsight\Detective
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! get_option( 'ravndet_delete_data_on_uninstall' ) ) {
	return;
}

global $wpdb;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- explicit opt-in cleanup of own tables.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ravndet_signals" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ravndet_snapshots" );
// phpcs:enable

foreach ( array( 'ravndet_db_version', 'ravndet_retention_days', 'ravndet_module_flags', 'ravndet_delete_data_on_uninstall' ) as $ravndet_option ) {
	delete_option( $ravndet_option );
}
wp_clear_scheduled_hook( 'ravndet_daily' );

$ravndet_dropin = WP_CONTENT_DIR . '/fatal-error-handler.php';
if ( file_exists( $ravndet_dropin ) && str_contains( (string) file_get_contents( $ravndet_dropin ), 'Ravnsight Detective drop-in' ) ) {
	wp_delete_file( $ravndet_dropin );
}
