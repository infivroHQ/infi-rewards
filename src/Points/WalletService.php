<?php
namespace InfiRewards\Points;

use InfiRewards\Database\WalletTable;
use InfiRewards\Database\TransactionsTable;
use InfiRewards\Database\Installer;
use InfiRewards\Points\Wallet as WalletModel;

defined( 'ABSPATH' ) || exit;

class WalletService {
	/** @var WalletService|null */
	private static $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// private: use singleton
	}

	public function get_wallet_for_user( int $user_id ): ?WalletModel {
		global $wpdb;

		$table = WalletTable::table_name();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d", $user_id ), ARRAY_A );
		if ( ! $row ) {
			return null;
		}

		$wallet = new WalletModel( (int) $row['user_id'], (int) $row['balance'] );
		return $wallet;
	}

	public function add_points( int $user_id, int $points, string $reason = '', ?int $order_id = null ): bool {
		if ( null !== $order_id && $order_id <= 0 ) {
			return false;
		}
		$event_type = null === $order_id ? 'adjustment' : 'order_earn';
		$event_key  = null === $order_id ? null : 'order_earn:' . $order_id;
		return $this->change_points( $user_id, $points, 'credit', $reason, $order_id, $event_type, $event_key );
	}

	public function subtract_points( int $user_id, int $points, string $reason = '', ?int $order_id = null ): bool {
		if ( null !== $order_id && $order_id <= 0 ) {
			return false;
		}
		$event_type = null === $order_id ? 'adjustment' : 'order_reversal';
		$event_key  = null === $order_id ? null : 'order_reversal:' . $order_id;
		return $this->change_points( $user_id, $points, 'debit', $reason, $order_id, $event_type, $event_key );
	}

	/**
	 * Debit a reward once for a stable request key supplied by the caller.
	 */
	public function redeem_points( int $user_id, int $points, int $reward_id, string $request_key, string $reason = '' ): bool {
		if ( $reward_id <= 0 || ! preg_match( '/^[A-Za-z0-9_-]{16,64}$/D', $request_key ) ) {
			return false;
		}
		return $this->change_points(
			$user_id,
			$points,
			'debit',
			$reason,
			null,
			'redemption',
			'redemption:' . $request_key,
			$reward_id
		);
	}

	/**
	 * Find a prior debit when a redemption request is retried.
	 */
	public function get_redemption_transaction( int $user_id, string $request_key ): ?array {
		global $wpdb;

		if ( $user_id <= 0 || ! preg_match( '/^[A-Za-z0-9_-]{16,64}$/D', $request_key ) ) {
			return null;
		}
		$table = TransactionsTable::table_name();
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d AND event_key = %s AND event_type = 'redemption'",
				$user_id,
				'redemption:' . $request_key
			),
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * Change a balance and write its ledger entry in one database transaction.
	 */
	private function change_points(
		int $user_id,
		int $points,
		string $type,
		string $reason,
		?int $order_id,
		string $event_type,
		?string $event_key,
		?int $reward_id = null
	): bool {
		global $wpdb;

		if ( $user_id <= 0 || $points <= 0 || $points > 2147483647 ) {
			return false;
		}

		// Both tables must be transactional before any balance change is allowed.
		$installed_version = get_option( 'infirewards_db_version', '0' );
		if ( version_compare( $installed_version, Installer::LEDGER_CONTEXT_VERSION, '<' ) ) {
			return false;
		}

		$wallet_table = WalletTable::table_name();
		$txn_table    = TransactionsTable::table_name();

		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return false;
		}

		// A zero row ensures concurrent first credits lock the same wallet.
		$created = $wpdb->query(
			$wpdb->prepare( "INSERT IGNORE INTO {$wallet_table} (user_id, balance) VALUES (%d, 0)", $user_id )
		);
		if ( false === $created ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		$balance = $wpdb->get_var(
			$wpdb->prepare( "SELECT balance FROM {$wallet_table} WHERE user_id = %d FOR UPDATE", $user_id )
		);
		if ( null === $balance ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		$balance = (int) $balance;
		if ( $balance < 0 || ( 'debit' === $type && $balance < $points ) ||
			( 'credit' === $type && $balance > 2147483647 - $points ) ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		$new_balance = 'credit' === $type ? $balance + $points : $balance - $points;
		$updated     = $wpdb->update(
			$wallet_table,
			array(
				'balance'    => $new_balance,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'user_id' => $user_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
		if ( 1 !== $updated ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		$txn_inserted = $wpdb->insert(
			$txn_table,
			array(
				'user_id'    => $user_id,
				'points'     => $points,
				'type'       => $type,
				'event_type' => $event_type,
				'event_key'  => $event_key,
				'reward_id'  => $reward_id,
				'reason'     => $reason,
				'order_id'   => $order_id,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%d', '%s' )
		);
		if ( 1 !== $txn_inserted ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		if ( false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		return true;
	}

	public function get_balance( int $user_id ): int {
		$wallet = $this->get_wallet_for_user( $user_id );
		return $wallet ? $wallet->points : 0;
	}
}
