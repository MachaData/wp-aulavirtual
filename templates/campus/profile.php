<?php
/**
 * Campus: student profile.
 *
 * @package SIQA\AulaVirtual
 *
 * @var WP_User $user
 * @var string  $phone
 * @var string  $document
 * @var string  $result
 * @var string  $message
 * @var string  $back_url
 */

use SIQA\AulaVirtual\Campus\CampusController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="av-campus av-campus--profile">
	<p><a href="<?php echo esc_url( $back_url ); ?>">&larr; <?php esc_html_e( 'Mis cursos', 'aula-virtual' ); ?></a></p>
	<h2><?php esc_html_e( 'Mis datos', 'aula-virtual' ); ?></h2>

	<?php if ( '' !== $message ) : ?>
		<div class="av-campus__notice av-campus__notice--<?php echo esc_attr( 'ok' === $result ? 'ok' : 'error' ); ?>" role="alert">
			<?php echo esc_html( $message ); ?>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="av-profile__form">
		<input type="hidden" name="action" value="<?php echo esc_attr( CampusController::ACTION_PROFILE ); ?>">
		<?php wp_nonce_field( CampusController::ACTION_PROFILE ); ?>

		<p>
			<label for="av-first-name"><?php esc_html_e( 'Nombre', 'aula-virtual' ); ?></label><br>
			<input type="text" id="av-first-name" name="first_name" value="<?php echo esc_attr( $user->first_name ); ?>" required autocomplete="given-name">
		</p>
		<p>
			<label for="av-last-name"><?php esc_html_e( 'Apellido', 'aula-virtual' ); ?></label><br>
			<input type="text" id="av-last-name" name="last_name" value="<?php echo esc_attr( $user->last_name ); ?>" autocomplete="family-name">
		</p>
		<p>
			<label for="av-email"><?php esc_html_e( 'Correo', 'aula-virtual' ); ?></label><br>
			<input type="email" id="av-email" value="<?php echo esc_attr( $user->user_email ); ?>" readonly>
			<small><?php esc_html_e( 'Tu correo identifica tu matrícula. Para cambiarlo, escríbenos.', 'aula-virtual' ); ?></small>
		</p>
		<p>
			<label for="av-phone"><?php esc_html_e( 'Teléfono / WhatsApp', 'aula-virtual' ); ?></label><br>
			<input type="tel" id="av-phone" name="phone" value="<?php echo esc_attr( $phone ); ?>" autocomplete="tel">
		</p>
		<p>
			<label for="av-document"><?php esc_html_e( 'Documento de identidad', 'aula-virtual' ); ?></label><br>
			<input type="text" id="av-document" name="document" value="<?php echo esc_attr( $document ); ?>">
		</p>

		<h3><?php esc_html_e( 'Cambiar contraseña', 'aula-virtual' ); ?></h3>
		<p><small><?php esc_html_e( 'Deja estos campos vacíos si no quieres cambiarla.', 'aula-virtual' ); ?></small></p>
		<p>
			<label for="av-current-password"><?php esc_html_e( 'Contraseña actual', 'aula-virtual' ); ?></label><br>
			<input type="password" id="av-current-password" name="current_password" autocomplete="current-password">
		</p>
		<p>
			<label for="av-new-password"><?php esc_html_e( 'Nueva contraseña (mínimo 8 caracteres)', 'aula-virtual' ); ?></label><br>
			<input type="password" id="av-new-password" name="new_password" autocomplete="new-password" minlength="8">
		</p>
		<p>
			<label for="av-confirm-password"><?php esc_html_e( 'Repite la nueva contraseña', 'aula-virtual' ); ?></label><br>
			<input type="password" id="av-confirm-password" name="confirm_password" autocomplete="new-password" minlength="8">
		</p>

		<p><button type="submit"><?php esc_html_e( 'Guardar', 'aula-virtual' ); ?></button></p>
	</form>
</div>
