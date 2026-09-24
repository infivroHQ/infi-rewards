<?php
namespace InfiRewards\Admin;

use InfiRewards\Rules\RulesEngine;

defined( 'ABSPATH' ) || exit;

class Admin {
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_post_infirewards_save_earning_rule', array( __CLASS__, 'save_earning_rule' ) );
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

	public static function render_earning_rule(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$rule = RulesEngine::get_instance()->get_rule();
		$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '';
		echo '<div class="wrap"><h1>' . esc_html__( 'Earning Rule', 'infirewards' ) . '</h1>';
		$notice = isset( $_GET['infirewards_notice'] ) ? sanitize_key( wp_unslash( $_GET['infirewards_notice'] ) ) : '';
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
