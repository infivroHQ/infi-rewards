<?php
namespace InfiRewards\Rewards;

use InfiRewards\Database\Installer;
use InfiRewards\Database\RedemptionsTable;
use InfiRewards\Database\RewardsTable;
use InfiRewards\Database\TransactionsTable;
use InfiRewards\Database\WalletTable;

defined( 'ABSPATH' ) || exit;

/** Reserves points and delivers one coupon per request key. */
class RedemptionService {
	public function redeem( int $user_id, int $reward_id, string $request_key ): string {
		global $wpdb;
		if ( $user_id <= 0 || $reward_id <= 0 || ! preg_match( '/^[a-f0-9]{32}$/D', $request_key ) ||
			! class_exists( 'WC_Coupon' ) || version_compare( get_option( 'infirewards_db_version', '0' ), Installer::REDEMPTION_VERSION, '<' ) ) {
			return 'unavailable';
		}
		$table    = RedemptionsTable::table_name();
		$existing = $this->get_by_key( $user_id, $request_key );
		if ( $existing ) {
			return (int) $existing['reward_id'] === $reward_id ? $this->issue_coupon( $existing ) : 'invalid';
		}
		$user = get_userdata( $user_id );
		if ( ! $user || ! is_email( $user->user_email ) ) {
			return 'email';
		}
		$wallets      = WalletTable::table_name();
		$transactions = TransactionsTable::table_name();
		$rewards      = RewardsTable::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Redemption uses row locks and current plugin table state.
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return 'error';
		}
		// All debits lock the same wallet row. This also serializes repeated requests.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Redemption uses row locks and current plugin table state.
		$created = $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO %i (user_id, balance) VALUES (%d, 0)', $wallets, $user_id ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Redemption uses row locks and current plugin table state.
		$balance = false === $created ? null : $wpdb->get_var( $wpdb->prepare( 'SELECT balance FROM %i WHERE user_id = %d FOR UPDATE', $wallets, $user_id ) );
		if ( null === $balance ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Redemption uses row locks and current plugin table state.
			$wpdb->query( 'ROLLBACK' );
			return 'error';
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Redemption uses row locks and current plugin table state.
		$existing = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE request_key = %s', $table, $request_key ), ARRAY_A );
		if ( $existing ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Redemption uses row locks and current plugin table state.
			$wpdb->query( 'ROLLBACK' );
			return (int) $existing['user_id'] === $user_id && (int) $existing['reward_id'] === $reward_id ? $this->issue_coupon( $existing ) : 'invalid';
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Redemption uses row locks and current plugin table state.
		$reward = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE reward_id = %d FOR UPDATE', $rewards, $reward_id ), ARRAY_A );
		if ( ! $reward || 1 !== (int) $reward['status'] || (int) $reward['points_cost'] <= 0 || (float) $reward['discount_amount'] <= 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Redemption uses row locks and current plugin table state.
			$wpdb->query( 'ROLLBACK' );
			return 'unavailable';
		}
		$cost = (int) $reward['points_cost'];
		if ( (int) $balance < $cost ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Redemption uses row locks and current plugin table state.
			$wpdb->query( 'ROLLBACK' );
			return 'points';
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Redemption uses row locks and current plugin table state.
		$updated = $wpdb->update(
			$wallets,
			array(
				'balance'    => (int) $balance - $cost,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'user_id' => $user_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Redemption uses row locks and current plugin table state.
		$inserted       = 1 === $updated ? $wpdb->insert(
			$transactions,
			array(
				'user_id'    => $user_id,
				'points'     => $cost,
				'type'       => 'debit',
				'event_type' => 'redemption',
				'event_key'  => 'redemption:' . $request_key,
				'reward_id'  => $reward_id,
				'reason'     => sprintf( 'Redeemed reward #%d', $reward_id ),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
		) : false;
		$transaction_id = $inserted ? (int) $wpdb->insert_id : 0;
		$code           = 'ir-' . substr( hash_hmac( 'sha256', $request_key, wp_salt( 'auth' ) ), 0, 32 );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Redemption uses row locks and current plugin table state.
		$recorded      = $transaction_id ? $wpdb->insert(
			$table,
			array(
				'user_id'         => $user_id,
				'reward_id'       => $reward_id,
				'transaction_id'  => $transaction_id,
				'request_key'     => $request_key,
				'discount_amount' => $reward['discount_amount'],
				'customer_email'  => $user->user_email,
				'coupon_code'     => $code,
				'status'          => 'pending',
				'created_at'      => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		) : false;
		$redemption_id = $recorded ? (int) $wpdb->insert_id : 0;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Redemption uses row locks and current plugin table state.
		if ( 1 !== $recorded || false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			return 'error';
		}
		$record = $this->get( $user_id, $redemption_id );
		return $record ? $this->issue_coupon( $record ) : 'pending';
	}

	public function get( int $user_id, int $redemption_id ): ?array {
		global $wpdb;
		$table = RedemptionsTable::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Redemption uses row locks and current plugin table state.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE user_id = %d AND redemption_id = %d', $table, $user_id, $redemption_id ), ARRAY_A );
		return $row ?: null;
	}

	public function get_by_key( int $user_id, string $key ): ?array {
		global $wpdb;
		$table = RedemptionsTable::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Redemption uses row locks and current plugin table state.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE user_id = %d AND request_key = %s', $table, $user_id, $key ), ARRAY_A );
		return $row ?: null;
	}

	public function history( int $user_id ): array {
		global $wpdb;
		$table = RedemptionsTable::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Redemption uses row locks and current plugin table state.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE user_id = %d ORDER BY redemption_id DESC LIMIT 20', $table, $user_id ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	public function points_history( int $user_id ): array {
		global $wpdb;
		$table = TransactionsTable::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Redemption uses row locks and current plugin table state.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE user_id = %d ORDER BY transaction_id DESC LIMIT 20', $table, $user_id ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/** A pending row is retained on failure, so the customer can retry delivery. */
	public function retry( int $user_id, int $redemption_id ): string {
		$record = $this->get( $user_id, $redemption_id );
		return $record ? $this->issue_coupon( $record ) : 'invalid';
	}

	private function issue_coupon( array $record ): string {
		global $wpdb;
		if ( ! class_exists( 'WC_Coupon' ) || ! function_exists( 'wc_get_coupon_id_by_code' ) ) {
			return 'pending';
		}
		if ( 'issued' === $record['status'] && (int) $record['coupon_id'] > 0 ) {
			return 'issued';
		}
		$table = RedemptionsTable::table_name();
		$id    = (int) $record['redemption_id'];
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Redemption uses row locks and current plugin table state.
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return 'pending';
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Redemption uses row locks and current plugin table state.
		$locked = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE redemption_id = %d FOR UPDATE', $table, $id ), ARRAY_A );
		if ( ! $locked ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Redemption uses row locks and current plugin table state.
			$wpdb->query( 'ROLLBACK' );
			return 'pending';
		}
		if ( 'issued' === $locked['status'] && (int) $locked['coupon_id'] > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Redemption uses row locks and current plugin table state.
			$wpdb->query( 'COMMIT' );
			return 'issued';
		}
		try {
			$code      = $locked['coupon_code'];
			$coupon_id = wc_get_coupon_id_by_code( $code );
			if ( $coupon_id ) {
				$owner = (int) get_post_meta( $coupon_id, '_infirewards_redemption_id', true );
				if ( $owner !== $id ) {
					// A save may have stopped after creating the coupon but before its metadata.
					$orphan = new \WC_Coupon( $coupon_id );
					if ( 0 !== $owner || abs( (float) $orphan->get_amount() - (float) $locked['discount_amount'] ) >= 0.000001 ||
						array( $locked['customer_email'] ) !== $orphan->get_email_restrictions() ||
						1 !== (int) $orphan->get_usage_limit() ) {
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Redemption uses row locks and current plugin table state.
						$wpdb->query( 'ROLLBACK' );
						return 'pending';
					}
					update_post_meta( $coupon_id, '_infirewards_redemption_id', $id );
					update_post_meta( $coupon_id, '_infirewards_user_id', (int) $locked['user_id'] );
				}
				$coupon_owner = (int) get_post_meta( $coupon_id, '_infirewards_user_id', true );
				if ( 0 === $coupon_owner ) {
					update_post_meta( $coupon_id, '_infirewards_user_id', (int) $locked['user_id'] );
				} elseif ( $coupon_owner !== (int) $locked['user_id'] ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Redemption uses row locks and current plugin table state.
					$wpdb->query( 'ROLLBACK' );
					return 'pending';
				}
			} else {
				$coupon = new \WC_Coupon();
				$coupon->set_code( $code );
				$coupon->set_discount_type( 'fixed_cart' );
				$coupon->set_amount( $locked['discount_amount'] );
				$coupon->set_usage_limit( 1 );
				$coupon->set_usage_limit_per_user( 1 );
				$coupon->set_email_restrictions( array( $locked['customer_email'] ) );
				$coupon->update_meta_data( '_infirewards_redemption_id', $id );
				$coupon->update_meta_data( '_infirewards_user_id', (int) $locked['user_id'] );
				$coupon_id = $coupon->save();
			}
			if ( ! $coupon_id ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Redemption uses row locks and current plugin table state.
				$wpdb->query( 'ROLLBACK' );
				return 'pending';
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Redemption uses row locks and current plugin table state.
			$updated = $wpdb->update(
				$table,
				array(
					'coupon_id' => $coupon_id,
					'status'    => 'issued',
				),
				array( 'redemption_id' => $id ),
				array( '%d', '%s' ),
				array( '%d' )
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Redemption uses row locks and current plugin table state.
			if ( 1 !== $updated || false === $wpdb->query( 'COMMIT' ) ) {
				$wpdb->query( 'ROLLBACK' );
				return 'pending';
			}
			return 'issued';
		} catch ( \Throwable $error ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Redemption uses row locks and current plugin table state.
			$wpdb->query( 'ROLLBACK' );
			return 'pending';
		}
	}
}
