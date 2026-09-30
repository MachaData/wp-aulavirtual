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
use SIQA\AulaVirtual\Landing\LandingRenderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="av-campus av-campus--profile">
	<div class="av-c-head">
		<div>
			<p class="av-c-eyebrow"><?php esc_html_e( 'Mi campus', 'aula-virtual' ); ?></p>
			<h1 class="av-c-title"><?php esc_html_e( 'Mis datos', 'aula-virtual' ); ?></h1>
			<p class="av-c-lead"><?php esc_html_e( 'Mantén tus datos al día para recibir los avisos del curso y tu certificado.', 'aula-virtual' ); ?></p>
		</div>
		<nav class="av-c-menu" aria-label="<?php esc_attr_e( 'Menú del campus', 'aula-virtual' ); ?>">
			<a href="<?php echo esc_url( $back_url ); ?>"><?php echo LandingRenderer::icon( 'book' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?><?php esc_html_e( 'Mis cursos', 'aula-virtual' ); ?></a>
			<a class="is-active" href="<?php echo esc_url( $controller->campus_url( array( CampusController::QUERY_PROFILE => 1 ) ) ); ?>"><?php echo LandingRenderer::icon( 'user' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?><?php esc_html_e( 'Mis datos', 'aula-virtual' ); ?></a>
			<a href="<?php echo esc_url( wp_logout_url( $back_url ) ); ?>"><?php echo LandingRenderer::icon( 'logout' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?><?php esc_html_e( 'Salir', 'aula-virtual' ); ?></a>
		</nav>
	</div>

	<?php if ( '' !== $message ) : ?>
		<div class="av-c-notice av-c-notice--<?php echo esc_attr( 'ok' === $result ? 'ok' : 'error' ); ?>" role="alert">
			<?php echo esc_html( $message ); ?>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="av-c-form">
		<input type="hidden" name="action" value="<?php echo esc_attr( CampusController::ACTION_PROFILE ); ?>">
		<?php wp_nonce_field( CampusController::ACTION_PROFILE ); ?>

		<section class="av-c-card">
			<h2 class="av-c-card__title"><?php esc_html_e( 'Datos personales', 'aula-virtual' ); ?></h2>
			<div class="av-c-fields">
				<p class="av-c-field">
					<label for="av-first-name"><?php esc_html_e( 'Nombre', 'aula-virtual' ); ?></label>
					<input type="text" id="av-first-name" name="first_name" value="<?php echo esc_attr( $user->first_name ); ?>" required autocomplete="given-name">
				</p>
				<p class="av-c-field">
					<label for="av-last-name"><?php esc_html_e( 'Apellido', 'aula-virtual' ); ?></label>
					<input type="text" id="av-last-name" name="last_name" value="<?php echo esc_attr( $user->last_name ); ?>" autocomplete="family-name">
				</p>
				<p class="av-c-field av-c-field--full">
					<label for="av-email"><?php esc_html_e( 'Correo', 'aula-virtual' ); ?></label>
					<input type="email" id="av-email" value="<?php echo esc_attr( $user->user_email ); ?>" readonly>
					<small><?php esc_html_e( 'Tu correo identifica tu matrícula. Para cambiarlo, escríbenos.', 'aula-virtual' ); ?></small>
				</p>
				<p class="av-c-field">
					<label for="av-phone"><?php esc_html_e( 'Teléfono / WhatsApp', 'aula-virtual' ); ?></label>
					<input type="tel" id="av-phone" name="phone" value="<?php echo esc_attr( $phone ); ?>" autocomplete="tel">
				</p>
				<p class="av-c-field">
					<label for="av-document"><?php esc_html_e( 'Documento de identidad', 'aula-virtual' ); ?></label>
					<input type="text" id="av-document" name="document" value="<?php echo esc_attr( $document ); ?>">
				</p>
			</div>
		</section>

		<section class="av-c-card">
			<h2 class="av-c-card__title"><?php esc_html_e( 'Cambiar contraseña', 'aula-virtual' ); ?></h2>
			<p class="av-c-muted"><?php esc_html_e( 'Deja estos campos vacíos si no quieres cambiarla.', 'aula-virtual' ); ?></p>
			<div class="av-c-fields">
				<p class="av-c-field av-c-field--full">
					<label for="av-current-password"><?php esc_html_e( 'Contraseña actual', 'aula-virtual' ); ?></label>
					<input type="password" id="av-current-password" name="current_password" autocomplete="current-password">
				</p>
				<p class="av-c-field">
					<label for="av-new-password"><?php esc_html_e( 'Nueva contraseña (mínimo 8 caracteres)', 'aula-virtual' ); ?></label>
					<input type="password" id="av-new-password" name="new_password" autocomplete="new-password" minlength="8">
				</p>
				<p class="av-c-field">
					<label for="av-confirm-password"><?php esc_html_e( 'Repite la nueva contraseña', 'aula-virtual' ); ?></label>
					<input type="password" id="av-confirm-password" name="confirm_password" autocomplete="new-password" minlength="8">
				</p>
			</div>
		</section>

		<p class="av-c-form__submit"><button type="submit" class="av-c-btn"><?php esc_html_e( 'Guardar cambios', 'aula-virtual' ); ?></button></p>
	</form>
</div>
