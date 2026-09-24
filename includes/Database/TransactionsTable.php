<?php
namespace InfiRewards\Database;

defined( 'ABSPATH' ) || exit;

class TransactionsTable {
	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'infirewards_transactions';
	}
}
