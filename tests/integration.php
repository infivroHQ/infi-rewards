<?php
/**
 * Integration checks for a disposable WordPress + WooCommerce installation.
 *
 * Run with IR_TEST_DISPOSABLE=1 IR_TEST_WP_ROOT=/path/to/site php tests/integration.php.
 */
if ( '1' !== getenv( 'IR_TEST_DISPOSABLE' ) || ! getenv( 'IR_TEST_WP_ROOT' ) ) {
	fwrite( STDERR, "Set IR_TEST_DISPOSABLE=1 and IR_TEST_WP_ROOT to a disposable site.\n" );
	exit( 2 );
}
$_SERVER['HTTP_HOST'] = 'localhost';
require rtrim( getenv( 'IR_TEST_WP_ROOT' ), '/' ) . '/wp-load.php';

use InfiRewards\Database\RedemptionsTable;
use InfiRewards\Database\TransactionsTable;
use InfiRewards\Database\WalletTable;
use InfiRewards\Points\PointsManager;
use InfiRewards\Points\WalletService;
use InfiRewards\Rewards\RedemptionService;
use InfiRewards\Rewards\RewardRepository;
use InfiRewards\Rules\RulesEngine;

if ( ! class_exists( WC_Order::class ) || ! class_exists( RulesEngine::class ) ) {
	throw new RuntimeException( 'Activate WooCommerce and infiRewards first.' );
}
add_filter( 'pre_wp_mail', '__return_true' );

function ir_check( bool $pass, string $message ): void {
	if ( ! $pass ) {
		throw new RuntimeException( $message );
	}
	echo "PASS: {$message}\n";
}
function ir_balance( int $user_id ): int {
	return WalletService::get_instance()->get_balance( $user_id );
}
function ir_order( int $user_id, WC_Product_Simple $product ): WC_Order {
	$order = wc_create_order( array( 'customer_id' => $user_id ) );
	$order->add_product( $product, 1 );
	if ( $user_id > 0 ) {
		$order->set_billing_email( get_userdata( $user_id )->user_email );
	}
	$order->set_shipping_total( '2.00' );
	$order->set_cart_tax( '1.00' );
	$order->set_total( '15.75' );
	$order->save();
	return $order;
}
function ir_count( string $table, string $where, int $id ): int {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where} = %d", $id ) );
}

$tag = bin2hex( random_bytes( 5 ) );
$user_id = wp_create_user( 'ir_' . $tag, wp_generate_password(), 'ir_' . $tag . '@example.test' );
ir_check( is_int( $user_id ), 'customer created' );
$rules = RulesEngine::get_instance();
ir_check( $rules->save_rule( '2', true ), 'rate saved' );
$repo = new RewardRepository();
ir_check( $repo->save( 0, 'Integration reward ' . $tag, '5.00', '20', true ), 'reward saved' );
$reward = $repo->all()[0];
$reward_id = (int) $reward['reward_id'];
$product = new WC_Product_Simple();
$product->set_name( 'Integration item ' . $tag );
$product->set_regular_price( '12.75' );
$product->save();

$order = ir_order( $user_id, $product );
$order->update_status( 'completed' );
ir_check( 25 === ir_balance( $user_id ), 'floor((15.75 - 1 tax - 2 shipping) x 2) = 25 points' );
PointsManager::get_instance()->handle_order_points( $order->get_id() );
ir_check( 25 === ir_balance( $user_id ) && 1 === ir_count( TransactionsTable::table_name(), 'order_id', $order->get_id() ), 'repeat completed hook awards once' );

$service = new RedemptionService();
$key = bin2hex( random_bytes( 16 ) );
ir_check( 'issued' === $service->redeem( $user_id, $reward_id, $key ), 'reward issues coupon' );
$redemption = $service->get_by_key( $user_id, $key );
$coupon = new WC_Coupon( (int) $redemption['coupon_id'] );
ir_check( 5 === ir_balance( $user_id ) && 'fixed_cart' === $coupon->get_discount_type() && 5.0 === (float) $coupon->get_amount() && 1 === (int) $coupon->get_usage_limit() && array( 'ir_' . $tag . '@example.test' ) === $coupon->get_email_restrictions(), 'one debit and customer-specific single-use coupon' );
wp_set_current_user( $user_id );
$discounts = new WC_Discounts( $order );
ir_check( true === $discounts->is_coupon_valid( $coupon ), 'issued coupon passes WooCommerce validation for its owner' );
wp_set_current_user( 0 );
$other_discounts = new WC_Discounts( $order );
ir_check( is_wp_error( $other_discounts->is_coupon_valid( $coupon ) ), 'issued coupon rejects another account' );
wp_set_current_user( $user_id );
ir_check( 'issued' === $service->redeem( $user_id, $reward_id, $key ) && 5 === ir_balance( $user_id ), 'repeat request key does not debit twice' );
$customer_page = do_shortcode( '[infirewards]' );
ir_check( false !== strpos( $customer_page, $redemption['coupon_code'] ) && false !== strpos( $customer_page, 'Current balance:' ) && false !== strpos( $customer_page, $reward['name'] ), 'customer shortcode shows balance, reward, and issued coupon' );
$coupon->set_usage_count( 1 );
$coupon->save();
$customer_page = do_shortcode( '[infirewards]' );
ir_check( false === strpos( $customer_page, 'infirewards-coupon-copy' ) && false !== strpos( $customer_page, 'Used' ), 'used coupon is labelled used without a copy button' );
ir_check( 'points' === $service->redeem( $user_id, $reward_id, bin2hex( random_bytes( 16 ) ) ), 'insufficient points rejected' );

