<?php
$_SERVER['HTTP_HOST'] = 'localhost';
require rtrim( getenv( 'IR_TEST_WP_ROOT' ), '/' ) . '/wp-load.php';
$barrier = getenv( 'IR_TEST_BARRIER' );
$deadline = microtime( true ) + 10;
while ( ! file_exists( $barrier ) && microtime( true ) < $deadline ) {
	usleep( 10000 );
}
echo ( new \InfiRewards\Rewards\RedemptionService() )->redeem( (int) getenv( 'IR_TEST_USER' ), (int) getenv( 'IR_TEST_REWARD' ), getenv( 'IR_TEST_KEY' ) );
