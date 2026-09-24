<?php
/**
 * Reward table name helper.
 *
 * @package InfiRewards
 */

namespace InfiRewards\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Reward table access.
 */
class RewardsTable {
	/**
	 * Return the current site reward table name.
	 *
	 * @return string
	 */
	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'infirewards_rewards';
	}
}
