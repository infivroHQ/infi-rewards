<?php
/**
 * WooCommerce My Account entry for customer rewards.
 *
 * @package InfiRewards
 */

namespace InfiRewards\Customer;

defined( 'ABSPATH' ) || exit;

/** Add the rewards view to WooCommerce My Account. */
class AccountEndpoint {
	private const ENDPOINT       = 'infirewards';
	private const OPTION         = 'infirewards_show_in_my_account';
	private const REWRITE_OPTION = 'infirewards_account_endpoint_rewrite_version';

	/** Register account hooks. */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register_endpoint' ) );
		add_action( 'init', array( __CLASS__, 'maybe_flush_rewrite_rules' ), 20 );
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'add_menu_item' ) );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( __CLASS__, 'render' ) );
	}

	/** Whether the account menu entry is enabled. */
	public static function is_enabled(): bool {
		return 'yes' === get_option( self::OPTION, 'yes' );
	}

	/** Register the rewards URL beneath the account page. */
	public static function register_endpoint(): void {
		add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
	}

	/** Flush once on existing installations as well as new ones. */
	public static function maybe_flush_rewrite_rules(): void {
		if ( '1' === get_option( self::REWRITE_OPTION ) ) {
			return;
		}
		flush_rewrite_rules();
		update_option( self::REWRITE_OPTION, '1', false );
	}

	/**
	 * Add the rewards item before logout.
	 *
	 * @param array $items Existing account menu items.
	 * @return array
	 */
	public static function add_menu_item( array $items ): array {
		if ( ! self::is_enabled() ) {
			return $items;
		}

		// Keep logout last in the account navigation.
		$logout = null;
		if ( isset( $items['customer-logout'] ) ) {
			$logout = $items['customer-logout'];
			unset( $items['customer-logout'] );
		}
		$items[ self::ENDPOINT ] = __( 'Points & Rewards', 'infi-rewards' );
		if ( null !== $logout ) {
			$items['customer-logout'] = $logout;
		}
		return $items;
	}

	/** Render the shared customer rewards view. */
	public static function render(): void {
		if ( self::is_enabled() ) {
			echo RewardsShortcode::render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The view escapes its own output.
		}
	}
}
