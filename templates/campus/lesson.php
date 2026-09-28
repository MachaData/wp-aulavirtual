<?php
/**
 * Campus: one session.
 *
 * @package SIQA\AulaVirtual
 *
 * @var array<string, mixed>             $lesson
 * @var array<string, mixed>|null        $live
 * @var array<int, array<string, mixed>> $materials
 * @var bool                             $completed
 * @var string                           $back_url
 */

use SIQA\AulaVirtual\Campus\CampusController;
use SIQA\AulaVirtual\LiveClasses\LiveClassService;
use SIQA\AulaVirtual\Videos\VideoEmbed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="av-campus av-campus--lesson">
	<p><a href="<?php echo esc_url( $back_url ); ?>">&larr; <?php esc_html_e( 'Volver al temario', 'aula-virtual' ); ?></a></p>

	<h2><?php echo esc_html( (string) $lesson['title'] ); ?></h2>

	<?php if ( null !== $live ) : ?>
		<section class="av-live av-live--<?php echo esc_attr( $live['state'] ); ?>">
			<h3><?php esc_html_e( 'Clase en vivo', 'aula-virtual' ); ?> &middot; <?php echo esc_html( $live['provider'] ); ?></h3>
			<p class="av-live__when">
				<?php echo esc_html( $live['start_local'] ); ?> &ndash; <?php echo esc_html( $live['end_local'] ); ?>
				<small>(<?php echo esc_html( $live['timezone'] ); ?>)</small>
			</p>
			<?php if ( '' !== $live['message'] ) : ?>
				<p class="av-live__message"><?php echo esc_html( $live['message'] ); ?></p>
			<?php endif; ?>
			<?php if ( 'cancelled' === $live['status'] ) : ?>
				<p class="av-live__state"><?php esc_html_e( 'Esta clase fue cancelada.', 'aula-virtual' ); ?></p>
			<?php elseif ( LiveClassService::WINDOW_OPEN === $live['state'] && '' !== $live['url'] ) : ?>
				<p>
					<a class="av-live__join" href="<?php echo esc_url( $live['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Entrar a la clase', 'aula-virtual' ); ?></a>
				</p>
				<?php if ( '' !== $live['meeting_id'] || '' !== $live['access_code'] ) : ?>
					<p class="av-live__credentials">
						<?php if ( '' !== $live['meeting_id'] ) : ?><span><?php esc_html_e( 'ID:', 'aula-virtual' ); ?> <code><?php echo esc_html( $live['meeting_id'] ); ?></code></span><?php endif; ?>
						<?php if ( '' !== $live['access_code'] ) : ?><span><?php esc_html_e( 'Codigo:', 'aula-virtual' ); ?> <code><?php echo esc_html( $live['access_code'] ); ?></code></span><?php endif; ?>
					</p>
				<?php endif; ?>
			<?php elseif ( LiveClassService::WINDOW_BEFORE === $live['state'] ) : ?>
				<p class="av-live__state"><?php esc_html_e( 'El boton para entrar aparecera aqui poco antes de la hora de inicio.', 'aula-virtual' ); ?></p>
			<?php else : ?>
				<p class="av-live__state"><?php esc_html_e( 'La clase ya termino.', 'aula-virtual' ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $live['recording'] ) : ?>
				<div class="av-live__recording">
					<h4><?php esc_html_e( 'Grabacion', 'aula-virtual' ); ?></h4>
					<?php
					echo '<div class="av-video">' . VideoEmbed::render( '', $live['recording'] ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- VideoEmbed escapes.
					?>
				</div>
			<?php endif; ?>
		</section>
	<?php endif; ?>

	<?php if ( ! empty( $lesson['video_url'] ) ) : ?>
		<div class="av-lesson__video av-video">
			<?php
			// VideoEmbed escapa y firma (Bunny) segun el proveedor.
			echo VideoEmbed::render( (string) $lesson['video_provider'], (string) $lesson['video_url'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $lesson['description'] ) ) : ?>
		<p class="av-lesson__description"><?php echo esc_html( (string) $lesson['description'] ); ?></p>
	<?php endif; ?>

	<?php if ( ! empty( $lesson['content'] ) ) : ?>
		<div class="av-lesson__content"><?php echo wp_kses_post( (string) $lesson['content'] ); ?></div>
	<?php endif; ?>

	<?php if ( ! empty( $materials ) ) : ?>
		<section class="av-materials">
			<h3><?php esc_html_e( 'Materiales', 'aula-virtual' ); ?></h3>
			<ul>
				<?php foreach ( $materials as $av_material ) : ?>
					<li>
						<a href="<?php echo esc_url( $av_material['url'] ); ?>" target="_blank" rel="noopener noreferrer" <?php echo $av_material['downloadable'] && 'link' !== $av_material['type'] ? 'download' : ''; ?>>
							<?php echo esc_html( $av_material['title'] ); ?>
						</a>
						<small><?php echo esc_html( strtoupper( $av_material['type'] ) ); ?></small>
						<?php if ( '' !== $av_material['description'] ) : ?>
							<p><?php echo esc_html( $av_material['description'] ); ?></p>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<?php if ( $completed ) : ?>
		<p class="av-lesson__done"><?php esc_html_e( 'Ya marcaste esta sesion como completada.', 'aula-virtual' ); ?></p>
	<?php else : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( CampusController::ACTION_COMPLETE ); ?>">
			<input type="hidden" name="lesson_id" value="<?php echo esc_attr( (string) (int) $lesson['id'] ); ?>">
			<?php wp_nonce_field( CampusController::ACTION_COMPLETE ); ?>
			<button type="submit"><?php esc_html_e( 'Marcar como completada', 'aula-virtual' ); ?></button>
		</form>
	<?php endif; ?>
</div>
