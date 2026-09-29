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
<div class="av-reg-page">
	<div class="av-reg-result av-reg-result--closed">
		<span class="av-reg-result__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M8 12h8"/></svg></span>
		<h1><?php esc_html_e( 'Inscripción no disponible', 'aula-virtual' ); ?></h1>
		<p><?php echo esc_html( $message ); ?></p>
		<a class="av-reg__link" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Volver al inicio', 'aula-virtual' ); ?></a>
	</div>
</div>
