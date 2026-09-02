<?php
/**
 * Admin notice partial.
 *
 * @package SIQA\AulaVirtual
 *
 * @var array{type: string, message: string}|null $notice
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $notice ) ) {
	return;
}
?>
<div class="notice notice-<?php echo esc_attr( 'success' === $notice['type'] ? 'success' : 'error' ); ?> is-dismissible">
	<p><?php echo esc_html( $notice['message'] ); ?></p>
</div>
