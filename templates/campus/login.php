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
	<div class="av-c-auth">
		<p class="av-c-eyebrow"><?php esc_html_e( 'Campus virtual', 'aula-virtual' ); ?></p>
		<h1 class="av-c-title"><?php esc_html_e( 'Ingresa a tu campus', 'aula-virtual' ); ?></h1>
		<p class="av-c-lead"><?php esc_html_e( 'Usa el correo con el que te inscribiste y tu contraseña.', 'aula-virtual' ); ?></p>
		<?php
		wp_login_form(
			array(
				'redirect'       => $redirect,
				'form_id'        => 'av-login-form',
				'label_username' => __( 'Correo', 'aula-virtual' ),
				'label_password' => __( 'Contraseña', 'aula-virtual' ),
				'label_remember' => __( 'Recordarme en este equipo', 'aula-virtual' ),
				'label_log_in'   => __( 'Entrar', 'aula-virtual' ),
				'remember'       => true,
			)
		);
		?>
		<p class="av-c-auth__links">
			<a href="<?php echo esc_url( wp_lostpassword_url( $redirect ) ); ?>">
				<?php esc_html_e( '¿Olvidaste tu contraseña o es tu primera vez?', 'aula-virtual' ); ?>
			</a>
		</p>
	</div>
</div>
