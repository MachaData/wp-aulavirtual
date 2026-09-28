<?php
/**
 * Registration link not usable.
 *
 * @package SIQA\AulaVirtual
 *
 * @var string $message
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="av-registration av-registration--closed" style="max-width:560px;margin:40px auto;padding:0 16px;">
	<h1><?php esc_html_e( 'Inscripcion no disponible', 'aula-virtual' ); ?></h1>
	<p><?php echo esc_html( $message ); ?></p>
	<p><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Volver al inicio', 'aula-virtual' ); ?></a></p>
</div>
