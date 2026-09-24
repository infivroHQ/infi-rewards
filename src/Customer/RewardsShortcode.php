<?php
namespace InfiRewards\Customer;

use InfiRewards\Points\WalletService;
use InfiRewards\Rewards\RedemptionService;
use InfiRewards\Rewards\RewardRepository;

defined( 'ABSPATH' ) || exit;

/** Customer balance, rewards and recent activity. */
class RewardsShortcode {
	public static function init(): void {
		add_shortcode( 'infirewards', array( __CLASS__, 'render' ) );
		add_filter( 'woocommerce_coupon_is_valid', array( __CLASS__, 'validate_coupon_owner' ), 10, 2 );
		add_action( 'admin_post_infirewards_redeem', array( __CLASS__, 'redeem' ) );
		add_action( 'admin_post_infirewards_retry_coupon', array( __CLASS__, 'retry' ) );
		add_action( 'admin_post_nopriv_infirewards_redeem', array( __CLASS__, 'login_required' ) );
		add_action( 'admin_post_nopriv_infirewards_retry_coupon', array( __CLASS__, 'login_required' ) );
	}

	/** Restrict issued coupons to the account that spent the points. */
	public static function validate_coupon_owner( $valid, $coupon ): bool {
		$redemption_id = (int) $coupon->get_meta( '_infirewards_redemption_id', true );
		if ( ! $redemption_id ) {
			return (bool) $valid;
		}
		return (bool) $valid && get_current_user_id() === (int) $coupon->get_meta( '_infirewards_user_id', true );
	}

	public static function login_required(): void {
		wp_safe_redirect( wp_login_url( wp_get_referer() ? wp_get_referer() : home_url( '/' ) ) );
		exit;
	}

