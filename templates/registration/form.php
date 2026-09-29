<?php
/**
 * Public registration form.
 *
 * Themes can override it with aula-virtual/registration/form.php.
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
$av_title      = $course instanceof WP_Post ? get_the_title( $course ) : (string) $edition['name'];
$av_cover      = $course instanceof WP_Post ? get_the_post_thumbnail( $course, 'large', array( 'alt' => '', 'loading' => 'eager' ) ) : '';
$av_start      = empty( $edition['start_date'] ) ? '' : date_i18n( (string) get_option( 'date_format' ), (int) strtotime( $edition['start_date'] . ' UTC' ) );
$av_schedule   = trim( (string) ( $edition['schedule_days'] ?? '' ) . ' ' . (string) ( $edition['schedule_time'] ?? '' ) );
$av_paid       = (int) ( $edition['product_id'] ?? 0 ) > 0;
$av_review     = (int) ( $link['requires_approval'] ?? 1 ) > 0;

if ( $av_review ) {
	$av_note = $av_paid
		? __( 'Revisaremos tu solicitud y te enviaremos por correo el enlace de pago para confirmar tu lugar.', 'aula-virtual' )
		: __( 'Revisaremos tu solicitud y te escribiremos por correo con los siguientes pasos.', 'aula-virtual' );
} else {
	$av_note = $av_paid
		? __( 'Al enviar el formulario recibirás por correo el enlace de pago para confirmar tu lugar.', 'aula-virtual' )
		: __( 'Al enviar el formulario quedas inscrito y recibirás por correo tu acceso al campus.', 'aula-virtual' );
}
?>
<div class="av-reg-page">
	<div class="av-reg">
		<aside class="av-reg__info">
			<?php if ( '' !== $av_cover ) : ?>
				<div class="av-reg__cover"><?php echo $av_cover; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core markup. ?></div>
			<?php endif; ?>
			<p class="av-reg__eyebrow"><?php esc_html_e( 'Inscripción', 'aula-virtual' ); ?></p>
			<h1 class="av-reg__title"><?php echo esc_html( $av_title ); ?></h1>

			<ul class="av-reg__facts">
				<li><span><?php esc_html_e( 'Edición', 'aula-virtual' ); ?></span><strong><?php echo esc_html( (string) $edition['name'] ); ?></strong></li>
				<li><span><?php esc_html_e( 'Modalidad', 'aula-virtual' ); ?></span><?php echo esc_html( $av_modalities[ $edition['modality'] ] ?? (string) $edition['modality'] ); ?></li>
				<?php if ( '' !== $av_start ) : ?>
					<li><span><?php esc_html_e( 'Inicio', 'aula-virtual' ); ?></span><?php echo esc_html( $av_start ); ?></li>
				<?php endif; ?>
				<?php if ( '' !== $av_schedule ) : ?>
					<li><span><?php esc_html_e( 'Horario', 'aula-virtual' ); ?></span><?php echo esc_html( $av_schedule ); ?></li>
				<?php endif; ?>
			</ul>

			<?php if ( ! empty( $edition['price_display'] ) ) : ?>
				<p class="av-reg__price"><?php echo esc_html( (string) $edition['price_display'] ); ?></p>
			<?php endif; ?>

			<p class="av-reg__note"><?php echo esc_html( $av_note ); ?></p>
		</aside>

		<section class="av-reg__main">
			<h2><?php esc_html_e( 'Completa tus datos', 'aula-virtual' ); ?></h2>
			<p class="av-reg__lead"><?php esc_html_e( 'Toma menos de un minuto.', 'aula-virtual' ); ?></p>

			<?php if ( '' !== $error ) : ?>
				<div class="av-reg__error" role="alert"><?php echo esc_html( $error ); ?></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( $action ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( RegistrationController::ACTION_SUBMIT ); ?>">
				<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
				<?php wp_nonce_field( RegistrationController::ACTION_SUBMIT . '_' . $token ); ?>
				<div class="av-reg__hp" aria-hidden="true">
					<label>Website <input type="text" name="av_website" tabindex="-1" autocomplete="off"></label>
				</div>

				<div class="av-reg__grid">
					<p class="av-reg__field">
						<label for="av-first-name"><?php esc_html_e( 'Nombre', 'aula-virtual' ); ?></label>
						<input type="text" id="av-first-name" name="first_name" required autocomplete="given-name">
					</p>
					<p class="av-reg__field">
						<label for="av-last-name"><?php esc_html_e( 'Apellido', 'aula-virtual' ); ?></label>
						<input type="text" id="av-last-name" name="last_name" required autocomplete="family-name">
					</p>
					<p class="av-reg__field av-reg__field--full">
						<label for="av-email"><?php esc_html_e( 'Correo electrónico', 'aula-virtual' ); ?></label>
						<input type="email" id="av-email" name="email" required autocomplete="email" inputmode="email">
					</p>
					<p class="av-reg__field">
						<label for="av-phone"><?php esc_html_e( 'Teléfono / WhatsApp', 'aula-virtual' ); ?></label>
						<input type="tel" id="av-phone" name="phone" autocomplete="tel" inputmode="tel">
					</p>
					<p class="av-reg__field">
						<label for="av-document"><?php esc_html_e( 'DNI o documento', 'aula-virtual' ); ?> <small><?php esc_html_e( '(opcional)', 'aula-virtual' ); ?></small></label>
						<input type="text" id="av-document" name="document" autocomplete="off">
					</p>
				</div>

				<button type="submit" class="av-reg__submit"><?php esc_html_e( 'Enviar inscripción', 'aula-virtual' ); ?></button>
				<p class="av-reg__privacy"><?php esc_html_e( 'Usamos tus datos solo para gestionar tu inscripción y tu acceso al curso.', 'aula-virtual' ); ?></p>
			</form>
		</section>
	</div>
</div>
