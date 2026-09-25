<?php
/**
 * Plugin Name: infiRewards – Loyalty Points & Rewards
 * Plugin URI:  https://infivro.com
 * Description: Loyalty points and rewards for WooCommerce.
 * Version:     0.1.0
 * Author:      Infivro
 * Author URI:  https://infivro.com
 * Text Domain: infi-rewards
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 7.0
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

defined( 'ABSPATH' ) || exit;

// Use Composer when available, with a fallback for plugin packages without vendor/.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
} else {
	spl_autoload_register(
		function ( string $class_name ): void {
			$prefix = 'InfiRewards\\';
			if ( 0 !== strpos( $class_name, $prefix ) ) {
				return;
			}

			$relative = substr( $class_name, strlen( $prefix ) );
			$file     = __DIR__ . '/src/' . str_replace( '\\', '/', $relative ) . '.php';
			if ( is_file( $file ) ) {
				require_once $file;
			}
		}
	);
}

// Define plugin constants
if ( ! defined( 'INFIREWARDS_VERSION' ) ) {
	define( 'INFIREWARDS_VERSION', '0.1.0' );
}

if ( ! defined( 'INFIREWARDS_PLUGIN_FILE' ) ) {
	define( 'INFIREWARDS_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'INFIREWARDS_PLUGIN_DIR' ) ) {
	define( 'INFIREWARDS_PLUGIN_DIR', __DIR__ );
}

// Boot the plugin.
try {
	if ( class_exists( \InfiRewards\Plugin::class ) ) {
		\InfiRewards\Plugin::get_instance()->boot();
	}
} catch ( Throwable $e ) {
	// Avoid a fatal error on sites with an incomplete dependency installation.
	do_action( 'infirewards_boot_error', $e );
}

// Register activation hook to create database tables.
// This will create the following tables using dbDelta:
// - {prefix}infirewards_wallets (wallet_id, user_id, balance, updated_at)
// - {prefix}infirewards_transactions (transaction_id, user_id, order_id, points, reason, created_at)
// - {prefix}infirewards_rules (rule_id, type, config, points, status)
// dbDelta handles charset, collation, and creating/updating indexes safely. The installer is idempotent.
register_activation_hook(
	__FILE__,
	function () {
		if ( class_exists( \InfiRewards\Database\Installer::class ) ) {
			// Use the Installer singleton to create or update required tables.
			\InfiRewards\Database\Installer::get_instance()->install();
		}
	}
);

register_deactivation_hook(
	__FILE__,
	function (): void {
		delete_option( 'infirewards_account_endpoint_rewrite_version' );
		flush_rewrite_rules();
	}
);

/**
 * Uninstall handler wrapper.
 * WordPress will include this plugin file before calling the uninstall callback, so ensure
 * the Installer class is available (composer autoload may be required).
 * This function delegates to the Installer singleton to perform table drops and cleanup.
 */
function infirewards_uninstall(): void {
	// Attempt to load autoloader if present
	if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
		require_once __DIR__ . '/vendor/autoload.php';
	}

	if ( class_exists( \InfiRewards\Database\Installer::class ) ) {
		\InfiRewards\Database\Installer::get_instance()->uninstall();
	}

	delete_option( 'infirewards_show_in_my_account' );
	delete_option( 'infirewards_account_endpoint_rewrite_version' );
}

register_uninstall_hook( __FILE__, 'infirewards_uninstall' );
