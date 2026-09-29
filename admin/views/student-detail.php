<?php
/**
 * Student profile: enrollments, access, personal data and history.
 *
 * @package SIQA\AulaVirtual
 *
 * @var WP_User                                   $user
 * @var array<int, array<string, mixed>>          $enrollments
 * @var array<int, array<string, mixed>|null>     $editions
 * @var array<int, array<string, mixed>>          $targets
 * @var array<int, array<string, mixed>>          $all_editions
 * @var string                                    $tab
 * @var bool                                      $can_edit
 * @var bool                                      $needs_password
 * @var string                                    $last_login
 * @var string                                    $link_expiry
 * @var array<int, array<string, mixed>>          $history
 * @var array{type: string, message: string}|null $notice
 */

use SIQA\AulaVirtual\Admin\AdminMenu;
use SIQA\AulaVirtual\Admin\StudentsScreen;
use SIQA\AulaVirtual\Core\AuditLog;
use SIQA\AulaVirtual\Enrollments\EnrollmentStatus;
use SIQA\AulaVirtual\Permissions\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$av_uid   = (int) $user->ID;
$av_post  = admin_url( 'admin-post.php' );
$av_date  = static function ( $value, bool $time = false ): string {
	if ( empty( $value ) || str_starts_with( (string) $value, '0000' ) ) {
		return '';
	}

	return date_i18n( (string) get_option( 'date_format' ) . ( $time ? ' H:i' : '' ), (int) strtotime( $value . ' UTC' ) );
};
$av_tone  = array(
	EnrollmentStatus::PENDING   => 'yellow',
	EnrollmentStatus::APPROVED  => 'blue',
	EnrollmentStatus::ACTIVE    => 'green',
	EnrollmentStatus::COMPLETED => 'blue',
	EnrollmentStatus::SUSPENDED => 'red',
	EnrollmentStatus::EXPIRED   => 'gray',
	EnrollmentStatus::CANCELLED => 'red',
	EnrollmentStatus::REJECTED  => 'red',
);
$av_hidden = static function ( string $action ) use ( $av_uid ): void {
	echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">';
	echo '<input type="hidden" name="user_id" value="' . esc_attr( (string) $av_uid ) . '">';
	wp_nonce_field( $action );
};
$av_can_enroll = current_user_can( Capabilities::ENROLL_STUDENTS );
$av_completed  = count( array_filter( $enrollments, static fn( array $e ): bool => EnrollmentStatus::COMPLETED === $e['status'] ) );
$av_active     = count( array_filter( $enrollments, static fn( array $e ): bool => EnrollmentStatus::grants_access( (string) $e['status'] ) ) );
$av_phone      = (string) get_user_meta( $av_uid, 'av_phone', true );
$av_document   = (string) get_user_meta( $av_uid, 'av_document', true );
$av_tabs       = array(
	'matriculas' => array( __( 'Matrículas', 'aula-virtual' ), count( $enrollments ) ),
	'acceso'     => array( __( 'Acceso y contraseña', 'aula-virtual' ), null ),
	'datos'      => array( __( 'Datos personales', 'aula-virtual' ), null ),
	'historial'  => array( __( 'Historial', 'aula-virtual' ), null ),
);
$av_actions    = array(
	AuditLog::ENROLLMENT_CREATED => __( 'Matrícula creada', 'aula-virtual' ),
	AuditLog::ENROLLMENT_UPDATED => __( 'Estado de matrícula cambiado', 'aula-virtual' ),
	AuditLog::ENROLLMENT_MOVED   => __( 'Matrícula movida de edición', 'aula-virtual' ),
	AuditLog::ACCESS_EXTENDED    => __( 'Fecha de acceso cambiada', 'aula-virtual' ),
	AuditLog::PROGRESS_RESET     => __( 'Avance reiniciado', 'aula-virtual' ),
	AuditLog::ACCESS_LINK_SENT   => __( 'Enlace de acceso enviado', 'aula-virtual' ),
	AuditLog::PASSWORD_SET       => __( 'Contraseña cambiada por un administrador', 'aula-virtual' ),
	AuditLog::STUDENT_UPDATED    => __( 'Datos personales editados', 'aula-virtual' ),
	AuditLog::REQUEST_APPROVED   => __( 'Solicitud aprobada', 'aula-virtual' ),
	AuditLog::REQUEST_REJECTED   => __( 'Solicitud rechazada', 'aula-virtual' ),
	AuditLog::CERTIFICATE_ISSUED => __( 'Certificado emitido', 'aula-virtual' ),
);
$av_edition_label = static function ( ?array $edition ): string {
	return null === $edition ? __( 'Edición eliminada', 'aula-virtual' ) : get_the_title( (int) $edition['course_id'] ) . ' · ' . $edition['name'];
};
?>
<div class="wrap av-admin">
	<a class="av-back" href="<?php echo esc_url( StudentsScreen::url() ); ?>"><span class="dashicons dashicons-arrow-left-alt2"></span><?php esc_html_e( 'Alumnos', 'aula-virtual' ); ?></a>

	<div class="av-header">
		<div>
			<h1>
				<?php echo esc_html( (string) $user->display_name ); ?>
				<?php if ( $needs_password ) : ?>
					<span class="av-badge av-badge--yellow"><?php esc_html_e( 'Aún no creó su contraseña', 'aula-virtual' ); ?></span>
				<?php endif; ?>
			</h1>
			<p class="av-header__meta">
				<span><span class="dashicons dashicons-email"></span><?php echo esc_html( (string) $user->user_email ); ?></span>
				<?php if ( '' !== $av_phone ) : ?>
					<span><span class="dashicons dashicons-phone"></span><?php echo esc_html( $av_phone ); ?></span>
				<?php endif; ?>
				<span><span class="dashicons dashicons-admin-users"></span><?php echo esc_html( sprintf( /* translators: %s: date. */ __( 'En el sitio desde %s', 'aula-virtual' ), $av_date( $user->user_registered ) ) ); ?></span>
			</p>
		</div>
		<?php if ( $av_can_enroll ) : ?>
			<div class="av-header__actions">
				<form method="post" action="<?php echo esc_url( $av_post ); ?>">
					<?php $av_hidden( StudentsScreen::ACTION_ACCESS_LINK ); ?>
					<button type="submit" class="button button-primary"><span class="dashicons dashicons-email-alt" style="margin:4px 4px 0 -2px;font-size:16px;width:16px;height:16px"></span><?php esc_html_e( 'Reenviar enlace de acceso', 'aula-virtual' ); ?></button>
				</form>
			</div>
		<?php endif; ?>
	</div>

	<?php require AV_PATH . 'admin/views/notice.php'; ?>

	<div class="av-stats">
		<div class="av-stat"><p class="av-stat__label"><?php esc_html_e( 'Matrículas activas', 'aula-virtual' ); ?></p><p class="av-stat__value"><?php echo esc_html( (string) $av_active ); ?> <small><?php echo esc_html( sprintf( /* translators: %d: total. */ __( 'de %d', 'aula-virtual' ), count( $enrollments ) ) ); ?></small></p></div>
		<div class="av-stat"><p class="av-stat__label"><?php esc_html_e( 'Cursos terminados', 'aula-virtual' ); ?></p><p class="av-stat__value"><?php echo esc_html( (string) $av_completed ); ?></p></div>
		<div class="av-stat<?php echo $needs_password ? ' av-stat--attention' : ''; ?>">
			<p class="av-stat__label"><?php esc_html_e( 'Último ingreso', 'aula-virtual' ); ?></p>
			<p class="av-stat__value" style="font-size:16px"><?php echo esc_html( '' === $last_login ? __( 'Nunca registrado', 'aula-virtual' ) : $av_date( $last_login, true ) ); ?></p>
			<?php if ( $needs_password ) : ?>
				<p class="av-stat__hint"><a href="<?php echo esc_url( StudentsScreen::url( $av_uid, 'acceso' ) ); ?>"><?php esc_html_e( 'Ayúdale a entrar →', 'aula-virtual' ); ?></a></p>
			<?php endif; ?>
		</div>
	</div>

	<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Secciones del alumno', 'aula-virtual' ); ?>">
		<?php foreach ( $av_tabs as $av_key => $av_item ) : ?>
			<a href="<?php echo esc_url( StudentsScreen::url( $av_uid, $av_key ) ); ?>" class="nav-tab<?php echo $tab === $av_key ? ' nav-tab-active' : ''; ?>"<?php echo $tab === $av_key ? ' aria-current="page"' : ''; ?>>
				<?php echo esc_html( $av_item[0] ); ?>
				<?php if ( null !== $av_item[1] ) : ?><span class="av-count"><?php echo esc_html( (string) $av_item[1] ); ?></span><?php endif; ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="av-panel">
	<?php if ( 'matriculas' === $tab ) : ?>

		<?php if ( empty( $enrollments ) ) : ?>
			<div class="av-empty">
				<span class="dashicons dashicons-welcome-learn-more"></span>
				<h3><?php esc_html_e( 'Sin matrículas', 'aula-virtual' ); ?></h3>
				<p><?php esc_html_e( 'Matricúlalo en una edición con el formulario de abajo.', 'aula-virtual' ); ?></p>
			</div>
		<?php else : ?>
			<div class="av-enrollments">
			<?php foreach ( $enrollments as $av_e ) : ?>
				<?php
				$av_eid     = (int) $av_e['id'];
				$av_edition = $editions[ (int) $av_e['edition_id'] ] ?? null;
				$av_pct     = max( 0.0, min( 100.0, (float) $av_e['progress_percentage'] ) );
				$av_next    = EnrollmentStatus::transitions()[ $av_e['status'] ] ?? array();
				$av_until   = $av_date( $av_e['expires_at'] ?? '' );
				?>
				<details class="av-enrollment">
					<summary>
						<span class="av-enrollment__title">
							<strong><?php echo esc_html( $av_edition_label( $av_edition ) ); ?></strong>
							<span class="av-sub">
								<?php echo esc_html( sprintf( /* translators: %s: date. */ __( 'Matriculado el %s', 'aula-virtual' ), $av_date( $av_e['enrolled_at'] ?? '' ) ) ); ?>
								<?php echo '' !== $av_until ? ' · ' . esc_html( sprintf( /* translators: %s: date. */ __( 'acceso hasta el %s', 'aula-virtual' ), $av_until ) ) : ''; ?>
							</span>
						</span>
						<span class="av-badge av-badge--<?php echo esc_attr( $av_tone[ $av_e['status'] ] ?? 'gray' ); ?>"><?php echo esc_html( EnrollmentStatus::label( (string) $av_e['status'] ) ); ?></span>
						<span class="av-progress" style="width:180px">
							<span class="av-progress__track"><span class="av-progress__bar<?php echo $av_pct >= 100 ? ' is-complete' : ''; ?>" style="display:block;width:<?php echo esc_attr( (string) round( $av_pct, 1 ) ); ?>%"></span></span>
							<span class="av-progress__value"><?php echo esc_html( number_format_i18n( $av_pct, 0 ) . '%' ); ?></span>
						</span>
						<span class="av-enrollment__toggle"><?php esc_html_e( 'Gestionar', 'aula-virtual' ); ?></span>
					</summary>

					<div class="av-enrollment__body av-grid-2">
						<?php if ( array() !== $av_next ) : ?>
							<form method="post" action="<?php echo esc_url( $av_post ); ?>" class="av-card av-card--white">
								<h3><?php esc_html_e( 'Cambiar estado', 'aula-virtual' ); ?></h3>
								<p class="description"><?php esc_html_e( 'Suspender quita el acceso sin borrar nada; activar lo devuelve.', 'aula-virtual' ); ?></p>
								<input type="hidden" name="action" value="<?php echo esc_attr( StudentsScreen::ACTION_ENROLLMENT ); ?>">
								<input type="hidden" name="operation" value="status">
								<input type="hidden" name="enrollment_id" value="<?php echo esc_attr( (string) $av_eid ); ?>">
								<?php wp_nonce_field( StudentsScreen::ACTION_ENROLLMENT ); ?>
								<div class="av-row">
									<select name="status" required aria-label="<?php esc_attr_e( 'Nuevo estado', 'aula-virtual' ); ?>">
										<option value=""><?php esc_html_e( 'Elige un estado…', 'aula-virtual' ); ?></option>
										<?php foreach ( $av_next as $av_s ) : ?>
											<option value="<?php echo esc_attr( $av_s ); ?>"><?php echo esc_html( EnrollmentStatus::label( $av_s ) ); ?></option>
										<?php endforeach; ?>
									</select>
									<?php submit_button( __( 'Aplicar', 'aula-virtual' ), 'secondary', '', false ); ?>
								</div>
							</form>
						<?php endif; ?>

						<form method="post" action="<?php echo esc_url( $av_post ); ?>" class="av-card av-card--white">
							<h3><?php esc_html_e( 'Acceso hasta', 'aula-virtual' ); ?></h3>
							<p class="description"><?php esc_html_e( 'Amplía o recorta el acceso de esta persona. Vacío: sigue las fechas de la edición.', 'aula-virtual' ); ?></p>
							<input type="hidden" name="action" value="<?php echo esc_attr( StudentsScreen::ACTION_ENROLLMENT ); ?>">
							<input type="hidden" name="operation" value="access">
							<input type="hidden" name="enrollment_id" value="<?php echo esc_attr( (string) $av_eid ); ?>">
							<?php wp_nonce_field( StudentsScreen::ACTION_ENROLLMENT ); ?>
							<div class="av-row">
								<input type="date" name="access_until" value="<?php echo esc_attr( empty( $av_e['expires_at'] ) ? '' : substr( (string) $av_e['expires_at'], 0, 10 ) ); ?>" aria-label="<?php esc_attr_e( 'Fecha', 'aula-virtual' ); ?>">
								<?php submit_button( __( 'Guardar', 'aula-virtual' ), 'secondary', '', false ); ?>
								<?php if ( ! empty( $av_e['expires_at'] ) ) : ?>
									<button type="submit" name="clear" value="1" class="button-link"><?php esc_html_e( 'Quitar fecha', 'aula-virtual' ); ?></button>
								<?php endif; ?>
							</div>
						</form>

						<form method="post" action="<?php echo esc_url( $av_post ); ?>" class="av-card av-card--white">
							<h3><?php esc_html_e( 'Mover a otra edición', 'aula-virtual' ); ?></h3>
							<p class="description"><?php esc_html_e( 'Para pasarle a otra cohorte u otro curso. El avance empieza de cero porque las sesiones son distintas.', 'aula-virtual' ); ?></p>
							<input type="hidden" name="action" value="<?php echo esc_attr( StudentsScreen::ACTION_ENROLLMENT ); ?>">
							<input type="hidden" name="operation" value="move">
							<input type="hidden" name="enrollment_id" value="<?php echo esc_attr( (string) $av_eid ); ?>">
							<?php wp_nonce_field( StudentsScreen::ACTION_ENROLLMENT ); ?>
							<div class="av-row">
								<select name="edition_id" required aria-label="<?php esc_attr_e( 'Edición de destino', 'aula-virtual' ); ?>">
									<option value=""><?php esc_html_e( 'Elige la edición de destino', 'aula-virtual' ); ?></option>
									<?php foreach ( $targets as $av_t ) : ?>
										<option value="<?php echo esc_attr( (string) (int) $av_t['id'] ); ?>"><?php echo esc_html( $av_edition_label( $av_t ) ); ?></option>
									<?php endforeach; ?>
								</select>
								<?php submit_button( __( 'Mover', 'aula-virtual' ), 'secondary', '', false ); ?>
							</div>
						</form>

						<form method="post" action="<?php echo esc_url( $av_post ); ?>" class="av-card av-card--white" data-av-confirm="<?php esc_attr_e( '¿Reiniciar el avance? Las sesiones completadas volverán a aparecer pendientes.', 'aula-virtual' ); ?>">
							<h3><?php esc_html_e( 'Reiniciar avance', 'aula-virtual' ); ?></h3>
							<p class="description"><?php esc_html_e( 'Borra las sesiones marcadas como completadas. Útil si repite el curso. El certificado, si lo tiene, se conserva.', 'aula-virtual' ); ?></p>
							<input type="hidden" name="action" value="<?php echo esc_attr( StudentsScreen::ACTION_ENROLLMENT ); ?>">
							<input type="hidden" name="operation" value="reset">
							<input type="hidden" name="enrollment_id" value="<?php echo esc_attr( (string) $av_eid ); ?>">
							<?php wp_nonce_field( StudentsScreen::ACTION_ENROLLMENT ); ?>
							<?php submit_button( __( 'Reiniciar avance', 'aula-virtual' ), 'secondary', '', false ); ?>
						</form>
					</div>
					<?php if ( null !== $av_edition ) : ?>
						<p class="av-enrollment__foot"><a href="<?php echo esc_url( AdminMenu::editions_url( array( 'edition' => (int) $av_edition['id'], 'tab' => 'alumnos' ) ) ); ?>"><?php esc_html_e( 'Ver la edición →', 'aula-virtual' ); ?></a></p>
					<?php endif; ?>
				</details>
			<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( $av_can_enroll ) : ?>
			<form method="post" action="<?php echo esc_url( $av_post ); ?>" class="av-card">
				<h3><?php esc_html_e( 'Matricular en otra edición', 'aula-virtual' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Por ejemplo, darle acceso a otro curso o a la siguiente cohorte.', 'aula-virtual' ); ?></p>
				<?php $av_hidden( StudentsScreen::ACTION_ENROLL ); ?>
				<?php if ( empty( $targets ) ) : ?>
					<p><?php esc_html_e( 'Ya está matriculado en todas las ediciones que gestionas.', 'aula-virtual' ); ?></p>
				<?php else : ?>
					<div class="av-row">
						<select name="edition_id" required aria-label="<?php esc_attr_e( 'Edición', 'aula-virtual' ); ?>">
							<option value=""><?php esc_html_e( 'Elige una edición', 'aula-virtual' ); ?></option>
							<?php foreach ( $targets as $av_t ) : ?>
								<option value="<?php echo esc_attr( (string) (int) $av_t['id'] ); ?>"><?php echo esc_html( $av_edition_label( $av_t ) ); ?></option>
							<?php endforeach; ?>
						</select>
						<?php submit_button( __( 'Matricular', 'aula-virtual' ), 'primary', '', false ); ?>
					</div>
					<p style="margin:10px 0 0"><label><input type="checkbox" name="notify" value="1" checked> <?php esc_html_e( 'Enviarle el correo de bienvenida', 'aula-virtual' ); ?></label></p>
				<?php endif; ?>
			</form>
		<?php endif; ?>

	<?php elseif ( 'acceso' === $tab ) : ?>

		<div class="av-panel__intro">
			<p>
				<?php
				echo esc_html(
					$needs_password
						? __( 'Esta persona todavía no creó su contraseña: su cuenta la creó el sistema al matricularla. Lo más sencillo es reenviarle el enlace de acceso.', 'aula-virtual' )
						: __( 'Esta persona ya tiene contraseña. Si no la recuerda, reenvíale el enlace o pídele que use «Olvidé mi contraseña» en el campus.', 'aula-virtual' )
				);
				?>
			</p>
		</div>

		<div class="av-grid-2">
			<?php if ( $av_can_enroll ) : ?>
				<form method="post" action="<?php echo esc_url( $av_post ); ?>" class="av-card">
					<h3><?php esc_html_e( 'Reenviar enlace de acceso', 'aula-virtual' ); ?></h3>
					<p class="description">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: email, 2: lifetime. */
								__( 'Le llega a %1$s un enlace para crear o cambiar su contraseña. Vence en %2$s y anula cualquier enlace anterior.', 'aula-virtual' ),
								(string) $user->user_email,
								$link_expiry
							)
						);
						?>
					</p>
					<?php $av_hidden( StudentsScreen::ACTION_ACCESS_LINK ); ?>
					<input type="hidden" name="return" value="<?php echo esc_attr( StudentsScreen::url( $av_uid, 'acceso' ) ); ?>">
					<?php submit_button( __( 'Enviar enlace', 'aula-virtual' ), 'primary', '', false ); ?>
				</form>
			<?php endif; ?>

			<?php if ( $can_edit ) : ?>
				<form method="post" action="<?php echo esc_url( $av_post ); ?>" class="av-card" autocomplete="off">
					<h3><?php esc_html_e( 'Poner una contraseña', 'aula-virtual' ); ?></h3>
					<p class="description"><?php esc_html_e( 'Para casos de soporte, por ejemplo si el correo no le llega. No se envía por correo: compártela por un canal seguro y pídele que la cambie en «Mis datos».', 'aula-virtual' ); ?></p>
					<?php $av_hidden( StudentsScreen::ACTION_PASSWORD ); ?>
					<div class="av-row">
						<div class="av-grow">
							<label for="av-pass"><?php esc_html_e( 'Nueva contraseña', 'aula-virtual' ); ?></label>
							<input type="password" id="av-pass" name="password" minlength="8" required autocomplete="new-password">
						</div>
						<div class="av-grow">
							<label for="av-pass2"><?php esc_html_e( 'Repetir', 'aula-virtual' ); ?></label>
							<input type="password" id="av-pass2" name="password_confirm" minlength="8" required autocomplete="new-password">
						</div>
					</div>
					<p class="av-row" style="margin:12px 0 0">
						<button type="button" class="button" data-av-generate="av-pass,av-pass2"><?php esc_html_e( 'Generar una segura', 'aula-virtual' ); ?></button>
						<?php submit_button( __( 'Guardar contraseña', 'aula-virtual' ), 'secondary', '', false ); ?>
					</p>
				</form>

				<form method="post" action="<?php echo esc_url( $av_post ); ?>" class="av-card" data-av-confirm="<?php esc_attr_e( '¿Cerrar todas sus sesiones? Tendrá que volver a entrar en todos sus dispositivos.', 'aula-virtual' ); ?>">
					<h3><?php esc_html_e( 'Cerrar sus sesiones abiertas', 'aula-virtual' ); ?></h3>
					<p class="description"><?php esc_html_e( 'Si sospechas que otra persona usa su cuenta. Tendrá que volver a entrar en todos sus dispositivos.', 'aula-virtual' ); ?></p>
					<?php $av_hidden( StudentsScreen::ACTION_LOGOUT ); ?>
					<?php submit_button( __( 'Cerrar sesiones', 'aula-virtual' ), 'secondary', '', false ); ?>
				</form>
			<?php else : ?>
				<div class="av-card">
					<h3><?php esc_html_e( 'Contraseña y sesiones', 'aula-virtual' ); ?></h3>
					<p class="description"><?php esc_html_e( 'Solo un administrador del sitio puede poner una contraseña o cerrar las sesiones de esta cuenta.', 'aula-virtual' ); ?></p>
				</div>
			<?php endif; ?>
		</div>

	<?php elseif ( 'datos' === $tab ) : ?>

		<?php if ( $can_edit ) : ?>
			<form method="post" action="<?php echo esc_url( $av_post ); ?>">
				<?php $av_hidden( StudentsScreen::ACTION_PROFILE ); ?>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><label for="av-fn"><?php esc_html_e( 'Nombre', 'aula-virtual' ); ?></label></th><td><input type="text" id="av-fn" name="first_name" class="regular-text" value="<?php echo esc_attr( (string) $user->first_name ); ?>"></td></tr>
					<tr><th scope="row"><label for="av-ln"><?php esc_html_e( 'Apellido', 'aula-virtual' ); ?></label></th><td><input type="text" id="av-ln" name="last_name" class="regular-text" value="<?php echo esc_attr( (string) $user->last_name ); ?>"></td></tr>
					<tr>
						<th scope="row"><label for="av-em"><?php esc_html_e( 'Correo electrónico', 'aula-virtual' ); ?></label></th>
						<td>
							<input type="email" id="av-em" name="email" class="regular-text" required value="<?php echo esc_attr( (string) $user->user_email ); ?>">
							<p class="description"><?php esc_html_e( 'Es su usuario para entrar al campus y donde recibe los correos. Comprueba que esté bien escrito.', 'aula-virtual' ); ?></p>
						</td>
					</tr>
					<tr><th scope="row"><label for="av-ph"><?php esc_html_e( 'Teléfono / WhatsApp', 'aula-virtual' ); ?></label></th><td><input type="text" id="av-ph" name="phone" class="regular-text" value="<?php echo esc_attr( $av_phone ); ?>"></td></tr>
					<tr><th scope="row"><label for="av-doc"><?php esc_html_e( 'Documento', 'aula-virtual' ); ?></label></th><td><input type="text" id="av-doc" name="document" class="regular-text" value="<?php echo esc_attr( $av_document ); ?>"></td></tr>
				</table>
				<?php submit_button( __( 'Guardar datos', 'aula-virtual' ) ); ?>
			</form>
		<?php else : ?>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><?php esc_html_e( 'Nombre', 'aula-virtual' ); ?></th><td><?php echo esc_html( trim( $user->first_name . ' ' . $user->last_name ) ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Correo electrónico', 'aula-virtual' ); ?></th><td><?php echo esc_html( (string) $user->user_email ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Teléfono / WhatsApp', 'aula-virtual' ); ?></th><td><?php echo esc_html( $av_phone ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Documento', 'aula-virtual' ); ?></th><td><?php echo esc_html( $av_document ); ?></td></tr>
			</table>
			<p class="description"><?php esc_html_e( 'Solo un administrador del sitio puede editar estos datos.', 'aula-virtual' ); ?></p>
		<?php endif; ?>

	<?php else : ?>

		<?php if ( empty( $history ) ) : ?>
			<div class="av-empty">
				<span class="dashicons dashicons-backup"></span>
				<h3><?php esc_html_e( 'Sin movimientos registrados', 'aula-virtual' ); ?></h3>
				<p><?php esc_html_e( 'Aquí aparecen las acciones del equipo sobre esta cuenta: matrículas, cambios de estado, enlaces enviados.', 'aula-virtual' ); ?></p>
			</div>
		<?php else : ?>
			<table class="widefat striped av-table">
				<thead><tr><th scope="col" style="width:180px"><?php esc_html_e( 'Fecha', 'aula-virtual' ); ?></th><th scope="col"><?php esc_html_e( 'Acción', 'aula-virtual' ); ?></th><th scope="col"><?php esc_html_e( 'Quién', 'aula-virtual' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $history as $av_h ) : ?>
					<?php
					$av_actor = (int) $av_h['actor_id'] > 0 ? get_userdata( (int) $av_h['actor_id'] ) : false;
					$av_data  = json_decode( (string) $av_h['data'], true );
					$av_extra = '';
					if ( is_array( $av_data ) && isset( $av_data['from'], $av_data['to'] ) && is_string( $av_data['to'] ) ) {
						$av_extra = EnrollmentStatus::label( (string) $av_data['from'] ) . ' → ' . EnrollmentStatus::label( (string) $av_data['to'] );
					}
					?>
					<tr>
						<td><?php echo esc_html( $av_date( $av_h['created_at'], true ) ); ?></td>
						<td><?php echo esc_html( $av_actions[ $av_h['action'] ] ?? (string) $av_h['action'] ); ?><?php echo '' !== $av_extra ? '<span class="av-sub">' . esc_html( $av_extra ) . '</span>' : ''; ?></td>
						<td><?php echo esc_html( false === $av_actor ? __( 'Sistema', 'aula-virtual' ) : (string) $av_actor->display_name ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

	<?php endif; ?>
	</div>
</div>
