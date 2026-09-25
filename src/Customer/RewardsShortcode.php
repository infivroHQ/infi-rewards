<?php
namespace InfiRewards\Customer;

use InfiRewards\Rewards\RedemptionService;

defined( 'ABSPATH' ) || exit;

/** Customer balance, rewards and recent activity. */
class RewardsShortcode {
	public static function init(): void {
		add_shortcode( 'infirewards', array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_styles' ) );
		add_filter( 'woocommerce_coupon_is_valid', array( __CLASS__, 'validate_coupon_owner' ), 10, 2 );
		add_action( 'admin_post_infirewards_redeem', array( __CLASS__, 'redeem' ) );
		add_action( 'admin_post_infirewards_retry_coupon', array( __CLASS__, 'retry' ) );
		add_action( 'admin_post_nopriv_infirewards_redeem', array( __CLASS__, 'login_required' ) );
		add_action( 'admin_post_nopriv_infirewards_retry_coupon', array( __CLASS__, 'login_required' ) );
	}

	public static function enqueue_styles(): void {
		if ( ( function_exists( 'is_account_page' ) && is_account_page() ) || ( is_singular() && get_post() && has_shortcode( get_post()->post_content, 'infirewards' ) ) ) {
			wp_enqueue_style( 'infirewards-customer', plugins_url( 'assets/css/customer-rewards.css', INFIREWARDS_PLUGIN_FILE ), array(), INFIREWARDS_VERSION );
			wp_enqueue_script( 'infirewards-customer', plugins_url( 'assets/js/customer-rewards.js', INFIREWARDS_PLUGIN_FILE ), array(), INFIREWARDS_VERSION, true );
		}
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
		return RewardsPage::render();
	}
}
