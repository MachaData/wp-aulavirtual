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
<div class="av-reg-page">
	<div class="av-reg-result">
		<span class="av-reg-result__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
		<h1><?php esc_html_e( 'Recibimos tu inscripción', 'aula-virtual' ); ?></h1>
		<p><?php esc_html_e( 'Te enviamos un correo de confirmación. Revisa también la carpeta de spam o promociones.', 'aula-virtual' ); ?></p>
		<p><?php esc_html_e( 'Los siguientes pasos llegarán a ese mismo correo.', 'aula-virtual' ); ?></p>
		<a class="av-reg__link" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Volver al inicio', 'aula-virtual' ); ?></a>
	</div>
</div>
