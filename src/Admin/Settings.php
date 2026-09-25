<?php
/**
 * Loyalty program settings in wp-admin.
 *
 * @package InfiRewards
 */

namespace InfiRewards\Admin;

use InfiRewards\Customer\AccountEndpoint;
use InfiRewards\Rules\RulesEngine;

defined( 'ABSPATH' ) || exit;

/** Loyalty program settings in wp-admin. */
class Settings {
	/** Render the page. */
	public static function render(): void {
		$rule     = RulesEngine::get_instance()->get_rule();
		$active   = $rule && 1 === (int) $rule['status'] && (float) $rule['rate'] > 0;
		$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This notice only changes displayed text.
		$notice   = isset( $_GET['infirewards_notice'] ) && is_scalar( $_GET['infirewards_notice'] ) ? sanitize_key( wp_unslash( $_GET['infirewards_notice'] ) ) : '';
		?>
		<div class="wrap infirewards-page">
			<?php PageHeader::render( 'infirewards-settings' ); ?>
			<div class="infirewards-page__heading"><h2><?php esc_html_e( 'Settings', 'infirewards' ); ?></h2></div>
			<?php
			if ( 'saved' === $notice ) :
				?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Settings saved.', 'infirewards' ); ?></p></div><?php endif; ?>
			<div class="infirewards-page__settings-grid">
				<section class="infirewards-page__panel"><h3><span class="dashicons dashicons-admin-generic" aria-hidden="true"></span><?php esc_html_e( 'General', 'infirewards' ); ?></h3><p class="infirewards-page__muted"><?php esc_html_e( 'Your current rewards program configuration.', 'infirewards' ); ?></p>
					<dl class="infirewards-page__setting-list"><div><dt><?php esc_html_e( 'Points label', 'infirewards' ); ?></dt><dd><?php esc_html_e( 'Points', 'infirewards' ); ?></dd></div><div><dt><?php esc_html_e( 'Earning rule', 'infirewards' ); ?></dt><dd><span class="infirewards-page__status <?php echo $active ? 'is-active' : 'is-inactive'; ?>"><?php echo esc_html( $active ? __( 'Active', 'infirewards' ) : __( 'Inactive', 'infirewards' ) ); ?></span><a href="<?php echo esc_url( admin_url( 'admin.php?page=infirewards-earning-rule' ) ); ?>"><?php esc_html_e( 'Edit rule', 'infirewards' ); ?></a></dd></div><div><dt><?php esc_html_e( 'Store currency', 'infirewards' ); ?></dt><dd><?php echo esc_html( $currency ); ?></dd></div><div><dt><?php esc_html_e( 'Decimal handling', 'infirewards' ); ?></dt><dd><?php esc_html_e( 'Points earned are rounded down to whole numbers.', 'infirewards' ); ?></dd></div></dl>
				</section>
				<section class="infirewards-page__panel"><h3><span class="dashicons dashicons-admin-users" aria-hidden="true"></span><?php esc_html_e( 'Customer Page', 'infirewards' ); ?></h3><p class="infirewards-page__muted"><?php esc_html_e( 'Choose where customers can access their points and rewards.', 'infirewards' ); ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="infirewards_save_display"><?php wp_nonce_field( 'infirewards_save_display' ); ?>
						<label class="infirewards-page__setting-toggle"><span><strong><?php esc_html_e( 'Enable My Account endpoint', 'infirewards' ); ?></strong><small><?php esc_html_e( 'Show Points & Rewards in the WooCommerce My Account menu.', 'infirewards' ); ?></small></span><input type="checkbox" name="show_in_my_account" value="1" <?php checked( AccountEndpoint::is_enabled() ); ?>></label>
						<p class="infirewards-page__muted"><?php esc_html_e( 'The shortcode below works even when this menu entry is off.', 'infirewards' ); ?></p>
						<p class="infirewards-page__actions"><button class="button button-primary" type="submit"><?php esc_html_e( 'Save Changes', 'infirewards' ); ?></button></p>
					</form>
				</section>
			</div>
			<section class="infirewards-page__panel infirewards-page__shortcode"><h3><span class="dashicons dashicons-shortcode" aria-hidden="true"></span><?php esc_html_e( 'Shortcode', 'infirewards' ); ?></h3><p class="infirewards-page__muted"><?php esc_html_e( 'Add this shortcode to a page or post to show customers their balance, available rewards, and history.', 'infirewards' ); ?></p><code>[infirewards]</code></section>
		</div>
		<?php
	}
}
