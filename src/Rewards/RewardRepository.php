<?php
/**
 * Reward persistence for fixed cart discounts.
 *
 * @package InfiRewards
 */

namespace InfiRewards\Rewards;

use InfiRewards\Database\RewardsTable;

defined( 'ABSPATH' ) || exit;

/**
 * Storage and validation for fixed cart discount rewards.
 */
class RewardRepository {
	/**
	 * Find a reward by ID.
	 *
	 * @param int $reward_id Reward ID.
	 * @return array|null
	 */
	public function get( int $reward_id ): ?array {
		global $wpdb;
		if ( $reward_id <= 0 ) {
			return null;
		}
		$table = RewardsTable::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Reward data is stored in a plugin table and may change in admin.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE reward_id = %d', $table, $reward_id ), ARRAY_A );
		return $row ? $row : null;
	}

	/**
	 * List rewards, including disabled ones, for store administration.
	 *
	 * @return array
	 */
	public function all(): array {
		global $wpdb;
		$table = RewardsTable::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Reward data is stored in a plugin table and may change in admin.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY reward_id DESC', $table ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Create or update a reward after validating the store currency precision.
	 *
	 * @param int    $reward_id       Reward ID, or zero to create.
	 * @param string $name            Reward name.
	 * @param string $discount_amount Fixed cart discount.
	 * @param string $points_cost     Whole point cost.
	 * @param bool   $active          Whether customers may redeem it.
	 * @return bool
	 */
	public function save( int $reward_id, string $name, string $discount_amount, string $points_cost, bool $active ): bool {
		global $wpdb;
		$name              = trim( $name );
		$discount_amount   = trim( $discount_amount );
		$points_cost       = trim( $points_cost );
		$currency_decimals = function_exists( 'wc_get_price_decimals' ) ? (int) wc_get_price_decimals() : 2;
		$currency_decimals = max( 0, min( 6, $currency_decimals ) );
		$name_length       = function_exists( 'mb_strlen' ) ? mb_strlen( $name, 'UTF-8' ) : strlen( $name );
		if ( '' === $name || $name_length > 255 ||
			! preg_match( '/^(?:0|[1-9][0-9]{0,11})(?:\.([0-9]{1,6}))?$/D', $discount_amount, $matches ) ||
			! preg_match( '/[1-9]/', $discount_amount ) ||
			( isset( $matches[1] ) && strlen( $matches[1] ) > $currency_decimals ) ||
			! preg_match( '/^[1-9][0-9]{0,9}$/D', $points_cost ) ||
			(float) $points_cost > 2147483647 ) {
			return false;
		}
		$data  = array(
			'name'            => $name,
			'discount_amount' => $discount_amount,
			'points_cost'     => (int) $points_cost,
			'status'          => $active ? 1 : 0,
			'updated_at'      => current_time( 'mysql', true ),
		);
		$table = RewardsTable::table_name();
		if ( $reward_id > 0 ) {
			if ( ! $this->get( $reward_id ) ) {
				return false;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Reward data is stored in a plugin table and may change in admin.
			return false !== $wpdb->update( $table, $data, array( 'reward_id' => $reward_id ), array( '%s', '%s', '%d', '%d', '%s' ), array( '%d' ) );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Reward data is stored in a plugin table and may change in admin.
		return 1 === $wpdb->insert( $table, $data, array( '%s', '%s', '%d', '%d', '%s' ) );
	}
}
