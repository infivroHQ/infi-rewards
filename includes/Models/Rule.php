<?php
namespace InfiRewards\Models;

defined( 'ABSPATH' ) || exit;

class Rule {
	public int $id;
	public string $name;
	public array $config = array();

	public function __construct() {
		// stub
	}
}
