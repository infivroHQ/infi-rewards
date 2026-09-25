<?php
/**
 * Customer balances and activity in wp-admin.
 *
 * @package InfiRewards
 */

namespace InfiRewards\Admin;

use InfiRewards\Database\TransactionsTable;
use InfiRewards\Database\WalletTable;

defined( 'ABSPATH' ) || exit;

/** Customer balances and activity in wp-admin. */
class Customers {
	/** Render the page. */
	public static function render(): void {
		global $wpdb;
		$wallets      = WalletTable::table_name();
		$transactions = TransactionsTable::table_name();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- GET parameters only filter and paginate this read-only page.
		$search       = isset( $_GET['s'] ) && is_scalar( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$page         = isset( $_GET['paged'] ) && is_scalar( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		$sort         = isset( $_GET['sort'] ) && is_scalar( $_GET['sort'] ) ? sanitize_key( wp_unslash( $_GET['sort'] ) ) : 'balance';
		$sort         = in_array( $sort, array( 'balance', 'recent', 'name' ), true ) ? $sort : 'balance';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$order_by     = array(
			'balance' => 'w.balance DESC',
			'recent'  => 'last_activity DESC',
			'name'    => 'u.display_name ASC',
		)[ $sort ];
		$where        = '';
		$args         = array();
		if ( '' !== $search ) {
			$where = ' WHERE (u.display_name LIKE %s OR u.user_email LIKE %s)';
			$like  = '%' . $wpdb->esc_like( $search ) . '%';
			$args  = array( $like, $like );
		}
		// Dynamic table names are internal; search values and limits are prepared.
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$base        = "FROM {$wallets} w INNER JOIN {$wpdb->users} u ON u.ID = w.user_id"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table names.
		$count_sql   = "SELECT COUNT(*) {$base}{$where}";
		$total       = (int) $wpdb->get_var( $args ? $wpdb->prepare( $count_sql, $args ) : $count_sql );
		$limit       = 20;
		$page        = min( $page, max( 1, (int) ceil( $total / $limit ) ) );
		$offset      = ( $page - 1 ) * $limit;
		$list_sql    = "SELECT w.user_id, w.balance, u.display_name, u.user_email, COALESCE(t.earned, 0) AS earned, t.last_activity
			{$base} LEFT JOIN (SELECT user_id, SUM(CASE WHEN type = 'credit' THEN points ELSE 0 END) AS earned, MAX(created_at) AS last_activity FROM {$transactions} GROUP BY user_id) t ON t.user_id = w.user_id
			{$where} ORDER BY {$order_by}, w.user_id ASC LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Order is chosen from a fixed whitelist.
		$rows        = $wpdb->get_results( $wpdb->prepare( $list_sql, array_merge( $args, array( $limit, $offset ) ) ), ARRAY_A );
		$rows        = is_array( $rows ) ? $rows : array();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Customer selection is read-only.
		$selected_id = isset( $_GET['customer_id'] ) && is_scalar( $_GET['customer_id'] ) ? absint( wp_unslash( $_GET['customer_id'] ) ) : 0;
		if ( ! $selected_id && $rows ) {
			$selected_id = (int) $rows[0]['user_id'];
		}
		$customer = $selected_id ? get_userdata( $selected_id ) : false;
		$wallet   = $customer ? $wpdb->get_row( $wpdb->prepare( "SELECT balance FROM {$wallets} WHERE user_id = %d", $selected_id ), ARRAY_A ) : null;
		if ( ! $wallet ) {
			$customer = false;
		}
		$summary  = $customer ? $wpdb->get_row( $wpdb->prepare( "SELECT COALESCE(SUM(CASE WHEN type = 'credit' THEN points ELSE 0 END), 0) AS earned, COALESCE(SUM(CASE WHEN event_type = 'redemption' AND type = 'debit' THEN points ELSE 0 END), 0) AS redeemed, MAX(CASE WHEN event_type = 'redemption' THEN created_at ELSE NULL END) AS last_redeemed FROM {$transactions} WHERE user_id = %d", $selected_id ), ARRAY_A ) : null;
		$activity = $customer ? $wpdb->get_results( $wpdb->prepare( "SELECT points, type, event_type, reason, order_id, created_at FROM {$transactions} WHERE user_id = %d ORDER BY transaction_id DESC LIMIT 6", $selected_id ), ARRAY_A ) : array();
		$activity = is_array( $activity ) ? $activity : array();
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		?>
		<div class="wrap infirewards-page">
			<?php PageHeader::render( 'infirewards-customers' ); ?>
			<div class="infirewards-page__heading"><h2><?php esc_html_e( 'Customers', 'infi-rewards' ); ?></h2></div>
			<div class="infirewards-page__customer-grid">
				<section class="infirewards-page__panel"><h3><?php esc_html_e( 'Customers', 'infi-rewards' ); ?></h3>
					<form class="infirewards-page__filters" method="get"><input type="hidden" name="page" value="infirewards-customers"><label class="screen-reader-text" for="infirewards-customer-search"><?php esc_html_e( 'Search customers', 'infi-rewards' ); ?></label><input id="infirewards-customer-search" name="s" type="search" placeholder="<?php esc_attr_e( 'Search by name or email...', 'infi-rewards' ); ?>" value="<?php echo esc_attr( $search ); ?>"><label class="screen-reader-text" for="infirewards-customer-sort"><?php esc_html_e( 'Sort customers', 'infi-rewards' ); ?></label><select name="sort" id="infirewards-customer-sort"><option value="balance" <?php selected( $sort, 'balance' ); ?>><?php esc_html_e( 'Highest balance', 'infi-rewards' ); ?></option><option value="recent" <?php selected( $sort, 'recent' ); ?>><?php esc_html_e( 'Recent activity', 'infi-rewards' ); ?></option><option value="name" <?php selected( $sort, 'name' ); ?>><?php esc_html_e( 'Name', 'infi-rewards' ); ?></option></select><button class="button" type="submit"><?php esc_html_e( 'Filter', 'infi-rewards' ); ?></button></form>
					<div class="infirewards-page__table-wrap"><table class="widefat infirewards-page__table"><thead><tr><th scope="col"><?php esc_html_e( 'Customer', 'infi-rewards' ); ?></th><th scope="col"><?php esc_html_e( 'Available Points', 'infi-rewards' ); ?></th><th scope="col"><?php esc_html_e( 'Total Earned', 'infi-rewards' ); ?></th><th scope="col"><?php esc_html_e( 'Last Activity', 'infi-rewards' ); ?></th><th scope="col"><?php esc_html_e( 'Action', 'infi-rewards' ); ?></th></tr></thead><tbody>
					<?php
					foreach ( $rows as $row ) :
						$url = add_query_arg(
							array(
								'customer_id' => (int) $row['user_id'],
								's'           => $search,
								'sort'        => $sort,
								'paged'       => $page,
							),
							admin_url( 'admin.php?page=infirewards-customers' )
						);
						?>
						<tr class="<?php echo $selected_id === (int) $row['user_id'] ? 'is-selected' : ''; ?>"><td><span class="infirewards-page__identity"><?php echo get_avatar( (int) $row['user_id'], 32 ); ?><span><strong><?php echo esc_html( $row['display_name'] ? $row['display_name'] : $row['user_email'] ); ?></strong><small><?php echo esc_html( $row['user_email'] ); ?></small></span></span></td><td><strong><?php echo esc_html( number_format_i18n( (int) $row['balance'] ) ); ?></strong></td><td><?php echo esc_html( number_format_i18n( (int) $row['earned'] ) ); ?></td><td><?php echo $row['last_activity'] ? esc_html( mysql2date( get_option( 'date_format' ), $row['last_activity'] ) ) : '—'; ?></td><td><a class="button button-small" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'View', 'infi-rewards' ); ?></a></td></tr>
						<?php
					endforeach; if ( ! $rows ) :
						?>
						<tr><td colspan="5" class="infirewards-page__empty"><?php esc_html_e( 'No customers match this view.', 'infi-rewards' ); ?></td></tr><?php endif; ?>
					</tbody></table></div>
					<?php // translators: 1: first customer shown, 2: last customer shown, 3: total customers. ?>
					<div class="infirewards-page__pagination"><span><?php echo esc_html( sprintf( __( 'Showing %1$d–%2$d of %3$d customers', 'infi-rewards' ), $total ? $offset + 1 : 0, min( $offset + $limit, $total ), $total ) ); ?></span><span>
					<?php
					if ( $page > 1 ) :
						?>
						<a class="button" href="
						<?php
						echo esc_url(
							add_query_arg(
								array(
									's'     => $search,
									'sort'  => $sort,
									'paged' => $page - 1,
								),
								admin_url( 'admin.php?page=infirewards-customers' )
							)
						);
						?>
						"><?php esc_html_e( 'Previous', 'infi-rewards' ); ?></a><?php endif; ?>
		<?php
		if ( $offset + $limit < $total ) :
			?>
						<a class="button" href="
						<?php
						echo esc_url(
							add_query_arg(
								array(
									's'     => $search,
									'sort'  => $sort,
									'paged' => $page + 1,
								),
								admin_url( 'admin.php?page=infirewards-customers' )
							)
						);
						?>
						"><?php esc_html_e( 'Next', 'infi-rewards' ); ?></a><?php endif; ?></span></div>
				</section>
				<div class="infirewards-page__sidebar">
					<section class="infirewards-page__panel"><h3><?php esc_html_e( 'Customer Details', 'infi-rewards' ); ?></h3>
					<?php
					if ( $customer ) :
						?>
						<div class="infirewards-page__profile"><?php echo get_avatar( $customer->ID, 48 ); ?><span><strong><?php echo esc_html( $customer->display_name ? $customer->display_name : $customer->user_email ); ?></strong><small><?php echo esc_html( $customer->user_email ); ?></small></span></div>
					<dl class="infirewards-page__details"><div><dt><?php esc_html_e( 'Available points', 'infi-rewards' ); ?></dt><dd><?php echo esc_html( number_format_i18n( (int) $wallet['balance'] ) ); ?></dd></div><div><dt><?php esc_html_e( 'Total earned', 'infi-rewards' ); ?></dt><dd><?php echo esc_html( number_format_i18n( (int) $summary['earned'] ) ); ?></dd></div><div><dt><?php esc_html_e( 'Points redeemed', 'infi-rewards' ); ?></dt><dd><?php echo esc_html( number_format_i18n( (int) $summary['redeemed'] ) ); ?></dd></div><div><dt><?php esc_html_e( 'Last redeemed', 'infi-rewards' ); ?></dt><dd><?php echo $summary['last_redeemed'] ? esc_html( mysql2date( get_option( 'date_format' ), $summary['last_redeemed'] ) ) : '—'; ?></dd></div></dl>
						<?php
					else :
						?>
						<p class="infirewards-page__muted"><?php esc_html_e( 'Select a customer to see their points.', 'infi-rewards' ); ?></p><?php endif; ?></section>
					<section class="infirewards-page__panel"><h3><?php esc_html_e( 'Recent Activity', 'infi-rewards' ); ?></h3>
					<?php
					if ( $activity ) :
						?>
						<ol class="infirewards-page__timeline">
						<?php
						foreach ( $activity as $item ) :
								$debit = 'debit' === $item['type'];
							?>
	<li><span class="dashicons <?php echo $debit ? 'dashicons-minus' : 'dashicons-plus'; ?>" aria-hidden="true"></span><span><strong><?php echo esc_html( self::activity_label( $item ) ); ?></strong><small><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $item['created_at'] ) ); ?></small></span><b class="<?php echo $debit ? 'is-debit' : ''; ?>"><?php echo esc_html( ( $debit ? '−' : '+' ) . number_format_i18n( (int) $item['points'] ) ); ?></b></li><?php endforeach; ?></ol>
						<?php
					else :
						?>
						<p class="infirewards-page__muted"><?php esc_html_e( 'No points activity yet.', 'infi-rewards' ); ?></p><?php endif; ?>
					</section>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Describe a ledger entry for the selected customer.
	 *
	 * @param array $entry Transaction data.
	 * @return string
	 */
	private static function activity_label( array $entry ): string {
		switch ( $entry['event_type'] ) {
			case 'order_earn':
				// translators: %d is the order ID.
				return sprintf( __( 'Earned points for order #%d', 'infi-rewards' ), (int) $entry['order_id'] );
			case 'order_reversal':
				return __( 'Order points reversed', 'infi-rewards' );
			case 'redemption':
				return __( 'Redeemed reward', 'infi-rewards' );
			default:
				return $entry['reason'] ? $entry['reason'] : __( 'Points adjustment', 'infi-rewards' );
		}
	}
}
