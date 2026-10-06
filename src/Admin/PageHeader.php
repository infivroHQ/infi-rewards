<?php
/**
 * Shared admin page navigation.
 *
 * @package InfiRewards
 */

namespace InfiRewards\Admin;

defined( 'ABSPATH' ) || exit;

/** Shared admin page navigation. */
class PageHeader {
	/**
	 * Render navigation for a page.
	 *
	 * @param string $current Current admin page slug.
	 */
	public static function render( string $current ): void {
		$pages = array(
			'infirewards'              => array( __( 'Overview', 'infivro-loyalty-rewards' ), 'dashicons-chart-area' ),
			'infirewards-earning-rule' => array( __( 'Earning Rules', 'infivro-loyalty-rewards' ), 'dashicons-admin-generic' ),
			'infirewards-rewards'      => array( __( 'Rewards', 'infivro-loyalty-rewards' ), 'dashicons-awards' ),
			'infirewards-customers'    => array( __( 'Customers', 'infivro-loyalty-rewards' ), 'dashicons-groups' ),
			'infirewards-settings'     => array( __( 'Settings', 'infivro-loyalty-rewards' ), 'dashicons-admin-settings' ),
		);
		?>
		<h1 class="infirewards-page__brand">
			<img src="<?php echo esc_url( plugins_url( 'assets/images/infivro-rewards-logo.png', INFIREWARDS_PLUGIN_FILE ) ); ?>" alt="" width="56" height="56" />
			<span><?php esc_html_e( 'Infivro Loyalty Rewards for WooCommerce', 'infivro-loyalty-rewards' ); ?></span>
		</h1>
		<nav class="infirewards-page__nav" aria-label="<?php esc_attr_e( 'Infivro Loyalty Rewards for WooCommerce pages', 'infivro-loyalty-rewards' ); ?>">
			<?php foreach ( $pages as $slug => $page ) : ?>
				<a class="<?php echo $current === $slug ? 'is-current' : ''; ?>" <?php echo $current === $slug ? 'aria-current="page"' : ''; ?> href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slug ) ); ?>"><span class="dashicons <?php echo esc_attr( $page[1] ); ?>" aria-hidden="true"></span><?php echo esc_html( $page[0] ); ?></a>
			<?php endforeach; ?>
		</nav>
		<?php
	}
}
