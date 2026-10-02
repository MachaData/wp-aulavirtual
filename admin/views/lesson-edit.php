<?php
/**
 * Lesson editor: content and video on the left, publishing and release on the
 * right, then live class, materials and comments as cards.
 *
 * Every block keeps its own form (and nonce); the side fields belong to the
 * main form through the HTML `form` attribute.
 *
 * @package SIQA\AulaVirtual
 *
 * @var array<string, mixed>                      $lesson
 * @var array<string, mixed>|null                 $edition
 * @var array<string, mixed>|null                 $live
 * @var array<int, array<string, mixed>>          $materials
 * @var array<int, array<string, mixed>>          $comments
 * @var string                                    $timezone
 * @var array{type: string, message: string}|null $notice
 */

use SIQA\AulaVirtual\Admin\AdminMenu;
use SIQA\AulaVirtual\Admin\LessonScreen;
use SIQA\AulaVirtual\Campus\CampusController;
use SIQA\AulaVirtual\Curriculum\LessonType;
use SIQA\AulaVirtual\LiveClasses\LiveClassRepository;
use SIQA\AulaVirtual\LiveClasses\LiveClassService;
use SIQA\AulaVirtual\Materials\MaterialService;
use SIQA\AulaVirtual\Videos\VideoEmbed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$av_lesson_id  = (int) $lesson['id'];
$av_admin_post = admin_url( 'admin-post.php' );
$av_form       = 'av-lesson-form';
$av_hidden     = static function ( string $action ) use ( $av_lesson_id ): void {
	printf( '<input type="hidden" name="action" value="%s"><input type="hidden" name="lesson_id" value="%d">', esc_attr( $action ), $av_lesson_id );
	wp_nonce_field( $action );
};
$av_is_live    = LessonType::LIVE === (string) $lesson['lesson_type'];
$av_published  = 'publish' === (string) $lesson['status'];
$av_release    = (string) $lesson['release_type'];
$av_back       = AdminMenu::editions_url( array( 'edition' => (int) $lesson['edition_id'] ) );
$av_campus_url = '';