	public static function redeem(): void {
		$user_id   = get_current_user_id();
		$reward_id = isset( $_POST['reward_id'] ) && is_scalar( $_POST['reward_id'] ) ? absint( wp_unslash( $_POST['reward_id'] ) ) : 0;
		$key       = isset( $_POST['request_key'] ) && is_scalar( $_POST['request_key'] ) ? sanitize_key( wp_unslash( $_POST['request_key'] ) ) : '';
		if ( ! $user_id ) {
			self::login_required();
		}
		if ( ! $reward_id || ! wp_verify_nonce( isset( $_POST['_wpnonce'] ) && is_scalar( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '', 'infirewards_redeem_' . $reward_id ) ) {
			self::redirect( 'invalid' );
		}
		$service = new RedemptionService();
		$result  = $service->redeem( $user_id, $reward_id, $key );
		$record  = $service->get_by_key( $user_id, $key );
		self::redirect( $result, $record ? (int) $record['redemption_id'] : 0 );
	}

	public static function retry(): void {
		$user_id = get_current_user_id();
		$id      = isset( $_POST['redemption_id'] ) && is_scalar( $_POST['redemption_id'] ) ? absint( wp_unslash( $_POST['redemption_id'] ) ) : 0;
		if ( ! $user_id ) {
			self::login_required();
		}
		if ( ! $id || ! wp_verify_nonce( isset( $_POST['_wpnonce'] ) && is_scalar( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '', 'infirewards_retry_' . $id ) ) {
			self::redirect( 'invalid' );
		}
		self::redirect( ( new RedemptionService() )->retry( $user_id, $id ), $id );
	}

	private static function redirect( string $notice, int $id = 0 ): void {
		$url = wp_get_referer() ? wp_get_referer() : home_url( '/' );
		$url = remove_query_arg( array( 'infirewards_notice', 'infirewards_redemption' ), $url );
		wp_safe_redirect(
			add_query_arg(
				array(
					'infirewards_notice'     => $notice,
					'infirewards_redemption' => $id,
				),
				$url
			)
		);
		exit;
	}

	public static function render(): string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Log in to see and redeem rewards.', 'infirewards' ) . '</p>';
		}
		if ( ! class_exists( 'WC_Coupon' ) ) {
			return '<p>' . esc_html__( 'Rewards are temporarily unavailable.', 'infirewards' ) . '</p>';
		}
		$user_id        = get_current_user_id();
		$balance        = WalletService::get_instance()->get_balance( $user_id );
		$service        = new RedemptionService();
		$notice         = isset( $_GET['infirewards_notice'] ) && is_scalar( $_GET['infirewards_notice'] ) ? sanitize_key( wp_unslash( $_GET['infirewards_notice'] ) ) : '';
		$redemption_id  = isset( $_GET['infirewards_redemption'] ) && is_scalar( $_GET['infirewards_redemption'] ) ? absint( wp_unslash( $_GET['infirewards_redemption'] ) ) : 0;
		$noticed_record = $redemption_id ? $service->get( $user_id, $redemption_id ) : null;
		$messages       = array(
			'issued'      => __( 'Reward redeemed. Your coupon is shown below.', 'infirewards' ),
			'pending'     => __( 'Your points were deducted, but the coupon could not be delivered yet. Retry it below; you will not be charged again.', 'infirewards' ),
			'points'      => __( 'You do not have enough points for that reward.', 'infirewards' ),
			'unavailable' => __( 'That reward is no longer available.', 'infirewards' ),
			'email'       => __( 'Add a valid email address to your account before redeeming.', 'infirewards' ),
			'invalid'     => __( 'The redemption request was invalid.', 'infirewards' ),
			'error'       => __( 'Could not redeem the reward. Please try again.', 'infirewards' ),
		);
		ob_start();
		echo '<div class="infirewards-customer"><h2>' . esc_html__( 'Your points and rewards', 'infirewards' ) . '</h2>';
		if ( isset( $messages[ $notice ] ) && ( ! in_array( $notice, array( 'issued', 'pending' ), true ) || $noticed_record ) ) {
			echo '<p role="status">' . esc_html( $messages[ $notice ] ) . '</p>';
			if ( 'issued' === $notice && $noticed_record && 'issued' === $noticed_record['status'] ) {
				echo '<p><strong>' . esc_html__( 'Coupon code:', 'infirewards' ) . '</strong> <code>' . esc_html( $noticed_record['coupon_code'] ) . '</code></p>';
			}
		}
		echo '<p><strong>' . esc_html__( 'Current balance:', 'infirewards' ) . '</strong> ' . esc_html( number_format_i18n( $balance ) ) . ' ' . esc_html__( 'points', 'infirewards' ) . '</p>';
		echo '<h3>' . esc_html__( 'Available rewards', 'infirewards' ) . '</h3>';
		$rewards = array_filter(
			( new RewardRepository() )->all(),
			static function ( $reward ) {
				return 1 === (int) $reward['status'];
			}
		);
		if ( ! $rewards ) {
			echo '<p>' . esc_html__( 'No rewards are available right now.', 'infirewards' ) . '</p>';
		}
		foreach ( $rewards as $reward ) {
			$can_redeem = $balance >= (int) $reward['points_cost'];
			echo '<div class="infirewards-reward"><h4>' . esc_html( $reward['name'] ) . '</h4>';
			echo '<p>' . wp_kses_post( wc_price( $reward['discount_amount'] ) ) . ' ' . esc_html__( 'cart discount', 'infirewards' ) . ' — ' . esc_html( number_format_i18n( $reward['points_cost'] ) ) . ' ' . esc_html__( 'points', 'infirewards' ) . '</p>';
			if ( $can_redeem ) {
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
				echo '<input type="hidden" name="action" value="infirewards_redeem"><input type="hidden" name="reward_id" value="' . esc_attr( $reward['reward_id'] ) . '">';
				echo '<input type="hidden" name="request_key" value="' . esc_attr( str_replace( '-', '', wp_generate_uuid4() ) ) . '">';
				wp_nonce_field( 'infirewards_redeem_' . $reward['reward_id'] );
				echo '<button type="submit">' . esc_html__( 'Redeem', 'infirewards' ) . '</button></form>';
			} else {
				echo '<p>' . esc_html__( 'More points needed', 'infirewards' ) . '</p>';
			}
			echo '</div>';
		}
		echo '<h3>' . esc_html__( 'Recent redemptions', 'infirewards' ) . '</h3>';
		$redemptions = $service->history( $user_id );
		if ( ! $redemptions ) {
			echo '<p>' . esc_html__( 'No redemptions yet.', 'infirewards' ) . '</p>';
		}
		foreach ( $redemptions as $record ) {
			echo '<div class="infirewards-redemption"><p>' . esc_html( $record['created_at'] ) . ': ' . esc_html__( 'Reward', 'infirewards' ) . ' #' . esc_html( $record['reward_id'] );
			if ( 'issued' === $record['status'] ) {
				echo ' — ' . esc_html__( 'Coupon:', 'infirewards' ) . ' <code>' . esc_html( $record['coupon_code'] ) . '</code>';
			} else {
				echo ' — ' . esc_html__( 'Coupon pending', 'infirewards' );
			}
			echo '</p>';
			if ( 'pending' === $record['status'] ) {
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="infirewards_retry_coupon"><input type="hidden" name="redemption_id" value="' . esc_attr( $record['redemption_id'] ) . '">';
				wp_nonce_field( 'infirewards_retry_' . $record['redemption_id'] );
				echo '<button type="submit">' . esc_html__( 'Retry coupon', 'infirewards' ) . '</button></form>';
			}
			echo '</div>';
		}
		echo '<h3>' . esc_html__( 'Recent points activity', 'infirewards' ) . '</h3>';
		$history = $service->points_history( $user_id );
		if ( ! $history ) {
			echo '<p>' . esc_html__( 'No points activity yet.', 'infirewards' ) . '</p>';
		}
		foreach ( $history as $entry ) {
			echo '<p>' . esc_html( $entry['created_at'] ) . ': ' . esc_html( 'credit' === $entry['type'] ? '+' : '-' ) . esc_html( number_format_i18n( $entry['points'] ) ) . ' ' . esc_html( $entry['reason'] ) . '</p>';
		}
		echo '</div>';
		return (string) ob_get_clean();
	}
}
