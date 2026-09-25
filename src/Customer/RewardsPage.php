<?php
namespace InfiRewards\Customer;

use InfiRewards\Database\TransactionsTable;
use InfiRewards\Points\WalletService;
use InfiRewards\Rewards\RedemptionService;
use InfiRewards\Rewards\RewardRepository;
use InfiRewards\Rules\RulesEngine;

defined( 'ABSPATH' ) || exit;

/** The customer rewards view shared by My Account and the shortcode. */
class RewardsPage {
	public static function render(): string {
		$user_id     = get_current_user_id();
		$balance     = WalletService::get_instance()->get_balance( $user_id );
		$service     = new RedemptionService();
		$rewards     = array_values(
			array_filter(
				( new RewardRepository() )->all(),
				static function ( $reward ) {
					return 1 === (int) $reward['status'];
				}
			)
		);
		$redemptions = $service->history( $user_id );
		$history     = $service->points_history( $user_id );
		$rule        = RulesEngine::get_instance()->get_rule();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This tab only selects a read-only view.
		$tab         = isset( $_GET['infirewards_tab'] ) && is_scalar( $_GET['infirewards_tab'] ) ? sanitize_key( wp_unslash( $_GET['infirewards_tab'] ) ) : 'overview';
		$tab         = in_array( $tab, array( 'overview', 'rewards', 'activity' ), true ) ? $tab : 'overview';
		$base_url    = remove_query_arg( array( 'infirewards_tab', 'infirewards_notice', 'infirewards_redemption' ) );
		$next_reward = null;
		foreach ( $rewards as $reward ) {
			if ( (int) $reward['points_cost'] > $balance && ( ! $next_reward || (int) $reward['points_cost'] < (int) $next_reward['points_cost'] ) ) {
				$next_reward = $reward;
			}
		}
		global $wpdb;
		$table = TransactionsTable::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from the WordPress prefix.
		$redeemed_points = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(points), 0) FROM {$table} WHERE user_id = %d AND type = 'debit' AND event_type = 'redemption'", $user_id ) );
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Redirect parameters only select a notice and the current user's record.
		$notice          = isset( $_GET['infirewards_notice'] ) && is_scalar( $_GET['infirewards_notice'] ) ? sanitize_key( wp_unslash( $_GET['infirewards_notice'] ) ) : '';
		$redemption_id   = isset( $_GET['infirewards_redemption'] ) && is_scalar( $_GET['infirewards_redemption'] ) ? absint( wp_unslash( $_GET['infirewards_redemption'] ) ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$noticed_record  = $redemption_id ? $service->get( $user_id, $redemption_id ) : null;
		$messages        = array(
			'issued'      => __( 'Reward redeemed. Your coupon is shown below.', 'infi-rewards' ),
			'pending'     => __( 'Your points were deducted, but the coupon could not be delivered yet. Retry it below; you will not be charged again.', 'infi-rewards' ),
			'points'      => __( 'You do not have enough points for that reward.', 'infi-rewards' ),
			'unavailable' => __( 'That reward is no longer available.', 'infi-rewards' ),
			'email'       => __( 'Add a valid email address to your account before redeeming.', 'infi-rewards' ),
			'invalid'     => __( 'The redemption request was invalid.', 'infi-rewards' ),
			'error'       => __( 'Could not redeem the reward. Please try again.', 'infi-rewards' ),
		);
		ob_start();
		?>
		<div class="infirewards-customer">
			<header class="infirewards-customer__header"><h2><?php esc_html_e( 'Points & Rewards', 'infi-rewards' ); ?></h2><p><?php esc_html_e( 'Earn points, redeem rewards and enjoy exclusive benefits.', 'infi-rewards' ); ?></p></header>
			<nav class="infirewards-customer__tabs" aria-label="<?php esc_attr_e( 'Points and rewards sections', 'infi-rewards' ); ?>">
				<?php
				foreach ( array(
					'overview' => __( 'Overview', 'infi-rewards' ),
					'rewards'  => __( 'Rewards', 'infi-rewards' ),
					'activity' => __( 'Activity', 'infi-rewards' ),
				) as $key => $label ) :
					?>
					<a class="infirewards-customer__tab <?php echo $tab === $key ? 'is-active' : ''; ?>" <?php echo $tab === $key ? 'aria-current="page"' : ''; ?> href="<?php echo esc_url( 'overview' === $key ? $base_url : add_query_arg( 'infirewards_tab', $key, $base_url ) ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>
			<?php if ( isset( $messages[ $notice ] ) && ( ! in_array( $notice, array( 'issued', 'pending' ), true ) || $noticed_record ) ) : ?>
				<div class="infirewards-customer__notice" role="status"><p><?php echo esc_html( $messages[ $notice ] ); ?></p>
				<?php
				if ( 'issued' === $notice && 'issued' === $noticed_record['status'] && ! self::coupon_is_used( $noticed_record ) ) :
					?>
					<p><?php self::coupon_copy_button( $noticed_record['coupon_code'] ); ?></p>
					<?php
				elseif ( 'issued' === $notice && 'issued' === $noticed_record['status'] ) :
					?>
					<p><span class="infirewards-redemption__used"><?php esc_html_e( 'Used', 'infi-rewards' ); ?></span></p>
					<?php
elseif ( 'pending' === $notice && 'pending' === $noticed_record['status'] ) :
	?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="infirewards_retry_coupon"><input type="hidden" name="redemption_id" value="<?php echo esc_attr( $noticed_record['redemption_id'] ); ?>"><?php wp_nonce_field( 'infirewards_retry_' . $noticed_record['redemption_id'] ); ?><button type="submit"><?php esc_html_e( 'Retry coupon', 'infi-rewards' ); ?></button></form><?php endif; ?></div>
			<?php endif; ?>
			<?php if ( 'overview' === $tab ) : ?>
				<section aria-labelledby="ir-my-points"><h3 class="infirewards-customer__section-title" id="ir-my-points"><?php esc_html_e( 'My points', 'infi-rewards' ); ?></h3>
					<div class="infirewards-customer__stats"><div class="infirewards-customer__stat"><span class="infirewards-customer__icon" aria-hidden="true">☆</span><div><span><?php esc_html_e( 'Available points', 'infi-rewards' ); ?></span><strong><?php echo esc_html( number_format_i18n( $balance ) ); ?></strong><span class="screen-reader-text"><?php esc_html_e( 'Current balance:', 'infi-rewards' ); ?></span></div></div><div class="infirewards-customer__stat"><span class="infirewards-customer__icon" aria-hidden="true">◎</span><div><span><?php esc_html_e( 'Redeemed points', 'infi-rewards' ); ?></span><strong><?php echo esc_html( number_format_i18n( $redeemed_points ) ); ?></strong><small><?php esc_html_e( 'Lifetime redeemed', 'infi-rewards' ); ?></small></div></div></div>
					<?php
					if ( $next_reward ) :
						$cost     = (int) $next_reward['points_cost'];
						$progress = min( 100, (int) floor( $balance * 100 / $cost ) );
						// translators: 1: points needed, 2: reward name.
						$unlock_label = sprintf( __( '%1$s more points to unlock %2$s', 'infi-rewards' ), number_format_i18n( $cost - $balance ), $next_reward['name'] );
						// translators: %d: percentage of the next reward's points cost earned.
						$progress_label = sprintf( __( 'You’re %d%% of the way there!', 'infi-rewards' ), $progress );
						?>
						<div class="infirewards-customer__progress-card"><span class="infirewards-customer__icon" aria-hidden="true">♧</span><div class="infirewards-customer__progress-content"><strong><?php echo esc_html( $unlock_label ); ?></strong><div class="infirewards-customer__progress-meta"><span><?php echo esc_html( $progress_label ); ?></span><span><?php echo esc_html( number_format_i18n( $balance ) . ' / ' . number_format_i18n( $cost ) ); ?> <?php esc_html_e( 'points', 'infi-rewards' ); ?></span></div><div class="infirewards-customer__progress-track" role="progressbar" aria-valuenow="<?php echo esc_attr( $balance ); ?>" aria-valuemin="0" aria-valuemax="<?php echo esc_attr( $cost ); ?>" aria-label="<?php esc_attr_e( 'Progress to next reward', 'infi-rewards' ); ?>"><span style="width:<?php echo esc_attr( $progress ); ?>%"></span></div></div></div><?php endif; ?>
				</section>
				<section aria-labelledby="ir-available-rewards"><div class="infirewards-customer__section-heading"><h3 class="infirewards-customer__section-title" id="ir-available-rewards"><?php esc_html_e( 'Available rewards', 'infi-rewards' ); ?></h3><a href="<?php echo esc_url( add_query_arg( 'infirewards_tab', 'rewards', $base_url ) ); ?>"><?php esc_html_e( 'View all rewards', 'infi-rewards' ); ?> →</a></div><div class="infirewards-customer__rewards">
				<?php
				if ( ! $rewards ) :
					?>
					<p><?php esc_html_e( 'No rewards are available right now.', 'infi-rewards' ); ?></p>
					<?php
else :
	foreach ( array_slice( $rewards, 0, 2 ) as $reward ) {
		self::reward_card( $reward, $balance );
	} endif;
?>
</div></section>
				<section aria-labelledby="ir-ways-to-earn"><h3 class="infirewards-customer__section-title" id="ir-ways-to-earn"><?php esc_html_e( 'Ways to earn', 'infi-rewards' ); ?></h3><div class="infirewards-customer__ways">
				<?php
				if ( $rule && 1 === (int) $rule['status'] && (float) $rule['rate'] > 0 ) :
					// translators: 1: points earned, 2: price of one currency unit.
					$earning_label = sprintf( __( 'Earn %1$s points for each %2$s spent on eligible orders.', 'infi-rewards' ), $rule['rate'], wp_strip_all_tags( wc_price( 1 ) ) );
					?>
					<div class="infirewards-customer__way"><span class="infirewards-customer__icon" aria-hidden="true">♧</span><div><strong><?php esc_html_e( 'Points for purchases', 'infi-rewards' ); ?></strong><p><?php echo esc_html( $earning_label ); ?></p></div></div>
					<?php
else :
	?>
					<p><?php esc_html_e( 'Ways to earn points will appear here when available.', 'infi-rewards' ); ?></p><?php endif; ?></div></section>
				<?php if ( $redemptions ) { self::redemptions( array_slice( $redemptions, 0, 3 ) ); } ?>
			<?php elseif ( 'rewards' === $tab ) : ?>
				<section aria-labelledby="ir-all-rewards"><h3 class="infirewards-customer__section-title" id="ir-all-rewards"><?php esc_html_e( 'Available rewards', 'infi-rewards' ); ?></h3><div class="infirewards-customer__rewards">
				<?php
				if ( ! $rewards ) :
					?>
					<p><?php esc_html_e( 'No rewards are available right now.', 'infi-rewards' ); ?></p>
					<?php
else :
	foreach ( $rewards as $reward ) {
		self::reward_card( $reward, $balance );
	} endif;
?>
</div></section>
				<?php self::redemptions( $redemptions ); ?>
			<?php else : ?>
				<section aria-labelledby="ir-points-activity"><h3 class="infirewards-customer__section-title" id="ir-points-activity"><?php esc_html_e( 'Recent points activity', 'infi-rewards' ); ?></h3><div class="infirewards-customer__panel">
				<?php
				if ( ! $history ) :
					?>
					<p><?php esc_html_e( 'No points activity yet.', 'infi-rewards' ); ?></p>
					<?php
else :
	foreach ( $history as $entry ) :
		?>
	<div class="infirewards-customer__activity"><div><strong><?php echo esc_html( $entry['reason'] ); ?></strong><small><?php echo esc_html( $entry['created_at'] ); ?></small></div><strong class="<?php echo 'credit' === $entry['type'] ? 'is-credit' : 'is-debit'; ?>"><?php echo esc_html( ( 'credit' === $entry['type'] ? '+' : '-' ) . number_format_i18n( $entry['points'] ) ); ?></strong></div>
		<?php
endforeach;
endif;
?>
</div></section>
				<?php self::redemptions( $redemptions ); ?>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	private static function reward_card( array $reward, int $balance ): void {
		$cost       = (int) $reward['points_cost'];
		$can_redeem = $balance >= $cost;
		// translators: %s: additional points needed to redeem the reward.
		$needed_label = $can_redeem ? '' : sprintf( __( 'Need %s more points', 'infi-rewards' ), number_format_i18n( $cost - $balance ) );
		?>
		<article class="infirewards-reward"><div class="infirewards-reward__main"><span class="infirewards-customer__icon" aria-hidden="true">♢</span><div><h4><?php echo esc_html( $reward['name'] ); ?></h4><p><?php echo wp_kses_post( wc_price( $reward['discount_amount'] ) ); ?> <?php esc_html_e( 'off your next order', 'infi-rewards' ); ?></p></div></div><div class="infirewards-reward__footer"><strong>◉ <?php echo esc_html( number_format_i18n( $cost ) ); ?> <?php esc_html_e( 'points', 'infi-rewards' ); ?></strong>
		<?php
		if ( $can_redeem ) :
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="infirewards_redeem"><input type="hidden" name="reward_id" value="<?php echo esc_attr( $reward['reward_id'] ); ?>"><input type="hidden" name="request_key" value="<?php echo esc_attr( str_replace( '-', '', wp_generate_uuid4() ) ); ?>"><?php wp_nonce_field( 'infirewards_redeem_' . $reward['reward_id'] ); ?><button type="submit"><?php esc_html_e( 'Redeem now', 'infi-rewards' ); ?></button></form>
			<?php
else :
	?>
			<span class="infirewards-reward__unavailable"><?php echo esc_html( $needed_label ); ?></span><?php endif; ?></div></article>
		<?php
	}

	private static function redemptions( array $redemptions ): void {
		?>
		<section aria-labelledby="ir-redemptions"><h3 class="infirewards-customer__section-title" id="ir-redemptions"><?php esc_html_e( 'Recent redemptions', 'infi-rewards' ); ?></h3><div class="infirewards-customer__panel">
		<?php
		if ( ! $redemptions ) :
			?>
			<p><?php esc_html_e( 'No redemptions yet.', 'infi-rewards' ); ?></p>
			<?php
else :
	foreach ( $redemptions as $record ) :
		// translators: %s: reward ID.
		$reward_label = sprintf( __( 'Reward #%s', 'infi-rewards' ), $record['reward_id'] );
		?>
	<div class="infirewards-customer__activity infirewards-redemption"><div><strong><?php echo esc_html( $reward_label ); ?></strong><small><?php echo esc_html( $record['created_at'] ); ?></small>
		<?php
		if ( 'issued' === $record['status'] && ! self::coupon_is_used( $record ) ) :
			?>
				<?php self::coupon_copy_button( $record['coupon_code'] ); ?>
				<?php
		elseif ( 'issued' === $record['status'] ) :
			?>
				<span class="infirewards-redemption__used"><?php esc_html_e( 'Used', 'infi-rewards' ); ?></span>
				<?php
else :
	?>
	<span><?php esc_html_e( 'Coupon pending', 'infi-rewards' ); ?></span><?php endif; ?></div>
		<?php
		if ( 'pending' === $record['status'] ) :
			?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="infirewards_retry_coupon"><input type="hidden" name="redemption_id" value="<?php echo esc_attr( $record['redemption_id'] ); ?>"><?php wp_nonce_field( 'infirewards_retry_' . $record['redemption_id'] ); ?><button type="submit"><?php esc_html_e( 'Retry coupon', 'infi-rewards' ); ?></button></form><?php endif; ?></div>
		<?php
endforeach;
endif;
?>
</div></section>
		<?php
	}

	/** Determine whether WooCommerce has recorded this single-use coupon as redeemed. */
	private static function coupon_is_used( array $record ): bool {
		$coupon_id = isset( $record['coupon_id'] ) ? (int) $record['coupon_id'] : 0;
		if ( ! $coupon_id || ! class_exists( 'WC_Coupon' ) ) {
			return false;
		}

		$coupon = new \WC_Coupon( $coupon_id );
		return $coupon->get_id() === $coupon_id && $coupon->get_usage_count() > 0;
	}

	/** Render a compact control without exposing the coupon code in the layout. */
	private static function coupon_copy_button( string $coupon_code ): void {
		?>
		<button class="infirewards-coupon-copy" type="button" data-coupon-code="<?php echo esc_attr( $coupon_code ); ?>" aria-label="<?php echo esc_attr__( 'Copy coupon code to clipboard', 'infi-rewards' ); ?>"><span aria-hidden="true">⧉</span> <?php esc_html_e( 'Copy coupon', 'infi-rewards' ); ?></button>
		<span class="screen-reader-text infirewards-coupon-copy__status" aria-live="polite"></span>
		<?php
	}
}
