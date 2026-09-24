<?php
namespace InfiRewards\Services;

use InfiRewards\Database\RulesTable;

defined( 'ABSPATH' ) || exit;

class RulesEngine {
	/** @var RulesEngine|null */
	private static $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
	}

	/**
	 * Evaluate rules for an order and return total points earned.
	 * Rules are stored in the rules table with a JSON `config` field.
	 * Supported rule types (example): per_order, per_product, signup
	 *
	 * @param int $user_id
	 * @param int $order_id
	 * @return int
	 */
	public function evaluate_rules_for_order( int $user_id, int $order_id ): int {
		global $wpdb;

		$total = 0;
		$table = RulesTable::table_name();
		$rows  = $wpdb->get_results( "SELECT * FROM {$table}", ARRAY_A );
		if ( empty( $rows ) ) {
			return 0;
		}

		foreach ( $rows as $row ) {
			$config = array();
			if ( ! empty( $row['config'] ) ) {
				$config = json_decode( $row['config'], true );
			}

			// Skip disabled rules
			if ( isset( $config['enabled'] ) && ! $config['enabled'] ) {
				continue;
			}

			$rule_type = $config['type'] ?? 'per_order';

			// Basic validation: does this order qualify for this rule?
			if ( $this->validate_rule( $config, $order_id ) ) {
				// Example implementations — real logic should be expanded.
				switch ( $rule_type ) {
					case 'per_product':
						// If per_product, points may depend on matching products and quantities.
						// Implemented minimally: expect config['points'] per matching product unit and config['products'] array of product IDs.
						$points_per_unit = isset( $config['points'] ) ? (int) $config['points'] : 0;
						if ( function_exists( 'wc_get_order' ) ) {
							$order = wc_get_order( $order_id );
							if ( $order ) {
								foreach ( $order->get_items() as $item ) {
									$product_id = (int) $item->get_product_id();
									$qty        = (int) $item->get_quantity();
									if ( isset( $config['products'] ) && is_array( $config['products'] ) && in_array( $product_id, $config['products'], true ) ) {
										$total += $points_per_unit * $qty;
									}
								}
							}
						}
						break;

					case 'signup':
						// signup bonuses are not order-related — skip here
						break;

					case 'per_order':
					default:
						// Apply fixed points for the order
						$total += isset( $config['points'] ) ? (int) $config['points'] : 0;
						break;
				}
			}
		}

		return (int) $total;
	}

	/**
	 * Validate whether an order qualifies for a given rule config.
	 * This is a minimal implementation — expand business rules as needed.
	 *
	 * @param array $rule
	 * @param int   $order_id
	 * @return bool
	 */
	public function validate_rule( array $rule, int $order_id ): bool {
		// Basic guard: if rule has conditions, check them
		if ( empty( $rule ) ) {
			return false;
		}

		// If rule specifies a minimum order total
		if ( isset( $rule['min_order_total'] ) && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				return false;
			}
			$total = (float) $order->get_total();
			if ( $total < (float) $rule['min_order_total'] ) {
				return false;
			}
		}

		// If rule specifies product conditions, ensure at least one matching product exists
		if ( isset( $rule['products'] ) && is_array( $rule['products'] ) && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				return false;
			}
			$has_match = false;
			foreach ( $order->get_items() as $item ) {
				$product_id = (int) $item->get_product_id();
				if ( in_array( $product_id, $rule['products'], true ) ) {
					$has_match = true;
					break;
				}
			}
			if ( ! $has_match ) {
				return false;
			}
		}

		// Other conditions (customer role, categories, etc.) can be added here.

		return true;
	}
}
