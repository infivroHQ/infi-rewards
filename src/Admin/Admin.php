<?php
namespace InfiRewards\Admin;

use InfiRewards\Rules\RulesEngine;
use InfiRewards\Rewards\RewardRepository;

defined( 'ABSPATH' ) || exit;

class Admin {
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_post_infirewards_save_earning_rule', array( __CLASS__, 'save_earning_rule' ) );
		add_action( 'admin_post_infirewards_save_reward', array( __CLASS__, 'save_reward' ) );
	}

	public static function register_menu(): void {
		add_menu_page(
			__( 'infiRewards', 'infirewards' ),
			__( 'infiRewards', 'infirewards' ),
			'manage_options',
			'infirewards',
			array( __CLASS__, 'render_dashboard' ),
			'dashicons-star-filled'
		);
		add_submenu_page(
			'infirewards',
			__( 'Earning Rule', 'infirewards' ),
			__( 'Earning Rule', 'infirewards' ),
			'manage_options',
			'infirewards-earning-rule',
			array( __CLASS__, 'render_earning_rule' )
		);
		add_submenu_page(
			'infirewards',
			__( 'Rewards', 'infirewards' ),
			__( 'Rewards', 'infirewards' ),
			'manage_options',
			'infirewards-rewards',
			array( __CLASS__, 'render_rewards' )
		);
	}

	public static function render_dashboard(): void {
		echo '<div class="wrap"><h1>' . esc_html__( 'infiRewards', 'infirewards' ) . '</h1></div>';
	}

	public static function save_earning_rule(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot edit earning rules.', 'infirewards' ) );
		}
		check_admin_referer( 'infirewards_save_earning_rule' );
		$rate = isset( $_POST['rate'] ) && is_scalar( $_POST['rate'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['rate'] ) ) ) : '';
		$active = isset( $_POST['active'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['active'] ) );
		$saved = RulesEngine::get_instance()->save_rule( $rate, $active );
		wp_safe_redirect( add_query_arg( 'infirewards_notice', $saved ? 'saved' : 'invalid', admin_url( 'admin.php?page=infirewards-earning-rule' ) ) );
		exit;
	}

	/** Handle a reward edit submitted by an administrator. */
	public static function save_reward(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot edit rewards.', 'infirewards' ) );
		}
		check_admin_referer( 'infirewards_save_reward' );
		$reward_id = isset( $_POST['reward_id'] ) && is_scalar( $_POST['reward_id'] ) ? absint( wp_unslash( $_POST['reward_id'] ) ) : 0;
		$name = isset( $_POST['name'] ) && is_scalar( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$amount = isset( $_POST['discount_amount'] ) && is_scalar( $_POST['discount_amount'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['discount_amount'] ) ) ) : '';
		$cost = isset( $_POST['points_cost'] ) && is_scalar( $_POST['points_cost'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['points_cost'] ) ) ) : '';
		$active = isset( $_POST['active'] ) && is_scalar( $_POST['active'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['active'] ) );
		$saved = ( new RewardRepository() )->save( $reward_id, $name, $amount, $cost, $active );
		$url = admin_url( 'admin.php?page=infirewards-rewards' );
		if ( $reward_id > 0 ) {
			$url = add_query_arg( 'reward_id', $reward_id, $url );
		}
		wp_safe_redirect( add_query_arg( 'infirewards_notice', $saved ? 'saved' : 'invalid', $url ) );
		exit;
	}

	/** Render the reward form and list. */
	public static function render_rewards(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$repository = new RewardRepository();
		$reward_id = isset( $_GET['reward_id'] ) && is_scalar( $_GET['reward_id'] ) ? absint( wp_unslash( $_GET['reward_id'] ) ) : 0;
		$reward = $reward_id > 0 ? $repository->get( $reward_id ) : null;
		$notice = isset( $_GET['infirewards_notice'] ) && is_scalar( $_GET['infirewards_notice'] ) ? sanitize_key( wp_unslash( $_GET['infirewards_notice'] ) ) : '';
		$decimals = function_exists( 'wc_get_price_decimals' ) ? (int) wc_get_price_decimals() : 2;
		$decimals = max( 0, min( 6, $decimals ) );
		$step = 0 === $decimals ? '1' : '0.' . str_repeat( '0', $decimals - 1 ) . '1';
		$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '';
		echo '<div class="wrap"><h1>' . esc_html__( 'Rewards', 'infirewards' ) . '</h1>';
		if ( 'saved' === $notice ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Reward saved.', 'infirewards' ) . '</p></div>';
		} elseif ( 'invalid' === $notice || ( $reward_id > 0 && ! $reward ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Could not save the reward. Enter a name, a positive discount in the store currency, and a whole points cost from 1 to 2,147,483,647.', 'infirewards' ) . '</p></div>';
		}
		echo '<h2>' . esc_html( $reward ? __( 'Edit Reward', 'infirewards' ) : __( 'Create Reward', 'infirewards' ) ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="infirewards_save_reward">';
		echo '<input type="hidden" name="reward_id" value="' . esc_attr( $reward ? $reward['reward_id'] : 0 ) . '">';
		wp_nonce_field( 'infirewards_save_reward' );
		echo '<table class="form-table"><tr><th scope="row"><label for="infirewards-reward-name">' . esc_html__( 'Name', 'infirewards' ) . '</label></th><td><input class="regular-text" id="infirewards-reward-name" name="name" type="text" maxlength="255" required value="' . esc_attr( $reward ? $reward['name'] : '' ) . '"></td></tr>';
		echo '<tr><th scope="row"><label for="infirewards-discount">' . esc_html__( 'Fixed cart discount', 'infirewards' ) . '</label></th><td><input id="infirewards-discount" name="discount_amount" type="number" min="' . esc_attr( $step ) . '" step="' . esc_attr( $step ) . '" required value="' . esc_attr( $reward ? $reward['discount_amount'] : '' ) . '"> ' . esc_html( $currency ) . '</td></tr>';
		echo '<tr><th scope="row"><label for="infirewards-cost">' . esc_html__( 'Points cost', 'infirewards' ) . '</label></th><td><input id="infirewards-cost" name="points_cost" type="number" min="1" max="2147483647" step="1" required value="' . esc_attr( $reward ? $reward['points_cost'] : '' ) . '"></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Status', 'infirewards' ) . '</th><td><label><input type="checkbox" name="active" value="1" ' . checked( ! $reward || 1 === (int) $reward['status'], true, false ) . '> ' . esc_html__( 'Active', 'infirewards' ) . '</label></td></tr></table>';
		submit_button( $reward ? __( 'Save Reward', 'infirewards' ) : __( 'Create Reward', 'infirewards' ) );
		echo '</form>';
		if ( $reward ) {
			echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=infirewards-rewards' ) ) . '">' . esc_html__( 'Create another reward', 'infirewards' ) . '</a></p>';
		}
		echo '<h2>' . esc_html__( 'All Rewards', 'infirewards' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Name', 'infirewards' ) . '</th><th>' . esc_html__( 'Discount', 'infirewards' ) . '</th><th>' . esc_html__( 'Points cost', 'infirewards' ) . '</th><th>' . esc_html__( 'Status', 'infirewards' ) . '</th></tr></thead><tbody>';
		$rewards = $repository->all();
		if ( ! $rewards ) {
			echo '<tr><td colspan="4">' . esc_html__( 'No rewards yet.', 'infirewards' ) . '</td></tr>';
		}
		foreach ( $rewards as $item ) {
			$url = add_query_arg( 'reward_id', (int) $item['reward_id'], admin_url( 'admin.php?page=infirewards-rewards' ) );
			echo '<tr><td><a href="' . esc_url( $url ) . '">' . esc_html( $item['name'] ) . '</a></td><td>' . esc_html( $item['discount_amount'] . ' ' . $currency ) . '</td><td>' . esc_html( $item['points_cost'] ) . '</td><td>' . esc_html( 1 === (int) $item['status'] ? __( 'Active', 'infirewards' ) : __( 'Disabled', 'infirewards' ) ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	public static function render_earning_rule(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$rule = RulesEngine::get_instance()->get_rule();
		$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '';
		echo '<div class="wrap"><h1>' . esc_html__( 'Earning Rule', 'infirewards' ) . '</h1>';
		$notice = isset( $_GET['infirewards_notice'] ) && is_scalar( $_GET['infirewards_notice'] ) ? sanitize_key( wp_unslash( $_GET['infirewards_notice'] ) ) : '';
		if ( 'saved' === $notice ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Earning rule saved.', 'infirewards' ) . '</p></div>';
		} elseif ( 'invalid' === $notice ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Enter a nonnegative rate with up to four decimal places (maximum 1,000,000).', 'infirewards' ) . '</p></div>';
		}
		// translators: %s is the WooCommerce store currency code.
		echo '<p>' . esc_html( sprintf( __( 'Customers earn points per one unit of store currency (%s). The eligible amount is the paid order total after discounts, excluding shipping and taxes. Points are rounded down to whole numbers. Guest orders earn no points.', 'infirewards' ), $currency ) ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="infirewards_save_earning_rule">';
		wp_nonce_field( 'infirewards_save_earning_rule' );
		echo '<table class="form-table"><tr><th scope="row"><label for="infirewards-rate">' . esc_html__( 'Points per currency unit', 'infirewards' ) . '</label></th><td><input id="infirewards-rate" name="rate" type="number" min="0" max="1000000" step="0.0001" required value="' . esc_attr( $rule ? $rule['rate'] : '0' ) . '"></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Status', 'infirewards' ) . '</th><td><label><input type="checkbox" name="active" value="1" ' . checked( $rule && 1 === (int) $rule['status'], true, false ) . '> ' . esc_html__( 'Active', 'infirewards' ) . '</label></td></tr></table>';
		submit_button( $rule ? __( 'Save Rule', 'infirewards' ) : __( 'Create Rule', 'infirewards' ) );
		echo '</form></div>';
	}
}
