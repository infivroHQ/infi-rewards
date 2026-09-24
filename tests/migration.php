<?php
/** Rebuild a v1.2 schema in a disposable site, then run the current upgrade. */
if ( '1' !== getenv( 'IR_TEST_DISPOSABLE' ) || '1' !== getenv( 'IR_TEST_SCHEMA_RESET' ) || ! getenv( 'IR_TEST_WP_ROOT' ) ) {
	fwrite( STDERR, "Set IR_TEST_DISPOSABLE=1, IR_TEST_SCHEMA_RESET=1, and IR_TEST_WP_ROOT to a disposable site.\n" );
	exit( 2 );
}
$_SERVER['HTTP_HOST'] = 'localhost';
require rtrim( getenv( 'IR_TEST_WP_ROOT' ), '/' ) . '/wp-load.php';

use InfiRewards\Database\Installer;
use InfiRewards\Database\RedemptionsTable;
use InfiRewards\Database\RewardsTable;
use InfiRewards\Database\TransactionsTable;
use InfiRewards\Database\WalletTable;

function ir_migration_check( bool $pass, string $message ): void {
	if ( ! $pass ) {
		throw new RuntimeException( $message );
	}
	echo "PASS: {$message}\n";
}

// This test intentionally removes plugin tables. An explicit database name guard
// prevents a disposable flag from being used against a normal site by mistake.
global $wpdb;
$database = $wpdb->get_var( 'SELECT DATABASE()' );
ir_migration_check( 0 === strpos( $database, 'ir_verify_' ), 'using isolated verification database' );
$wallets = WalletTable::table_name();
$transactions = TransactionsTable::table_name();
$rewards = RewardsTable::table_name();
$redemptions = RedemptionsTable::table_name();
$rules = $wpdb->prefix . 'infirewards_rules';
foreach ( array( $redemptions, $rewards, $transactions, $wallets, $rules ) as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}
$collate = $wpdb->get_charset_collate();
$wpdb->query( "CREATE TABLE {$wallets} (wallet_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, user_id BIGINT UNSIGNED NOT NULL, balance INT NOT NULL DEFAULT 0, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (wallet_id), UNIQUE KEY user_id (user_id)) ENGINE=InnoDB {$collate}" );
$wpdb->query( "CREATE TABLE {$transactions} (transaction_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, user_id BIGINT UNSIGNED NOT NULL, order_id BIGINT UNSIGNED DEFAULT NULL, points INT NOT NULL, type VARCHAR(20) NOT NULL DEFAULT 'credit', reason VARCHAR(255) DEFAULT '', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (transaction_id), KEY user_id_idx (user_id), KEY order_id_idx (order_id)) ENGINE=InnoDB {$collate}" );
$wpdb->query( "CREATE TABLE {$rules} (rule_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, type VARCHAR(50) NOT NULL DEFAULT '', config LONGTEXT, points INT NOT NULL DEFAULT 0, status TINYINT(1) NOT NULL DEFAULT 1, PRIMARY KEY (rule_id), KEY type_idx (type), KEY status_idx (status)) {$collate}" );
$wpdb->insert( $wallets, array( 'user_id' => 900001, 'balance' => 11 ) );
$wpdb->insert( $transactions, array( 'user_id' => 900001, 'order_id' => 900002, 'points' => 10, 'type' => 'credit' ) );
$wpdb->insert( $transactions, array( 'user_id' => 900001, 'order_id' => 900003, 'points' => -2, 'type' => 'credit' ) );
update_option( 'infirewards_db_version', '1.2' );
Installer::get_instance()->maybe_update_tables();
ir_migration_check( Installer::REDEMPTION_VERSION === get_option( 'infirewards_db_version' ), 'v1.2 schema upgrades to current version' );
ir_migration_check( $wpdb->get_var( "SHOW TABLES LIKE '{$rewards}'" ) === $rewards, 'rewards table created' );
ir_migration_check( $wpdb->get_var( "SHOW TABLES LIKE '{$redemptions}'" ) === $redemptions, 'redemptions table created' );
$rows = $wpdb->get_results( "SELECT * FROM {$transactions} WHERE user_id = 900001 ORDER BY transaction_id", ARRAY_A );
ir_migration_check( 3 === count( $rows ) && 2 === (int) $rows[1]['points'] && 'debit' === $rows[1]['type'], 'signed legacy debit normalized' );
ir_migration_check( 3 === (int) $rows[2]['points'] && 'Legacy balance adjustment' === $rows[2]['reason'], 'legacy balance reconciled to ledger' );
ir_migration_check( 'order_earn:900002' === $rows[0]['event_key'] && 'order_reversal:900003' === $rows[1]['event_key'], 'historical order events backfilled' );
Installer::get_instance()->install();
ir_migration_check( 3 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$transactions} WHERE user_id = 900001" ), 'migration rerun does not add entries' );
echo "Migration checks complete.\n";
