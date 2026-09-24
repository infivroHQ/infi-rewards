<?php
namespace InfiRewards\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Database installer for infiRewards.
 *
 * Creates required tables on plugin activation and provides a migration entrypoint.
 */
class Installer {
	/** @var Installer|null */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return Installer
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor to enforce singleton usage
	 */
	private function __construct() {
	}

	/**
	 * Run installer to create or update tables. Intended to be called on plugin activation.
	 */
	public function install(): void {
		global $wpdb;

		// Ensure we have the right functions for dbDelta
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$prefix = $wpdb->prefix;

		// Table: infirewards_wallets
		// - wallet_id: primary key
		// - user_id: WordPress user ID (unique)
		// - balance: integer point balance
		// - updated_at: last update timestamp
		$wallets_table = $prefix . 'infirewards_wallets';
		$sql_wallets   = "CREATE TABLE {$wallets_table} (
            wallet_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            balance INT NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (wallet_id),
            UNIQUE KEY user_id (user_id)
        ) {$charset_collate};";

		// Table: infirewards_transactions
		// - transaction_id: primary key
		// - user_id: WordPress user ID
		// - order_id: related order (nullable)
		// - points: points credited/debited
		// - reason: short description
		// - created_at: timestamp
		$transactions_table = $prefix . 'infirewards_transactions';
		$sql_transactions   = "CREATE TABLE {$transactions_table} (
            transaction_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            order_id BIGINT(20) UNSIGNED DEFAULT NULL,
            points INT NOT NULL,
            reason VARCHAR(255) DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (transaction_id),
            KEY user_id_idx (user_id),
            KEY order_id_idx (order_id)
        ) {$charset_collate};";

		// Table: infirewards_rules
		// - rule_id: primary key
		// - type: rule type (per_order, per_product, signup, etc.)
		// - config: JSON/text configuration for the rule
		// - points: default points value for the rule
		// - status: active/inactive flag
		$rules_table = $prefix . 'infirewards_rules';
		$sql_rules   = "CREATE TABLE {$rules_table} (
            rule_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            type VARCHAR(50) NOT NULL DEFAULT '',
            config LONGTEXT,
            points INT NOT NULL DEFAULT 0,
            status TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY  (rule_id),
            KEY type_idx (type),
            KEY status_idx (status)
        ) {$charset_collate};";

		// Execute the table creation / updates using dbDelta which is safe to run multiple times
		dbDelta( $sql_wallets );
		dbDelta( $sql_transactions );
		dbDelta( $sql_rules );

		// Store DB version for future migrations
		$installed_version = get_option( 'infirewards_db_version' );
		$current_version   = '1.0';
		if ( $installed_version !== $current_version ) {
			update_option( 'infirewards_db_version', $current_version );
		}
	}

	/**
	 * Entry point for future migrations. Compares stored DB version and applies changes.
	 * This method should be safe to call repeatedly.
	 */
	public function maybe_update_tables(): void {
		$installed_version = get_option( 'infirewards_db_version', '1.0' );

		// Example migration pattern: if ( version_compare( $installed_version, '1.1', '<' ) ) { ... }
		// For now there are no migrations; placeholder for future updates.

		// After applying migrations, update version option.
		// update_option( 'infirewards_db_version', '1.1' );
	}

	/**
	 * Uninstall / cleanup handler.
	 * Drops plugin tables and removes stored options. This should be idempotent and safe to run.
	 */
	public function uninstall(): void {
		global $wpdb;
		$prefix = $wpdb->prefix;

		// Safety: Only drop tables when the site admin has explicitly allowed it.
		// This prevents accidental data loss during uninstall. To enable table
		// removal, set the `infirewards_drop_tables_on_uninstall` option to true
		// (e.g., via plugin settings or WP-CLI) before uninstalling.
		$should_drop = (bool) get_option( 'infirewards_drop_tables_on_uninstall', false );

		if ( ! $should_drop ) {
			// Do not drop tables; only remove plugin flags/options.
			delete_option( 'infirewards_db_version' );
			return;
		}

		$tables = array(
			$prefix . 'infirewards_wallets',
			$prefix . 'infirewards_transactions',
			$prefix . 'infirewards_rules',
		);

		foreach ( $tables as $table ) {
			// Drop each table if it exists. This is irreversible.
			$wpdb->query( "DROP TABLE IF EXISTS {$table};" );
		}

		// Cleanup related options after dropping tables
		delete_option( 'infirewards_db_version' );
		delete_option( 'infirewards_drop_tables_on_uninstall' );
	}
}
