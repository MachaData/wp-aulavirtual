<?php
/**
 * Lesson editor.
 *
 * @package SIQA\AulaVirtual
 *
 * @var array<string, mixed>                      $lesson
 * @var array<string, mixed>|null                 $edition
 * @var array<string, mixed>|null                 $live
 * @var array<int, array<string, mixed>>          $materials
 * @var string                                    $timezone
 * @var array{type: string, message: string}|null $notice
 */

use SIQA\AulaVirtual\Admin\AdminMenu;
use SIQA\AulaVirtual\Admin\LessonScreen;
use SIQA\AulaVirtual\Curriculum\LessonType;
use SIQA\AulaVirtual\LiveClasses\LiveClassRepository;
use SIQA\AulaVirtual\LiveClasses\LiveClassService;
use SIQA\AulaVirtual\Materials\MaterialService;
use SIQA\AulaVirtual\Videos\VideoEmbed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$av_lesson_id = (int) $lesson['id'];
$av_admin_post = admin_url( 'admin-post.php' );
$av_hidden = static function ( string $action ) use ( $av_lesson_id ): void {
	printf( '<input type="hidden" name="action" value="%s"><input type="hidden" name="lesson_id" value="%d">', esc_attr( $action ), $av_lesson_id );
	wp_nonce_field( $action );
};
?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html( (string) $lesson['title'] ); ?></h1>
	<hr class="wp-header-end">

	<?php require AV_PATH . 'admin/views/notice.php'; ?>

	<p>
		<a href="<?php echo esc_url( AdminMenu::editions_url( array( 'edition' => (int) $lesson['edition_id'] ) ) ); ?>">&larr; <?php echo esc_html( null === $edition ? __( 'Volver a la edicion', 'aula-virtual' ) : $edition['name'] ); ?></a>
	</p>

	<div style="display:grid;grid-template-columns:minmax(0,2fr) minmax(320px,1fr);gap:28px;align-items:start">
		<div>
			<h2><?php esc_html_e( 'Contenido de la sesion', 'aula-virtual' ); ?></h2>
			<form method="post" action="<?php echo esc_url( $av_admin_post ); ?>">
				<?php $av_hidden( LessonScreen::ACTION_SAVE ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="av-title"><?php esc_html_e( 'Titulo', 'aula-virtual' ); ?></label></th>
						<td><input type="text" id="av-title" name="title" class="large-text" required value="<?php echo esc_attr( (string) $lesson['title'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="av-type"><?php esc_html_e( 'Tipo', 'aula-virtual' ); ?></label></th>
						<td>
							<select id="av-type" name="lesson_type">
								<?php foreach ( LessonType::available() as $av_value => $av_label ) : ?>
									<option value="<?php echo esc_attr( $av_value ); ?>" <?php selected( $lesson['lesson_type'], $av_value ); ?>><?php echo esc_html( $av_label ); ?></option>
								<?php endforeach; ?>
							</select>
							<select name="status">
								<option value="publish" <?php selected( $lesson['status'], 'publish' ); ?>><?php esc_html_e( 'Publicada', 'aula-virtual' ); ?></option>
								<option value="draft" <?php selected( $lesson['status'], 'draft' ); ?>><?php esc_html_e( 'Borrador', 'aula-virtual' ); ?></option>
							</select>
							<label style="margin-left:12px"><input type="hidden" name="is_preview" value="0"><input type="checkbox" name="is_preview" value="1" <?php checked( (int) $lesson['is_preview'], 1 ); ?>> <?php esc_html_e( 'Vista previa gratuita', 'aula-virtual' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="av-description"><?php esc_html_e( 'Descripcion corta', 'aula-virtual' ); ?></label></th>
						<td><textarea id="av-description" name="description" class="large-text" rows="2"><?php echo esc_textarea( (string) $lesson['description'] ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><label for="av-video"><?php esc_html_e( 'Video', 'aula-virtual' ); ?></label></th>
						<td>
							<select name="video_provider" style="vertical-align:top">
								<?php foreach ( VideoEmbed::providers() as $av_value => $av_label ) : ?>
									<option value="<?php echo esc_attr( $av_value ); ?>" <?php selected( (string) $lesson['video_provider'], $av_value ); ?>><?php echo esc_html( $av_label ); ?></option>
								<?php endforeach; ?>
							</select>
							<textarea id="av-video" name="video_url" class="large-text" rows="2" placeholder="https://youtu.be/...  |  https://iframe.mediadelivery.net/embed/123/guid  |  <iframe ...>"><?php echo esc_textarea( (string) $lesson['video_url'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'YouTube, Vimeo, Bunny Stream (URL de embed, de reproduccion o el GUID del video), un MP4, un codigo incrustado o un shortcode. Con "Detectar automaticamente" basta pegar la URL.', 'aula-virtual' ); ?></p>
							<label><?php esc_html_e( 'Duracion (min)', 'aula-virtual' ); ?> <input type="number" name="duration" min="0" class="small-text" value="<?php echo esc_attr( (string) (int) $lesson['duration'] ); ?>"></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Contenido', 'aula-virtual' ); ?></th>
						<td>
							<?php
							wp_editor(
								(string) $lesson['content'],
								'av-content',
								array(
									'textarea_name' => 'content',
									'textarea_rows' => 14,
									'media_buttons' => true,
								)
							);
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Liberacion', 'aula-virtual' ); ?></th>
						<td>
							<select name="release_type">
								<option value="immediate" <?php selected( $lesson['release_type'], 'immediate' ); ?>><?php esc_html_e( 'Disponible de inmediato', 'aula-virtual' ); ?></option>
								<option value="date" <?php selected( $lesson['release_type'], 'date' ); ?>><?php esc_html_e( 'A partir de una fecha', 'aula-virtual' ); ?></option>
								<option value="offset" <?php selected( $lesson['release_type'], 'offset' ); ?>><?php esc_html_e( 'X dias despues de la matricula', 'aula-virtual' ); ?></option>
							</select>
							<input type="date" name="release_date" value="<?php echo esc_attr( empty( $lesson['release_date'] ) ? '' : substr( (string) $lesson['release_date'], 0, 10 ) ); ?>">
							<input type="number" name="release_offset" min="0" class="small-text" value="<?php echo esc_attr( (string) (int) $lesson['release_offset'] ); ?>"> <?php esc_html_e( 'dias', 'aula-virtual' ); ?>
							<p class="description"><?php esc_html_e( 'La liberacion programada se aplica en el campus en la siguiente fase (content drip). Los datos ya se guardan.', 'aula-virtual' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Guardar sesion', 'aula-virtual' ) ); ?>
			</form>

			<form method="post" action="<?php echo esc_url( $av_admin_post ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Eliminar esta sesion y su progreso asociado?', 'aula-virtual' ) ); ?>');">
				<?php $av_hidden( LessonScreen::ACTION_DELETE ); ?>
				<button type="submit" class="button-link-delete"><?php esc_html_e( 'Eliminar sesion', 'aula-virtual' ); ?></button>
			</form>
		</div>

		<div>
			<h2><?php esc_html_e( 'Clase en vivo', 'aula-virtual' ); ?></h2>
			<form method="post" action="<?php echo esc_url( $av_admin_post ); ?>" class="av-live-form">
				<?php $av_hidden( LessonScreen::ACTION_SAVE_LIVE ); ?>
				<input type="hidden" name="timezone" value="<?php echo esc_attr( $timezone ); ?>">
				<p class="description"><?php printf( /* translators: %s: time zone. */ esc_html__( 'Horas en la zona de la edicion: %s', 'aula-virtual' ), esc_html( $timezone ) ); ?></p>
				<p>
					<label><?php esc_html_e( 'Plataforma', 'aula-virtual' ); ?><br>
						<select name="provider">
							<?php foreach ( LiveClassService::providers() as $av_value => $av_label ) : ?>
								<option value="<?php echo esc_attr( $av_value ); ?>" <?php selected( $live['provider'] ?? 'zoom', $av_value ); ?>><?php echo esc_html( $av_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				</p>
				<p><label><?php esc_html_e( 'Inicio', 'aula-virtual' ); ?><br><input type="datetime-local" name="start_local" required value="<?php echo esc_attr( null === $live ? '' : LiveClassService::to_local( (string) $live['start_datetime'], $timezone, 'Y-m-d\TH:i' ) ); ?>"></label></p>
				<p><label><?php esc_html_e( 'Fin', 'aula-virtual' ); ?><br><input type="datetime-local" name="end_local" value="<?php echo esc_attr( null === $live ? '' : LiveClassService::to_local( (string) $live['end_datetime'], $timezone, 'Y-m-d\TH:i' ) ); ?>"></label> <span class="description"><?php esc_html_e( 'o duracion', 'aula-virtual' ); ?> <input type="number" name="duration" min="15" class="small-text" value="90"> min</span></p>
				<p><label><?php esc_html_e( 'Enlace de la reunion', 'aula-virtual' ); ?><br><input type="url" name="meeting_url" class="widefat" value="<?php echo esc_attr( (string) ( $live['meeting_url'] ?? '' ) ); ?>"></label></p>
				<p>
					<label><?php esc_html_e( 'ID', 'aula-virtual' ); ?> <input type="text" name="meeting_id" value="<?php echo esc_attr( (string) ( $live['meeting_id'] ?? '' ) ); ?>"></label>
					<label><?php esc_html_e( 'Codigo', 'aula-virtual' ); ?> <input type="text" name="access_code" value="<?php echo esc_attr( (string) ( $live['access_code'] ?? '' ) ); ?>"></label>
				</p>
				<p>
					<label><?php esc_html_e( 'Mostrar boton desde', 'aula-virtual' ); ?> <input type="number" name="open_before" min="0" class="small-text" value="<?php echo esc_attr( (string) (int) ( $live['open_before'] ?? 15 ) ); ?>"> <?php esc_html_e( 'min antes', 'aula-virtual' ); ?></label><br>
					<label><?php esc_html_e( 'Ocultar boton', 'aula-virtual' ); ?> <input type="number" name="close_after" min="0" class="small-text" value="<?php echo esc_attr( (string) (int) ( $live['close_after'] ?? 30 ) ); ?>"> <?php esc_html_e( 'min despues del fin', 'aula-virtual' ); ?></label>
				</p>
				<p><label><?php esc_html_e( 'Mensaje para el alumno', 'aula-virtual' ); ?><br><textarea name="message" class="widefat" rows="2"><?php echo esc_textarea( (string) ( $live['message'] ?? '' ) ); ?></textarea></label></p>
				<p><label><?php esc_html_e( 'Grabacion (URL, despues de la clase)', 'aula-virtual' ); ?><br><input type="url" name="recording_url" class="widefat" value="<?php echo esc_attr( (string) ( $live['recording_url'] ?? '' ) ); ?>"></label></p>
				<p>
					<label><?php esc_html_e( 'Estado', 'aula-virtual' ); ?>
						<select name="status">
							<option value="<?php echo esc_attr( LiveClassRepository::STATUS_SCHEDULED ); ?>" <?php selected( $live['status'] ?? 'scheduled', 'scheduled' ); ?>><?php esc_html_e( 'Programada', 'aula-virtual' ); ?></option>
							<option value="<?php echo esc_attr( LiveClassRepository::STATUS_DONE ); ?>" <?php selected( $live['status'] ?? '', 'done' ); ?>><?php esc_html_e( 'Realizada', 'aula-virtual' ); ?></option>
							<option value="<?php echo esc_attr( LiveClassRepository::STATUS_CANCELLED ); ?>" <?php selected( $live['status'] ?? '', 'cancelled' ); ?>><?php esc_html_e( 'Cancelada', 'aula-virtual' ); ?></option>
						</select>
					</label>
				</p>
				<?php submit_button( null === $live ? __( 'Programar clase', 'aula-virtual' ) : __( 'Guardar clase', 'aula-virtual' ), 'secondary', 'submit', false ); ?>
			</form>
			<?php if ( null !== $live ) : ?>
				<form method="post" action="<?php echo esc_url( $av_admin_post ); ?>" style="margin-top:8px">
					<?php $av_hidden( LessonScreen::ACTION_REMOVE_LIVE ); ?>
					<button type="submit" class="button-link-delete"><?php esc_html_e( 'Quitar clase en vivo', 'aula-virtual' ); ?></button>
				</form>
			<?php endif; ?>

			<h2 style="margin-top:32px"><?php esc_html_e( 'Materiales', 'aula-virtual' ); ?></h2>
			<?php if ( empty( $materials ) ) : ?>
				<p class="description"><?php esc_html_e( 'Sin materiales todavia.', 'aula-virtual' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<tbody>
					<?php foreach ( $materials as $av_material ) : ?>
						<tr>
							<td>
								<a href="<?php echo esc_url( MaterialService::url( $av_material ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( (string) $av_material['title'] ); ?></a>
								<br><small><?php echo esc_html( strtoupper( (string) $av_material['file_type'] ) ); ?><?php echo (int) $av_material['downloadable'] ? '' : ' &middot; ' . esc_html__( 'solo lectura', 'aula-virtual' ); ?></small>
							</td>
							<td style="width:60px;text-align:right">
								<form method="post" action="<?php echo esc_url( $av_admin_post ); ?>">
									<?php $av_hidden( LessonScreen::ACTION_DELETE_MATERIAL ); ?>
									<input type="hidden" name="material_id" value="<?php echo esc_attr( (string) (int) $av_material['id'] ); ?>">
									<button type="submit" class="button-link-delete" aria-label="<?php esc_attr_e( 'Eliminar', 'aula-virtual' ); ?>">&times;</button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( $av_admin_post ); ?>" style="margin-top:12px" id="av-material-form">
				<?php $av_hidden( LessonScreen::ACTION_ADD_MATERIAL ); ?>
				<p><input type="text" name="title" class="widefat" placeholder="<?php esc_attr_e( 'Titulo (opcional, se toma del archivo)', 'aula-virtual' ); ?>"></p>
				<p>
					<input type="hidden" name="attachment_id" id="av-material-attachment" value="0">
					<button type="button" class="button" id="av-material-choose"><?php esc_html_e( 'Elegir archivo de la biblioteca', 'aula-virtual' ); ?></button>
					<span id="av-material-name" class="description"></span>
				</p>
				<p><input type="url" name="external_url" class="widefat" placeholder="<?php esc_attr_e( 'o enlace externo (Drive, Notion, web)', 'aula-virtual' ); ?>"></p>
				<p><label><input type="hidden" name="downloadable" value="0"><input type="checkbox" name="downloadable" value="1" checked> <?php esc_html_e( 'Permitir descarga', 'aula-virtual' ); ?></label></p>
				<?php submit_button( __( 'Anadir material', 'aula-virtual' ), 'secondary', 'submit', false ); ?>
				<p class="description"><?php printf( /* translators: %s: extensions. */ esc_html__( 'Formatos: %s.', 'aula-virtual' ), esc_html( implode( ', ', MaterialService::allowed_extensions() ) ) ); ?></p>
			</form>
			<script>
			( function () {
				var button = document.getElementById( 'av-material-choose' );
				if ( ! button || ! window.wp || ! wp.media ) { return; }
				button.addEventListener( 'click', function ( e ) {
					e.preventDefault();
					var frame = wp.media( { title: '<?php echo esc_js( __( 'Elegir material', 'aula-virtual' ) ); ?>', multiple: false } );
					frame.on( 'select', function () {
						var file = frame.state().get( 'selection' ).first().toJSON();
						document.getElementById( 'av-material-attachment' ).value = file.id;
						document.getElementById( 'av-material-name' ).textContent = file.filename || file.title;
					} );
					frame.open();
				} );
			} )();
			</script>
		</div>
	</div>
</div>
