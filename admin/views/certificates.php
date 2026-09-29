<?php
/**
 * Certificates: list, filter and manual issue.
 *
 * @package SIQA\AulaVirtual
 *
 * @var array<int, array<string, mixed>>          $items
 * @var array<int, string>                        $urls
 * @var array<int, array<string, mixed>>          $editions
 * @var int                                       $edition_filter
 * @var bool                                      $auto_enabled
 * @var array{type: string, message: string}|null $notice
 */

use SIQA\AulaVirtual\Admin\CertificatesScreen;
use SIQA\AulaVirtual\Admin\SettingsScreen;
use SIQA\AulaVirtual\Certificates\CertificateRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$av_courses = array();
foreach ( $editions as $av_edition ) {
	$av_courses[ (int) $av_edition['course_id'] ] = get_the_title( (int) $av_edition['course_id'] );
}

$av_edition_names = array();
foreach ( $editions as $av_edition ) {
	$av_edition_names[ (int) $av_edition['id'] ] = (string) $av_edition['name'];
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Certificados', 'aula-virtual' ); ?></h1>

	<?php require AV_PATH . 'admin/views/notice.php'; ?>

	<p class="description">
		<?php
		echo esc_html(
			$auto_enabled
				? __( 'La emisión automática está activa: cada alumno recibe su certificado al completar el curso.', 'aula-virtual' )
				: __( 'La emisión automática está desactivada: los certificados se emiten desde esta pantalla.', 'aula-virtual' )
		);
		?>
		<a href="<?php echo esc_url( add_query_arg( array( 'page' => SettingsScreen::SLUG, 'tab' => 'certificates' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Configurar', 'aula-virtual' ); ?></a>
	</p>

	<div style="display:grid;grid-template-columns:minmax(0,1.6fr) minmax(320px,1fr);gap:28px;align-items:start">
		<div>
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="margin-bottom:12px">
				<input type="hidden" name="page" value="<?php echo esc_attr( CertificatesScreen::SLUG ); ?>">
				<label for="av-edition-filter" class="screen-reader-text"><?php esc_html_e( 'Filtrar por edición', 'aula-virtual' ); ?></label>
				<select name="edition" id="av-edition-filter">
					<option value="0"><?php esc_html_e( 'Todas las ediciones (últimas 100)', 'aula-virtual' ); ?></option>
					<?php foreach ( $av_courses as $av_course_id => $av_course_title ) : ?>
						<optgroup label="<?php echo esc_attr( $av_course_title ); ?>">
							<?php foreach ( $editions as $av_edition ) : ?>
								<?php if ( (int) $av_edition['course_id'] !== $av_course_id ) { continue; } ?>
								<option value="<?php echo esc_attr( (string) (int) $av_edition['id'] ); ?>" <?php selected( $edition_filter, (int) $av_edition['id'] ); ?>><?php echo esc_html( (string) $av_edition['name'] ); ?></option>
							<?php endforeach; ?>
						</optgroup>
					<?php endforeach; ?>
				</select>
				<?php submit_button( __( 'Filtrar', 'aula-virtual' ), 'secondary', 'submit', false ); ?>
			</form>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Alumno', 'aula-virtual' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Curso / edición', 'aula-virtual' ); ?></th>
						<th scope="col" style="width:150px"><?php esc_html_e( 'Código', 'aula-virtual' ); ?></th>
						<th scope="col" style="width:110px"><?php esc_html_e( 'Emitido', 'aula-virtual' ); ?></th>
						<th scope="col" style="width:90px"><?php esc_html_e( 'Estado', 'aula-virtual' ); ?></th>
						<th scope="col" style="width:150px"></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $items ) ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'Todavía no hay certificados.', 'aula-virtual' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $items as $av_item ) : ?>
						<?php
						$av_user    = get_userdata( (int) $av_item['user_id'] );
						$av_revoked = CertificateRepository::STATUS_REVOKED === (string) $av_item['status'];
						$av_url     = $urls[ (int) $av_item['id'] ] ?? '';
						?>
						<tr>
							<td>
								<?php if ( false !== $av_user ) : ?>
									<strong><?php echo esc_html( (string) $av_user->display_name ); ?></strong><br>
									<small><?php echo esc_html( (string) $av_user->user_email ); ?></small>
								<?php else : ?>
									<em><?php esc_html_e( 'Usuario eliminado', 'aula-virtual' ); ?></em>
								<?php endif; ?>
							</td>
							<td>
								<?php echo esc_html( get_the_title( (int) $av_item['course_id'] ) ); ?><br>
								<small><?php echo esc_html( $av_edition_names[ (int) $av_item['edition_id'] ] ?? ( '#' . (int) $av_item['edition_id'] ) ); ?></small>
							</td>
							<td><code><?php echo esc_html( (string) $av_item['certificate_code'] ); ?></code></td>
							<td><?php echo esc_html( ! empty( $av_item['issued_at'] ) ? mysql2date( (string) get_option( 'date_format' ), (string) $av_item['issued_at'] ) : '' ); ?></td>
							<td>
								<?php if ( $av_revoked ) : ?>
									<span style="color:#b32d2e"><?php esc_html_e( 'Anulado', 'aula-virtual' ); ?></span>
								<?php else : ?>
									<span style="color:#00a32a"><?php esc_html_e( 'Vigente', 'aula-virtual' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<a href="<?php echo esc_url( $av_url ); ?>" target="_blank" rel="noopener" class="button button-small"><?php esc_html_e( 'Ver', 'aula-virtual' ); ?></a>
								<?php if ( ! $av_revoked ) : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline" onsubmit="return confirm('<?php echo esc_js( __( '¿Anular este certificado? Su enlace público dejará de validar.', 'aula-virtual' ) ); ?>');">
										<input type="hidden" name="action" value="<?php echo esc_attr( CertificatesScreen::ACTION_REVOKE ); ?>">
										<input type="hidden" name="certificate_id" value="<?php echo esc_attr( (string) (int) $av_item['id'] ); ?>">
										<?php wp_nonce_field( CertificatesScreen::ACTION_REVOKE ); ?>
										<button type="submit" class="button-link-delete"><?php esc_html_e( 'Anular', 'aula-virtual' ); ?></button>
									</form>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>

		<div>
			<h2><?php esc_html_e( 'Emitir manualmente', 'aula-virtual' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( CertificatesScreen::ACTION_ISSUE ); ?>">
				<?php wp_nonce_field( CertificatesScreen::ACTION_ISSUE ); ?>
				<p>
					<label for="av-issue-edition"><?php esc_html_e( 'Edición', 'aula-virtual' ); ?></label><br>
					<select name="edition_id" id="av-issue-edition" class="widefat" required>
						<option value=""><?php esc_html_e( 'Selecciona una edición', 'aula-virtual' ); ?></option>
						<?php foreach ( $av_courses as $av_course_id => $av_course_title ) : ?>
							<optgroup label="<?php echo esc_attr( $av_course_title ); ?>">
								<?php foreach ( $editions as $av_edition ) : ?>
									<?php if ( (int) $av_edition['course_id'] !== $av_course_id ) { continue; } ?>
									<option value="<?php echo esc_attr( (string) (int) $av_edition['id'] ); ?>" <?php selected( $edition_filter, (int) $av_edition['id'] ); ?>><?php echo esc_html( (string) $av_edition['name'] ); ?></option>
								<?php endforeach; ?>
							</optgroup>
						<?php endforeach; ?>
					</select>
				</p>
				<p>
					<label for="av-issue-user"><?php esc_html_e( 'Alumno', 'aula-virtual' ); ?></label><br>
					<?php
					wp_dropdown_users(
						array(
							'name'              => 'user_id',
							'id'                => 'av-issue-user',
							'class'             => 'widefat',
							'show'              => 'display_name_with_login',
							'show_option_none'  => __( 'Selecciona un usuario', 'aula-virtual' ),
							'option_none_value' => 0,
							'number'            => 200,
						)
					);
					?>
				</p>
				<p class="description"><?php esc_html_e( 'El alumno debe estar matriculado en la edición con una matrícula aprobada, activa o completada. Si ya tiene un certificado vigente, se conserva el existente.', 'aula-virtual' ); ?></p>
				<?php submit_button( __( 'Emitir certificado', 'aula-virtual' ) ); ?>
			</form>
		</div>
	</div>
</div>
