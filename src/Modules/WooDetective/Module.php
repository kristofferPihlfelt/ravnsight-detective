<?php
/**
 * WooDetective: WooCommerce-aware signals. Only arms itself when
 * WooCommerce is active. Fatal errors in the checkout are already caught
 * by ErrorDetective — this module adds what only Woo hooks can see:
 * orders that FAIL. No customer data leaves the shop: gateway id and
 * order number only (DATA-POLICY).
 *
 * @package Ravnsight\Detective
 */

namespace Ravnsight\Detective\Modules\WooDetective;

use Ravnsight\Detective\Core\ModuleInterface;
use Ravnsight\Detective\Core\SignalStore;

defined( 'ABSPATH' ) || exit;

final class Module implements ModuleInterface {

	/**
	 * Feature flag key.
	 *
	 * @return string
	 */
	public static function key() {
		return 'woo_detective';
	}

	/**
	 * Whether WooCommerce is active on this site.
	 *
	 * @return bool
	 */
	public static function active() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * The active WooCommerce version, or '' when absent.
	 *
	 * @return string
	 */
	public static function version() {
		return self::active() && defined( 'WC_VERSION' ) ? (string) WC_VERSION : '';
	}

	/**
	 * Hook up. No-op unless WooCommerce is loaded.
	 */
	public function boot() {
		if ( ! self::active() ) {
			return;
		}

		add_action( 'woocommerce_order_status_failed', array( $this, 'order_failed' ), 10, 2 );
	}

	/**
	 * An order reached status "failed" — almost always the payment step.
	 *
	 * @param int    $order_id Order id.
	 * @param object $order    WC_Order (untyped: Woo may be absent at parse time).
	 */
	public function order_failed( $order_id, $order = null ) {
		$gateway = '';
		if ( $order && method_exists( $order, 'get_payment_method' ) ) {
			$gateway = (string) $order->get_payment_method();
		}

		SignalStore::record(
			'error.wc_order_failed',
			'critical',
			sprintf( 'Order #%d failed%s', (int) $order_id, '' !== $gateway ? ' (gateway: ' . $gateway . ')' : '' ),
			array(
				'type'    => 'plugin',
				'id'      => 'woocommerce',
				'version' => self::version(),
			),
			array(
				'order_id' => (int) $order_id,
				'gateway'  => $gateway,
			),
			'/checkout'
		);
	}
}
