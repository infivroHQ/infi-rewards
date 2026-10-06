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
					<h2><?php esc_html_e( 'Earning Rules', 'infivro-loyalty-rewards' ); ?></h2>
					<p><?php esc_html_e( 'Set how customers earn points from eligible purchases.', 'infivro-loyalty-rewards' ); ?></p>
				</div>
				<a class="button button-primary" href="#infirewards-rule-form"><?php echo esc_html( $rule ? __( 'Edit Rule', 'infivro-loyalty-rewards' ) : __( 'Add Rule', 'infivro-loyalty-rewards' ) ); ?></a>
			</div>
			<?php if ( 'saved' === $notice ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Earning rule saved.', 'infivro-loyalty-rewards' ); ?></p></div>
			<?php elseif ( 'invalid' === $notice ) : ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'Enter a nonnegative rate with up to four decimal places (maximum 1,000,000).', 'infivro-loyalty-rewards' ); ?></p></div>
			<?php endif; ?>
			<section class="infirewards-rules__panel" aria-labelledby="infirewards-rule-list-title">
				<div class="infirewards-rules__section-heading">
					<h3 id="infirewards-rule-list-title"><?php esc_html_e( 'All Rules', 'infivro-loyalty-rewards' ); ?></h3>
					<?php // translators: %d is the number of configured earning rules. ?>
					<span><?php echo esc_html( sprintf( _n( '%d rule', '%d rules', $rule ? 1 : 0, 'infivro-loyalty-rewards' ), $rule ? 1 : 0 ) ); ?></span>
				</div>
				<div class="infirewards-rules__table-wrap">
					<table class="widefat infirewards-rules__table">
						<thead><tr>
							<th scope="col"><?php esc_html_e( 'Rule', 'infivro-loyalty-rewards' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Trigger', 'infivro-loyalty-rewards' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Points', 'infivro-loyalty-rewards' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Status', 'infivro-loyalty-rewards' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Action', 'infivro-loyalty-rewards' ); ?></th>
						</tr></thead>
						<tbody>
						<?php if ( $rule ) : ?>
							<tr>
								<td><span class="infirewards-rules__rule-name"><span class="dashicons dashicons-cart" aria-hidden="true"></span><span><strong><?php esc_html_e( 'Purchase Points', 'infivro-loyalty-rewards' ); ?></strong><small><?php esc_html_e( 'Earn points on completed eligible orders', 'infivro-loyalty-rewards' ); ?></small></span></span></td>
								<td><span class="infirewards-rules__trigger"><?php esc_html_e( 'Purchase', 'infivro-loyalty-rewards' ); ?></span></td>
								<?php // translators: %s is the WooCommerce store currency code. ?>
								<td><strong><?php echo esc_html( $rule['rate'] ); ?></strong> <?php echo esc_html( sprintf( __( 'points per %s', 'infivro-loyalty-rewards' ), $currency ) ); ?></td>
								<td><span class="infirewards-rules__status <?php echo $active ? 'is-active' : 'is-inactive'; ?>"><?php echo esc_html( $active ? __( 'Active', 'infivro-loyalty-rewards' ) : __( 'Inactive', 'infivro-loyalty-rewards' ) ); ?></span></td>
								<td><a href="#infirewards-rule-form"><?php esc_html_e( 'Edit', 'infivro-loyalty-rewards' ); ?></a></td>
							</tr>
						<?php else : ?>
							<tr><td colspan="5" class="infirewards-rules__empty"><?php esc_html_e( 'No earning rule yet. Add a purchase rule to start awarding points.', 'infivro-loyalty-rewards' ); ?></td></tr>
						<?php endif; ?>
						</tbody>
					</table>
				</div>
			</section>
			<div class="infirewards-rules__bottom">
				<section class="infirewards-rules__panel infirewards-rules__form" id="infirewards-rule-form" aria-labelledby="infirewards-rule-form-title">
					<h3 id="infirewards-rule-form-title"><?php echo esc_html( $rule ? __( 'Edit Purchase Rule', 'infivro-loyalty-rewards' ) : __( 'Create Purchase Rule', 'infivro-loyalty-rewards' ) ); ?></h3>
					<p><?php esc_html_e( 'Choose the number of points earned for each unit of store currency.', 'infivro-loyalty-rewards' ); ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="infirewards_save_earning_rule">
						<?php wp_nonce_field( 'infirewards_save_earning_rule' ); ?>
						<label for="infirewards-rate"><?php esc_html_e( 'Points per currency unit', 'infivro-loyalty-rewards' ); ?></label>
						<div class="infirewards-rules__input-row"><input id="infirewards-rate" name="rate" type="number" min="0" max="1000000" step="0.01" required value="<?php echo esc_attr( $rule ? $rule['rate'] : '1' ); ?>"><span><?php echo esc_html( $currency ); ?></span></div>
						<p class="description"><?php esc_html_e( 'Up to two decimal places. Points are rounded down to whole numbers.', 'infivro-loyalty-rewards' ); ?></p>
						<label class="infirewards-rules__checkbox"><input type="checkbox" name="active" value="1" <?php checked( $rule ? $active : true ); ?>> <?php esc_html_e( 'Active', 'infivro-loyalty-rewards' ); ?></label>
						<p class="infirewards-rules__submit"><button class="button button-primary" type="submit"><?php echo esc_html( $rule ? __( 'Save Rule', 'infivro-loyalty-rewards' ) : __( 'Create Rule', 'infivro-loyalty-rewards' ) ); ?></button></p>
					</form>
				</section>
				<aside class="infirewards-rules__panel infirewards-rules__guide">
					<h3><?php esc_html_e( 'How purchase points work', 'infivro-loyalty-rewards' ); ?></h3>
					<div class="infirewards-rules__guide-icon"><span class="dashicons dashicons-info-outline" aria-hidden="true"></span></div>
					<p><?php esc_html_e( 'Points are awarded when a logged-in customer’s order is completed.', 'infivro-loyalty-rewards' ); ?></p>
					<ul>
						<li><?php esc_html_e( 'The eligible amount is the paid total after discounts.', 'infivro-loyalty-rewards' ); ?></li>
						<li><?php esc_html_e( 'Shipping and taxes are excluded.', 'infivro-loyalty-rewards' ); ?></li>
						<li><?php esc_html_e( 'Guest orders do not earn points.', 'infivro-loyalty-rewards' ); ?></li>
					</ul>
				</aside>
			</div>
		</div>
		<?php
	}
}
