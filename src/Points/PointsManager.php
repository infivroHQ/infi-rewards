<?php
namespace InfiRewards\Points;

use InfiRewards\Rules\RulesEngine;

defined( 'ABSPATH' ) || exit;

class PointsManager {
	private static $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function handle_order_points( int $order_id ): void {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order || 'completed' !== $order->get_status() ) {
			return;
		}
		$user_id = (int) $order->get_user_id();
		if ( $user_id <= 0 ) {
			// A guest order cannot become eligible if an account is attached later.
			$order->update_meta_data( '_infirewards_completed_as_guest', 'yes' );
			$order->save();
			return;
		}
		if ( 'yes' === $order->get_meta( '_infirewards_completed_as_guest', true ) ) {
			return;
		}
		$points = RulesEngine::get_instance()->evaluate_rules_for_order( $user_id, $order_id );
		if ( $points > 0 ) {
			WalletService::get_instance()->add_points( $user_id, $points, sprintf( 'Points for order #%d', $order_id ), $order_id );
		}
	}

	public function handle_refund_points( int $order_id ): void {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order || ! in_array( $order->get_status(), array( 'cancelled', 'refunded' ), true ) ) {
			return;
		}
		WalletService::get_instance()->reverse_order_points( $order_id );
	}
}
