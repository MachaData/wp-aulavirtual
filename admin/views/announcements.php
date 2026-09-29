<?php
/**
 * Announcements: list and form.
 *
 * @package SIQA\AulaVirtual
 *
 * @var array<int, array<string, mixed>>          $items
 * @var array<int, array<string, mixed>>          $editions
 * @var array{type: string, message: string}|null $notice
 */

use SIQA\AulaVirtual\Admin\AnnouncementsScreen;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$av_courses = array();
foreach ( $editions as $av_edition ) {
	$av_courses[ (int) $av_edition['course_id'] ] = get_the_title( (int) $av_edition['course_id'] );
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Anuncios', 'aula-virtual' ); ?></h1>

	<?php require AV_PATH . 'admin/views/notice.php'; ?>

	<div style="display:grid;grid-template-columns:minmax(0,1.4fr) minmax(320px,1fr);gap:28px;align-items:start">
		<div>
			<h2><?php esc_html_e( 'Publicados', 'aula-virtual' ); ?></h2>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Anuncio', 'aula-virtual' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Destinatarios', 'aula-virtual' ); ?></th>
						<th scope="col" style="width:130px"><?php esc_html_e( 'Fecha', 'aula-virtual' ); ?></th>
						<th scope="col" style="width:60px"></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $items ) ) : ?>
					<tr><td colspan="4"><?php esc_html_e( 'Todavía no hay anuncios.', 'aula-virtual' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $items as $av_item ) : ?>
						<tr>
							<td>
								<strong><?php echo esc_html( (string) $av_item['title'] ); ?></strong>
								<?php echo (int) $av_item['send_email'] ? ' <span class="dashicons dashicons-email" title="' . esc_attr__( 'Enviado por correo', 'aula-virtual' ) . '"></span>' : ''; ?>
								<div class="description"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( (string) $av_item['content'] ), 20 ) ); ?></div>
							</td>
							<td>
								<?php echo esc_html( get_the_title( (int) $av_item['course_id'] ) ); ?><br>
								<small><?php echo esc_html( (int) $av_item['edition_id'] > 0 ? __( 'Solo una edición', 'aula-virtual' ) : __( 'Todas las ediciones', 'aula-virtual' ) ); ?></small>
							</td>
							<td><?php echo esc_html( mysql2date( (string) get_option( 'date_format' ), (string) $av_item['created_at'] ) ); ?></td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<input type="hidden" name="action" value="<?php echo esc_attr( AnnouncementsScreen::ACTION_DELETE ); ?>">
									<input type="hidden" name="announcement_id" value="<?php echo esc_attr( (string) (int) $av_item['id'] ); ?>">
									<?php wp_nonce_field( AnnouncementsScreen::ACTION_DELETE ); ?>
									<button type="submit" class="button-link-delete" aria-label="<?php esc_attr_e( 'Eliminar', 'aula-virtual' ); ?>">&times;</button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>

		<div>
			<h2><?php esc_html_e( 'Nuevo anuncio', 'aula-virtual' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( AnnouncementsScreen::ACTION_CREATE ); ?>">
				<?php wp_nonce_field( AnnouncementsScreen::ACTION_CREATE ); ?>
				<p>
					<label for="av-target"><?php esc_html_e( 'Para', 'aula-virtual' ); ?></label><br>
					<select name="target" id="av-target" class="widefat" required>
						<?php foreach ( $av_courses as $av_course_id => $av_course_title ) : ?>
							<optgroup label="<?php echo esc_attr( $av_course_title ); ?>">
								<option value="course:<?php echo esc_attr( (string) $av_course_id ); ?>"><?php esc_html_e( 'Todas las ediciones', 'aula-virtual' ); ?></option>
								<?php foreach ( $editions as $av_edition ) : ?>
									<?php if ( (int) $av_edition['course_id'] !== $av_course_id ) { continue; } ?>
									<option value="edition:<?php echo esc_attr( (string) (int) $av_edition['id'] ); ?>"><?php echo esc_html( (string) $av_edition['name'] ); ?></option>
								<?php endforeach; ?>
							</optgroup>
						<?php endforeach; ?>
					</select>
				</p>
				<p><input type="text" name="title" class="widefat" required placeholder="<?php esc_attr_e( 'Título', 'aula-virtual' ); ?>"></p>
				<?php wp_editor( '', 'av-announcement-content', array( 'textarea_name' => 'content', 'textarea_rows' => 8, 'media_buttons' => false, 'teeny' => true ) ); ?>
				<p style="margin-top:10px"><label><input type="checkbox" name="send_email" value="1" checked> <?php esc_html_e( 'Enviar también por correo a los alumnos matriculados', 'aula-virtual' ); ?></label></p>
				<p class="description"><?php esc_html_e( 'El correo usa la plantilla "Nuevo anuncio" de Aula Virtual > Emails. El anuncio queda visible en el campus de cada alumno.', 'aula-virtual' ); ?></p>
				<?php submit_button( __( 'Publicar', 'aula-virtual' ) ); ?>
			</form>
		</div>
	</div>
</div>
