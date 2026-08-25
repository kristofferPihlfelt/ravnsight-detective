<?php
/**
 * Global helpers. Everything else is namespaced under Ravnsight\Detective\.
 *
 * @package Ravnsight\Detective
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'ravndet_cap' ) ) {
	/**
	 * Capability required for every Detective screen and action.
	 *
	 * @return string
	 */
	function ravndet_cap() {
		/**
		 * Filter the capability required to use Ravnsight Detective.
		 *
		 * @param string $capability Default 'manage_options'.
		 */
		return apply_filters( 'ravndet_capability', 'manage_options' );
	}
}

if ( ! function_exists( 'ravndet_url' ) ) {
	/**
	 * Admin URL for one of the plugin's own screens.
	 *
	 * @param string $page Page suffix (dashboard|timeline|settings|premium).
	 * @param array  $args Extra query args.
	 * @return string
	 */
	function ravndet_url( $page = 'dashboard', $args = array() ) {
		return add_query_arg( $args, admin_url( 'admin.php?page=ravnsight-detective' . ( 'dashboard' === $page ? '' : '-' . $page ) ) );
	}
}
