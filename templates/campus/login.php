<?php
/**
 * Campus login.
 *
 * @package SIQA\AulaVirtual
 *
 * @var string $redirect
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="av-campus av-campus--login">
	<h2><?php esc_html_e( 'Ingresa al campus', 'aula-virtual' ); ?></h2>
	<?php
	wp_login_form(
		array(
			'redirect'       => $redirect,
			'label_username' => __( 'Correo o usuario', 'aula-virtual' ),
			'label_password' => __( 'Contraseña', 'aula-virtual' ),
			'label_log_in'   => __( 'Entrar', 'aula-virtual' ),
			'remember'       => true,
		)
	);
	?>
	<p>
		<a href="<?php echo esc_url( wp_lostpassword_url( $redirect ) ); ?>">
			<?php esc_html_e( 'Olvidé mi contraseña', 'aula-virtual' ); ?>
		</a>
	</p>
</div>
