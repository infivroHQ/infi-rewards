<?php
namespace InfiRewards\Database;

defined( 'ABSPATH' ) || exit;

class RulesTable {
	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'infirewards_rules';
	}
}
