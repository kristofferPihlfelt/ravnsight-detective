<?php
/**
 * Human knowledge per signal type: what it is, why it matters, what to do.
 * The difference between a log viewer and a detective.
 *
 * @package Ravnsight\Detective
 */

namespace Ravnsight\Detective\Support;

defined( 'ABSPATH' ) || exit;

final class SignalInfo {

	/**
	 * Short human label for a signal type.
	 *
	 * @param string $type Signal type.
	 * @return string
	 */
	public static function label( $type ) {
		switch ( $type ) {
			case 'error.php_fatal':
				return __( 'Fatal error', 'ravnsight-detective' );
			case 'error.php_warning':
				return __( 'PHP warning', 'ravnsight-detective' );
			case 'error.php_deprecated':
				return __( 'Deprecation', 'ravnsight-detective' );
			case 'change.plugin_updated':
				return __( 'Plugin updated', 'ravnsight-detective' );
			case 'change.plugin_activated':
				return __( 'Plugin activated', 'ravnsight-detective' );
			case 'change.plugin_deactivated':
				return __( 'Plugin deactivated', 'ravnsight-detective' );
			case 'change.theme_switched':
				return __( 'Theme switched', 'ravnsight-detective' );
			case 'change.theme_updated':
				return __( 'Theme updated', 'ravnsight-detective' );
			case 'change.core_updated':
				return __( 'WordPress updated', 'ravnsight-detective' );
			case 'change.option_changed':
				return __( 'Setting changed', 'ravnsight-detective' );
			case 'change.php_version':
				return __( 'PHP version changed', 'ravnsight-detective' );
			default:
				return $type;
		}
	}

	/**
	 * What does this mean, and what should I do?
	 *
	 * @param string $type Signal type.
	 * @return array{what: string, action: string}
	 */
	public static function guidance( $type ) {
		switch ( $type ) {
			case 'error.php_fatal':
				return array(
					'what'   => __( 'The code stopped completely at this point — whatever the visitor or WordPress was doing was aborted. Fatal errors in a plugin usually mean a broken update, a PHP version mismatch, or a conflict with another plugin.', 'ravnsight-detective' ),
					'action' => __( 'Check the timeline: did this start right after an update or activation? If the component below is a plugin you can spare, deactivate it and see if the error stops. Report the full message, file and line to the developer — that is exactly what they need.', 'ravnsight-detective' ),
				);
			case 'error.php_warning':
				return array(
					'what'   => __( 'The code hit something unexpected but kept running. Individual warnings are usually harmless; the same warning thousands of times, or warnings that start suddenly, point at a real defect or a bad interaction.', 'ravnsight-detective' ),
					'action' => __( 'Look at when it first appeared and what changed around then. A warning that appears right after an update is the update\'s fault until proven otherwise. A rising count deserves attention.', 'ravnsight-detective' ),
				);
			case 'error.php_deprecated':
				return array(
					'what'   => __( 'The component uses a PHP or WordPress feature that is on its way out. Nothing is broken today, but this code will fail on a future PHP or WordPress upgrade.', 'ravnsight-detective' ),
					'action' => __( 'No urgency — but many deprecations from a component without recent updates is a sign it has been abandoned. Plan a replacement before your next PHP upgrade.', 'ravnsight-detective' ),
				);
			case 'change.option_changed':
				return array(
					'what'   => __( 'A core WordPress setting changed. The component listed is our best guess at who changed it, based on the code that made the call.', 'ravnsight-detective' ),
					'action' => __( 'If you did not make this change yourself, find out what did — settings that change "by themselves" are how a site ends up suddenly noindexed or redirected.', 'ravnsight-detective' ),
				);
			case 'change.php_version':
				return array(
					'what'   => __( 'The server PHP version changed — usually your host upgrading. New PHP versions break old code; errors that start today often trace back to this.', 'ravnsight-detective' ),
					'action' => __( 'Watch the error columns for the next few days. Deprecations recorded before the upgrade were the warning signs.', 'ravnsight-detective' ),
				);
			default:
				if ( 0 === strpos( $type, 'change.' ) ) {
					return array(
						'what'   => __( 'A change to the site software. Changes are not problems — but when an error starts, the change right before it is the first suspect.', 'ravnsight-detective' ),
						'action' => __( 'Nothing to do by itself. If errors appear after this point in the timeline, this change is where to start looking.', 'ravnsight-detective' ),
					);
				}

				return array(
					'what'   => '',
					'action' => '',
				);
		}
	}
}
