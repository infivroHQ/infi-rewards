<?php
namespace InfiRewards\Services;

use InfiRewards\Database\WalletTable;
use InfiRewards\Database\TransactionsTable;
use InfiRewards\Models\Wallet as WalletModel;
use InfiRewards\Models\Transaction as TransactionModel;

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

		$wallet = new WalletModel( (int) $row['user_id'], (float) $row['points'] );
		return $wallet;
	}

	public function add_points( int $user_id, int $points, string $reason = '', ?int $order_id = null ): bool {
		global $wpdb;

		if ( $points <= 0 ) {
			return false;
		}

		$wallet_table = WalletTable::table_name();
		$txn_table    = TransactionsTable::table_name();

		// Get existing balance
		$current = $this->get_wallet_for_user( $user_id );
		if ( $current ) {
			$new_balance = $current->points + $points;
			$updated     = $wpdb->update(
				$wallet_table,
				array( 'points' => $new_balance ),
				array( 'user_id' => $user_id ),
				array( '%f' ),
				array( '%d' )
			);
			if ( false === $updated ) {
				return false;
			}
		} else {
			$inserted = $wpdb->insert(
				$wallet_table,
				array(
					'user_id' => $user_id,
					'points'  => $points,
				),
				array( '%d', '%f' )
			);
			if ( false === $inserted ) {
				return false;
			}
		}

		// Create transaction record
		$txn_inserted = $wpdb->insert(
			$txn_table,
			array(
				'user_id'    => $user_id,
				'points'     => $points,
				'type'       => 'credit',
				'reason'     => $reason,
				'order_id'   => $order_id,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%f', '%s', '%s', '%d', '%s' )
		);

		return false !== $txn_inserted;
	}

	public function subtract_points( int $user_id, int $points, string $reason = '', ?int $order_id = null ): bool {
		global $wpdb;

		if ( $points <= 0 ) {
			return false;
		}

		$wallet_table = WalletTable::table_name();
		$txn_table    = TransactionsTable::table_name();

		$current = $this->get_wallet_for_user( $user_id );
		$balance = $current ? $current->points : 0.0;

		if ( $balance < $points ) {
			// Not enough points — business rule: could allow negative balances or fail. We fail by default.
			return false;
		}

		$new_balance = $balance - $points;
		$updated     = $wpdb->update(
			$wallet_table,
			array( 'points' => $new_balance ),
			array( 'user_id' => $user_id ),
			array( '%f' ),
			array( '%d' )
		);
		if ( false === $updated ) {
			return false;
		}

		$txn_inserted = $wpdb->insert(
			$txn_table,
			array(
				'user_id'    => $user_id,
				'points'     => $points,
				'type'       => 'debit',
				'reason'     => $reason,
				'order_id'   => $order_id,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%f', '%s', '%s', '%d', '%s' )
		);

		return false !== $txn_inserted;
	}

	public function get_balance( int $user_id ): float {
		$wallet = $this->get_wallet_for_user( $user_id );
		return $wallet ? $wallet->points : 0.0;
	}
}
