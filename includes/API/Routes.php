<?php
namespace InfiRewards\API;

defined( 'ABSPATH' ) || exit;

class Routes {
	public static function register(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes(): void {
		$namespace = 'infirewards/v1';

		register_rest_route(
			$namespace,
			'/rules',
			array(
				'methods'  => 'GET',
				'callback' => array( __CLASS__, 'handle_rules_get' ),
			)
		);

		register_rest_route(
			$namespace,
			'/wallet',
			array(
				'methods'  => 'GET',
				'callback' => array( __CLASS__, 'handle_wallet_get' ),
			)
		);

		register_rest_route(
			$namespace,
			'/transactions',
			array(
				'methods'  => 'GET',
				'callback' => array( __CLASS__, 'handle_transactions_get' ),
			)
		);
	}

	public static function handle_rules_get( $request ) {
		return rest_ensure_response( array( 'message' => 'rules endpoint (stub)' ) );
	}

	public static function handle_wallet_get( $request ) {
		return rest_ensure_response( array( 'message' => 'wallet endpoint (stub)' ) );
	}

	public static function handle_transactions_get( $request ) {
		return rest_ensure_response( array( 'message' => 'transactions endpoint (stub)' ) );
	}
}
