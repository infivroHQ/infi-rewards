<?php
namespace InfiRewards\Integrations\WooCommerce;

use InfiRewards\Points\PointsManager;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce Hooks integration for infiRewards.
 *
 * Registers order-related hooks and delegates to the core PointsManager.
 * Uses a singleton pattern and ensures hooks are registered only once.
 */
class Hooks {
	/** @var Hooks|null */
	private static $instance = null;

	/** @var bool */
	private $registered = false;

	/**
	 * Initialize the Hooks singleton and register hooks.
	 * Called by the plugin bootstrap on init.
	 */
	public static function init(): void {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		self::$instance->register_hooks();
	}

	/**
	 * Private constructor to enforce singleton.
	 */
	private function __construct() {
		// no-op
	}

	/**
	 * Register WooCommerce hooks. This method is idempotent.
	 */
	private function register_hooks(): void {
		if ( $this->registered ) {
			return; // already registered
		}

		// Award points when an order becomes completed
		add_action( 'woocommerce_order_status_completed', array( $this, 'on_order_completed' ), 10, 1 );

		// Reverse awarded points when an order is refunded
		add_action( 'woocommerce_order_status_refunded', array( $this, 'on_order_refunded' ), 10, 1 );

		// Optional: remove/reverse points when order is cancelled
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'on_order_cancelled' ), 10, 1 );

		$this->registered = true;
	}

	/**
	 * Handler for `woocommerce_order_status_completed`.
	 * Delegates to PointsManager::handle_order_points().
	 *
	 * @param int|\WC_Order $order_id Order ID or WC_Order instance (WC passes ID by default)
	 */
	public function on_order_completed( $order_id ): void {
		$id = is_object( $order_id ) && method_exists( $order_id, 'get_id' ) ? (int) $order_id->get_id() : (int) $order_id;
		if ( $id <= 0 ) {
			return;
		}

		if ( class_exists( PointsManager::class ) ) {
			PointsManager::get_instance()->handle_order_points( $id );
		}
	}

	/**
	 * Handler for `woocommerce_order_status_refunded`.
	 * Delegates to PointsManager::handle_refund_points().
	 *
	 * @param int|\WC_Order $order_id
	 */
	public function on_order_refunded( $order_id ): void {
		$id = is_object( $order_id ) && method_exists( $order_id, 'get_id' ) ? (int) $order_id->get_id() : (int) $order_id;
		if ( $id <= 0 ) {
			return;
		}

		if ( class_exists( PointsManager::class ) ) {
			PointsManager::get_instance()->handle_refund_points( $id );
		}
	}

	/**
	 * Optional handler for `woocommerce_order_status_cancelled`.
	 * Remove or reverse points awarded for cancelled orders.
	 * Delegates to refund handler by default.
	 *
	 * @param int|\WC_Order $order_id
	 */
	public function on_order_cancelled( $order_id ): void {
		$id = is_object( $order_id ) && method_exists( $order_id, 'get_id' ) ? (int) $order_id->get_id() : (int) $order_id;
		if ( $id <= 0 ) {
			return;
		}

		if ( class_exists( PointsManager::class ) ) {
			PointsManager::get_instance()->handle_refund_points( $id );
		}
	}
}
