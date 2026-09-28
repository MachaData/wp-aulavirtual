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
 * @var bool                             $comments_enabled
 * @var array<int, array<string, mixed>> $comments
 * @var bool                             $can_moderate
 * @var int                              $current_user_id
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

	<?php if ( ! empty( $comments_enabled ) ) : ?>
		<section class="av-comments" id="av-comments">
			<h3><?php esc_html_e( 'Preguntas y comentarios', 'aula-virtual' ); ?></h3>

			<?php if ( empty( $comments ) ) : ?>
				<p class="av-comments__empty"><?php esc_html_e( 'Todavia no hay comentarios. Escribe el primero.', 'aula-virtual' ); ?></p>
			<?php else : ?>
				<ul class="av-comments__list">
					<?php foreach ( $comments as $av_comment ) : ?>
						<?php $av_render = static function ( array $c ) use ( $can_moderate, $current_user_id, $lesson ): void { ?>
							<li class="av-comment<?php echo (int) $c['is_staff'] ? ' av-comment--staff' : ''; ?>" id="av-comment-<?php echo esc_attr( (string) (int) $c['id'] ); ?>">
								<p class="av-comment__meta">
									<strong><?php echo esc_html( (string) $c['author'] ); ?></strong>
									<?php if ( (int) $c['is_staff'] ) : ?><span class="av-comment__badge"><?php esc_html_e( 'Instructor', 'aula-virtual' ); ?></span><?php endif; ?>
									<small><?php echo esc_html( mysql2date( (string) get_option( 'date_format' ) . ' H:i', (string) $c['created_at'] ) ); ?></small>
								</p>
								<div class="av-comment__body"><?php echo wp_kses_post( wpautop( esc_html( (string) $c['content'] ) ) ); ?></div>
								<?php if ( $can_moderate || (int) $c['user_id'] === $current_user_id ) : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="av-comment__delete" onsubmit="return confirm('<?php echo esc_js( __( 'Eliminar este comentario?', 'aula-virtual' ) ); ?>');">
										<input type="hidden" name="action" value="<?php echo esc_attr( CampusController::ACTION_DELETE_COMMENT ); ?>">
										<input type="hidden" name="comment_id" value="<?php echo esc_attr( (string) (int) $c['id'] ); ?>">
										<input type="hidden" name="lesson_id" value="<?php echo esc_attr( (string) (int) $lesson['id'] ); ?>">
										<?php wp_nonce_field( CampusController::ACTION_DELETE_COMMENT ); ?>
										<button type="submit" class="av-link-button"><?php esc_html_e( 'Eliminar', 'aula-virtual' ); ?></button>
									</form>
								<?php endif; ?>
								<?php if ( (int) $c['parent_id'] === 0 ) : ?>
									<details class="av-comment__reply">
										<summary><?php esc_html_e( 'Responder', 'aula-virtual' ); ?></summary>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
											<input type="hidden" name="action" value="<?php echo esc_attr( CampusController::ACTION_COMMENT ); ?>">
											<input type="hidden" name="lesson_id" value="<?php echo esc_attr( (string) (int) $lesson['id'] ); ?>">
											<input type="hidden" name="parent_id" value="<?php echo esc_attr( (string) (int) $c['id'] ); ?>">
											<?php wp_nonce_field( CampusController::ACTION_COMMENT ); ?>
											<textarea name="content" rows="3" required maxlength="2000"></textarea>
											<button type="submit"><?php esc_html_e( 'Enviar respuesta', 'aula-virtual' ); ?></button>
										</form>
									</details>
								<?php endif; ?>
							</li>
						<?php }; ?>
						<?php $av_render( $av_comment ); ?>
						<?php if ( ! empty( $av_comment['replies'] ) ) : ?>
							<ul class="av-comments__replies">
								<?php foreach ( $av_comment['replies'] as $av_reply ) : ?>
									<?php $av_render( $av_reply ); ?>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="av-comments__form">
				<input type="hidden" name="action" value="<?php echo esc_attr( CampusController::ACTION_COMMENT ); ?>">
				<input type="hidden" name="lesson_id" value="<?php echo esc_attr( (string) (int) $lesson['id'] ); ?>">
				<?php wp_nonce_field( CampusController::ACTION_COMMENT ); ?>
				<label for="av-comment-content"><?php esc_html_e( 'Escribe tu pregunta o comentario', 'aula-virtual' ); ?></label>
				<textarea name="content" id="av-comment-content" rows="4" required maxlength="2000"></textarea>
				<button type="submit"><?php esc_html_e( 'Publicar', 'aula-virtual' ); ?></button>
			</form>
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
