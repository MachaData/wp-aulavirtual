<?php
/**
 * Registration received.
 *
 * @package SIQA\AulaVirtual
 *
 * @var array<string, mixed>|null $link
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="av-registration av-registration--success" style="max-width:560px;margin:40px auto;padding:0 16px;">
	<h1><?php esc_html_e( 'Recibimos tu inscripcion', 'aula-virtual' ); ?></h1>
	<p><?php esc_html_e( 'Te enviamos un correo de confirmacion. Cuando tu solicitud sea revisada recibiras los siguientes pasos en el mismo correo.', 'aula-virtual' ); ?></p>
	<p><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Volver al inicio', 'aula-virtual' ); ?></a></p>
</div>
