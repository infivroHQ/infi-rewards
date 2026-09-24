<?php
namespace InfiRewards\Points;

use InfiRewards\Rules\RulesEngine;

defined( 'ABSPATH' ) || exit;

class PointsManager {
	/** @var PointsManager|null */
	private static $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
	}

	public function handle_order_points( int $order_id ): void {
		// Load order and user
		if ( ! function_exists( 'wc_get_order' ) ) {
			return; // WooCommerce not available
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$user_id = (int) $order->get_user_id();
		if ( $user_id <= 0 ) {
			return; // guest orders: business decision needed
		}

		// Evaluate rules to determine points for this order
		$points = RulesEngine::get_instance()->evaluate_rules_for_order( $user_id, $order_id );

		if ( $points > 0 ) {
			// Add points to wallet with a reason referencing the order
			WalletService::get_instance()->add_points( $user_id, (int) $points, sprintf( 'Points for order #%d', $order_id ), $order_id );
		}
	}

	public function handle_refund_points( int $order_id ): void {
		global $wpdb;

		// When an order is refunded, reverse credited points tied to that order.
		$txn_table = \InfiRewards\Database\TransactionsTable::table_name();
		$rows      = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$txn_table} WHERE order_id = %d AND type = %s", $order_id, 'credit' ), ARRAY_A );
		if ( empty( $rows ) ) {
			return;
		}

		foreach ( $rows as $row ) {
			$user_id = (int) $row['user_id'];
			$points  = (int) $row['points'];
			if ( $points <= 0 ) {
				continue;
			}
			// Subtract the points that were credited for this order
			WalletService::get_instance()->subtract_points( $user_id, $points, sprintf( 'Reversed points for refunded order #%d', $order_id ), $order_id );
		}
	}
}
