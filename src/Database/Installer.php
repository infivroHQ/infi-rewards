<?php
namespace InfiRewards\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Database installer for infiRewards.
 *
 * Creates required tables on plugin activation and provides a migration entrypoint.
 */
class Installer {
	public const TRANSACTIONAL_VERSION  = '1.2';
	public const LEDGER_CONTEXT_VERSION = '1.3';
	public const REDEMPTION_VERSION     = '1.5';
	private const DB_VERSION            = self::REDEMPTION_VERSION;
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
	 * Run installer to create or update tables.
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
		$wallets_table = esc_sql( $prefix . 'infirewards_wallets' );
		$sql_wallets   = "CREATE TABLE {$wallets_table} (
            wallet_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            balance INT NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (wallet_id),
            UNIQUE KEY user_id (user_id)
        ) ENGINE=InnoDB {$charset_collate};";

		// Table: infirewards_transactions
		// - transaction_id: primary key
		// - user_id: WordPress user ID
		// - order_id: related order (nullable)
		// - points: positive integer points credited/debited
		// - type: credit or debit
		// - event_type/event_key: source of the change and unique retry key
		// - reward_id: redeemed reward, when applicable
		// - reason: short description
		// - created_at: timestamp
		$transactions_table = esc_sql( $prefix . 'infirewards_transactions' );
		$sql_transactions   = "CREATE TABLE {$transactions_table} (
            transaction_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            order_id BIGINT(20) UNSIGNED DEFAULT NULL,
            points INT NOT NULL,
            type VARCHAR(20) NOT NULL DEFAULT 'credit',
            event_type VARCHAR(32) NOT NULL DEFAULT 'legacy',
            event_key VARCHAR(100) DEFAULT NULL,
            reward_id BIGINT(20) UNSIGNED DEFAULT NULL,
            reason VARCHAR(255) DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (transaction_id),
            KEY user_id_idx (user_id),
            KEY order_id_idx (order_id),
            UNIQUE KEY event_key (event_key)
        ) ENGINE=InnoDB {$charset_collate};";

		// Table: infirewards_rules
		// - rule_id: primary key
		// - type: rule type (per_order, per_product, signup, etc.)
		// - config: JSON/text configuration for the rule
		// - points: default points value for the rule
		// - status: active/inactive flag
		$rules_table = esc_sql( $prefix . 'infirewards_rules' );
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

		$rewards_table = esc_sql( $prefix . 'infirewards_rewards' );
		$sql_rewards   = "CREATE TABLE {$rewards_table} (
            reward_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            discount_amount DECIMAL(18,6) NOT NULL,
            points_cost INT UNSIGNED NOT NULL,
            status TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (reward_id),
            KEY status_idx (status)
        ) ENGINE=InnoDB {$charset_collate};";

		$redemptions_table = esc_sql( $prefix . 'infirewards_redemptions' );
		$sql_redemptions   = "CREATE TABLE {$redemptions_table} (
            redemption_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            reward_id BIGINT(20) UNSIGNED NOT NULL,
            transaction_id BIGINT(20) UNSIGNED NOT NULL,
            request_key VARCHAR(64) NOT NULL,
            discount_amount DECIMAL(18,6) NOT NULL,
            customer_email VARCHAR(100) NOT NULL,
            coupon_code VARCHAR(64) NOT NULL,
            coupon_id BIGINT(20) UNSIGNED DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (redemption_id),
            UNIQUE KEY request_key (request_key),
            UNIQUE KEY transaction_id (transaction_id),
            KEY user_id_idx (user_id)
        ) ENGINE=InnoDB {$charset_collate};";

		// Execute the table creation / updates using dbDelta which is safe to run multiple times
		dbDelta( $sql_wallets );
		dbDelta( $sql_transactions );
		dbDelta( $sql_rules );
		dbDelta( $sql_rewards );
		dbDelta( $sql_redemptions );

		// Older rows had no type. Normalize any signed debits before marking this version installed.
		$installed_version = get_option( 'infirewards_db_version', '0' );
		$migrated          = true;
		if ( version_compare( $installed_version, self::DB_VERSION, '<' ) ) {
			$migrated = false !== $wpdb->query( "UPDATE {$transactions_table} SET type = 'debit', points = ABS(points) WHERE points < 0" );
		}

		// Do not claim a completed migration if dbDelta could not add a column or index.
		$wallet_column            = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$wallets_table} LIKE %s", 'balance' ) );
		$type_column              = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$transactions_table} LIKE %s", 'type' ) );
		$event_column             = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$transactions_table} LIKE %s", 'event_type' ) );
		$key_column               = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$transactions_table} LIKE %s", 'event_key' ) );
		$reward_column            = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$transactions_table} LIKE %s", 'reward_id' ) );
		$reward_name_column       = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$rewards_table} LIKE %s", 'name' ) );
		$discount_column          = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$rewards_table} LIKE %s", 'discount_amount' ) );
		$cost_column              = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$rewards_table} LIKE %s", 'points_cost' ) );
		$status_column            = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$rewards_table} LIKE %s", 'status' ) );
		$redemption_coupon_column = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$redemptions_table} LIKE %s", 'coupon_id' ) );
		$redemption_amount_column = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$redemptions_table} LIKE %s", 'discount_amount' ) );
		$redemption_email_column  = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$redemptions_table} LIKE %s", 'customer_email' ) );
		$redemption_code_column   = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$redemptions_table} LIKE %s", 'coupon_code' ) );
		$redemption_key_unique    = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND INDEX_NAME = %s AND NON_UNIQUE = 0', $redemptions_table, 'request_key' ) );
		$unique_key               = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND INDEX_NAME = %s AND NON_UNIQUE = 0',
				$transactions_table,
				'event_key'
			)
		);
		if ( $migrated && 'balance' === $wallet_column && 'type' === $type_column &&
			'event_type' === $event_column && 'event_key' === $key_column && 'reward_id' === $reward_column &&
			'name' === $reward_name_column && 'discount_amount' === $discount_column &&
			'points_cost' === $cost_column && 'status' === $status_column &&
			'coupon_id' === $redemption_coupon_column && 'discount_amount' === $redemption_amount_column &&
			'customer_email' === $redemption_email_column && 'coupon_code' === $redemption_code_column &&
			1 === (int) $redemption_key_unique &&
			1 === (int) $unique_key && $this->ensure_innodb( $wallets_table ) &&
			$this->ensure_innodb( $transactions_table ) && $this->ensure_innodb( $rewards_table ) && $this->ensure_innodb( $redemptions_table ) &&
			$this->reconcile_legacy_balances( $wallets_table, $transactions_table ) &&
			$this->backfill_order_events( $transactions_table ) ) {
			update_option( 'infirewards_db_version', self::DB_VERSION );
		}
	}

	/**
	 * Convert an older nontransactional table before wallet writes resume.
	 */
	private function ensure_innodb( string $table ): bool {
		global $wpdb;

		$engine = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
				$table
			)
		);
		if ( ! $engine ) {
			return false;
		}
		if ( 'InnoDB' === $engine ) {
			return true;
		}

		// Table names are built from the configured WordPress prefix.
		$quoted_table = esc_sql( str_replace( '`', '``', $table ) );
		if ( false === $wpdb->query( "ALTER TABLE `{$quoted_table}` ENGINE=InnoDB" ) ) {
			return false;
		}

		return 'InnoDB' === $wpdb->get_var(
			$wpdb->prepare(
				'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
				$table
			)
		);
	}

	/**
	 * Add an opening entry for any balance not explained by the old ledger.
	 * Row locks make interrupted and concurrent upgrades safe to retry.
	 */
	private function reconcile_legacy_balances( string $wallets_table, string $transactions_table ): bool {
		global $wpdb;
		$wallets_table      = esc_sql( $wallets_table );
		$transactions_table = esc_sql( $transactions_table );

		$wallets = $wpdb->get_results( "SELECT user_id FROM {$wallets_table}", ARRAY_A );
		if ( null === $wallets || ! empty( $wpdb->last_error ) ) {
			return false;
		}

		foreach ( $wallets as $wallet ) {
			$user_id = (int) $wallet['user_id'];
			if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
				return false;
			}
			$balance = $wpdb->get_var(
				$wpdb->prepare( "SELECT balance FROM {$wallets_table} WHERE user_id = %d FOR UPDATE", $user_id )
			);
			$ledger  = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COALESCE(SUM(CASE WHEN type = 'credit' THEN points WHEN type = 'debit' THEN -points ELSE 0 END), 0) FROM {$transactions_table} WHERE user_id = %d",
					$user_id
				)
			);
			if ( null === $balance || null === $ledger ) {
				$wpdb->query( 'ROLLBACK' );
				return false;
			}

			$difference = (int) $balance - (int) $ledger;
			if ( 0 !== $difference ) {
				$inserted = $wpdb->insert(
					$transactions_table,
					array(
						'user_id' => $user_id,
						'points'  => abs( $difference ),
						'type'    => $difference > 0 ? 'credit' : 'debit',
						'reason'  => 'Legacy balance adjustment',
					),
					array( '%d', '%d', '%s', '%s' )
				);
				if ( 1 !== $inserted ) {
					$wpdb->query( 'ROLLBACK' );
					return false;
				}
			}
			if ( false === $wpdb->query( 'COMMIT' ) ) {
				$wpdb->query( 'ROLLBACK' );
				return false;
			}
		}
		return true;
	}

	/**
	 * Give the first historical earn and reversal for each order a stable key.
	 * Extra historical rows remain in the ledger without claiming the same key.
	 */
	private function backfill_order_events( string $transactions_table ): bool {
		global $wpdb;
		$transactions_table = esc_sql( $transactions_table );

		$orders = $wpdb->get_results(
			"SELECT order_id, type, MIN(transaction_id) AS first_id
			FROM {$transactions_table}
			WHERE order_id IS NOT NULL AND order_id > 0 AND type IN ('credit', 'debit')
			GROUP BY order_id, type",
			ARRAY_A
		);
		if ( null === $orders || ! empty( $wpdb->last_error ) ) {
			return false;
		}

		foreach ( $orders as $order ) {
			$type      = 'credit' === $order['type'] ? 'order_earn' : 'order_reversal';
			$event_key = $type . ':' . (int) $order['order_id'];
			$first_id  = (int) $order['first_id'];
			$existing  = $wpdb->get_var(
				$wpdb->prepare( "SELECT event_key FROM {$transactions_table} WHERE transaction_id = %d", $first_id )
			);
			if ( null !== $existing ) {
				if ( $event_key !== $existing ) {
					return false;
				}
				continue;
			}

			$updated = $wpdb->update(
				$transactions_table,
				array(
					'event_type' => $type,
					'event_key'  => $event_key,
				),
				array( 'transaction_id' => $first_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
			if ( 1 !== $updated ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Upgrade sites that activated a previous plugin version.
	 * This method should be safe to call repeatedly.
	 */
	public function maybe_update_tables(): void {
		$installed_version = get_option( 'infirewards_db_version', '0' );
		if ( version_compare( $installed_version, self::DB_VERSION, '<' ) ) {
			$this->install();
		}
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
			esc_sql( $prefix . 'infirewards_wallets' ),
			esc_sql( $prefix . 'infirewards_transactions' ),
			esc_sql( $prefix . 'infirewards_rules' ),
			esc_sql( $prefix . 'infirewards_rewards' ),
			esc_sql( $prefix . 'infirewards_redemptions' ),
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
