<?php
/**
 * Campus: one session, with the course outline on the right.
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
 * @var int                              $done_count
 * @var float                            $percentage
 * @var array{title: string, url: string, available: bool}|null $prev
 * @var array{title: string, url: string, available: bool}|null $next
 * @var array<int, array<string, mixed>> $sections
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

$av_duration   = LandingRenderer::duration_label( (int) ( $lesson['duration'] ?? 0 ) );
$av_percent    = max( 0.0, min( 100.0, (float) ( $percentage ?? 0 ) ) );
$av_sections   = isset( $sections ) && is_array( $sections ) ? $sections : array();
$av_comments_n = 0;
$av_type_icon  = array(
	LessonType::VIDEO    => 'play',
	LessonType::TEXT     => 'text',
	LessonType::LIVE     => 'live',
	LessonType::MATERIAL => 'file',
);

foreach ( (array) ( $comments ?? array() ) as $av_c ) {
	$av_comments_n += 1 + count( (array) ( $av_c['replies'] ?? array() ) );
}

$av_has_text  = ! empty( $lesson['description'] ) || ! empty( $lesson['content'] );
$av_tabs      = array_filter(
	array(
		'about'     => $av_has_text ? __( 'Descripción', 'aula-virtual' ) : '',
		'materials' => ! empty( $materials ) ? sprintf( /* translators: %d: number of files. */ __( 'Materiales (%d)', 'aula-virtual' ), count( $materials ) ) : '',
		'comments'  => ! empty( $comments_enabled ) ? ( $av_comments_n > 0 ? sprintf( /* translators: %d: number of comments. */ __( 'Preguntas (%d)', 'aula-virtual' ), $av_comments_n ) : __( 'Preguntas', 'aula-virtual' ) ) : '',
	)
);
$av_first_tab = (string) array_key_first( $av_tabs );
?>
<div class="av-campus av-campus--lesson av-player" data-av-player>
	<div class="av-player__bar">
		<a class="av-c-back" href="<?php echo esc_url( $back_url ); ?>"><?php echo LandingRenderer::icon( 'back' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?><span><?php echo esc_html( '' !== (string) ( $course_title ?? '' ) ? (string) $course_title : __( 'Volver al temario', 'aula-virtual' ) ); ?></span></a>
		<button type="button" class="av-player__toggle" data-av-outline-toggle aria-controls="av-outline" aria-expanded="true">
			<?php echo LandingRenderer::icon( 'book' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?>
			<span><?php esc_html_e( 'Contenido del curso', 'aula-virtual' ); ?></span>
		</button>
	</div>

	<div class="av-player__layout">
		<div class="av-player__main">
			<?php if ( ! empty( $lesson['video_url'] ) ) : ?>
				<div class="av-player__stage av-lesson__video av-video">
					<?php
					// VideoEmbed escapa y firma (Bunny) segun el proveedor.
					echo VideoEmbed::render( (string) ( $lesson['video_provider'] ?? '' ), (string) $lesson['video_url'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
				</div>
			<?php endif; ?>

			<div class="av-player__head">
				<div>
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
				</div>
				<div class="av-player__complete">
					<?php if ( $completed ) : ?>
						<span class="av-c-badge av-c-badge--done"><?php echo LandingRenderer::icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?><?php esc_html_e( 'Completada', 'aula-virtual' ); ?></span>
					<?php else : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="<?php echo esc_attr( CampusController::ACTION_COMPLETE ); ?>">
							<input type="hidden" name="lesson_id" value="<?php echo esc_attr( (string) (int) $lesson['id'] ); ?>">
							<?php wp_nonce_field( CampusController::ACTION_COMPLETE ); ?>
							<button type="submit" class="av-c-btn"><?php echo LandingRenderer::icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?><?php esc_html_e( 'Marcar como completada', 'aula-virtual' ); ?></button>
						</form>
					<?php endif; ?>
				</div>
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

			<nav class="av-c-lesson-nav" aria-label="<?php esc_attr_e( 'Navegación entre sesiones', 'aula-virtual' ); ?>">
				<div class="av-c-lesson-nav__prev">
					<?php if ( ! empty( $prev ) ) : ?>
						<a href="<?php echo esc_url( $prev['url'] ); ?>"><?php echo LandingRenderer::icon( 'back' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?><span><small><?php esc_html_e( 'Anterior', 'aula-virtual' ); ?></small><?php echo esc_html( $prev['title'] ); ?></span></a>
					<?php endif; ?>
				</div>
				<div class="av-c-lesson-nav__next">
					<?php if ( ! empty( $next ) ) : ?>
						<a href="<?php echo esc_url( $next['url'] ); ?>"><span><small><?php echo esc_html( empty( $next['available'] ) ? __( 'Siguiente (aún cerrada)', 'aula-virtual' ) : __( 'Siguiente', 'aula-virtual' ) ); ?></small><?php echo esc_html( $next['title'] ); ?></span><?php echo LandingRenderer::icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?></a>
					<?php endif; ?>
				</div>
			</nav>

			<?php if ( array() !== $av_tabs ) : ?>
				<div class="av-tabs" data-av-tabs>
					<div class="av-tabs__list" role="tablist" aria-label="<?php esc_attr_e( 'Secciones de la sesión', 'aula-virtual' ); ?>">
						<?php foreach ( $av_tabs as $av_key => $av_label ) : ?>
							<button type="button" role="tab" class="av-tabs__tab" id="av-tab-<?php echo esc_attr( $av_key ); ?>" aria-controls="av-panel-<?php echo esc_attr( $av_key ); ?>" aria-selected="<?php echo $av_key === $av_first_tab ? 'true' : 'false'; ?>"><?php echo esc_html( $av_label ); ?></button>
						<?php endforeach; ?>
					</div>

					<?php if ( isset( $av_tabs['about'] ) ) : ?>
						<section class="av-tabs__panel" role="tabpanel" id="av-panel-about" aria-labelledby="av-tab-about">
							<?php if ( ! empty( $lesson['description'] ) ) : ?>
								<p class="av-lesson__description av-c-lead"><?php echo esc_html( (string) $lesson['description'] ); ?></p>
							<?php endif; ?>
							<?php if ( ! empty( $lesson['content'] ) ) : ?>
								<div class="av-lesson__content av-c-prose"><?php echo wp_kses_post( (string) $lesson['content'] ); ?></div>
							<?php endif; ?>
						</section>
					<?php endif; ?>

					<?php if ( isset( $av_tabs['materials'] ) ) : ?>
						<section class="av-tabs__panel av-materials" role="tabpanel" id="av-panel-materials" aria-labelledby="av-tab-materials">
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

					<?php if ( isset( $av_tabs['comments'] ) ) : ?>
						<div class="av-tabs__panel" role="tabpanel" id="av-panel-comments" aria-labelledby="av-tab-comments">
				<section class="av-comments" id="av-comments">
					<h2 class="av-c-sr"><?php esc_html_e( 'Preguntas y comentarios', 'aula-virtual' ); ?></h2>

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
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>

		<aside class="av-outline" id="av-outline" aria-label="<?php esc_attr_e( 'Contenido del curso', 'aula-virtual' ); ?>">
			<div class="av-outline__head">
				<h2><?php esc_html_e( 'Contenido del curso', 'aula-virtual' ); ?></h2>
				<button type="button" class="av-outline__close" data-av-outline-toggle aria-controls="av-outline" aria-label="<?php esc_attr_e( 'Cerrar el contenido del curso', 'aula-virtual' ); ?>">&times;</button>
			</div>
			<div class="av-outline__progress">
				<span class="av-c-progress__bar"><span style="width:<?php echo esc_attr( (string) round( $av_percent, 1 ) ); ?>%"></span></span>
				<small>
					<?php
					printf(
						/* translators: 1: completed sessions, 2: total sessions. */
						esc_html__( '%1$d de %2$d sesiones completadas', 'aula-virtual' ),
						(int) ( $done_count ?? 0 ),
						(int) ( $total ?? 0 )
					);
					?>
				</small>
			</div>

			<div class="av-outline__sections">
				<?php foreach ( $av_sections as $av_s_index => $av_section ) : ?>
					<?php
					$av_has_current = in_array( (int) $lesson['id'], array_map( static fn( array $i ): int => (int) $i['lesson']['id'], $av_section['items'] ), true );
					$av_s_title     = '' !== $av_section['title']
						? sprintf( /* translators: 1: section number, 2: section title. */ __( 'Sección %1$d: %2$s', 'aula-virtual' ), $av_s_index + 1, $av_section['title'] )
						: __( 'Sesiones', 'aula-virtual' );
					$av_s_minutes   = LandingRenderer::duration_label( (int) $av_section['minutes'] );
					?>
					<details class="av-outline__section"<?php echo $av_has_current || 1 === count( $av_sections ) ? ' open' : ''; ?>>
						<summary>
							<span class="av-outline__section-title"><?php echo esc_html( $av_s_title ); ?></span>
							<span class="av-outline__section-meta"><?php echo esc_html( (int) $av_section['done'] . ' / ' . (int) $av_section['total'] . ( '' !== $av_s_minutes ? ' | ' . $av_s_minutes : '' ) ); ?></span>
							<?php echo LandingRenderer::icon( 'down' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?>
						</summary>
						<ol class="av-outline__items">
							<?php foreach ( $av_section['items'] as $av_item ) : ?>
								<?php
								$av_l         = $av_item['lesson'];
								$av_current   = (int) $av_l['id'] === (int) $lesson['id'];
								$av_available = ! empty( $av_item['available'] );
								$av_minutes   = LandingRenderer::duration_label( (int) ( $av_l['duration'] ?? 0 ) );
								$av_class     = array( 'av-outline__item' );
								$av_class[]   = $av_item['completed'] ? 'is-completed' : ( $av_available ? 'is-open' : 'is-locked' );

								if ( $av_current ) {
									$av_class[] = 'is-current';
								}
								?>
								<li class="<?php echo esc_attr( implode( ' ', $av_class ) ); ?>"<?php echo $av_current ? ' aria-current="step"' : ''; ?>>
									<span class="av-outline__check" aria-hidden="true"><?php echo $av_item['completed'] ? LandingRenderer::icon( 'check' ) : ( $av_available ? '' : LandingRenderer::icon( 'lock' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?></span>
									<div class="av-outline__body">
										<?php if ( $av_available ) : ?>
											<a class="av-outline__title" href="<?php echo esc_url( (string) $av_item['url'] ); ?>"><?php echo esc_html( (int) $av_item['number'] . '. ' . (string) $av_l['title'] ); ?></a>
										<?php else : ?>
											<span class="av-outline__title"><?php echo esc_html( (int) $av_item['number'] . '. ' . (string) $av_l['title'] ); ?></span>
										<?php endif; ?>
										<div class="av-outline__meta">
											<span>
												<?php echo LandingRenderer::icon( $av_type_icon[ (string) $av_l['lesson_type'] ] ?? 'text' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?>
												<?php
												if ( ! $av_available ) {
													/* translators: %s: date. */
													echo esc_html( sprintf( __( 'Se abre el %s', 'aula-virtual' ), (string) $av_item['available_at'] ) );
												} else {
													echo esc_html( '' !== $av_minutes ? $av_minutes : LessonType::label( (string) $av_l['lesson_type'] ) );
												}
												?>
											</span>
											<?php if ( ! empty( $av_item['materials'] ) ) : ?>
												<details class="av-outline__res">
													<summary><?php echo LandingRenderer::icon( 'file' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?><?php echo esc_html( sprintf( /* translators: %d: number of files. */ __( 'Recursos (%d)', 'aula-virtual' ), count( $av_item['materials'] ) ) ); ?></summary>
													<ul>
														<?php foreach ( $av_item['materials'] as $av_res ) : ?>
															<li><a href="<?php echo esc_url( $av_res['url'] ); ?>" target="_blank" rel="noopener noreferrer" <?php echo $av_res['downloadable'] && 'link' !== $av_res['type'] ? 'download' : ''; ?>><?php echo LandingRenderer::icon( 'link' === $av_res['type'] ? 'link' : 'download' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?><?php echo esc_html( $av_res['title'] ); ?></a></li>
														<?php endforeach; ?>
													</ul>
												</details>
											<?php endif; ?>
										</div>
									</div>
								</li>
							<?php endforeach; ?>
						</ol>
					</details>
				<?php endforeach; ?>
			</div>
		</aside>
		<div class="av-outline__backdrop" data-av-outline-close hidden></div>
	</div>
</div>
