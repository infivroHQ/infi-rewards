<?php

namespace InfiRewards\Admin;

defined( 'ABSPATH' ) || exit;

class Admin {
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function register_menu(): void {
		add_menu_page(
			__( 'infiRewards', 'infirewards' ),
			__( 'infiRewards', 'infirewards' ),
			'manage_options',
			'infirewards',
			array( __CLASS__, 'render_settings_page' ),
			'dashicons-star-filled'
		);
	}

	public static function render_settings_page(): void {
		echo '<div id="infirewards-admin-app"></div>';
	}

	public static function enqueue_assets(): void {
		$asset_path = plugin_dir_url( INFIREWARDS_PLUGIN_FILE ) . 'assets/build/admin.js';
		wp_register_script( 'infirewards-admin', $asset_path, array(), INFIREWARDS_VERSION, true );
		wp_localize_script(
			'infirewards-admin',
			'infiRewards',
			array(
				'restUrl' => esc_url_raw( rest_url() ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			)
		);
		wp_enqueue_script( 'infirewards-admin' );
		wp_enqueue_style( 'infirewards-admin-css', plugin_dir_url( INFIREWARDS_PLUGIN_FILE ) . 'assets/build/admin.css', array(), INFIREWARDS_VERSION );
	}
}