// Simulate a delivery that stopped after recording the debit and losing its coupon.
wp_delete_post( (int) $redemption['coupon_id'], true );
$wpdb->update( RedemptionsTable::table_name(), array( 'status' => 'pending', 'coupon_id' => null ), array( 'redemption_id' => $redemption['redemption_id'] ) );
ir_check( 'issued' === $service->retry( $user_id, (int) $redemption['redemption_id'] ) && 5 === ir_balance( $user_id ), 'pending coupon retry issues once without another debit' );

$repo->save( $reward_id, $reward['name'], '5.00', '20', false );
ir_check( 'unavailable' === $service->redeem( $user_id, $reward_id, bin2hex( random_bytes( 16 ) ) ), 'disabled reward rejected' );
$repo->save( $reward_id, $reward['name'], '5.00', '20', true );

$order->update_status( 'cancelled' );
PointsManager::get_instance()->handle_refund_points( $order->get_id() );
ir_check( 5 === ir_balance( $user_id ), 'spent order points produce a waived reversal' );
$reversal = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . TransactionsTable::table_name() . ' WHERE event_key = %s', 'order_reversal:' . $order->get_id() ), ARRAY_A );
ir_check( $reversal && 0 === (int) $reversal['points'], 'waived reversal is recorded once' );

$unspent = ir_order( $user_id, $product );
$unspent->update_status( 'completed' );
ir_check( 30 === ir_balance( $user_id ), 'second order earns normally' );
$unspent->update_status( 'refunded' );
PointsManager::get_instance()->handle_refund_points( $unspent->get_id() );
ir_check( 5 === ir_balance( $user_id ), 'full refund reverses unspent points once' );

$guest = ir_order( 0, $product );
$guest->update_status( 'completed' );
$guest->set_customer_id( $user_id );
$guest->save();
PointsManager::get_instance()->handle_order_points( $guest->get_id() );
ir_check( 5 === ir_balance( $user_id ), 'completed guest order stays ineligible after account assignment' );

$ledger = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(CASE WHEN type = \'credit\' THEN points ELSE -points END), 0) FROM ' . TransactionsTable::table_name() . ' WHERE user_id = %d', $user_id ) );
ir_check( $ledger === ir_balance( $user_id ), 'wallet matches signed transaction ledger' );

// Two separate processes compete for the same 25 points.
$race_user = wp_create_user( 'ir_race_' . $tag, wp_generate_password(), 'ir_race_' . $tag . '@example.test' );
$race_order = ir_order( $race_user, $product );
$race_order->update_status( 'completed' );
$barrier = tempnam( sys_get_temp_dir(), 'ir_barrier_' );
unlink( $barrier );
$workers = array();
foreach ( range( 1, 2 ) as $n ) {
	$env = array_merge( $_ENV, array(
		'IR_TEST_WP_ROOT' => getenv( 'IR_TEST_WP_ROOT' ),
		'IR_TEST_USER' => (string) $race_user,
		'IR_TEST_REWARD' => (string) $reward_id,
		'IR_TEST_KEY' => bin2hex( random_bytes( 16 ) ),
		'IR_TEST_BARRIER' => $barrier,
	) );
	$process = proc_open( array( PHP_BINARY, __DIR__ . '/redemption-worker.php' ), array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes, null, $env );
	ir_check( is_resource( $process ), 'redemption worker started' );
	$workers[] = array( $process, $pipes );
}
file_put_contents( $barrier, 'go' );
$results = array();
foreach ( $workers as list( $process, $pipes ) ) {
	$results[] = trim( stream_get_contents( $pipes[1] ) );
	$error = stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );
	ir_check( 0 === proc_close( $process ), 'redemption worker completed: ' . $error );
}
unlink( $barrier );
sort( $results );
ir_check( array( 'issued', 'points' ) === $results && 5 === ir_balance( $race_user ) && 1 === ir_count( RedemptionsTable::table_name(), 'user_id', $race_user ), 'simultaneous redemption permits one spend' );

echo "Integration checks complete.\n";
