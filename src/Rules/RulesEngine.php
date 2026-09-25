<?php
namespace InfiRewards\Rules;

use InfiRewards\Database\RulesTable;

defined( 'ABSPATH' ) || exit;

class RulesEngine {
	private const TYPE       = 'points_per_currency_unit';
	private static $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function get_rule(): ?array {
		global $wpdb;
		$table = RulesTable::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- The rule is stored in a plugin table and may change in admin.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE type = %s ORDER BY rule_id ASC LIMIT 1', $table, self::TYPE ), ARRAY_A );
		if ( ! $row ) {
			return null;
		}
		$config      = json_decode( $row['config'], true );
		$row['rate'] = is_array( $config ) && isset( $config['rate'] ) ? (string) $config['rate'] : '0';
		return $row;
	}

	/** Save the one storewide rate. Older fixed-point rule types are ignored. */
	public function save_rule( string $rate, bool $active ): bool {
		global $wpdb;
		if ( ! preg_match( '/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,4})?$/D', $rate ) || (float) $rate > 1000000 ) {
			return false;
		}
		$table = RulesTable::table_name();
		$data  = array(
			'type'   => self::TYPE,
			'config' => wp_json_encode( array( 'rate' => $rate ) ),
			'points' => 0,
			'status' => $active ? 1 : 0,
		);
		$rule  = $this->get_rule();
		if ( $rule ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- The rule is stored in a plugin table and may change in admin.
			return false !== $wpdb->update( $table, $data, array( 'rule_id' => (int) $rule['rule_id'] ) );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- The rule is stored in a plugin table and may change in admin.
		return 1 === $wpdb->insert( $table, $data );
	}

	/** Calculate from the paid order amount, excluding shipping and all taxes. */
	public function evaluate_rules_for_order( int $user_id, int $order_id ): int {
		if ( $user_id <= 0 || ! function_exists( 'wc_get_order' ) ) {
			return 0;
		}
		$rule = $this->get_rule();
		if ( ! $rule || 1 !== (int) $rule['status'] ) {
			return 0;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order || (int) $order->get_user_id() !== $user_id ) {
			return 0;
		}
		$rate = (string) $rule['rate'];
		if ( ! preg_match( '/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,4})?$/D', $rate ) || (float) $rate > 1000000 ) {
			return 0;
		}
		$decimals   = function_exists( 'wc_get_price_decimals' ) ? (int) wc_get_price_decimals() : 2;
		$decimals   = max( 0, min( 6, $decimals ) );
		$scale      = 10 ** $decimals;
		$amount     = max( 0, (float) $order->get_total() - (float) $order->get_total_tax() - (float) $order->get_shipping_total() );
		$minor      = (int) round( $amount * $scale );
		$rate_parts = explode( '.', $rate, 2 );
		$rate_units = (int) $rate_parts[0] * 10000 + (int) str_pad( $rate_parts[1] ?? '', 4, '0' );
		if ( $minor <= 0 || $rate_units <= 0 || $minor > intdiv( PHP_INT_MAX, $rate_units ) ) {
			return 0;
		}
		$points = intdiv( $minor * $rate_units, $scale * 10000 );
		return $points <= 2147483647 ? $points : 0;
	}
}
