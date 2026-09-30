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
 * @var int                              $position
 * @var int                              $total
 * @var array{title: string, url: string}|null $prev
 * @var array{title: string, url: string}|null $next
 * @var string                           $course_title
 */

use SIQA\AulaVirtual\Campus\CampusController;
use SIQA\AulaVirtual\Curriculum\LessonType;
use SIQA\AulaVirtual\Landing\LandingRenderer;
use SIQA\AulaVirtual\LiveClasses\LiveClassService;
use SIQA\AulaVirtual\Videos\VideoEmbed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<?php
$av_duration = LandingRenderer::duration_label( (int) ( $lesson['duration'] ?? 0 ) );
?>
<div class="av-campus av-campus--lesson">
	<a class="av-c-back" href="<?php echo esc_url( $back_url ); ?>"><?php echo LandingRenderer::icon( 'back' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?><?php echo esc_html( '' !== (string) ( $course_title ?? '' ) ? (string) $course_title : __( 'Volver al temario', 'aula-virtual' ) ); ?></a>

	<div class="av-c-lesson-head">
		<p class="av-c-eyebrow">
			<?php if ( ! empty( $position ) && ! empty( $total ) ) : ?>
				<?php
				printf(
					/* translators: 1: session number, 2: total sessions. */
					esc_html__( 'Sesión %1$d de %2$d', 'aula-virtual' ),
					(int) $position,
					(int) $total
				);
				?>
				&middot;
			<?php endif; ?>
			<?php echo esc_html( LessonType::label( (string) $lesson['lesson_type'] ) ); ?>
			<?php if ( '' !== $av_duration ) : ?>
				&middot; <?php echo esc_html( $av_duration ); ?>
			<?php endif; ?>
		</p>
		<h1 class="av-c-title"><?php echo esc_html( (string) $lesson['title'] ); ?></h1>
		<?php if ( $completed ) : ?>
			<p class="av-c-badge av-c-badge--done"><?php echo LandingRenderer::icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?><?php esc_html_e( 'Completada', 'aula-virtual' ); ?></p>
		<?php endif; ?>
	</div>

	<?php if ( null !== $live ) : ?>
		<section class="av-live av-c-card av-live--<?php echo esc_attr( $live['state'] ); ?>">
			<h2 class="av-c-card__title"><?php echo LandingRenderer::icon( 'live' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?><?php esc_html_e( 'Clase en vivo', 'aula-virtual' ); ?> &middot; <?php echo esc_html( $live['provider'] ); ?></h2>
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
					<a class="av-live__join av-c-btn" href="<?php echo esc_url( $live['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Entrar a la clase', 'aula-virtual' ); ?></a>
				</p>
				<?php if ( '' !== $live['meeting_id'] || '' !== $live['access_code'] ) : ?>
					<p class="av-live__credentials">
						<?php if ( '' !== $live['meeting_id'] ) : ?><span><?php esc_html_e( 'ID:', 'aula-virtual' ); ?> <code><?php echo esc_html( $live['meeting_id'] ); ?></code></span><?php endif; ?>
						<?php if ( '' !== $live['access_code'] ) : ?><span><?php esc_html_e( 'Código:', 'aula-virtual' ); ?> <code><?php echo esc_html( $live['access_code'] ); ?></code></span><?php endif; ?>
					</p>
				<?php endif; ?>
			<?php elseif ( LiveClassService::WINDOW_BEFORE === $live['state'] ) : ?>
				<p class="av-live__state"><?php esc_html_e( 'El botón para entrar aparecerá aquí poco antes de la hora de inicio.', 'aula-virtual' ); ?></p>
			<?php else : ?>
				<p class="av-live__state"><?php esc_html_e( 'La clase ya terminó.', 'aula-virtual' ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $live['recording'] ) : ?>
				<div class="av-live__recording">
					<h4><?php esc_html_e( 'Grabación', 'aula-virtual' ); ?></h4>
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
		<p class="av-lesson__description av-c-lead"><?php echo esc_html( (string) $lesson['description'] ); ?></p>
	<?php endif; ?>

	<?php if ( ! empty( $lesson['content'] ) ) : ?>
		<div class="av-lesson__content av-c-prose"><?php echo wp_kses_post( (string) $lesson['content'] ); ?></div>
	<?php endif; ?>

	<?php if ( ! empty( $materials ) ) : ?>
		<section class="av-materials av-c-section">
			<h2 class="av-c-section__title"><?php esc_html_e( 'Materiales', 'aula-virtual' ); ?></h2>
			<ul class="av-c-files">
				<?php foreach ( $materials as $av_material ) : ?>
					<li>
						<a class="av-c-file" href="<?php echo esc_url( $av_material['url'] ); ?>" target="_blank" rel="noopener noreferrer" <?php echo $av_material['downloadable'] && 'link' !== $av_material['type'] ? 'download' : ''; ?>>
							<span class="av-c-file__icon"><?php echo LandingRenderer::icon( 'link' === $av_material['type'] ? 'link' : 'file' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?></span>
							<span class="av-c-file__text">
								<strong><?php echo esc_html( $av_material['title'] ); ?></strong>
								<?php if ( '' !== $av_material['description'] ) : ?>
									<small><?php echo esc_html( $av_material['description'] ); ?></small>
								<?php endif; ?>
							</span>
							<span class="av-c-file__type"><?php echo esc_html( strtoupper( $av_material['type'] ) ); ?></span>
							<span class="av-c-file__go"><?php echo LandingRenderer::icon( $av_material['downloadable'] && 'link' !== $av_material['type'] ? 'download' : 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<nav class="av-c-lesson-nav" aria-label="<?php esc_attr_e( 'Navegación entre sesiones', 'aula-virtual' ); ?>">
		<div class="av-c-lesson-nav__prev">
			<?php if ( ! empty( $prev ) ) : ?>
				<a href="<?php echo esc_url( $prev['url'] ); ?>"><small><?php esc_html_e( 'Anterior', 'aula-virtual' ); ?></small><span><?php echo esc_html( $prev['title'] ); ?></span></a>
			<?php endif; ?>
		</div>
		<div class="av-c-lesson-nav__done">
			<?php if ( $completed ) : ?>
				<span class="av-c-badge av-c-badge--done"><?php echo LandingRenderer::icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?><?php esc_html_e( 'Sesión completada', 'aula-virtual' ); ?></span>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( CampusController::ACTION_COMPLETE ); ?>">
					<input type="hidden" name="lesson_id" value="<?php echo esc_attr( (string) (int) $lesson['id'] ); ?>">
					<?php wp_nonce_field( CampusController::ACTION_COMPLETE ); ?>
					<button type="submit" class="av-c-btn"><?php echo LandingRenderer::icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?><?php esc_html_e( 'Marcar como completada', 'aula-virtual' ); ?></button>
				</form>
			<?php endif; ?>
		</div>
		<div class="av-c-lesson-nav__next">
			<?php if ( ! empty( $next ) ) : ?>
				<a href="<?php echo esc_url( $next['url'] ); ?>"><small><?php esc_html_e( 'Siguiente', 'aula-virtual' ); ?></small><span><?php echo esc_html( $next['title'] ); ?></span></a>
			<?php endif; ?>
		</div>
	</nav>
	<?php if ( ! empty( $comments_enabled ) ) : ?>
		<section class="av-comments av-c-section" id="av-comments">
			<h2 class="av-c-section__title"><?php esc_html_e( 'Preguntas y comentarios', 'aula-virtual' ); ?></h2>

			<?php if ( empty( $comments ) ) : ?>
				<p class="av-comments__empty"><?php esc_html_e( 'Todavía no hay comentarios. Escribe el primero.', 'aula-virtual' ); ?></p>
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
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="av-comment__delete" onsubmit="return confirm('<?php echo esc_js( __( '¿Eliminar este comentario?', 'aula-virtual' ) ); ?>');">
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
											<button type="submit" class="av-c-btn av-c-btn--small"><?php esc_html_e( 'Enviar respuesta', 'aula-virtual' ); ?></button>
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
				<button type="submit" class="av-c-btn"><?php esc_html_e( 'Publicar', 'aula-virtual' ); ?></button>
			</form>
		</section>
	<?php endif; ?>

</div>
