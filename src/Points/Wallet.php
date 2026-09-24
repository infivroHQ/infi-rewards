<?php
namespace InfiRewards\Points;

defined( 'ABSPATH' ) || exit;

class Wallet {
	public int $user_id;
	public int $points;

	public function __construct( int $user_id = 0, int $points = 0 ) {
		$this->user_id = $user_id;
		$this->points  = $points;
	}
}
