<?php
/**
 * Plugin Name:       Ravnsight Detective – Error & Change Monitoring
 * Plugin URI:        https://ravnsight.com/plugins/ravnsight-detective
 * Description:       See your WordPress site from the inside: PHP errors grouped and attributed to the plugin that caused them, and a timeline of every plugin, theme and core change. Local-first — sends nothing anywhere.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Ravnsight
 * Author URI:        https://ravnsight.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ravnsight-detective
 *
 * Build: free (2026-08-26 06:17:44)

 * @package Ravnsight\Detective
 */

defined( 'ABSPATH' ) || exit;

/*
 * Another copy (Free vs Pro, or a leftover folder) is already loaded.
 * Bail before declaring anything — this file must never contain
 * unconditional named function/class declarations (compile-time binding
 * would fatal before this guard runs).
 */
if ( defined( 'RAVNDET_VERSION' ) ) {
	

	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-warning"><p>';
			esc_html_e( 'Two copies of Ravnsight Detective are active. Only one can run — deactivate the other copy.', 'ravnsight-detective' );
			echo '</p></div>';
		}
	);
	return;
}

/*
 * Single source of truth for version, slug and paths.
 */
define( 'RAVNDET_VERSION', '0.1.0' );
define( 'RAVNDET_SLUG', 'ravnsight-detective' );

define( 'RAVNDET_PATH', plugin_dir_path( __FILE__ ) );
define( 'RAVNDET_URL', plugin_dir_url( __FILE__ ) );
define( 'RAVNDET_BASENAME', plugin_basename( __FILE__ ) );

/*
 * PSR-4 autoloader for Ravnsight\Detective\* in src/.
 * Ravnsight\Detective\Modules\ErrorDetective\Handler → src/Modules/ErrorDetective/Handler.php
 */
spl_autoload_register(
	function ( $class_name ) {
		$prefix = 'Ravnsight\\Detective\\';

		if ( strncmp( $prefix, $class_name, strlen( $prefix ) ) !== 0 ) {
			return;
		}

		$file = RAVNDET_PATH . 'src/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';

		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

// Global helper functions (ravndet_cap, ravndet_url …).
require_once RAVNDET_PATH . 'src/Support/functions.php';

/*
 * Deep query profiling (opt-in in Settings): WordPress's own SAVEQUERIES
 * switch, defined as early as a plugin can. Queries from here on are
 * captured; wp-config.php remains untouched.
 */
if ( ! defined( 'SAVEQUERIES' ) && get_option( 'ravndet_savequeries' ) ) {
	define( 'SAVEQUERIES', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- SAVEQUERIES is WordPress core's own profiling switch; defining it is the entire point of the opt-in.
}



/*
 * The error handler must be armed as early as possible — before other
 * plugins run their init code — but everything else waits for plugins_loaded.
 * Closures only (see the duplicate-copy guard above).
 */
add_action(
	'plugins_loaded',
	function () {
		Ravnsight\Detective\Core\Plugin::instance();

		
	},
	1
);



register_activation_hook(
	__FILE__,
	function () {
		require_once RAVNDET_PATH . 'src/Core/Migrator.php';
		require_once RAVNDET_PATH . 'src/Support/functions.php';
		Ravnsight\Detective\Core\Migrator::migrate();
	}
);
