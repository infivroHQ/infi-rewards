<?php
/**
 * Store owner overview for the loyalty program.
 *
 * @package InfiRewards
 */

namespace InfiRewards\Admin;

use InfiRewards\Customer\AccountEndpoint;
use InfiRewards\Database\RedemptionsTable;
use InfiRewards\Database\TransactionsTable;
use InfiRewards\Database\WalletTable;
use InfiRewards\Rewards\RewardRepository;
use InfiRewards\Rules\RulesEngine;

defined( 'ABSPATH' ) || exit;

/** Store owner summary of live loyalty data. */
class Overview {
	/** Render the admin landing page. */
	public static function render(): void {
		global $wpdb;

		$transactions = TransactionsTable::table_name();
		$wallets      = WalletTable::table_name();
		$redemptions  = RedemptionsTable::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Live admin aggregates from plugin tables change with each ledger write.
		$totals = $wpdb->get_row(
			$wpdb->prepare( "SELECT COALESCE(SUM(CASE WHEN type = 'credit' THEN points ELSE 0 END), 0) AS issued,
			COALESCE(SUM(CASE WHEN event_type = 'redemption' AND type = 'debit' THEN points ELSE 0 END), 0) AS redeemed
			FROM %i", $transactions ),
			ARRAY_A
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Live admin aggregates from plugin tables change with each ledger write.
		$outstanding = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(balance), 0) FROM %i', $wallets ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Live admin aggregates from plugin tables change with each ledger write.
		$reward_count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE status = %s', $redemptions, 'issued' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Live admin aggregates from plugin tables change with each ledger write.
		$activity = $wpdb->get_results( $wpdb->prepare( 'SELECT transaction_id, user_id, order_id, points, type, event_type, reason, created_at FROM %i ORDER BY transaction_id DESC LIMIT 5', $transactions ), ARRAY_A );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Live admin aggregates from plugin tables change with each ledger write.
		$customers  = $wpdb->get_results(
			$wpdb->prepare( 'SELECT w.user_id, w.balance, u.display_name, u.user_email
			FROM %i w INNER JOIN %i u ON u.ID = w.user_id
			ORDER BY w.balance DESC, w.user_id ASC LIMIT 5', $wallets, $wpdb->users ),
			ARRAY_A
		);
		$rule       = RulesEngine::get_instance()->get_rule();
		$has_rule   = $rule && 1 === (int) $rule['status'] && (float) $rule['rate'] > 0;
		$has_reward = false;
		foreach ( ( new RewardRepository() )->all() as $reward ) {
			if ( 1 === (int) $reward['status'] ) {
				$has_reward = true;
				break;
			}
		}
		$account_url = function_exists( 'wc_get_account_endpoint_url' ) && AccountEndpoint::is_enabled() ? wc_get_account_endpoint_url( 'infirewards' ) : '';
		$cards       = array(
			array( __( 'Points issued', 'infi-rewards' ), (int) ( $totals['issued'] ?? 0 ), 'dashicons-database-add' ),
			array( __( 'Points redeemed', 'infi-rewards' ), (int) ( $totals['redeemed'] ?? 0 ), 'dashicons-update' ),
			array( __( 'Outstanding points', 'infi-rewards' ), $outstanding, 'dashicons-clock' ),
			array( __( 'Rewards redeemed', 'infi-rewards' ), $reward_count, 'dashicons-tickets-alt' ),
		);
		?>
		<div class="wrap infirewards-overview">
			<?php PageHeader::render( 'infirewards' ); ?>
			<h2 class="infirewards-overview__title"><?php esc_html_e( 'Overview', 'infi-rewards' ); ?></h2>
			<div class="infirewards-overview__stats">
				<?php foreach ( $cards as $card ) : ?>
					<div class="infirewards-overview__card infirewards-overview__stat">
						<span class="dashicons <?php echo esc_attr( $card[2] ); ?>" aria-hidden="true"></span>
						<div><span class="infirewards-overview__label"><?php echo esc_html( $card[0] ); ?></span><strong><?php echo esc_html( number_format_i18n( $card[1] ) ); ?></strong></div>
					</div>
				<?php endforeach; ?>
			</div>
			<div class="infirewards-overview__grid">
				<?php if ( ! $has_rule || ! $has_reward ) : ?>
					<section class="infirewards-overview__card infirewards-overview__setup">
						<h2><?php esc_html_e( 'Getting started', 'infi-rewards' ); ?></h2>
						<p><?php esc_html_e( 'Set up points and a reward so customers can start using your program.', 'infi-rewards' ); ?></p>
						<ol>
							<li><span><strong><?php esc_html_e( 'Create an earning rule', 'infi-rewards' ); ?></strong><small><?php esc_html_e( 'Choose how many points customers earn per currency unit.', 'infi-rewards' ); ?></small></span><span class="infirewards-overview__step-action">
							<?php
							if ( $has_rule ) :
								?>
								<span class="infirewards-overview__done"><?php esc_html_e( 'Done', 'infi-rewards' ); ?></span>
								<?php else : ?>
								<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=infirewards-earning-rule' ) ); ?>"><?php esc_html_e( 'Create Rule', 'infi-rewards' ); ?></a><?php endif; ?></span></li>
							<li><span><strong><?php esc_html_e( 'Create a reward', 'infi-rewards' ); ?></strong><small><?php esc_html_e( 'Add a discount customers can redeem with points.', 'infi-rewards' ); ?></small></span><span class="infirewards-overview__step-action">
							<?php
							if ( $has_reward ) :
								?>
								<span class="infirewards-overview__done"><?php esc_html_e( 'Done', 'infi-rewards' ); ?></span>
								<?php else : ?>
								<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=infirewards-rewards' ) ); ?>"><?php esc_html_e( 'Create Reward', 'infi-rewards' ); ?></a><?php endif; ?></span></li>
							<?php if ( $account_url ) : ?>
								<li><span><strong><?php esc_html_e( 'Check the customer page', 'infi-rewards' ); ?></strong><small><?php esc_html_e( 'Preview where customers see points and redeem rewards.', 'infi-rewards' ); ?></small></span><span class="infirewards-overview__step-action"><a class="button" href="<?php echo esc_url( $account_url ); ?>"><?php esc_html_e( 'View page', 'infi-rewards' ); ?></a></span></li>
							<?php endif; ?>
						</ol>
					</section>
				<?php endif; ?>
				<section class="infirewards-overview__card infirewards-overview__activity">
					<h2><?php esc_html_e( 'Recent activity', 'infi-rewards' ); ?></h2>
					<?php if ( $activity ) : ?>
						<div class="infirewards-overview__table-wrap"><table class="widefat striped"><thead><tr><th scope="col"><?php esc_html_e( 'Date', 'infi-rewards' ); ?></th><th scope="col"><?php esc_html_e( 'Customer', 'infi-rewards' ); ?></th><th scope="col"><?php esc_html_e( 'Activity', 'infi-rewards' ); ?></th><th scope="col"><?php esc_html_e( 'Points', 'infi-rewards' ); ?></th></tr></thead><tbody>
						<?php
						foreach ( $activity as $entry ) :
							$user        = get_userdata( (int) $entry['user_id'] );
							$description = self::activity_label( $entry );
							$debit       = 'debit' === $entry['type'];
							?>
							<tr><td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $entry['created_at'] ) ); ?></td><td><?php echo esc_html( $user ? $user->display_name : __( 'Deleted customer', 'infi-rewards' ) ); ?></td><td><?php echo esc_html( $description ); ?></td><td><span class="infirewards-overview__points <?php echo esc_attr( $debit ? 'is-debit' : 'is-credit' ); ?>"><?php echo esc_html( ( 0 === (int) $entry['points'] ? '' : ( $debit ? '−' : '+' ) ) . number_format_i18n( (int) $entry['points'] ) ); ?></span></td></tr>
						<?php endforeach; ?>
						</tbody></table></div>
						<?php
					else :
						?>
						<p class="infirewards-overview__empty"><?php esc_html_e( 'No points activity yet. Transactions will appear here when customers earn or spend points.', 'infi-rewards' ); ?></p><?php endif; ?>
				</section>
				<section class="infirewards-overview__card infirewards-overview__customers">
					<h2><?php esc_html_e( 'Top customers', 'infi-rewards' ); ?></h2>
					<?php if ( $customers ) : ?>
						<div class="infirewards-overview__table-wrap"><table class="widefat striped"><thead><tr><th scope="col"><?php esc_html_e( 'Customer', 'infi-rewards' ); ?></th><th scope="col"><?php esc_html_e( 'Point balance', 'infi-rewards' ); ?></th></tr></thead><tbody>
						<?php foreach ( $customers as $customer ) : ?>
							<tr><td><strong><?php echo esc_html( $customer['display_name'] ? $customer['display_name'] : $customer['user_email'] ); ?></strong></td><td><?php echo esc_html( number_format_i18n( (int) $customer['balance'] ) ); ?></td></tr>
						<?php endforeach; ?>
						</tbody></table></div>
						<?php
					else :
						?>
						<p class="infirewards-overview__empty"><?php esc_html_e( 'Customer balances will appear here after points are earned.', 'infi-rewards' ); ?></p><?php endif; ?>
				</section>
			</div>
		</div>
		<?php
	}

	/**
	 * Give each ledger event a concise, readable label.
	 *
	 * @param array $entry Transaction row.
	 */
	private static function activity_label( array $entry ): string {
		switch ( $entry['event_type'] ) {
			case 'order_earn':
				// translators: %d is the WooCommerce order ID.
				return sprintf( __( 'Earned points for order #%d', 'infi-rewards' ), (int) $entry['order_id'] );
			case 'order_reversal':
				// translators: %d is the WooCommerce order ID.
				return (int) $entry['points'] > 0 ? sprintf( __( 'Reversed points for order #%d', 'infi-rewards' ), (int) $entry['order_id'] ) : __( 'Order reversal waived', 'infi-rewards' );
			case 'redemption':
				return __( 'Redeemed a reward', 'infi-rewards' );
			default:
				return $entry['reason'] ? $entry['reason'] : __( 'Points adjustment', 'infi-rewards' );
		}
	}
}
