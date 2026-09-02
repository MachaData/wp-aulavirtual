<?php
/**
 * Campus access denied.
 *
 * @package SIQA\AulaVirtual
 *
 * @var string $back_url
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="av-campus av-campus--denied">
	<h2><?php esc_html_e( 'Sin acceso', 'aula-virtual' ); ?></h2>
	<p><?php esc_html_e( 'No estas matriculado en esta edicion, o tu acceso ya no esta vigente.', 'aula-virtual' ); ?></p>
	<p><a href="<?php echo esc_url( $back_url ); ?>"><?php esc_html_e( 'Volver a mis cursos', 'aula-virtual' ); ?></a></p>
</div>
