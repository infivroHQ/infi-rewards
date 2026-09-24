<?php
namespace InfiRewards\Core;

defined( 'ABSPATH' ) || exit;

class Plugin {
	/** @var Plugin|null */
	private static $instance = null;

	private function __construct() {
		// Private to enforce singleton
	}

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot(): void {
		// Register core components, hooks, and services
		$this->register_admin();
		$this->register_api();
		$this->register_woocommerce();
	}

	protected function register_admin(): void {
		if ( is_admin() ) {
			if ( class_exists( \InfiRewards\Admin\Admin::class ) ) {
				\InfiRewards\Admin\Admin::init();
			}
		}
	}

	protected function register_api(): void {
		if ( class_exists( \InfiRewards\API\Routes::class ) ) {
			\InfiRewards\API\Routes::register();
		}
	}

	protected function register_woocommerce(): void {
		if ( class_exists( \InfiRewards\WooCommerce\Hooks::class ) ) {
			\InfiRewards\WooCommerce\Hooks::init();
		}
	}
}
