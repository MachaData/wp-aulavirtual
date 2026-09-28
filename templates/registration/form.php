<?php
/**
 * Public registration form.
 *
 * @package SIQA\AulaVirtual
 *
 * @var array<string, mixed> $link
 * @var array<string, mixed> $edition
 * @var WP_Post|null         $course
 * @var string               $token
 * @var string               $error
 * @var string               $action
 */

use SIQA\AulaVirtual\Editions\EditionService;
use SIQA\AulaVirtual\Enrollments\RegistrationController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$av_modalities = EditionService::modalities();
?>
<div class="av-registration" style="max-width:560px;margin:40px auto;padding:0 16px;">
	<h1><?php echo esc_html( $course instanceof WP_Post ? get_the_title( $course ) : (string) $edition['name'] ); ?></h1>
	<p class="av-registration__edition">
		<strong><?php echo esc_html( (string) $edition['name'] ); ?></strong>
		&middot; <?php echo esc_html( $av_modalities[ $edition['modality'] ] ?? (string) $edition['modality'] ); ?>
		<?php if ( ! empty( $edition['start_date'] ) ) : ?>
			&middot; <?php echo esc_html( date_i18n( (string) get_option( 'date_format' ), (int) strtotime( $edition['start_date'] . ' UTC' ) ) ); ?>
		<?php endif; ?>
	</p>
	<?php if ( ! empty( $edition['schedule_days'] ) || ! empty( $edition['schedule_time'] ) ) : ?>
		<p><?php echo esc_html( trim( (string) $edition['schedule_days'] . ' ' . (string) $edition['schedule_time'] ) ); ?></p>
	<?php endif; ?>
	<?php if ( ! empty( $edition['price_display'] ) ) : ?>
		<p><strong><?php echo esc_html( (string) $edition['price_display'] ); ?></strong></p>
	<?php endif; ?>

	<?php if ( '' !== $error ) : ?>
		<div class="av-registration__error" role="alert" style="padding:12px;border:1px solid #dc2626;border-radius:6px;margin-bottom:16px;">
			<?php echo esc_html( $error ); ?>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( $action ); ?>" class="av-registration__form">
		<input type="hidden" name="action" value="<?php echo esc_attr( RegistrationController::ACTION_SUBMIT ); ?>">
		<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
		<?php wp_nonce_field( RegistrationController::ACTION_SUBMIT . '_' . $token ); ?>
		<div style="position:absolute;left:-9999px;" aria-hidden="true">
			<label>Website <input type="text" name="av_website" tabindex="-1" autocomplete="off"></label>
		</div>

		<p>
			<label for="av-first-name"><?php esc_html_e( 'Nombre', 'aula-virtual' ); ?></label><br>
			<input type="text" id="av-first-name" name="first_name" required autocomplete="given-name" style="width:100%">
		</p>
		<p>
			<label for="av-last-name"><?php esc_html_e( 'Apellido', 'aula-virtual' ); ?></label><br>
			<input type="text" id="av-last-name" name="last_name" required autocomplete="family-name" style="width:100%">
		</p>
		<p>
			<label for="av-email"><?php esc_html_e( 'Correo electronico', 'aula-virtual' ); ?></label><br>
			<input type="email" id="av-email" name="email" required autocomplete="email" style="width:100%">
		</p>
		<p>
			<label for="av-phone"><?php esc_html_e( 'Telefono / WhatsApp', 'aula-virtual' ); ?></label><br>
			<input type="tel" id="av-phone" name="phone" autocomplete="tel" style="width:100%">
		</p>
		<p>
			<label for="av-document"><?php esc_html_e( 'Documento de identidad (opcional)', 'aula-virtual' ); ?></label><br>
			<input type="text" id="av-document" name="document" style="width:100%">
		</p>
		<p>
			<button type="submit" class="av-registration__submit"><?php esc_html_e( 'Enviar inscripcion', 'aula-virtual' ); ?></button>
		</p>
	</form>
</div>
