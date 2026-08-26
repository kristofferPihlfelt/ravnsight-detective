<?php
/**
 * Bootstraps the module registry and the admin.
 *
 * @package Ravnsight\Detective
 */

namespace Ravnsight\Detective\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Singleton entry point. Keep it thin: it wires, it does not do.
 */
final class Plugin {

	/**
	 * Singleton.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Module registry.
	 *
	 * @var ModuleRegistry
	 */
	public $modules;

	/**
	 * Get or create the instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Wire everything.
	 */
	private function __construct() {
		Migrator::maybe_migrate();

		$this->modules = new ModuleRegistry();
		$this->modules->register( \Ravnsight\Detective\Modules\ErrorDetective\Module::class );
		$this->modules->register( \Ravnsight\Detective\Modules\ChangeDetective\Module::class );
		$this->modules->register( \Ravnsight\Detective\Modules\PerfDetective\Module::class );
		$this->modules->register( \Ravnsight\Detective\Modules\JsDetective\Module::class );
		$this->modules->register( \Ravnsight\Detective\Modules\MailDetective\Module::class );
		$this->modules->register( \Ravnsight\Detective\Modules\WooDetective\Module::class );
		$this->modules->register( \Ravnsight\Detective\Modules\CronDetective\Module::class );
		$this->modules->boot();

		if ( is_admin() ) {
			new \Ravnsight\Detective\Admin\Admin();
			
		}

		

		// Housekeeping: prune old signals daily via WP-cron (no external deps).
		add_action( 'ravndet_daily', array( SignalStore::class, 'prune' ) );
		if ( ! wp_next_scheduled( 'ravndet_daily' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'ravndet_daily' );
		}
	}
}
