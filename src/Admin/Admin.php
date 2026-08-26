<?php
/**
 * Admin: menu, pages, actions. Logic here, markup in templates/.
 *
 * @package Ravnsight\Detective
 */

namespace Ravnsight\Detective\Admin;

use Ravnsight\Detective\Core\FeatureFlags;
use Ravnsight\Detective\Core\Migrator;

defined( 'ABSPATH' ) || exit;

final class Admin {

	/**
	 * Attach all admin hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_ravndet_save_settings', array( $this, 'save_settings' ) );
		add_action( 'admin_post_ravndet_dropin', array( $this, 'dropin_action' ) );
	}

	/**
	 * Menu + subpages.
	 */
	public function register_menu() {
		$cap = ravndet_cap();

		add_menu_page(
			__( 'Ravnsight Detective', 'ravnsight-detective' ),
			__( 'Ravnsight', 'ravnsight-detective' ),
			$cap,
			'ravnsight-detective',
			array( $this, 'render_dashboard' ),
			'dashicons-visibility',
			79
		);

		$subpages = array(
			'ravnsight-detective'          => array( __( 'Dashboard', 'ravnsight-detective' ), 'render_dashboard' ),
			'ravnsight-detective-timeline' => array( __( 'Timeline', 'ravnsight-detective' ), 'render_timeline' ),
			'ravnsight-detective-settings' => array( __( 'Settings', 'ravnsight-detective' ), 'render_settings' ),
			'ravnsight-detective-premium'  => array( __( 'Go further', 'ravnsight-detective' ), 'render_premium' ),
		);
		foreach ( $subpages as $slug => list( $title, $method ) ) {
			add_submenu_page( 'ravnsight-detective', $title, $title, $cap, $slug, array( $this, $method ) );
		}
	}

	/**
	 * Styles on our own screens only.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function assets( $hook ) {
		if ( ! str_contains( (string) $hook, 'ravnsight-detective' ) ) {
			return;
		}
		$file = RAVNDET_PATH . 'assets/css/admin.css';
		wp_enqueue_style( 'ravndet-admin', RAVNDET_URL . 'assets/css/admin.css', array(), RAVNDET_VERSION . '-' . (string) filemtime( $file ) );
	}

	/**
	 * Dashboard page.
	 */
	public function render_dashboard() {
		$data   = Queries::dashboard();
		$health = Health::overview();
		require RAVNDET_PATH . 'templates/admin-dashboard.php';
	}

	/**
	 * Timeline page.
	 */
	public function render_timeline() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only GET filters.
		$filter_type     = isset( $_GET['signal_type'] ) ? sanitize_key( wp_unslash( $_GET['signal_type'] ) ) : '';
		$filter_severity = isset( $_GET['severity'] ) ? sanitize_key( wp_unslash( $_GET['severity'] ) ) : '';
		// phpcs:enable
		$signals = Queries::timeline( $filter_type, $filter_severity );
		require RAVNDET_PATH . 'templates/admin-timeline.php';
	}

	/**
	 * Settings page.
	 */
	public function render_settings() {
		$retention = (int) get_option( 'ravndet_retention_days', 7 );
		$flags     = array(
			'error_detective'  => FeatureFlags::enabled( 'error_detective' ),
			'change_detective' => FeatureFlags::enabled( 'change_detective' ),
			'perf_detective'   => FeatureFlags::enabled( 'perf_detective' ),
			'js_detective'     => FeatureFlags::enabled( 'js_detective' ),
			'mail_detective'   => FeatureFlags::enabled( 'mail_detective' ),
			'woo_detective'    => FeatureFlags::enabled( 'woo_detective' ),
			'cron_detective'   => FeatureFlags::enabled( 'cron_detective' ),
		);
		$savequeries = (bool) get_option( 'ravndet_savequeries', false );
		$site_info   = Health::site_info();
		$delete_on_uninstall = (bool) get_option( 'ravndet_delete_data_on_uninstall', false );
		$dropin_status       = \Ravnsight\Detective\Modules\ErrorDetective\Dropin::status();
		require RAVNDET_PATH . 'templates/admin-settings.php';
	}

	/**
	 * The single upsell surface in the free build (guideline 11).
	 */
	public function render_premium() {
		require RAVNDET_PATH . 'templates/admin-premium.php';
	}

	/**
	 * Install/remove the fatal-error-handler drop-in (PRG, dedicated action).
	 */
	public function dropin_action() {
		check_admin_referer( 'ravndet_admin' );
		if ( ! current_user_can( ravndet_cap() ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'ravnsight-detective' ) );
		}
		$dropin_action = isset( $_POST['dropin_action'] ) ? sanitize_key( wp_unslash( $_POST['dropin_action'] ) ) : '';
		$notice        = 'saved';
		if ( 'install' === $dropin_action ) {
			$result = \Ravnsight\Detective\Modules\ErrorDetective\Dropin::install();
			$notice = is_wp_error( $result ) ? 'dropin_foreign' : 'dropin_installed';
		} elseif ( 'uninstall' === $dropin_action ) {
			\Ravnsight\Detective\Modules\ErrorDetective\Dropin::uninstall();
			$notice = 'dropin_removed';
		}
		wp_safe_redirect( add_query_arg( 'ravndet_notice', $notice, ravndet_url( 'settings' ) ) );
		exit;
	}

	/**
	 * Save settings (PRG).
	 */
	public function save_settings() {
		check_admin_referer( 'ravndet_admin' );
		if ( ! current_user_can( ravndet_cap() ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'ravnsight-detective' ) );
		}

		$retention = isset( $_POST['retention_days'] ) ? absint( wp_unslash( $_POST['retention_days'] ) ) : 7;
		$retention = min( 7, max( 1, $retention ) );
		
		update_option( 'ravndet_retention_days', $retention, false );
		update_option( 'ravndet_delete_data_on_uninstall', isset( $_POST['delete_data_on_uninstall'] ) ? 1 : 0, false );

		FeatureFlags::save(
			array(
				'error_detective'  => isset( $_POST['module_error_detective'] ),
				'change_detective' => isset( $_POST['module_change_detective'] ),
				'perf_detective'   => isset( $_POST['module_perf_detective'] ),
				'js_detective'     => isset( $_POST['module_js_detective'] ),
				'mail_detective'   => isset( $_POST['module_mail_detective'] ),
				'woo_detective'    => isset( $_POST['module_woo_detective'] ),
				'cron_detective'   => isset( $_POST['module_cron_detective'] ),
			)
		);
		update_option( 'ravndet_savequeries', isset( $_POST['savequeries'] ) ? 1 : 0, true );

		wp_safe_redirect( add_query_arg( 'ravndet_notice', 'saved', ravndet_url( 'settings' ) ) );
		exit;
	}
}
