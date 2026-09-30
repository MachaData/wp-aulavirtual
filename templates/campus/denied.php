<?php
/**
 * Campus access denied.
 *
 * @package SIQA\AulaVirtual
 *
 * @var string $back_url
 */

use SIQA\AulaVirtual\Landing\LandingRenderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="av-campus av-campus--denied">
	<div class="av-c-empty">
		<?php echo LandingRenderer::icon( 'lock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?>
		<h1 class="av-c-title"><?php esc_html_e( 'Sin acceso', 'aula-virtual' ); ?></h1>
		<p><?php esc_html_e( 'No estás matriculado en esta edición, o tu acceso ya no está vigente. Si crees que es un error, escríbenos.', 'aula-virtual' ); ?></p>
		<a class="av-c-btn" href="<?php echo esc_url( $back_url ); ?>"><?php esc_html_e( 'Volver a mis cursos', 'aula-virtual' ); ?></a>
	</div>
</div>
