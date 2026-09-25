<?php
/**
 * Earning rule administration.
 *
 * @package InfiRewards
 */

namespace InfiRewards\Admin;

use InfiRewards\Rules\RulesEngine;

defined( 'ABSPATH' ) || exit;

/** Render the purchase earning rule screen. */
class EarningRules {
	/** Render the rule list and editor. */
	public static function render(): void {
		$rule     = RulesEngine::get_instance()->get_rule();
		$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only redirect notice.
		$notice = isset( $_GET['infirewards_notice'] ) && is_scalar( $_GET['infirewards_notice'] ) ? sanitize_key( wp_unslash( $_GET['infirewards_notice'] ) ) : '';
		$active = $rule && 1 === (int) $rule['status'];
		?>
		<div class="wrap infirewards-rules">
			<?php PageHeader::render( 'infirewards-earning-rule' ); ?>
			<div class="infirewards-rules__heading">
				<div>
					<h2><?php esc_html_e( 'Earning Rules', 'infi-rewards' ); ?></h2>
					<p><?php esc_html_e( 'Set how customers earn points from eligible purchases.', 'infi-rewards' ); ?></p>
				</div>
				<a class="button button-primary" href="#infirewards-rule-form"><?php echo esc_html( $rule ? __( 'Edit Rule', 'infi-rewards' ) : __( 'Add Rule', 'infi-rewards' ) ); ?></a>
			</div>
			<?php if ( 'saved' === $notice ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Earning rule saved.', 'infi-rewards' ); ?></p></div>
			<?php elseif ( 'invalid' === $notice ) : ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'Enter a nonnegative rate with up to four decimal places (maximum 1,000,000).', 'infi-rewards' ); ?></p></div>
			<?php endif; ?>
			<section class="infirewards-rules__panel" aria-labelledby="infirewards-rule-list-title">
				<div class="infirewards-rules__section-heading">
					<h3 id="infirewards-rule-list-title"><?php esc_html_e( 'All Rules', 'infi-rewards' ); ?></h3>
					<?php // translators: %d is the number of configured earning rules. ?>
					<span><?php echo esc_html( sprintf( _n( '%d rule', '%d rules', $rule ? 1 : 0, 'infi-rewards' ), $rule ? 1 : 0 ) ); ?></span>
				</div>
				<div class="infirewards-rules__table-wrap">
					<table class="widefat infirewards-rules__table">
						<thead><tr>
							<th scope="col"><?php esc_html_e( 'Rule', 'infi-rewards' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Trigger', 'infi-rewards' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Points', 'infi-rewards' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Status', 'infi-rewards' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Action', 'infi-rewards' ); ?></th>
						</tr></thead>
						<tbody>
						<?php if ( $rule ) : ?>
							<tr>
								<td><span class="infirewards-rules__rule-name"><span class="dashicons dashicons-cart" aria-hidden="true"></span><span><strong><?php esc_html_e( 'Purchase Points', 'infi-rewards' ); ?></strong><small><?php esc_html_e( 'Earn points on completed eligible orders', 'infi-rewards' ); ?></small></span></span></td>
								<td><span class="infirewards-rules__trigger"><?php esc_html_e( 'Purchase', 'infi-rewards' ); ?></span></td>
								<?php // translators: %s is the WooCommerce store currency code. ?>
								<td><strong><?php echo esc_html( $rule['rate'] ); ?></strong> <?php echo esc_html( sprintf( __( 'points per %s', 'infi-rewards' ), $currency ) ); ?></td>
								<td><span class="infirewards-rules__status <?php echo $active ? 'is-active' : 'is-inactive'; ?>"><?php echo esc_html( $active ? __( 'Active', 'infi-rewards' ) : __( 'Inactive', 'infi-rewards' ) ); ?></span></td>
								<td><a href="#infirewards-rule-form"><?php esc_html_e( 'Edit', 'infi-rewards' ); ?></a></td>
							</tr>
						<?php else : ?>
							<tr><td colspan="5" class="infirewards-rules__empty"><?php esc_html_e( 'No earning rule yet. Add a purchase rule to start awarding points.', 'infi-rewards' ); ?></td></tr>
						<?php endif; ?>
						</tbody>
					</table>
				</div>
			</section>
			<div class="infirewards-rules__bottom">
				<section class="infirewards-rules__panel infirewards-rules__form" id="infirewards-rule-form" aria-labelledby="infirewards-rule-form-title">
					<h3 id="infirewards-rule-form-title"><?php echo esc_html( $rule ? __( 'Edit Purchase Rule', 'infi-rewards' ) : __( 'Create Purchase Rule', 'infi-rewards' ) ); ?></h3>
					<p><?php esc_html_e( 'Choose the number of points earned for each unit of store currency.', 'infi-rewards' ); ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="infirewards_save_earning_rule">
						<?php wp_nonce_field( 'infirewards_save_earning_rule' ); ?>
						<label for="infirewards-rate"><?php esc_html_e( 'Points per currency unit', 'infi-rewards' ); ?></label>
						<div class="infirewards-rules__input-row"><input id="infirewards-rate" name="rate" type="number" min="0" max="1000000" step="0.01" required value="<?php echo esc_attr( $rule ? $rule['rate'] : '1' ); ?>"><span><?php echo esc_html( $currency ); ?></span></div>
						<p class="description"><?php esc_html_e( 'Up to two decimal places. Points are rounded down to whole numbers.', 'infi-rewards' ); ?></p>
						<label class="infirewards-rules__checkbox"><input type="checkbox" name="active" value="1" <?php checked( $rule ? $active : true ); ?>> <?php esc_html_e( 'Active', 'infi-rewards' ); ?></label>
						<p class="infirewards-rules__submit"><button class="button button-primary" type="submit"><?php echo esc_html( $rule ? __( 'Save Rule', 'infi-rewards' ) : __( 'Create Rule', 'infi-rewards' ) ); ?></button></p>
					</form>
				</section>
				<aside class="infirewards-rules__panel infirewards-rules__guide">
					<h3><?php esc_html_e( 'How purchase points work', 'infi-rewards' ); ?></h3>
					<div class="infirewards-rules__guide-icon"><span class="dashicons dashicons-info-outline" aria-hidden="true"></span></div>
					<p><?php esc_html_e( 'Points are awarded when a logged-in customer’s order is completed.', 'infi-rewards' ); ?></p>
					<ul>
						<li><?php esc_html_e( 'The eligible amount is the paid total after discounts.', 'infi-rewards' ); ?></li>
						<li><?php esc_html_e( 'Shipping and taxes are excluded.', 'infi-rewards' ); ?></li>
						<li><?php esc_html_e( 'Guest orders do not earn points.', 'infi-rewards' ); ?></li>
					</ul>
				</aside>
			</div>
		</div>
		<?php
	}
}
