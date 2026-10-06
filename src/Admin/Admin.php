<?php
namespace InfiRewards\Admin;

use InfiRewards\Rules\RulesEngine;
use InfiRewards\Rewards\RewardRepository;

defined( 'ABSPATH' ) || exit;

class Admin {
	private static $page_hooks = array();

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'admin_post_infirewards_save_earning_rule', array( __CLASS__, 'save_earning_rule' ) );
		add_action( 'admin_post_infirewards_save_reward', array( __CLASS__, 'save_reward' ) );
		add_action( 'admin_post_infirewards_save_display', array( __CLASS__, 'save_display' ) );
	}

	public static function register_menu(): void {
		$dashboard_hook = add_menu_page(
			__( 'Infivro Loyalty Rewards for WooCommerce', 'infivro-loyalty-rewards' ),
			__( 'Infivro Rewards', 'infivro-loyalty-rewards' ),
			'manage_options',
			'infirewards',
			array( __CLASS__, 'render_dashboard' ),
			plugins_url( 'assets/images/infivro-rewards-icon.png', INFIREWARDS_PLUGIN_FILE )
		);
		add_submenu_page(
			'infirewards',
			__( 'Overview', 'infivro-loyalty-rewards' ),
			__( 'Overview', 'infivro-loyalty-rewards' ),
			'manage_options',
			'infirewards',
			array( __CLASS__, 'render_dashboard' )
		);
		$rule_hook        = add_submenu_page(
			'infirewards',
			__( 'Earning Rules', 'infivro-loyalty-rewards' ),
			__( 'Earning Rules', 'infivro-loyalty-rewards' ),
			'manage_options',
			'infirewards-earning-rule',
			array( __CLASS__, 'render_earning_rule' )
		);
		$rewards_hook     = add_submenu_page(
			'infirewards',
			__( 'Rewards', 'infivro-loyalty-rewards' ),
			__( 'Rewards', 'infivro-loyalty-rewards' ),
			'manage_options',
			'infirewards-rewards',
			array( __CLASS__, 'render_rewards' )
		);
		$customers_hook   = add_submenu_page(
			'infirewards',
			__( 'Customers', 'infivro-loyalty-rewards' ),
			__( 'Customers', 'infivro-loyalty-rewards' ),
			'manage_options',
			'infirewards-customers',
			array( __CLASS__, 'render_customers' )
		);
		$settings_hook    = add_submenu_page(
			'infirewards',
			__( 'Settings', 'infivro-loyalty-rewards' ),
			__( 'Settings', 'infivro-loyalty-rewards' ),
			'manage_options',
			'infirewards-settings',
			array( __CLASS__, 'render_settings' )
		);
		self::$page_hooks = array(
			'overview'     => $dashboard_hook,
			'earning-rule' => $rule_hook,
			'rewards'      => $rewards_hook,
			'customers'    => $customers_hook,
			'settings'     => $settings_hook,
		);
		foreach ( self::$page_hooks as $hook ) {
			add_action( 'load-' . $hook, array( __CLASS__, 'prepare_quiet_screen' ) );
		}
	}

	public static function enqueue_admin_assets( string $hook ): void {
		// The sidebar icon appears on every admin screen.
		wp_enqueue_style( 'infirewards-menu', plugins_url( 'assets/css/admin-menu.css', INFIREWARDS_PLUGIN_FILE ), array(), INFIREWARDS_VERSION );
		if ( ! in_array( $hook, self::$page_hooks, true ) ) {
			return;
		}
		wp_enqueue_style( 'infirewards-pages', plugins_url( 'assets/css/admin-pages.css', INFIREWARDS_PLUGIN_FILE ), array(), INFIREWARDS_VERSION );
		// Catch notices printed directly or added to the notice area by JavaScript.
		wp_add_inline_style( 'infirewards-pages', '#wpbody-content > :is(.notice, .update-nag, .updated, .error, .woocommerce-message, .woocommerce-error, .woocommerce-info, #message) { display: none !important; }' );
		if ( self::$page_hooks['overview'] === $hook ) {
			wp_enqueue_style( 'infirewards-overview', plugins_url( 'assets/css/admin-overview.css', INFIREWARDS_PLUGIN_FILE ), array(), INFIREWARDS_VERSION );
		} elseif ( self::$page_hooks['earning-rule'] === $hook ) {
			wp_enqueue_style( 'infirewards-earning-rules', plugins_url( 'assets/css/admin-earning-rules.css', INFIREWARDS_PLUGIN_FILE ), array(), INFIREWARDS_VERSION );
		}
	}

	/** Suppress outside admin notices on Infivro Loyalty Rewards for WooCommerce screens only. */
	public static function prepare_quiet_screen(): void {
		add_action( 'admin_head', array( __CLASS__, 'suppress_external_notices' ), PHP_INT_MAX );
	}

	/** Remove standard notice callbacks just before WordPress displays them. */
	public static function suppress_external_notices(): void {
		foreach ( array( 'admin_notices', 'all_admin_notices', 'network_admin_notices', 'user_admin_notices' ) as $hook ) {
			remove_all_actions( $hook );
		}
	}

	public static function render_dashboard(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		Overview::render();
	}

	public static function render_settings(): void {
		if ( current_user_can( 'manage_options' ) ) {
			Settings::render();
		}
	}

	public static function render_customers(): void {
		if ( current_user_can( 'manage_options' ) ) {
			Customers::render();
		}
	}

	/** Save whether rewards appear in the account menu. */
	public static function save_display(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot edit display settings.', 'infivro-loyalty-rewards' ) );
		}
		check_admin_referer( 'infirewards_save_display' );
		$enabled = isset( $_POST['show_in_my_account'] ) && is_scalar( $_POST['show_in_my_account'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['show_in_my_account'] ) );
		update_option( 'infirewards_show_in_my_account', $enabled ? 'yes' : 'no', false );
		wp_safe_redirect( add_query_arg( 'infirewards_notice', 'saved', admin_url( 'admin.php?page=infirewards-settings' ) ) );
		exit;
	}

	public static function save_earning_rule(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot edit earning rules.', 'infivro-loyalty-rewards' ) );
		}
		check_admin_referer( 'infirewards_save_earning_rule' );
		$rate   = isset( $_POST['rate'] ) && is_scalar( $_POST['rate'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['rate'] ) ) ) : '';
		$active = isset( $_POST['active'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['active'] ) );
		$saved  = RulesEngine::get_instance()->save_rule( $rate, $active );
		wp_safe_redirect( add_query_arg( 'infirewards_notice', $saved ? 'saved' : 'invalid', admin_url( 'admin.php?page=infirewards-earning-rule' ) ) );
		exit;
	}

	/** Handle a reward edit submitted by an administrator. */
	public static function save_reward(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot edit rewards.', 'infivro-loyalty-rewards' ) );
		}
		check_admin_referer( 'infirewards_save_reward' );
		$reward_id = isset( $_POST['reward_id'] ) && is_scalar( $_POST['reward_id'] ) ? absint( wp_unslash( $_POST['reward_id'] ) ) : 0;
		$name      = isset( $_POST['name'] ) && is_scalar( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$amount    = isset( $_POST['discount_amount'] ) && is_scalar( $_POST['discount_amount'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['discount_amount'] ) ) ) : '';
		$cost      = isset( $_POST['points_cost'] ) && is_scalar( $_POST['points_cost'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['points_cost'] ) ) ) : '';
		$active    = isset( $_POST['active'] ) && is_scalar( $_POST['active'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['active'] ) );
		$saved     = ( new RewardRepository() )->save( $reward_id, $name, $amount, $cost, $active );
		$url       = admin_url( 'admin.php?page=infirewards-rewards' );
		if ( $reward_id > 0 ) {
			$url = add_query_arg( 'reward_id', $reward_id, $url );
		}
		wp_safe_redirect( add_query_arg( 'infirewards_notice', $saved ? 'saved' : 'invalid', $url ) );
		exit;
	}

	/** Render the reward form and list. */
	public static function render_rewards(): void {
		if ( current_user_can( 'manage_options' ) ) {
			Rewards::render();
		}
	}

	public static function render_earning_rule(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		EarningRules::render();
	}
}
