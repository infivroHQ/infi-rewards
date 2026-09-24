<?php
namespace InfiRewards\Models;

defined( 'ABSPATH' ) || exit;

class Transaction {
	public int $id;
	public int $user_id;
	public float $points;
	public string $type;
	public ?int $order_id = null;
	public string $created_at;

	public function __construct() {
		// stub
	}
}
