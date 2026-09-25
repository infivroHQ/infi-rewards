<?php
/**
 * Reward management in wp-admin.
 *
 * @package InfiRewards
 */

namespace InfiRewards\Admin;

use InfiRewards\Database\RedemptionsTable;
use InfiRewards\Rewards\RewardRepository;

defined( 'ABSPATH' ) || exit;

/** Reward management in wp-admin. */
class Rewards {
	/** Render the page. */
	public static function render(): void {
		global $wpdb;
		$repository        = new RewardRepository();
		$rewards           = $repository->all();
		$reward_id         = isset( $_GET['reward_id'] ) && is_scalar( $_GET['reward_id'] ) ? absint( wp_unslash( $_GET['reward_id'] ) ) : 0;
		$reward            = $reward_id ? $repository->get( $reward_id ) : null;
		$notice            = isset( $_GET['infirewards_notice'] ) && is_scalar( $_GET['infirewards_notice'] ) ? sanitize_key( wp_unslash( $_GET['infirewards_notice'] ) ) : '';
		$search            = isset( $_GET['s'] ) && is_scalar( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$filter            = isset( $_GET['status'] ) && is_scalar( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'all';
		$filter            = in_array( $filter, array( 'all', 'active', 'inactive' ), true ) ? $filter : 'all';
		$currency          = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '';
		$code              = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '';
		$decimals          = function_exists( 'wc_get_price_decimals' ) ? max( 0, min( 6, (int) wc_get_price_decimals() ) ) : 2;
		$step              = 0 === $decimals ? '1' : '0.' . str_repeat( '0', $decimals - 1 ) . '1';
		$redemption_table  = RedemptionsTable::table_name();
		$counts            = $wpdb->get_results( "SELECT reward_id, COUNT(*) AS total FROM {$redemption_table} GROUP BY reward_id", OBJECT_K ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table name.
		$counts            = is_array( $counts ) ? $counts : array();
		$active_count      = count(
			array_filter(
				$rewards,
				static function ( $item ) {
					return 1 === (int) $item['status'];
				}
			)
		);
		$total_redemptions = array_sum(
			array_map(
				static function ( $row ) {
					return (int) $row->total;
				},
				$counts
			)
		);
		?>
		<div class="wrap infirewards-page">
			<?php PageHeader::render( 'infirewards-rewards' ); ?>
			<div class="infirewards-page__heading"><h2><?php esc_html_e( 'Rewards', 'infirewards' ); ?></h2><a class="button button-primary" href="#infirewards-reward-form"><?php esc_html_e( 'Add Reward', 'infirewards' ); ?></a></div>
			<?php
			if ( 'saved' === $notice ) :
				?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Reward saved.', 'infirewards' ); ?></p></div>
				<?php
			elseif ( 'invalid' === $notice || ( $reward_id && ! $reward ) ) :
				?>
				<div class="notice notice-error"><p><?php esc_html_e( 'Enter a name, positive discount and valid whole points cost.', 'infirewards' ); ?></p></div><?php endif; ?>
			<div class="infirewards-page__stats">
				<div class="infirewards-page__stat"><span class="dashicons dashicons-awards" aria-hidden="true"></span><span><?php esc_html_e( 'Active rewards', 'infirewards' ); ?><strong><?php echo esc_html( number_format_i18n( $active_count ) ); ?></strong></span></div>
				<div class="infirewards-page__stat"><span class="dashicons dashicons-update" aria-hidden="true"></span><span><?php esc_html_e( 'Total redemptions', 'infirewards' ); ?><strong><?php echo esc_html( number_format_i18n( $total_redemptions ) ); ?></strong></span></div>
			</div>
			<section class="infirewards-page__panel"><h3><?php esc_html_e( 'All Rewards', 'infirewards' ); ?></h3>
				<form class="infirewards-page__filters" method="get"><input type="hidden" name="page" value="infirewards-rewards"><label class="screen-reader-text" for="infirewards-reward-search"><?php esc_html_e( 'Search rewards', 'infirewards' ); ?></label><input id="infirewards-reward-search" name="s" type="search" placeholder="<?php esc_attr_e( 'Search rewards...', 'infirewards' ); ?>" value="<?php echo esc_attr( $search ); ?>"><label class="screen-reader-text" for="infirewards-reward-status"><?php esc_html_e( 'Filter rewards', 'infirewards' ); ?></label><select id="infirewards-reward-status" name="status"><option value="all"><?php esc_html_e( 'All rewards', 'infirewards' ); ?></option><option value="active" <?php selected( $filter, 'active' ); ?>><?php esc_html_e( 'Active', 'infirewards' ); ?></option><option value="inactive" <?php selected( $filter, 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'infirewards' ); ?></option></select><button class="button" type="submit"><?php esc_html_e( 'Filter', 'infirewards' ); ?></button></form>
				<div class="infirewards-page__table-wrap"><table class="widefat infirewards-page__table"><thead><tr><th scope="col"><?php esc_html_e( 'Reward', 'infirewards' ); ?></th><th scope="col"><?php esc_html_e( 'Type', 'infirewards' ); ?></th><th scope="col"><?php esc_html_e( 'Value', 'infirewards' ); ?></th><th scope="col"><?php esc_html_e( 'Points Cost', 'infirewards' ); ?></th><th scope="col"><?php esc_html_e( 'Redemptions', 'infirewards' ); ?></th><th scope="col"><?php esc_html_e( 'Status', 'infirewards' ); ?></th><th scope="col"><?php esc_html_e( 'Action', 'infirewards' ); ?></th></tr></thead><tbody>
				<?php
				$shown = 0;
				foreach ( $rewards as $item ) :
					if ( ( 'active' === $filter && 1 !== (int) $item['status'] ) || ( 'inactive' === $filter && 0 !== (int) $item['status'] ) || ( '' !== $search && false === stripos( $item['name'], $search ) ) ) {
						continue;
					}
					++$shown;
					$id       = (int) $item['reward_id'];
					$edit_url = add_query_arg( 'reward_id', $id, admin_url( 'admin.php?page=infirewards-rewards' ) ) . '#infirewards-reward-form';
					?>
					<tr><td><span class="infirewards-page__identity"><span class="dashicons dashicons-tickets-alt" aria-hidden="true"></span><span><strong><?php echo esc_html( $item['name'] ); ?></strong><small><?php esc_html_e( 'Fixed cart discount', 'infirewards' ); ?></small></span></span></td><td><span class="infirewards-page__tag"><?php esc_html_e( 'Coupon', 'infirewards' ); ?></span></td><td><?php echo esc_html( $currency . number_format_i18n( (float) $item['discount_amount'], $decimals ) ); ?></td><td><strong><?php echo esc_html( number_format_i18n( (int) $item['points_cost'] ) ); ?></strong></td><td><?php echo esc_html( number_format_i18n( isset( $counts[ $id ] ) ? (int) $counts[ $id ]->total : 0 ) ); ?></td><td><span class="infirewards-page__status <?php echo 1 === (int) $item['status'] ? 'is-active' : 'is-inactive'; ?>"><?php echo esc_html( 1 === (int) $item['status'] ? __( 'Active', 'infirewards' ) : __( 'Inactive', 'infirewards' ) ); ?></span></td><td><a class="button button-small" href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'infirewards' ); ?></a></td></tr>
					<?php
				endforeach;
				if ( ! $shown ) :
					?>
					<tr><td colspan="7" class="infirewards-page__empty"><?php esc_html_e( 'No rewards match this view.', 'infirewards' ); ?></td></tr><?php endif; ?>
				</tbody></table></div>
			</section>
			<section class="infirewards-page__panel">
				<div class="infirewards-page__section-top">
					<h3><?php esc_html_e( 'Customer Preview', 'infirewards' ); ?></h3>
					<?php if ( function_exists( 'wc_get_account_endpoint_url' ) && \InfiRewards\Customer\AccountEndpoint::is_enabled() ) : ?>
						<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'infirewards' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View customer page', 'infirewards' ); ?></a>
					<?php endif; ?>
				</div>
				<p class="infirewards-page__muted"><?php esc_html_e( 'Active rewards available for customers to redeem.', 'infirewards' ); ?></p>
				<div class="infirewards-page__preview-grid">
				<?php
				$preview_count = 0;
				foreach ( $rewards as $item ) :
					if ( 1 !== (int) $item['status'] ) {
						continue;
					}
					++$preview_count;
					?>
					<div class="infirewards-page__preview-card"><span class="dashicons dashicons-tickets-alt" aria-hidden="true"></span><strong><?php echo esc_html( $item['name'] ); ?></strong><small><?php echo esc_html( $currency . number_format_i18n( (float) $item['discount_amount'], $decimals ) ); ?> <?php esc_html_e( 'cart discount', 'infirewards' ); ?></small><b><?php echo esc_html( number_format_i18n( (int) $item['points_cost'] ) ); ?> <?php esc_html_e( 'points', 'infirewards' ); ?></b></div>
					<?php
					if ( 4 === $preview_count ) {
						break;
					}
				endforeach;
				if ( ! $preview_count ) :
					?>
					<p class="infirewards-page__muted"><?php esc_html_e( 'Create an active reward to see it here.', 'infirewards' ); ?></p><?php endif; ?>
				</div>
			</section>
			<section id="infirewards-reward-form" class="infirewards-page__panel infirewards-page__editor"><h3><?php echo esc_html( $reward ? __( 'Edit Reward', 'infirewards' ) : __( 'Create Reward', 'infirewards' ) ); ?></h3><p><?php esc_html_e( 'Customers redeem points for a single-use fixed cart discount coupon.', 'infirewards' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="infirewards_save_reward"><input type="hidden" name="reward_id" value="<?php echo esc_attr( $reward ? $reward['reward_id'] : 0 ); ?>"><?php wp_nonce_field( 'infirewards_save_reward' ); ?>
					<div class="infirewards-page__fields"><label for="infirewards-reward-name"><?php esc_html_e( 'Reward name', 'infirewards' ); ?><input id="infirewards-reward-name" name="name" type="text" maxlength="255" required value="<?php echo esc_attr( $reward ? $reward['name'] : '' ); ?>"></label><label for="infirewards-discount"><?php esc_html_e( 'Discount amount', 'infirewards' ); ?><span class="infirewards-page__input-suffix"><input id="infirewards-discount" name="discount_amount" type="number" min="<?php echo esc_attr( $step ); ?>" step="<?php echo esc_attr( $step ); ?>" required value="<?php echo esc_attr( $reward ? $reward['discount_amount'] : '' ); ?>"><span><?php echo esc_html( $code ); ?></span></span></label><label for="infirewards-cost"><?php esc_html_e( 'Points cost', 'infirewards' ); ?><input id="infirewards-cost" name="points_cost" type="number" min="1" max="2147483647" step="1" required value="<?php echo esc_attr( $reward ? $reward['points_cost'] : '' ); ?>"></label></div>
					<label class="infirewards-page__check"><input type="checkbox" name="active" value="1" <?php checked( ! $reward || 1 === (int) $reward['status'] ); ?>><?php esc_html_e( 'Active', 'infirewards' ); ?></label><p class="infirewards-page__actions"><button class="button button-primary" type="submit"><?php echo esc_html( $reward ? __( 'Save Reward', 'infirewards' ) : __( 'Create Reward', 'infirewards' ) ); ?></button>
					<?php
					if ( $reward ) :
						?>
						<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=infirewards-rewards#infirewards-reward-form' ) ); ?>"><?php esc_html_e( 'Create another reward', 'infirewards' ); ?></a><?php endif; ?></p>
				</form></section>
		</div>
		<?php
	}
}
