<?php
/**
 * Edition detail: header, summary cards and tabs (sessions, students,
 * registration, settings).
 *
 * @package SIQA\AulaVirtual
 *
 * @var array<string, mixed>                           $edition
 * @var WP_Post|null                                   $course
 * @var array<int, array<string, mixed>>               $lessons
 * @var array<int, array<string, mixed>>               $students
 * @var array<int, array{name: string, email: string}> $people
 * @var array<int, array<string, mixed>>               $links
 * @var array<string, int|float>                       $stats
 * @var string                                         $tab
 * @var string                                         $suggested_slug
 * @var array<int, array<string, mixed>>               $other_editions
 * @var array{type: string, message: string}|null      $notice
 */

use SIQA\AulaVirtual\Admin\AdminMenu;
use SIQA\AulaVirtual\Admin\EditionsScreen;
use SIQA\AulaVirtual\Admin\ImportScreen;
use SIQA\AulaVirtual\Admin\LessonScreen;
use SIQA\AulaVirtual\Admin\ReportsScreen;
use SIQA\AulaVirtual\Admin\RequestsScreen;
use SIQA\AulaVirtual\Admin\StudentsScreen;
use SIQA\AulaVirtual\Curriculum\LessonType;
use SIQA\AulaVirtual\Editions\EditionService;
use SIQA\AulaVirtual\Editions\EditionStatus;
use SIQA\AulaVirtual\Enrollments\EnrollmentStatus;
use SIQA\AulaVirtual\Enrollments\RegistrationService;
use SIQA\AulaVirtual\Permissions\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$av_id      = (int) $edition['id'];
$av_post    = admin_url( 'admin-post.php' );
$av_tab_url = static fn( string $t ): string => AdminMenu::editions_url( array( 'edition' => $av_id, 'tab' => $t ) );
$av_date    = static function ( $value, bool $time = false ): string {
	if ( empty( $value ) || str_starts_with( (string) $value, '0000' ) ) {
		return '';
	}

	$format = (string) get_option( 'date_format' ) . ( $time ? ' H:i' : '' );

	return date_i18n( $format, (int) strtotime( $value . ' UTC' ) );
};
$av_badge   = static function ( string $text, string $tone = 'gray' ): string {
	return '<span class="av-badge av-badge--' . esc_attr( $tone ) . '">' . esc_html( $text ) . '</span>';
};
$av_edition_tone = array(
	EditionStatus::DRAFT    => 'gray',
	EditionStatus::UPCOMING => 'blue',
	EditionStatus::OPEN     => 'green',
	EditionStatus::RUNNING  => 'blue',
	EditionStatus::FINISHED => 'gray',
	EditionStatus::ARCHIVED => 'gray',
);
$av_enroll_tone  = array(
	EnrollmentStatus::PENDING   => 'yellow',
	EnrollmentStatus::APPROVED  => 'blue',
	EnrollmentStatus::ACTIVE    => 'green',
	EnrollmentStatus::COMPLETED => 'blue',
	EnrollmentStatus::SUSPENDED => 'red',
	EnrollmentStatus::EXPIRED   => 'gray',
	EnrollmentStatus::CANCELLED => 'red',
	EnrollmentStatus::REJECTED  => 'red',
);
$av_sources      = array(
	EnrollmentStatus::SOURCE_MANUAL       => __( 'Manual', 'aula-virtual' ),
	EnrollmentStatus::SOURCE_WOOCOMMERCE  => __( 'Compra', 'aula-virtual' ),
	EnrollmentStatus::SOURCE_EXCEL        => __( 'Importación', 'aula-virtual' ),
	EnrollmentStatus::SOURCE_PRIVATE_LINK => __( 'Inscripción', 'aula-virtual' ),
	EnrollmentStatus::SOURCE_FREE         => __( 'Gratuita', 'aula-virtual' ),
	EnrollmentStatus::SOURCE_ADMIN        => __( 'Administrador', 'aula-virtual' ),
	EnrollmentStatus::SOURCE_MIGRATION    => __( 'Migración', 'aula-virtual' ),
);
$av_modalities = EditionService::modalities();
$av_start      = $av_date( $edition['start_date'] ?? '' );
$av_end        = $av_date( $edition['end_date'] ?? '' );
$av_schedule   = trim( (string) ( $edition['schedule_days'] ?? '' ) . ' ' . (string) ( $edition['schedule_time'] ?? '' ) );
$av_capacity   = (int) $edition['capacity'];
$av_tabs       = array(
	'sesiones'    => array( __( 'Sesiones', 'aula-virtual' ), count( $lessons ) ),
	'alumnos'     => array( __( 'Alumnos', 'aula-virtual' ), count( $students ) ),
	'inscripcion' => array( __( 'Inscripción', 'aula-virtual' ), count( $links ) ),
	'ajustes'     => array( __( 'Ajustes', 'aula-virtual' ), null ),
);
?>
<div class="wrap av-admin">
	<a class="av-back" href="<?php echo esc_url( AdminMenu::editions_url() ); ?>"><span class="dashicons dashicons-arrow-left-alt2"></span><?php esc_html_e( 'Ediciones', 'aula-virtual' ); ?></a>

	<div class="av-header">
		<div>
			<p class="av-header__eyebrow">
				<?php if ( $course instanceof WP_Post ) : ?>
					<a href="<?php echo esc_url( (string) get_edit_post_link( $course->ID ) ); ?>"><?php echo esc_html( get_the_title( $course ) ); ?></a>
				<?php else : ?>
					<?php esc_html_e( 'Curso eliminado', 'aula-virtual' ); ?>
				<?php endif; ?>
			</p>
			<h1>
				<?php echo esc_html( (string) $edition['name'] ); ?>
				<?php echo $av_badge( EditionStatus::label( (string) $edition['status'] ), $av_edition_tone[ $edition['status'] ] ?? 'gray' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the closure. ?>
			</h1>
			<p class="av-header__meta">
				<span><code><?php echo esc_html( (string) $edition['code'] ); ?></code></span>
				<span><span class="dashicons dashicons-video-alt3"></span><?php echo esc_html( $av_modalities[ $edition['modality'] ] ?? (string) $edition['modality'] ); ?></span>
				<?php if ( '' !== $av_start ) : ?>
					<span><span class="dashicons dashicons-calendar-alt"></span><?php echo esc_html( '' === $av_end ? $av_start : $av_start . ' – ' . $av_end ); ?></span>
				<?php endif; ?>
				<?php if ( '' !== $av_schedule ) : ?>
					<span><span class="dashicons dashicons-clock"></span><?php echo esc_html( $av_schedule ); ?></span>
				<?php endif; ?>
				<?php if ( ! empty( $edition['price_display'] ) ) : ?>
					<span><span class="dashicons dashicons-tag"></span><?php echo esc_html( (string) $edition['price_display'] ); ?></span>
				<?php endif; ?>
			</p>
		</div>
		<div class="av-header__actions">
			<?php if ( $course instanceof WP_Post && 'publish' === $course->post_status ) : ?>
				<a class="button" href="<?php echo esc_url( (string) get_permalink( $course ) ); ?>" target="_blank" rel="noopener"><span class="dashicons dashicons-external"></span><?php esc_html_e( 'Ver landing', 'aula-virtual' ); ?></a>
			<?php endif; ?>
			<a class="button" href="<?php echo esc_url( ReportsScreen::edition_url( $av_id ) ); ?>"><span class="dashicons dashicons-chart-bar"></span><?php esc_html_e( 'Reporte', 'aula-virtual' ); ?></a>
		</div>
	</div>

	<?php require AV_PATH . 'admin/views/notice.php'; ?>

	<div class="av-stats">
		<div class="av-stat">
			<p class="av-stat__label"><?php esc_html_e( 'Alumnos', 'aula-virtual' ); ?></p>
			<p class="av-stat__value">
				<?php echo esc_html( (string) (int) $stats['seats_taken'] ); ?>
				<small><?php echo esc_html( 0 === $av_capacity ? __( 'sin límite de plazas', 'aula-virtual' ) : sprintf( /* translators: %d: capacity. */ __( 'de %d plazas', 'aula-virtual' ), $av_capacity ) ); ?></small>
			</p>
		</div>
		<div class="av-stat">
			<p class="av-stat__label"><?php esc_html_e( 'Sesiones publicadas', 'aula-virtual' ); ?></p>
			<p class="av-stat__value">
				<?php echo esc_html( (string) (int) $stats['published_lessons'] ); ?>
				<small><?php echo esc_html( sprintf( /* translators: %d: total lessons. */ __( 'de %d', 'aula-virtual' ), count( $lessons ) ) ); ?></small>
			</p>
		</div>
		<div class="av-stat">
			<p class="av-stat__label"><?php esc_html_e( 'Avance medio', 'aula-virtual' ); ?></p>
			<p class="av-stat__value">
				<?php echo esc_html( number_format_i18n( (float) $stats['avg_progress'], 0 ) . '%' ); ?>
				<small><?php echo esc_html( sprintf( /* translators: %d: students who finished. */ _n( '%d terminó', '%d terminaron', (int) $stats['completed'], 'aula-virtual' ), (int) $stats['completed'] ) ); ?></small>
			</p>
		</div>
		<div class="av-stat<?php echo (int) $stats['pending_requests'] > 0 ? ' av-stat--attention' : ''; ?>">
			<p class="av-stat__label"><?php esc_html_e( 'Solicitudes pendientes', 'aula-virtual' ); ?></p>
			<p class="av-stat__value"><?php echo esc_html( (string) (int) $stats['pending_requests'] ); ?></p>
			<?php if ( (int) $stats['pending_requests'] > 0 ) : ?>
				<p class="av-stat__hint"><a href="<?php echo esc_url( add_query_arg( 'page', RequestsScreen::SLUG, admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Revisar y aprobar →', 'aula-virtual' ); ?></a></p>
			<?php endif; ?>
		</div>
	</div>

	<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Secciones de la edición', 'aula-virtual' ); ?>">
		<?php foreach ( $av_tabs as $av_key => $av_item ) : ?>
			<a href="<?php echo esc_url( $av_tab_url( $av_key ) ); ?>" class="nav-tab<?php echo $tab === $av_key ? ' nav-tab-active' : ''; ?>"<?php echo $tab === $av_key ? ' aria-current="page"' : ''; ?>>
				<?php echo esc_html( $av_item[0] ); ?>
				<?php if ( null !== $av_item[1] ) : ?>
					<span class="av-count"><?php echo esc_html( (string) $av_item[1] ); ?></span>
				<?php endif; ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="av-panel">
	<?php if ( 'sesiones' === $tab ) : ?>

		<div class="av-panel__intro">
			<p><?php esc_html_e( 'El temario que ve el alumno, en este orden. Pulsa el título para editar el video, el contenido, la clase en vivo y los materiales de cada sesión.', 'aula-virtual' ); ?></p>
		</div>

		<?php if ( empty( $lessons ) ) : ?>
			<div class="av-empty">
				<span class="dashicons dashicons-playlist-video"></span>
				<h3><?php esc_html_e( 'Aún no hay sesiones', 'aula-virtual' ); ?></h3>
				<p><?php esc_html_e( 'Añade la primera con el formulario de abajo. Podrás completar su contenido después.', 'aula-virtual' ); ?></p>
			</div>
		<?php else : ?>
			<?php $av_total = count( $lessons ); ?>
			<table class="widefat striped av-table">
				<thead>
					<tr>
						<th scope="col" class="av-col-order">#</th>
						<th scope="col"><?php esc_html_e( 'Sesión', 'aula-virtual' ); ?></th>
						<th scope="col" class="av-hide-sm"><?php esc_html_e( 'Tipo', 'aula-virtual' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Estado', 'aula-virtual' ); ?></th>
						<th scope="col" class="av-col-actions"><?php esc_html_e( 'Orden', 'aula-virtual' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( array_values( $lessons ) as $av_i => $av_lesson ) : ?>
					<?php
					$av_release = '';
					if ( 'date' === $av_lesson['release_type'] && ! empty( $av_lesson['release_date'] ) ) {
						/* translators: %s: date. */
						$av_release = sprintf( __( 'Se abre el %s', 'aula-virtual' ), $av_date( $av_lesson['release_date'] ) );
					} elseif ( 'offset' === $av_lesson['release_type'] && (int) $av_lesson['release_offset'] > 0 ) {
						/* translators: %d: days. */
						$av_release = sprintf( _n( 'Se abre %d día después de matricularse', 'Se abre %d días después de matricularse', (int) $av_lesson['release_offset'], 'aula-virtual' ), (int) $av_lesson['release_offset'] );
					}
					?>
					<tr>
						<td class="av-col-order"><?php echo esc_html( (string) ( $av_i + 1 ) ); ?></td>
						<td>
							<strong><a href="<?php echo esc_url( LessonScreen::url( (int) $av_lesson['id'] ) ); ?>"><?php echo esc_html( (string) $av_lesson['title'] ); ?></a></strong>
							<?php if ( '' !== $av_release ) : ?>
								<span class="av-sub"><?php echo esc_html( $av_release ); ?></span>
							<?php elseif ( empty( $av_lesson['video_url'] ) && empty( $av_lesson['content'] ) && LessonType::LIVE !== $av_lesson['lesson_type'] ) : ?>
								<span class="av-sub"><?php esc_html_e( 'Sin contenido todavía', 'aula-virtual' ); ?></span>
							<?php endif; ?>
						</td>
						<td class="av-hide-sm"><?php echo esc_html( LessonType::label( (string) $av_lesson['lesson_type'] ) ); ?></td>
						<td>
							<?php
							echo LessonType::STATUS_PUBLISH === $av_lesson['status'] // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the closure.
								? $av_badge( __( 'Publicada', 'aula-virtual' ), 'green' )
								: $av_badge( __( 'Borrador', 'aula-virtual' ), 'gray' );
							?>
						</td>
						<td class="av-col-actions">
							<form method="post" action="<?php echo esc_url( $av_post ); ?>" class="av-move">
								<input type="hidden" name="action" value="<?php echo esc_attr( EditionsScreen::ACTION_MOVE_LESSON ); ?>">
								<input type="hidden" name="edition_id" value="<?php echo esc_attr( (string) $av_id ); ?>">
								<input type="hidden" name="lesson_id" value="<?php echo esc_attr( (string) (int) $av_lesson['id'] ); ?>">
								<?php wp_nonce_field( EditionsScreen::ACTION_MOVE_LESSON ); ?>
								<button type="submit" name="direction" value="up" class="button" <?php disabled( 0 === $av_i ); ?> title="<?php esc_attr_e( 'Subir', 'aula-virtual' ); ?>" aria-label="<?php esc_attr_e( 'Subir', 'aula-virtual' ); ?>"><span class="dashicons dashicons-arrow-up-alt2"></span></button>
								<button type="submit" name="direction" value="down" class="button" <?php disabled( $av_i === $av_total - 1 ); ?> title="<?php esc_attr_e( 'Bajar', 'aula-virtual' ); ?>" aria-label="<?php esc_attr_e( 'Bajar', 'aula-virtual' ); ?>"><span class="dashicons dashicons-arrow-down-alt2"></span></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<div class="av-card">
			<h3><?php esc_html_e( 'Añadir sesión', 'aula-virtual' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Se crea publicada al final del temario. Acepta enlaces de YouTube, Vimeo y Bunny.', 'aula-virtual' ); ?></p>
			<form method="post" action="<?php echo esc_url( $av_post ); ?>" class="av-inline-form">
				<input type="hidden" name="action" value="<?php echo esc_attr( EditionsScreen::ACTION_ADD_LESSON ); ?>">
				<input type="hidden" name="edition_id" value="<?php echo esc_attr( (string) $av_id ); ?>">
				<?php wp_nonce_field( EditionsScreen::ACTION_ADD_LESSON ); ?>
				<div>
					<label for="av-lesson-title"><?php esc_html_e( 'Título', 'aula-virtual' ); ?></label>
					<input type="text" name="title" id="av-lesson-title" required placeholder="<?php esc_attr_e( 'Sesión 1: Introducción', 'aula-virtual' ); ?>">
				</div>
				<div>
					<label for="av-lesson-type"><?php esc_html_e( 'Tipo', 'aula-virtual' ); ?></label>
					<select name="lesson_type" id="av-lesson-type">
						<?php foreach ( LessonType::available() as $av_value => $av_label ) : ?>
							<option value="<?php echo esc_attr( $av_value ); ?>"><?php echo esc_html( $av_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div>
					<label for="av-lesson-video"><?php esc_html_e( 'Enlace del video (opcional)', 'aula-virtual' ); ?></label>
					<input type="url" name="video_url" id="av-lesson-video" placeholder="https://">
				</div>
				<div><?php submit_button( __( 'Añadir sesión', 'aula-virtual' ), 'primary', 'submit', false ); ?></div>
			</form>
		</div>

	<?php elseif ( 'alumnos' === $tab ) : ?>

		<div class="av-panel__intro">
			<p><?php esc_html_e( 'Quién está matriculado, cómo llegó y cuánto ha avanzado.', 'aula-virtual' ); ?></p>
			<?php if ( ! empty( $students ) && current_user_can( Capabilities::VIEW_REPORTS ) ) : ?>
				<a class="button" href="<?php echo esc_url( ReportsScreen::export_url( $av_id ) ); ?>"><?php esc_html_e( 'Exportar CSV', 'aula-virtual' ); ?></a>
			<?php endif; ?>
		</div>

		<?php if ( empty( $students ) ) : ?>
			<div class="av-empty">
				<span class="dashicons dashicons-groups"></span>
				<h3><?php esc_html_e( 'Nadie matriculado todavía', 'aula-virtual' ); ?></h3>
				<p><?php esc_html_e( 'Los alumnos llegan por el enlace de inscripción, por una compra o los matriculas tú desde aquí.', 'aula-virtual' ); ?></p>
			</div>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( $av_post ); ?>" id="av-bulk-form" data-av-confirm="<?php esc_attr_e( '¿Aplicar la acción a los alumnos marcados?', 'aula-virtual' ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( StudentsScreen::ACTION_BULK ); ?>">
			<input type="hidden" name="edition_id" value="<?php echo esc_attr( (string) $av_id ); ?>">
			<?php wp_nonce_field( StudentsScreen::ACTION_BULK ); ?>
			<div class="av-bulk">
				<label class="screen-reader-text" for="av-bulk-action"><?php esc_html_e( 'Acción en lote', 'aula-virtual' ); ?></label>
				<select name="bulk_action" id="av-bulk-action">
					<option value=""><?php esc_html_e( 'Acciones con los marcados…', 'aula-virtual' ); ?></option>
					<option value="access_link"><?php esc_html_e( 'Reenviar enlace de acceso', 'aula-virtual' ); ?></option>
					<option value="status_active"><?php esc_html_e( 'Activar', 'aula-virtual' ); ?></option>
					<option value="status_suspended"><?php esc_html_e( 'Suspender', 'aula-virtual' ); ?></option>
					<option value="status_completed"><?php esc_html_e( 'Marcar como completada', 'aula-virtual' ); ?></option>
					<option value="status_cancelled"><?php esc_html_e( 'Cancelar matrícula', 'aula-virtual' ); ?></option>
					<?php if ( ! empty( $other_editions ) ) : ?>
						<option value="enroll"><?php esc_html_e( 'Matricular también en otra edición', 'aula-virtual' ); ?></option>
					<?php endif; ?>
				</select>
				<?php if ( ! empty( $other_editions ) ) : ?>
					<select name="target_edition" aria-label="<?php esc_attr_e( 'Otra edición', 'aula-virtual' ); ?>">
						<option value="0"><?php esc_html_e( '(solo para "matricular en otra edición")', 'aula-virtual' ); ?></option>
						<?php foreach ( $other_editions as $av_other ) : ?>
							<option value="<?php echo esc_attr( (string) (int) $av_other['id'] ); ?>"><?php echo esc_html( get_the_title( (int) $av_other['course_id'] ) . ' · ' . $av_other['name'] ); ?></option>
						<?php endforeach; ?>
					</select>
					<label><input type="checkbox" name="notify" value="1" checked> <?php esc_html_e( 'con correo de bienvenida', 'aula-virtual' ); ?></label>
				<?php endif; ?>
				<?php submit_button( __( 'Aplicar', 'aula-virtual' ), 'secondary', '', false ); ?>
			</div>
			<table class="widefat striped av-table">
				<thead>
					<tr>
						<td class="check-column"><input type="checkbox" data-av-check-all="enrollment_ids[]" aria-label="<?php esc_attr_e( 'Marcar todos', 'aula-virtual' ); ?>"></td>
						<th scope="col"><?php esc_html_e( 'Alumno', 'aula-virtual' ); ?></th>
						<th scope="col" class="av-hide-sm"><?php esc_html_e( 'Origen', 'aula-virtual' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Estado', 'aula-virtual' ); ?></th>
						<th scope="col" style="width:200px"><?php esc_html_e( 'Avance', 'aula-virtual' ); ?></th>
						<th scope="col" class="av-hide-sm"><?php esc_html_e( 'Matriculado', 'aula-virtual' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $students as $av_student ) : ?>
					<?php
					$av_person = $people[ (int) $av_student['user_id'] ] ?? array(
						'name'  => __( 'Usuario eliminado', 'aula-virtual' ),
						'email' => '',
					);
					$av_pct    = max( 0.0, min( 100.0, (float) $av_student['progress_percentage'] ) );
					?>
					<tr>
						<th scope="row" class="check-column"><input type="checkbox" name="enrollment_ids[]" value="<?php echo esc_attr( (string) (int) $av_student['id'] ); ?>" aria-label="<?php echo esc_attr( $av_person['name'] ); ?>"></th>
						<td>
							<strong><a href="<?php echo esc_url( StudentsScreen::url( (int) $av_student['user_id'] ) ); ?>"><?php echo esc_html( $av_person['name'] ); ?></a></strong>
							<?php if ( '' !== $av_person['email'] ) : ?>
								<span class="av-sub"><?php echo esc_html( $av_person['email'] ); ?></span>
							<?php endif; ?>
							<div class="row-actions">
								<span><a href="<?php echo esc_url( StudentsScreen::url( (int) $av_student['user_id'] ) ); ?>"><?php esc_html_e( 'Gestionar', 'aula-virtual' ); ?></a> | </span>
								<span><a href="<?php echo esc_url( StudentsScreen::url( (int) $av_student['user_id'], 'acceso' ) ); ?>"><?php esc_html_e( 'Acceso y contraseña', 'aula-virtual' ); ?></a></span>
							</div>
						</td>
						<td class="av-hide-sm"><?php echo esc_html( $av_sources[ $av_student['source'] ] ?? (string) $av_student['source'] ); ?></td>
						<td><?php echo $av_badge( EnrollmentStatus::label( (string) $av_student['status'] ), $av_enroll_tone[ $av_student['status'] ] ?? 'gray' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the closure. ?></td>
						<td>
							<div class="av-progress" role="img" aria-label="<?php echo esc_attr( number_format_i18n( $av_pct, 0 ) . '%' ); ?>">
								<div class="av-progress__track"><div class="av-progress__bar<?php echo $av_pct >= 100 ? ' is-complete' : ''; ?>" style="width:<?php echo esc_attr( (string) round( $av_pct, 1 ) ); ?>%"></div></div>
								<span class="av-progress__value"><?php echo esc_html( number_format_i18n( $av_pct, 0 ) . '%' ); ?></span>
							</div>
						</td>
						<td class="av-hide-sm"><?php echo esc_html( $av_date( $av_student['enrolled_at'] ?? '' ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			</form>
		<?php endif; ?>

		<div class="av-grid-2" style="margin-top:16px">
			<?php if ( current_user_can( 'list_users' ) ) : ?>
				<div class="av-card">
					<h3><?php esc_html_e( 'Matricular a una persona', 'aula-virtual' ); ?></h3>
					<p class="description"><?php esc_html_e( 'Para alguien que ya tiene cuenta en el sitio. Recibe el correo de bienvenida.', 'aula-virtual' ); ?></p>
					<form method="post" action="<?php echo esc_url( $av_post ); ?>" class="av-inline-form av-inline-form--2">
						<input type="hidden" name="action" value="<?php echo esc_attr( EditionsScreen::ACTION_ENROLL ); ?>">
						<input type="hidden" name="edition_id" value="<?php echo esc_attr( (string) $av_id ); ?>">
						<?php wp_nonce_field( EditionsScreen::ACTION_ENROLL ); ?>
						<div>
							<label for="av-enroll-user"><?php esc_html_e( 'Usuario', 'aula-virtual' ); ?></label>
							<?php
							wp_dropdown_users(
								array(
									'name'              => 'user_id',
									'id'                => 'av-enroll-user',
									'show'              => 'display_name_with_login',
									'show_option_none'  => __( 'Selecciona un usuario', 'aula-virtual' ),
									'option_none_value' => 0,
									'number'            => 200,
								)
							);
							?>
						</div>
						<div><?php submit_button( __( 'Matricular', 'aula-virtual' ), 'secondary', 'submit', false ); ?></div>
					</form>
				</div>
			<?php endif; ?>
			<?php if ( current_user_can( Capabilities::IMPORT_STUDENTS ) ) : ?>
				<div class="av-card">
					<h3><?php esc_html_e( 'Importar desde Excel o CSV', 'aula-virtual' ); ?></h3>
					<p class="description"><?php esc_html_e( 'Para matricular a muchas personas a la vez. Crea las cuentas que falten y revisas todo antes de confirmar.', 'aula-virtual' ); ?></p>
					<a class="button" href="<?php echo esc_url( add_query_arg( 'page', ImportScreen::SLUG, admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Importar alumnos', 'aula-virtual' ); ?></a>
				</div>
			<?php endif; ?>
		</div>

	<?php elseif ( 'inscripcion' === $tab ) : ?>

		<div class="av-panel__intro">
			<p><?php esc_html_e( 'Comparte el enlace en la landing, por WhatsApp o por correo. Quien lo abra rellena sus datos y la solicitud llega a Solicitudes.', 'aula-virtual' ); ?></p>
		</div>

		<?php if ( empty( $links ) ) : ?>
			<div class="av-empty">
				<span class="dashicons dashicons-admin-links"></span>
				<h3><?php esc_html_e( 'Todavía no hay enlace de inscripción', 'aula-virtual' ); ?></h3>
				<p><?php esc_html_e( 'Crea uno abajo. Te proponemos una dirección fácil de dictar a partir del código de la edición.', 'aula-virtual' ); ?></p>
			</div>
		<?php else : ?>
			<table class="widefat striped av-table">
				<thead>
					<tr>
						<th scope="col" style="width:20%"><?php esc_html_e( 'Enlace', 'aula-virtual' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Dirección', 'aula-virtual' ); ?></th>
						<th scope="col" class="av-hide-sm" style="width:150px"><?php esc_html_e( 'Al inscribirse', 'aula-virtual' ); ?></th>
						<th scope="col" style="width:80px"><?php esc_html_e( 'Usos', 'aula-virtual' ); ?></th>
						<th scope="col" style="width:100px"><span class="screen-reader-text"><?php esc_html_e( 'Acciones', 'aula-virtual' ); ?></span></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $links as $av_link ) : ?>
					<?php
					$av_field  = 'av-link-' . (int) $av_link['id'];
					$av_url    = RegistrationService::link_url( (string) $av_link['token'] );
					$av_active = 'active' === $av_link['status'];
					?>
					<tr<?php echo $av_active ? '' : ' style="opacity:.6"'; ?>>
						<td>
							<strong><?php echo esc_html( (string) $av_link['label'] ); ?></strong>
							<?php if ( ! empty( $av_link['expires_at'] ) ) : ?>
								<span class="av-sub"><?php echo esc_html( sprintf( /* translators: %s: date. */ __( 'Vence el %s', 'aula-virtual' ), $av_date( $av_link['expires_at'] ) ) ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<div class="av-copy">
								<a class="av-url" href="<?php echo esc_url( $av_url ); ?>" target="_blank" rel="noopener" title="<?php echo esc_attr( $av_url ); ?>"><?php echo esc_html( (string) wp_parse_url( $av_url, PHP_URL_PATH ) ); ?></a>
								<input type="hidden" id="<?php echo esc_attr( $av_field ); ?>" value="<?php echo esc_attr( $av_url ); ?>">
								<button type="button" class="button" data-av-copy="<?php echo esc_attr( $av_field ); ?>" data-av-copied="<?php esc_attr_e( 'Copiado', 'aula-virtual' ); ?>"><?php esc_html_e( 'Copiar', 'aula-virtual' ); ?></button>
							</div>
						</td>
						<td class="av-hide-sm">
							<?php
							if ( ! $av_active ) {
								echo $av_badge( __( 'Desactivado', 'aula-virtual' ), 'gray' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the closure.
							} else {
								echo (int) $av_link['requires_approval'] // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the closure.
									? $av_badge( __( 'Tú apruebas', 'aula-virtual' ), 'yellow' )
									: $av_badge( __( 'Entra directo', 'aula-virtual' ), 'green' );
							}
							?>
						</td>
						<td>
							<?php
							echo esc_html(
								(int) $av_link['max_uses'] > 0
									? (int) $av_link['uses'] . ' / ' . (int) $av_link['max_uses']
									: (string) (int) $av_link['uses']
							);
							?>
						</td>
						<td class="av-col-actions">
							<form method="post" action="<?php echo esc_url( $av_post ); ?>"<?php echo $av_active ? ' data-av-confirm="' . esc_attr__( '¿Desactivar este enlace? Quien lo abra verá que ya no admite inscripciones.', 'aula-virtual' ) . '"' : ''; ?>>
								<input type="hidden" name="action" value="<?php echo esc_attr( EditionsScreen::ACTION_TOGGLE_LINK ); ?>">
								<input type="hidden" name="link_id" value="<?php echo esc_attr( (string) (int) $av_link['id'] ); ?>">
								<?php wp_nonce_field( EditionsScreen::ACTION_TOGGLE_LINK ); ?>
								<button type="submit" class="button-link<?php echo $av_active ? ' button-link-delete' : ''; ?>"><?php echo esc_html( $av_active ? __( 'Desactivar', 'aula-virtual' ) : __( 'Activar', 'aula-virtual' ) ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<div class="av-card">
			<h3><?php esc_html_e( 'Nuevo enlace', 'aula-virtual' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Puedes tener varios, por ejemplo uno para la landing y otro para WhatsApp, y ver cuántas personas llegan por cada uno.', 'aula-virtual' ); ?></p>
			<form method="post" action="<?php echo esc_url( $av_post ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( EditionsScreen::ACTION_CREATE_LINK ); ?>">
				<input type="hidden" name="edition_id" value="<?php echo esc_attr( (string) $av_id ); ?>">
				<?php wp_nonce_field( EditionsScreen::ACTION_CREATE_LINK ); ?>
				<div class="av-inline-form av-inline-form--3">
					<div>
						<label for="av-link-label"><?php esc_html_e( 'Nombre interno', 'aula-virtual' ); ?></label>
						<input type="text" name="label" id="av-link-label" placeholder="<?php esc_attr_e( 'Landing, WhatsApp, Instagram…', 'aula-virtual' ); ?>">
					</div>
					<div>
						<label for="av-link-slug"><?php esc_html_e( 'Dirección', 'aula-virtual' ); ?></label>
						<div class="av-field-prefix">
							<span><?php echo esc_html( (string) wp_parse_url( home_url( '/inscripcion/' ), PHP_URL_PATH ) ); ?></span>
							<input type="text" name="slug" id="av-link-slug" value="<?php echo esc_attr( $suggested_slug ); ?>" pattern="[a-z0-9\-]{3,60}" title="<?php esc_attr_e( 'Minúsculas, números y guiones, de 3 a 60 caracteres.', 'aula-virtual' ); ?>">
						</div>
					</div>
					<div><?php submit_button( __( 'Crear enlace', 'aula-virtual' ), 'primary', 'submit', false ); ?></div>
				</div>
				<fieldset style="margin-top:12px">
					<legend style="font-weight:600;margin-bottom:4px"><?php esc_html_e( 'Cuando alguien se inscriba', 'aula-virtual' ); ?></legend>
					<label style="display:block;margin:4px 0"><input type="radio" name="requires_approval" value="1" checked> <?php esc_html_e( 'Revisar y aprobar cada solicitud (recomendado)', 'aula-virtual' ); ?></label>
					<label style="display:block;margin:4px 0"><input type="radio" name="requires_approval" value="0"> <?php echo esc_html( (int) $edition['product_id'] > 0 ? __( 'Aprobar automáticamente: recibe al instante el enlace de pago', 'aula-virtual' ) : __( 'Aprobar automáticamente: queda matriculado al instante', 'aula-virtual' ) ); ?></label>
				</fieldset>
				<label style="display:block;margin:10px 0 0"><input type="checkbox" name="private" value="1"> <?php esc_html_e( 'Enlace privado: usar una dirección aleatoria difícil de adivinar', 'aula-virtual' ); ?></label>
			</form>
		</div>

	<?php else : ?>

		<form method="post" action="<?php echo esc_url( $av_post ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( EditionsScreen::ACTION_SAVE_EDITION ); ?>">
			<input type="hidden" name="edition_id" value="<?php echo esc_attr( (string) $av_id ); ?>">
			<?php wp_nonce_field( EditionsScreen::ACTION_SAVE_EDITION ); ?>
			<?php
			$av_values  = $edition;
			$av_courses = array();
			require AV_PATH . 'admin/views/partials/edition-fields.php';
			?>
			<?php submit_button( __( 'Guardar cambios', 'aula-virtual' ) ); ?>
		</form>

		<div class="av-card">
			<h3><?php esc_html_e( 'Duplicar edición', 'aula-virtual' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Crea la siguiente cohorte en borrador con las mismas sesiones. Si indicas la nueva fecha de inicio, las fechas de acceso y de las clases en vivo se desplazan los mismos días. No se copian alumnos, enlaces ni el producto de WooCommerce.', 'aula-virtual' ); ?></p>
			<form method="post" action="<?php echo esc_url( $av_post ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( EditionsScreen::ACTION_DUPLICATE ); ?>">
				<input type="hidden" name="edition_id" value="<?php echo esc_attr( (string) $av_id ); ?>">
				<?php wp_nonce_field( EditionsScreen::ACTION_DUPLICATE ); ?>
				<div class="av-inline-form av-inline-form--3">
					<div>
						<label for="av-dup-name"><?php esc_html_e( 'Nombre de la nueva edición', 'aula-virtual' ); ?></label>
						<input type="text" name="name" id="av-dup-name" placeholder="<?php echo esc_attr( sprintf( /* translators: %s: edition name. */ __( 'Copia de %s', 'aula-virtual' ), (string) $edition['name'] ) ); ?>">
					</div>
					<div>
						<label for="av-dup-start"><?php esc_html_e( 'Nueva fecha de inicio (opcional)', 'aula-virtual' ); ?></label>
						<input type="date" name="start_date" id="av-dup-start">
					</div>
					<div><?php submit_button( __( 'Duplicar', 'aula-virtual' ), 'secondary', 'submit', false ); ?></div>
				</div>
				<p style="margin:10px 0 0">
					<label style="margin-right:16px"><input type="checkbox" name="copy_live" value="1" checked> <?php esc_html_e( 'Copiar clases en vivo (sin grabaciones)', 'aula-virtual' ); ?></label>
					<label><input type="checkbox" name="copy_materials" value="1" checked> <?php esc_html_e( 'Copiar materiales', 'aula-virtual' ); ?></label>
				</p>
			</form>
		</div>

	<?php endif; ?>
	</div>
</div>
