<?php

namespace InfiRewards\Admin;

defined( 'ABSPATH' ) || exit;

class Admin {
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
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
	}

	public static function render_dashboard(): void {
		echo '<div class="wrap"><h1>' . esc_html__( 'infiRewards', 'infirewards' ) . '</h1></div>';
	}
}