if ( CampusController::page_id() > 0 ) {
	$av_base   = (string) get_permalink( CampusController::page_id() );
	$av_pretty = '' !== (string) get_option( 'permalink_structure', '' ) ? CampusController::pretty_path( array( CampusController::QUERY_LESSON => $av_lesson_id ) ) : null;

	$av_campus_url = null !== $av_pretty ? trailingslashit( $av_base ) . $av_pretty : add_query_arg( CampusController::QUERY_LESSON, $av_lesson_id, $av_base );
}
?>
<div class="wrap av-admin av-le">
	<a class="av-back" href="<?php echo esc_url( $av_back ); ?>"><span class="dashicons dashicons-arrow-left-alt2"></span><?php echo esc_html( null === $edition ? __( 'Volver a la edición', 'aula-virtual' ) : sprintf( /* translators: %s: edition name. */ __( 'Temario de %s', 'aula-virtual' ), (string) $edition['name'] ) ); ?></a>

	<div class="av-header">
		<div>
			<p class="av-header__eyebrow"><?php esc_html_e( 'Editar sesión', 'aula-virtual' ); ?></p>
			<h1>
				<?php echo esc_html( (string) $lesson['title'] ); ?>
				<span class="av-badge <?php echo $av_published ? 'av-badge--green' : 'av-badge--gray'; ?>"><?php echo esc_html( $av_published ? __( 'Publicada', 'aula-virtual' ) : __( 'Borrador', 'aula-virtual' ) ); ?></span>
			</h1>
		</div>
		<div class="av-header__actions">
			<?php if ( '' !== $av_campus_url ) : ?>
				<a class="button" href="<?php echo esc_url( $av_campus_url ); ?>" target="_blank" rel="noopener"><span class="dashicons dashicons-visibility"></span><?php esc_html_e( 'Ver en el campus', 'aula-virtual' ); ?></a>
			<?php endif; ?>
			<button type="submit" form="<?php echo esc_attr( $av_form ); ?>" class="button button-primary"><?php esc_html_e( 'Guardar sesión', 'aula-virtual' ); ?></button>
		</div>
	</div>
	<hr class="wp-header-end">

	<?php require AV_PATH . 'admin/views/notice.php'; ?>

	<div class="av-le__grid">
		<div class="av-le__main">

			<form method="post" action="<?php echo esc_url( $av_admin_post ); ?>" id="<?php echo esc_attr( $av_form ); ?>" data-av-dirty-watch>
				<?php $av_hidden( LessonScreen::ACTION_SAVE ); ?>

				<section class="av-card">
					<h2 class="av-card__title"><?php esc_html_e( 'Contenido', 'aula-virtual' ); ?></h2>
					<div class="av-field">
						<label for="av-title"><?php esc_html_e( 'Título', 'aula-virtual' ); ?></label>
						<input type="text" id="av-title" name="title" class="av-input av-input--title" required value="<?php echo esc_attr( (string) $lesson['title'] ); ?>">
					</div>
					<div class="av-field">
						<label for="av-description"><?php esc_html_e( 'Descripción corta', 'aula-virtual' ); ?> <span class="av-optional"><?php esc_html_e( 'opcional', 'aula-virtual' ); ?></span></label>
						<textarea id="av-description" name="description" class="av-input" rows="2" placeholder="<?php esc_attr_e( 'Una o dos frases: qué verá el alumno en esta sesión.', 'aula-virtual' ); ?>"><?php echo esc_textarea( (string) $lesson['description'] ); ?></textarea>
					</div>
					<div class="av-field">
						<label for="av-content"><?php esc_html_e( 'Texto de la sesión', 'aula-virtual' ); ?> <span class="av-optional"><?php esc_html_e( 'opcional', 'aula-virtual' ); ?></span></label>
						<?php
						wp_editor(
							(string) $lesson['content'],
							'av-content',
							array(
								'textarea_name' => 'content',
								'textarea_rows' => 10,
								'media_buttons' => true,
							)
						);
						?>
					</div>
				</section>

				<section class="av-card">
					<h2 class="av-card__title"><span class="dashicons dashicons-video-alt3"></span><?php esc_html_e( 'Video', 'aula-virtual' ); ?></h2>
					<div class="av-field">
						<label for="av-video"><?php esc_html_e( 'Enlace o código del video', 'aula-virtual' ); ?></label>
						<textarea id="av-video" name="video_url" class="av-input av-input--mono" rows="2" placeholder="https://vz-…b-cdn.net/…/playlist.m3u8  ·  https://youtu.be/…  ·  https://vimeo.com/…"><?php echo esc_textarea( (string) $lesson['video_url'] ); ?></textarea>
						<p class="av-help"><?php esc_html_e( 'Pega la URL de Bunny Stream, YouTube, Vimeo o un MP4. También acepta un código incrustado (iframe) o un shortcode.', 'aula-virtual' ); ?></p>
					</div>
					<div class="av-field-row">
						<div class="av-field">
							<label for="av-video-provider"><?php esc_html_e( 'Proveedor', 'aula-virtual' ); ?></label>
							<select id="av-video-provider" name="video_provider" class="av-input">
								<?php foreach ( VideoEmbed::providers() as $av_value => $av_label ) : ?>
									<option value="<?php echo esc_attr( $av_value ); ?>" <?php selected( (string) $lesson['video_provider'], $av_value ); ?>><?php echo esc_html( $av_label ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="av-field">
							<label for="av-duration"><?php esc_html_e( 'Duración', 'aula-virtual' ); ?></label>
							<span class="av-input-suffix"><input type="number" id="av-duration" name="duration" min="0" class="av-input" value="<?php echo esc_attr( (string) (int) $lesson['duration'] ); ?>"><span><?php esc_html_e( 'min', 'aula-virtual' ); ?></span></span>
						</div>
					</div>
					<?php if ( '' !== trim( (string) $lesson['video_url'] ) ) : ?>
						<details class="av-preview">
							<summary><?php esc_html_e( 'Ver vista previa del video guardado', 'aula-virtual' ); ?></summary>
							<div class="av-preview__frame">
								<?php
								// VideoEmbed escapa y firma (Bunny) según el proveedor.
								echo VideoEmbed::render( (string) $lesson['video_provider'], (string) $lesson['video_url'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								?>
							</div>
						</details>
					<?php endif; ?>
				</section>
			</form>

			<section class="av-card" id="av-materials">
				<h2 class="av-card__title"><span class="dashicons dashicons-paperclip"></span><?php esc_html_e( 'Materiales', 'aula-virtual' ); ?> <span class="av-count"><?php echo esc_html( (string) count( $materials ) ); ?></span></h2>
				<p class="av-help"><?php esc_html_e( 'El alumno los ve en la pestaña «Materiales» de la sesión y en el botón «Recursos» del contenido del curso.', 'aula-virtual' ); ?></p>

				<?php if ( ! empty( $materials ) ) : ?>
					<ul class="av-files">
						<?php foreach ( $materials as $av_material ) : ?>
							<li class="av-file">
								<span class="av-file__icon dashicons <?php echo 'link' === (string) $av_material['file_type'] ? 'dashicons-admin-links' : 'dashicons-media-document'; ?>"></span>
								<span class="av-file__text">
									<a href="<?php echo esc_url( MaterialService::url( $av_material ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( (string) $av_material['title'] ); ?></a>
									<small><?php echo esc_html( strtoupper( (string) $av_material['file_type'] ) ); ?> &middot; <?php echo (int) $av_material['downloadable'] ? esc_html__( 'descargable', 'aula-virtual' ) : esc_html__( 'solo lectura', 'aula-virtual' ); ?></small>
								</span>
								<form method="post" action="<?php echo esc_url( $av_admin_post ); ?>" data-av-confirm="<?php esc_attr_e( '¿Quitar este material de la sesión?', 'aula-virtual' ); ?>">
									<?php $av_hidden( LessonScreen::ACTION_DELETE_MATERIAL ); ?>
									<input type="hidden" name="material_id" value="<?php echo esc_attr( (string) (int) $av_material['id'] ); ?>">
									<button type="submit" class="button-link av-file__remove" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: material title. */ __( 'Quitar %s', 'aula-virtual' ), (string) $av_material['title'] ) ); ?>"><span class="dashicons dashicons-trash"></span></button>
								</form>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php else : ?>
					<div class="av-empty"><?php esc_html_e( 'Todavía no hay materiales en esta sesión.', 'aula-virtual' ); ?></div>
				<?php endif; ?>

				<form method="post" action="<?php echo esc_url( $av_admin_post ); ?>" class="av-add-material" data-av-material-form>
					<?php $av_hidden( LessonScreen::ACTION_ADD_MATERIAL ); ?>
					<input type="hidden" name="attachment_id" value="0" data-av-material-attachment>
					<h3><?php esc_html_e( 'Añadir material', 'aula-virtual' ); ?></h3>
					<div class="av-add-material__source">
						<button type="button" class="button av-drop" data-av-material-choose data-av-title="<?php esc_attr_e( 'Elegir o subir material', 'aula-virtual' ); ?>" data-av-button="<?php esc_attr_e( 'Usar este archivo', 'aula-virtual' ); ?>">
							<span class="dashicons dashicons-upload"></span>
							<strong><?php esc_html_e( 'Subir o elegir un archivo', 'aula-virtual' ); ?></strong>
							<small><?php esc_html_e( 'PDF, Word, Excel, PowerPoint, imágenes, audio o ZIP', 'aula-virtual' ); ?></small>
						</button>
						<span class="av-add-material__or"><?php esc_html_e( 'o', 'aula-virtual' ); ?></span>
						<div class="av-field">
							<label for="av-material-url"><?php esc_html_e( 'Enlace externo', 'aula-virtual' ); ?></label>
							<input type="url" id="av-material-url" name="external_url" class="av-input" placeholder="https://drive.google.com/…" data-av-material-url>
						</div>
					</div>
					<p class="av-chip" data-av-material-chip hidden><span class="dashicons dashicons-media-document"></span><span data-av-material-name></span><button type="button" class="button-link" data-av-material-clear aria-label="<?php esc_attr_e( 'Quitar el archivo elegido', 'aula-virtual' ); ?>">&times;</button></p>
					<div class="av-field-row">
						<div class="av-field">
							<label for="av-material-title"><?php esc_html_e( 'Nombre que verá el alumno', 'aula-virtual' ); ?> <span class="av-optional"><?php esc_html_e( 'opcional', 'aula-virtual' ); ?></span></label>
							<input type="text" id="av-material-title" name="title" class="av-input" placeholder="<?php esc_attr_e( 'Se toma del archivo si lo dejas vacío', 'aula-virtual' ); ?>" data-av-material-title>
						</div>
						<label class="av-check"><input type="hidden" name="downloadable" value="0"><input type="checkbox" name="downloadable" value="1" checked> <?php esc_html_e( 'Permitir descarga', 'aula-virtual' ); ?></label>
					</div>
					<p class="av-add-material__submit">
						<button type="submit" class="button button-primary" data-av-material-submit disabled><?php esc_html_e( 'Añadir material', 'aula-virtual' ); ?></button>
						<span class="av-help"><?php printf( /* translators: %s: extensions. */ esc_html__( 'Formatos: %s.', 'aula-virtual' ), esc_html( implode( ', ', MaterialService::allowed_extensions() ) ) ); ?></span>
					</p>
				</form>
			</section>

			<section class="av-card<?php echo $av_is_live || null !== $live ? ' av-card--first' : ' av-card--collapsed'; ?>" id="av-live" data-av-live-card>
				<h2 class="av-card__title"><span class="dashicons dashicons-video-alt2"></span><?php esc_html_e( 'Clase en vivo', 'aula-virtual' ); ?>
					<?php if ( null !== $live ) : ?>
						<span class="av-badge av-badge--blue"><?php echo esc_html( LiveClassService::to_local( (string) $live['start_datetime'], $timezone, (string) get_option( 'date_format' ) . ' H:i' ) ); ?></span>
					<?php endif; ?>
				</h2>

				<?php if ( ! $av_is_live && null === $live ) : ?>
					<p class="av-help av-live-hint"><?php esc_html_e( 'Esta sesión no tiene clase en vivo. Si la tendrá (Zoom, Meet…), elige el tipo «Clase en vivo» a la derecha o prográmala aquí.', 'aula-virtual' ); ?> <button type="button" class="button-link" data-av-live-open><?php esc_html_e( 'Programar una clase en vivo', 'aula-virtual' ); ?></button></p>
				<?php endif; ?>

				<form method="post" action="<?php echo esc_url( $av_admin_post ); ?>" class="av-live-form">
					<?php $av_hidden( LessonScreen::ACTION_SAVE_LIVE ); ?>
					<input type="hidden" name="timezone" value="<?php echo esc_attr( $timezone ); ?>">
					<div class="av-field-row av-field-row--3">
						<div class="av-field">
							<label for="av-live-provider"><?php esc_html_e( 'Plataforma', 'aula-virtual' ); ?></label>
							<select id="av-live-provider" name="provider" class="av-input">
								<?php foreach ( LiveClassService::providers() as $av_value => $av_label ) : ?>
									<option value="<?php echo esc_attr( $av_value ); ?>" <?php selected( $live['provider'] ?? 'zoom', $av_value ); ?>><?php echo esc_html( $av_label ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="av-field">
							<label for="av-live-start"><?php esc_html_e( 'Inicio', 'aula-virtual' ); ?></label>
							<input type="datetime-local" id="av-live-start" name="start_local" class="av-input" required value="<?php echo esc_attr( null === $live ? '' : LiveClassService::to_local( (string) $live['start_datetime'], $timezone, 'Y-m-d\TH:i' ) ); ?>">
						</div>
						<div class="av-field">
							<label for="av-live-length"><?php esc_html_e( 'Duración', 'aula-virtual' ); ?></label>
							<span class="av-input-suffix"><input type="number" id="av-live-length" name="duration" min="15" class="av-input" value="<?php echo esc_attr( (string) ( null === $live ? 90 : max( 15, (int) round( ( strtotime( (string) $live['end_datetime'] ) - strtotime( (string) $live['start_datetime'] ) ) / 60 ) ) ) ); ?>"><span><?php esc_html_e( 'min', 'aula-virtual' ); ?></span></span>
						</div>
					</div>
					<p class="av-help"><?php printf( /* translators: %s: time zone. */ esc_html__( 'Horas en la zona de la edición: %s.', 'aula-virtual' ), esc_html( $timezone ) ); ?></p>
					<div class="av-field">
						<label for="av-live-url"><?php esc_html_e( 'Enlace de la reunión', 'aula-virtual' ); ?></label>
						<input type="url" id="av-live-url" name="meeting_url" class="av-input" placeholder="https://zoom.us/j/…" value="<?php echo esc_attr( (string) ( $live['meeting_url'] ?? '' ) ); ?>">
					</div>
					<div class="av-field-row">
						<div class="av-field">
							<label for="av-live-id"><?php esc_html_e( 'ID de la reunión', 'aula-virtual' ); ?> <span class="av-optional"><?php esc_html_e( 'opcional', 'aula-virtual' ); ?></span></label>
							<input type="text" id="av-live-id" name="meeting_id" class="av-input" value="<?php echo esc_attr( (string) ( $live['meeting_id'] ?? '' ) ); ?>">
						</div>
						<div class="av-field">
							<label for="av-live-code"><?php esc_html_e( 'Código de acceso', 'aula-virtual' ); ?> <span class="av-optional"><?php esc_html_e( 'opcional', 'aula-virtual' ); ?></span></label>
							<input type="text" id="av-live-code" name="access_code" class="av-input" value="<?php echo esc_attr( (string) ( $live['access_code'] ?? '' ) ); ?>">
						</div>
					</div>
					<div class="av-field">
						<label for="av-live-message"><?php esc_html_e( 'Mensaje para el alumno', 'aula-virtual' ); ?> <span class="av-optional"><?php esc_html_e( 'opcional', 'aula-virtual' ); ?></span></label>
						<textarea id="av-live-message" name="message" class="av-input" rows="2"><?php echo esc_textarea( (string) ( $live['message'] ?? '' ) ); ?></textarea>
					</div>
					<details class="av-more"<?php echo null !== $live && ( '' !== (string) $live['recording_url'] || 'scheduled' !== (string) $live['status'] ) ? ' open' : ''; ?>>
						<summary><?php esc_html_e( 'Más opciones: botón de acceso, grabación y estado', 'aula-virtual' ); ?></summary>
						<div class="av-field-row">
							<div class="av-field">
								<label for="av-live-before"><?php esc_html_e( 'Mostrar el botón', 'aula-virtual' ); ?></label>
								<span class="av-input-suffix"><input type="number" id="av-live-before" name="open_before" min="0" class="av-input" value="<?php echo esc_attr( (string) (int) ( $live['open_before'] ?? 15 ) ); ?>"><span><?php esc_html_e( 'min antes', 'aula-virtual' ); ?></span></span>
							</div>
							<div class="av-field">
								<label for="av-live-after"><?php esc_html_e( 'Ocultar el botón', 'aula-virtual' ); ?></label>
								<span class="av-input-suffix"><input type="number" id="av-live-after" name="close_after" min="0" class="av-input" value="<?php echo esc_attr( (string) (int) ( $live['close_after'] ?? 30 ) ); ?>"><span><?php esc_html_e( 'min después', 'aula-virtual' ); ?></span></span>
							</div>
						</div>
						<div class="av-field">
							<label for="av-live-recording"><?php esc_html_e( 'Grabación (después de la clase)', 'aula-virtual' ); ?></label>
							<input type="url" id="av-live-recording" name="recording_url" class="av-input" value="<?php echo esc_attr( (string) ( $live['recording_url'] ?? '' ) ); ?>">
						</div>
						<div class="av-field">
							<label for="av-live-status"><?php esc_html_e( 'Estado', 'aula-virtual' ); ?></label>
							<select id="av-live-status" name="status" class="av-input">
								<option value="<?php echo esc_attr( LiveClassRepository::STATUS_SCHEDULED ); ?>" <?php selected( $live['status'] ?? 'scheduled', 'scheduled' ); ?>><?php esc_html_e( 'Programada', 'aula-virtual' ); ?></option>
								<option value="<?php echo esc_attr( LiveClassRepository::STATUS_DONE ); ?>" <?php selected( $live['status'] ?? '', 'done' ); ?>><?php esc_html_e( 'Realizada', 'aula-virtual' ); ?></option>
								<option value="<?php echo esc_attr( LiveClassRepository::STATUS_CANCELLED ); ?>" <?php selected( $live['status'] ?? '', 'cancelled' ); ?>><?php esc_html_e( 'Cancelada', 'aula-virtual' ); ?></option>
							</select>
						</div>
					</details>
					<p class="av-card__actions">
						<button type="submit" class="button button-primary"><?php echo esc_html( null === $live ? __( 'Programar clase', 'aula-virtual' ) : __( 'Guardar clase', 'aula-virtual' ) ); ?></button>
					</p>
				</form>
				<?php if ( null !== $live ) : ?>
					<form method="post" action="<?php echo esc_url( $av_admin_post ); ?>" class="av-inline-form" data-av-confirm="<?php esc_attr_e( '¿Quitar la clase en vivo de esta sesión?', 'aula-virtual' ); ?>">
						<?php $av_hidden( LessonScreen::ACTION_REMOVE_LIVE ); ?>
						<button type="submit" class="button-link-delete"><?php esc_html_e( 'Quitar clase en vivo', 'aula-virtual' ); ?></button>
					</form>
				<?php endif; ?>
			</section>

			<?php if ( isset( $comments ) && is_array( $comments ) ) : ?>
				<section class="av-card av-card--comments">
					<h2 class="av-card__title"><span class="dashicons dashicons-format-chat"></span><?php esc_html_e( 'Preguntas de los alumnos', 'aula-virtual' ); ?></h2>
					<?php if ( empty( $comments ) ) : ?>
						<div class="av-empty"><?php esc_html_e( 'Sin preguntas todavía. Puedes responder desde la misma sesión en el campus.', 'aula-virtual' ); ?></div>
					<?php else : ?>
						<ul class="av-comments">
							<?php foreach ( $comments as $av_root ) : ?>
								<?php foreach ( array_merge( array( $av_root ), $av_root['replies'] ) as $av_c ) : ?>
									<li class="av-comment<?php echo (int) $av_c['parent_id'] > 0 ? ' av-comment--reply' : ''; ?>">
										<div>
											<strong><?php echo esc_html( (string) $av_c['author'] ); ?></strong>
											<?php if ( (int) $av_c['is_staff'] ) : ?><span class="av-badge av-badge--blue av-badge--plain"><?php esc_html_e( 'Instructor', 'aula-virtual' ); ?></span><?php endif; ?>
											<small><?php echo esc_html( mysql2date( (string) get_option( 'date_format' ) . ' H:i', (string) $av_c['created_at'] ) ); ?></small>
											<p><?php echo esc_html( (string) $av_c['content'] ); ?></p>
										</div>
										<form method="post" action="<?php echo esc_url( $av_admin_post ); ?>" data-av-confirm="<?php esc_attr_e( '¿Eliminar este comentario?', 'aula-virtual' ); ?>">
											<?php $av_hidden( LessonScreen::ACTION_DELETE_COMMENT ); ?>
											<input type="hidden" name="comment_id" value="<?php echo esc_attr( (string) (int) $av_c['id'] ); ?>">
											<button type="submit" class="button-link av-file__remove" aria-label="<?php esc_attr_e( 'Eliminar', 'aula-virtual' ); ?>"><span class="dashicons dashicons-trash"></span></button>
										</form>
									</li>
								<?php endforeach; ?>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</section>
			<?php endif; ?>
		</div>

		<aside class="av-le__side">
			<section class="av-card">
				<h2 class="av-card__title"><?php esc_html_e( 'Publicación', 'aula-virtual' ); ?></h2>
				<div class="av-field">
					<label for="av-status"><?php esc_html_e( 'Estado', 'aula-virtual' ); ?></label>
					<select id="av-status" name="status" class="av-input" form="<?php echo esc_attr( $av_form ); ?>">
						<option value="publish" <?php selected( $lesson['status'], 'publish' ); ?>><?php esc_html_e( 'Publicada (visible en el campus)', 'aula-virtual' ); ?></option>
						<option value="draft" <?php selected( $lesson['status'], 'draft' ); ?>><?php esc_html_e( 'Borrador (oculta)', 'aula-virtual' ); ?></option>
					</select>
				</div>
				<div class="av-field">
					<label for="av-type"><?php esc_html_e( 'Tipo de sesión', 'aula-virtual' ); ?></label>
					<select id="av-type" name="lesson_type" class="av-input" form="<?php echo esc_attr( $av_form ); ?>" data-av-lesson-type>
						<?php foreach ( LessonType::available() as $av_value => $av_label ) : ?>
							<option value="<?php echo esc_attr( $av_value ); ?>" <?php selected( $lesson['lesson_type'], $av_value ); ?>><?php echo esc_html( $av_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<label class="av-check"><input type="hidden" name="is_preview" value="0" form="<?php echo esc_attr( $av_form ); ?>"><input type="checkbox" name="is_preview" value="1" form="<?php echo esc_attr( $av_form ); ?>" <?php checked( (int) $lesson['is_preview'], 1 ); ?>> <?php esc_html_e( 'Vista previa gratuita en la landing', 'aula-virtual' ); ?></label>
				<p class="av-card__actions">
					<button type="submit" form="<?php echo esc_attr( $av_form ); ?>" class="button button-primary button-large av-block"><?php esc_html_e( 'Guardar sesión', 'aula-virtual' ); ?></button>
				</p>
			</section>

			<section class="av-card">
				<h2 class="av-card__title"><?php esc_html_e( 'Disponibilidad', 'aula-virtual' ); ?></h2>
				<div class="av-field">
					<label for="av-release"><?php esc_html_e( '¿Cuándo se abre para el alumno?', 'aula-virtual' ); ?></label>
					<select id="av-release" name="release_type" class="av-input" form="<?php echo esc_attr( $av_form ); ?>" data-av-release>
						<option value="immediate" <?php selected( $av_release, 'immediate' ); ?>><?php esc_html_e( 'De inmediato', 'aula-virtual' ); ?></option>
						<option value="date" <?php selected( $av_release, 'date' ); ?>><?php esc_html_e( 'A partir de una fecha', 'aula-virtual' ); ?></option>
						<option value="offset" <?php selected( $av_release, 'offset' ); ?>><?php esc_html_e( 'Días después de matricularse', 'aula-virtual' ); ?></option>
					</select>
				</div>
				<div class="av-field" data-av-release-show="date"<?php echo 'date' === $av_release ? '' : ' hidden'; ?>>
					<label for="av-release-date"><?php esc_html_e( 'Fecha de apertura', 'aula-virtual' ); ?></label>
					<input type="date" id="av-release-date" name="release_date" class="av-input" form="<?php echo esc_attr( $av_form ); ?>" value="<?php echo esc_attr( empty( $lesson['release_date'] ) ? '' : substr( (string) $lesson['release_date'], 0, 10 ) ); ?>">
				</div>
				<div class="av-field" data-av-release-show="offset"<?php echo 'offset' === $av_release ? '' : ' hidden'; ?>>
					<label for="av-release-offset"><?php esc_html_e( 'Días después de la matrícula', 'aula-virtual' ); ?></label>
					<span class="av-input-suffix"><input type="number" id="av-release-offset" name="release_offset" min="0" class="av-input" form="<?php echo esc_attr( $av_form ); ?>" value="<?php echo esc_attr( (string) (int) $lesson['release_offset'] ); ?>"><span><?php esc_html_e( 'días', 'aula-virtual' ); ?></span></span>
				</div>
				<p class="av-help"><?php esc_html_e( 'Mientras no se abra, el alumno la ve con candado y la fecha en el temario.', 'aula-virtual' ); ?></p>
			</section>

			<section class="av-card av-card--danger">
				<form method="post" action="<?php echo esc_url( $av_admin_post ); ?>" data-av-confirm="<?php esc_attr_e( '¿Eliminar esta sesión y el progreso de los alumnos en ella? No se puede deshacer.', 'aula-virtual' ); ?>">
					<?php $av_hidden( LessonScreen::ACTION_DELETE ); ?>
					<button type="submit" class="button-link-delete"><span class="dashicons dashicons-trash"></span><?php esc_html_e( 'Eliminar sesión', 'aula-virtual' ); ?></button>
				</form>
			</section>
		</aside>
	</div>
</div>
