<?php
/**
 * Template: Redeem Form
 */
defined( 'ABSPATH' ) || exit;

?>
<form class="infirewards-redeem-form">
	<label><?php esc_html_e( 'Points to redeem', 'infirewards' ); ?></label>
	<input type="number" name="points" min="0" />
	<button type="submit"><?php esc_html_e( 'Redeem', 'infirewards' ); ?></button>
</form>
