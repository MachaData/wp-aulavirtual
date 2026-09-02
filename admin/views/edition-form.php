<?php
/**
 * New edition form.
 *
 * @package SIQA\AulaVirtual
 *
 * @var array<int, WP_Post>                       $courses
 * @var array{type: string, message: string}|null $notice
 */

use SIQA\AulaVirtual\Admin\AdminMenu;
use SIQA\AulaVirtual\Admin\EditionsScreen;
use SIQA\AulaVirtual\Editions\EditionService;
use SIQA\AulaVirtual\Editions\EditionStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Nueva edicion', 'aula-virtual' ); ?></h1>

	<?php require AV_PATH . 'admin/views/notice.php'; ?>

	<?php if ( empty( $courses ) ) : ?>
		<div class="notice notice-warning">
			<p>
				<?php esc_html_e( 'Primero crea un curso: la edicion es una cohorte de un curso existente.', 'aula-virtual' ); ?>
				<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=av_course' ) ); ?>">
					<?php esc_html_e( 'Crear curso', 'aula-virtual' ); ?>
				</a>
			</p>
		</div>
		<?php return; ?>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="<?php echo esc_attr( EditionsScreen::ACTION_SAVE_EDITION ); ?>">
		<?php wp_nonce_field( EditionsScreen::ACTION_SAVE_EDITION ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="av-course"><?php esc_html_e( 'Curso', 'aula-virtual' ); ?></label></th>
				<td>
					<select name="course_id" id="av-course" required>
						<?php foreach ( $courses as $av_course ) : ?>
							<option value="<?php echo esc_attr( (string) $av_course->ID ); ?>">
								<?php echo esc_html( get_the_title( $av_course ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="av-name"><?php esc_html_e( 'Nombre', 'aula-virtual' ); ?></label></th>
				<td>
					<input type="text" name="name" id="av-name" class="regular-text" required
						placeholder="<?php esc_attr_e( 'Julio 2027', 'aula-virtual' ); ?>">
					<p class="description"><?php esc_html_e( 'Como identificas la cohorte internamente.', 'aula-virtual' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="av-code"><?php esc_html_e( 'Codigo', 'aula-virtual' ); ?></label></th>
				<td>
					<input type="text" name="code" id="av-code" class="regular-text">
					<p class="description"><?php esc_html_e( 'Opcional. Si lo dejas vacio se genera a partir del curso y el nombre.', 'aula-virtual' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="av-modality"><?php esc_html_e( 'Modalidad', 'aula-virtual' ); ?></label></th>
				<td>
					<select name="modality" id="av-modality">
						<?php foreach ( EditionService::modalities() as $av_value => $av_label ) : ?>
							<option value="<?php echo esc_attr( $av_value ); ?>"><?php echo esc_html( $av_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="av-status"><?php esc_html_e( 'Estado', 'aula-virtual' ); ?></label></th>
				<td>
					<select name="status" id="av-status">
						<?php foreach ( EditionStatus::labels() as $av_value => $av_label ) : ?>
							<option value="<?php echo esc_attr( $av_value ); ?>"
								<?php selected( EditionStatus::OPEN, $av_value ); ?>>
								<?php echo esc_html( $av_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Solo las ediciones proximas, abiertas o en curso admiten matriculas.', 'aula-virtual' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="av-start"><?php esc_html_e( 'Inicio y fin', 'aula-virtual' ); ?></label></th>
				<td>
					<input type="date" name="start_date" id="av-start">
					<input type="date" name="end_date">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="av-access-start"><?php esc_html_e( 'Ventana de acceso', 'aula-virtual' ); ?></label></th>
				<td>
					<input type="date" name="access_start" id="av-access-start">
					<input type="date" name="access_end">
					<p class="description"><?php esc_html_e( 'Desde cuando y hasta cuando el alumno puede entrar. Vacio significa sin limite.', 'aula-virtual' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="av-capacity"><?php esc_html_e( 'Cupo', 'aula-virtual' ); ?></label></th>
				<td>
					<input type="number" name="capacity" id="av-capacity" min="0" value="0" class="small-text">
					<p class="description"><?php esc_html_e( '0 significa sin limite de plazas.', 'aula-virtual' ); ?></p>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Crear edicion', 'aula-virtual' ) ); ?>
		<a href="<?php echo esc_url( AdminMenu::editions_url() ); ?>"><?php esc_html_e( 'Cancelar', 'aula-virtual' ); ?></a>
	</form>
</div>
