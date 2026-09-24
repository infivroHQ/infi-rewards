<?php
namespace InfiRewards\Database;

defined( 'ABSPATH' ) || exit;

class WalletTable {
	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'infirewards_wallets';
	}
}
