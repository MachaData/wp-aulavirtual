<?php
/**
 * Edition detail: sessions and students.
 *
 * @package SIQA\AulaVirtual
 *
 * @var array<string, mixed>                      $edition
 * @var WP_Post|null                              $course
 * @var array<int, array<string, mixed>>          $lessons
 * @var array<int, array<string, mixed>>          $students
 * @var array<int, string>                        $names
 * @var array<int, array<string, mixed>>          $links
 * @var array{type: string, message: string}|null $notice
 */

use SIQA\AulaVirtual\Admin\AdminMenu;
use SIQA\AulaVirtual\Admin\EditionsScreen;
use SIQA\AulaVirtual\Admin\ImportScreen;
use SIQA\AulaVirtual\Admin\LessonScreen;
use SIQA\AulaVirtual\Curriculum\LessonType;
use SIQA\AulaVirtual\Editions\EditionStatus;
use SIQA\AulaVirtual\Enrollments\EnrollmentStatus;
use SIQA\AulaVirtual\Enrollments\RegistrationService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$av_edition_id = (int) $edition['id'];
?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html( (string) $edition['name'] ); ?></h1>
	<hr class="wp-header-end">

	<?php require AV_PATH . 'admin/views/notice.php'; ?>

	<p>
		<a href="<?php echo esc_url( AdminMenu::editions_url() ); ?>">&larr; <?php esc_html_e( 'Todas las ediciones', 'aula-virtual' ); ?></a>
	</p>

	<table class="widefat striped" style="max-width:760px">
		<tbody>
			<tr>
				<th scope="row" style="width:180px"><?php esc_html_e( 'Curso', 'aula-virtual' ); ?></th>
				<td><?php echo esc_html( $course instanceof WP_Post ? get_the_title( $course ) : __( 'Curso eliminado', 'aula-virtual' ) ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Codigo', 'aula-virtual' ); ?></th>
				<td><code><?php echo esc_html( (string) $edition['code'] ); ?></code></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Estado', 'aula-virtual' ); ?></th>
				<td><?php echo esc_html( EditionStatus::label( (string) $edition['status'] ) ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Cupo', 'aula-virtual' ); ?></th>
				<td>
					<?php
					printf(
						/* translators: 1: enrolled students, 2: capacity or "sin limite". */
						esc_html__( '%1$s matriculados de %2$s', 'aula-virtual' ),
						esc_html( (string) count( $students ) ),
						esc_html( 0 === (int) $edition['capacity'] ? __( 'plazas sin limite', 'aula-virtual' ) : (string) (int) $edition['capacity'] )
					);
					?>
				</td>
			</tr>
		</tbody>
	</table>

	<h2><?php esc_html_e( 'Enlace de inscripcion', 'aula-virtual' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Comparte este enlace en la landing, por WhatsApp o por correo. Quien lo abra podra inscribirse; la solicitud queda pendiente hasta que la apruebes.', 'aula-virtual' ); ?></p>

	<?php if ( ! empty( $links ) ) : ?>
		<table class="widefat striped" style="max-width:760px">
			<tbody>
			<?php foreach ( $links as $av_link ) : ?>
				<tr>
					<td style="width:180px"><?php echo esc_html( (string) $av_link['label'] ); ?></td>
					<td>
						<input type="text" readonly class="large-text" onclick="this.select()"
							value="<?php echo esc_attr( RegistrationService::link_url( (string) $av_link['token'] ) ); ?>">
					</td>
					<td style="width:120px">
						<?php
						printf(
							/* translators: %s: number of submissions. */
							esc_html__( '%s usos', 'aula-virtual' ),
							esc_html( (string) (int) $av_link['uses'] )
						);
						?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:12px 0 24px">
		<input type="hidden" name="action" value="<?php echo esc_attr( EditionsScreen::ACTION_CREATE_LINK ); ?>">
		<input type="hidden" name="edition_id" value="<?php echo esc_attr( (string) $av_edition_id ); ?>">
		<?php wp_nonce_field( EditionsScreen::ACTION_CREATE_LINK ); ?>
		<input type="text" name="label" class="regular-text" placeholder="<?php esc_attr_e( 'Etiqueta, por ejemplo: landing, WhatsApp', 'aula-virtual' ); ?>">
		<?php submit_button( empty( $links ) ? __( 'Generar enlace', 'aula-virtual' ) : __( 'Generar otro enlace', 'aula-virtual' ), 'secondary', 'submit', false ); ?>
	</form>

	<h2><?php esc_html_e( 'Sesiones', 'aula-virtual' ); ?></h2>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th scope="col" style="width:60px"><?php esc_html_e( 'Orden', 'aula-virtual' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Titulo', 'aula-virtual' ); ?></th>
				<th scope="col" style="width:140px"><?php esc_html_e( 'Tipo', 'aula-virtual' ); ?></th>
				<th scope="col" style="width:120px"><?php esc_html_e( 'Estado', 'aula-virtual' ); ?></th>
				<th scope="col" style="width:90px"><?php esc_html_e( 'Mover', 'aula-virtual' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( empty( $lessons ) ) : ?>
			<tr><td colspan="5"><?php esc_html_e( 'Esta edicion todavia no tiene sesiones.', 'aula-virtual' ); ?></td></tr>
		<?php else : ?>
			<?php $av_total = count( $lessons ); ?>
			<?php foreach ( array_values( $lessons ) as $av_i => $av_lesson ) : ?>
				<tr>
					<td><?php echo esc_html( (string) (int) $av_lesson['position'] ); ?></td>
					<td><strong><a href="<?php echo esc_url( LessonScreen::url( (int) $av_lesson['id'] ) ); ?>"><?php echo esc_html( (string) $av_lesson['title'] ); ?></a></strong></td>
					<td><?php echo esc_html( LessonType::label( (string) $av_lesson['lesson_type'] ) ); ?></td>
					<td><?php echo esc_html( LessonType::STATUS_PUBLISH === $av_lesson['status'] ? __( 'Publicada', 'aula-virtual' ) : __( 'Borrador', 'aula-virtual' ) ); ?></td>
					<td>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
							<input type="hidden" name="action" value="<?php echo esc_attr( EditionsScreen::ACTION_MOVE_LESSON ); ?>">
							<input type="hidden" name="edition_id" value="<?php echo esc_attr( (string) $av_edition_id ); ?>">
							<input type="hidden" name="lesson_id" value="<?php echo esc_attr( (string) (int) $av_lesson['id'] ); ?>">
							<?php wp_nonce_field( EditionsScreen::ACTION_MOVE_LESSON ); ?>
							<button type="submit" name="direction" value="up" class="button button-small" <?php disabled( 0 === $av_i ); ?> aria-label="<?php esc_attr_e( 'Subir', 'aula-virtual' ); ?>">&uarr;</button>
							<button type="submit" name="direction" value="down" class="button button-small" <?php disabled( $av_i === $av_total - 1 ); ?> aria-label="<?php esc_attr_e( 'Bajar', 'aula-virtual' ); ?>">&darr;</button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px">
		<input type="hidden" name="action" value="<?php echo esc_attr( EditionsScreen::ACTION_ADD_LESSON ); ?>">
		<input type="hidden" name="edition_id" value="<?php echo esc_attr( (string) $av_edition_id ); ?>">
		<?php wp_nonce_field( EditionsScreen::ACTION_ADD_LESSON ); ?>

		<input type="text" name="title" class="regular-text" required
			placeholder="<?php esc_attr_e( 'Titulo de la sesion', 'aula-virtual' ); ?>">

		<select name="lesson_type">
			<?php foreach ( LessonType::available() as $av_value => $av_label ) : ?>
				<option value="<?php echo esc_attr( $av_value ); ?>"><?php echo esc_html( $av_label ); ?></option>
			<?php endforeach; ?>
		</select>

		<input type="url" name="video_url" class="regular-text"
			placeholder="<?php esc_attr_e( 'URL del video (opcional)', 'aula-virtual' ); ?>">

		<?php submit_button( __( 'Anadir sesion', 'aula-virtual' ), 'secondary', 'submit', false ); ?>
	</form>

	<h2><?php esc_html_e( 'Alumnos', 'aula-virtual' ); ?></h2>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Alumno', 'aula-virtual' ); ?></th>
				<th scope="col" style="width:120px"><?php esc_html_e( 'Origen', 'aula-virtual' ); ?></th>
				<th scope="col" style="width:120px"><?php esc_html_e( 'Estado', 'aula-virtual' ); ?></th>
				<th scope="col" style="width:120px"><?php esc_html_e( 'Progreso', 'aula-virtual' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( empty( $students ) ) : ?>
			<tr><td colspan="4"><?php esc_html_e( 'Nadie matriculado todavia.', 'aula-virtual' ); ?></td></tr>
		<?php else : ?>
			<?php foreach ( $students as $av_student ) : ?>
				<tr>
					<td><?php echo esc_html( $names[ (int) $av_student['user_id'] ] ?? '' ); ?></td>
					<td><?php echo esc_html( (string) $av_student['source'] ); ?></td>
					<td><?php echo esc_html( EnrollmentStatus::label( (string) $av_student['status'] ) ); ?></td>
					<td><?php echo esc_html( number_format_i18n( (float) $av_student['progress_percentage'], 0 ) . '%' ); ?></td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>

	<?php if ( current_user_can( 'list_users' ) ) : ?>
		<p class="description" style="margin-top:8px">
			<?php
			printf(
				/* translators: %s: link to the import screen. */
				esc_html__( 'Para matricular a muchos alumnos a la vez, usa %s.', 'aula-virtual' ),
				'<a href="' . esc_url( add_query_arg( 'page', ImportScreen::SLUG, admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Importar alumnos', 'aula-virtual' ) . '</a>'
			);
			?>
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px">
			<input type="hidden" name="action" value="<?php echo esc_attr( EditionsScreen::ACTION_ENROLL ); ?>">
			<input type="hidden" name="edition_id" value="<?php echo esc_attr( (string) $av_edition_id ); ?>">
			<?php wp_nonce_field( EditionsScreen::ACTION_ENROLL ); ?>

			<?php
			wp_dropdown_users(
				array(
					'name'              => 'user_id',
					'show'              => 'display_name_with_login',
					'show_option_none'  => __( 'Selecciona un usuario', 'aula-virtual' ),
					'option_none_value' => 0,
					'number'            => 200,
				)
			);
			?>

			<?php submit_button( __( 'Matricular alumno', 'aula-virtual' ), 'secondary', 'submit', false ); ?>
		</form>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Duplicar edicion', 'aula-virtual' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Crea una nueva edicion en borrador con las mismas sesiones. Si indicas la nueva fecha de inicio, las fechas de acceso y de las clases en vivo se desplazan los mismos dias. No se copian alumnos ni el producto de WooCommerce.', 'aula-virtual' ); ?></p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:760px">
		<input type="hidden" name="action" value="<?php echo esc_attr( EditionsScreen::ACTION_DUPLICATE ); ?>">
		<input type="hidden" name="edition_id" value="<?php echo esc_attr( (string) $av_edition_id ); ?>">
		<?php wp_nonce_field( EditionsScreen::ACTION_DUPLICATE ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="av-dup-name"><?php esc_html_e( 'Nombre de la nueva edicion', 'aula-virtual' ); ?></label></th>
				<td><input type="text" name="name" id="av-dup-name" class="regular-text" placeholder="<?php echo esc_attr( sprintf( /* translators: %s: edition name. */ __( 'Copia de %s', 'aula-virtual' ), (string) $edition['name'] ) ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="av-dup-start"><?php esc_html_e( 'Nueva fecha de inicio', 'aula-virtual' ); ?></label></th>
				<td>
					<input type="datetime-local" name="start_date" id="av-dup-start">
					<p class="description"><?php esc_html_e( 'Opcional. Si la dejas vacia, la copia queda sin fechas.', 'aula-virtual' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Copiar tambien', 'aula-virtual' ); ?></th>
				<td>
					<label><input type="checkbox" name="copy_live" value="1" checked> <?php esc_html_e( 'Clases en vivo (sin grabaciones, en estado programada)', 'aula-virtual' ); ?></label><br>
					<label><input type="checkbox" name="copy_materials" value="1" checked> <?php esc_html_e( 'Materiales descargables', 'aula-virtual' ); ?></label>
				</td>
			</tr>
		</table>
		<?php submit_button( __( 'Duplicar edicion', 'aula-virtual' ), 'secondary' ); ?>
	</form>
</div>
