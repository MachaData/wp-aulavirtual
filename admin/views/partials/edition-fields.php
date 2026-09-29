<?php
/**
 * Edition fields, shared by the "new edition" form and the Settings tab.
 *
 * @package SIQA\AulaVirtual
 *
 * @var array<string, mixed>|null $av_values  Current edition (null when creating).
 * @var array<int, WP_Post>       $av_courses Courses to choose from (only when creating).
 */

use SIQA\AulaVirtual\Editions\EditionService;
use SIQA\AulaVirtual\Editions\EditionStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$av_values = isset( $av_values ) && is_array( $av_values ) ? $av_values : null;
$av_v      = static function ( string $key, string $fallback = '' ) use ( $av_values ): string {
	return null === $av_values || ! isset( $av_values[ $key ] ) ? $fallback : (string) $av_values[ $key ];
};
$av_date   = static function ( string $key ) use ( $av_v ): string {
	$value = $av_v( $key );

	return '' === $value || str_starts_with( $value, '0000' ) ? '' : substr( $value, 0, 10 );
};
$av_tz     = $av_v( 'timezone', wp_timezone_string() );
?>
<div class="av-form-section">
	<h3><?php esc_html_e( 'Identificación', 'aula-virtual' ); ?></h3>
	<table class="form-table" role="presentation">
		<?php if ( null === $av_values ) : ?>
			<tr>
				<th scope="row"><label for="av-course"><?php esc_html_e( 'Curso', 'aula-virtual' ); ?></label></th>
				<td>
					<select name="course_id" id="av-course" required>
						<?php foreach ( $av_courses as $av_course ) : ?>
							<option value="<?php echo esc_attr( (string) $av_course->ID ); ?>"><?php echo esc_html( get_the_title( $av_course ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
		<?php else : ?>
			<input type="hidden" name="course_id" value="<?php echo esc_attr( $av_v( 'course_id' ) ); ?>">
		<?php endif; ?>
		<tr>
			<th scope="row"><label for="av-name"><?php esc_html_e( 'Nombre', 'aula-virtual' ); ?></label></th>
			<td>
				<input type="text" name="name" id="av-name" class="regular-text" required value="<?php echo esc_attr( $av_v( 'name' ) ); ?>" placeholder="<?php esc_attr_e( 'Setiembre 2026', 'aula-virtual' ); ?>">
				<p class="description"><?php esc_html_e( 'Cómo se llama esta cohorte. Lo ven los alumnos en el campus y en los correos.', 'aula-virtual' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="av-code"><?php esc_html_e( 'Código', 'aula-virtual' ); ?></label></th>
			<td>
				<input type="text" name="code" id="av-code" class="regular-text code" value="<?php echo esc_attr( $av_v( 'code' ) ); ?>" placeholder="<?php esc_attr_e( 'Se genera solo si lo dejas vacío', 'aula-virtual' ); ?>">
				<p class="description"><?php esc_html_e( 'Aparece en la dirección del campus y del enlace de inscripción. Solo minúsculas, números y guiones.', 'aula-virtual' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="av-status"><?php esc_html_e( 'Estado', 'aula-virtual' ); ?></label></th>
			<td>
				<select name="status" id="av-status">
					<?php foreach ( EditionStatus::labels() as $av_value => $av_label ) : ?>
						<option value="<?php echo esc_attr( $av_value ); ?>" <?php selected( $av_v( 'status', EditionStatus::OPEN ), $av_value ); ?>><?php echo esc_html( $av_label ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Solo las ediciones próximas, con matrícula abierta o en curso aceptan inscripciones.', 'aula-virtual' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="av-modality"><?php esc_html_e( 'Modalidad', 'aula-virtual' ); ?></label></th>
			<td>
				<select name="modality" id="av-modality">
					<?php foreach ( EditionService::modalities() as $av_value => $av_label ) : ?>
						<option value="<?php echo esc_attr( $av_value ); ?>" <?php selected( $av_v( 'modality' ), $av_value ); ?>><?php echo esc_html( $av_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
	</table>
</div>

<div class="av-form-section">
	<h3><?php esc_html_e( 'Fechas y horario', 'aula-virtual' ); ?></h3>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Duración del curso', 'aula-virtual' ); ?></th>
			<td>
				<div class="av-form-row">
					<label><?php esc_html_e( 'Inicio', 'aula-virtual' ); ?> <input type="date" name="start_date" value="<?php echo esc_attr( $av_date( 'start_date' ) ); ?>"></label>
					<label><?php esc_html_e( 'Fin', 'aula-virtual' ); ?> <input type="date" name="end_date" value="<?php echo esc_attr( $av_date( 'end_date' ) ); ?>"></label>
				</div>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Acceso al campus', 'aula-virtual' ); ?></th>
			<td>
				<div class="av-form-row">
					<label><?php esc_html_e( 'Desde', 'aula-virtual' ); ?> <input type="date" name="access_start" value="<?php echo esc_attr( $av_date( 'access_start' ) ); ?>"></label>
					<label><?php esc_html_e( 'Hasta', 'aula-virtual' ); ?> <input type="date" name="access_end" value="<?php echo esc_attr( $av_date( 'access_end' ) ); ?>"></label>
				</div>
				<p class="description"><?php esc_html_e( 'Vacío significa sin límite: el alumno entra en cuanto se matricula y conserva el acceso.', 'aula-virtual' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Días y horario', 'aula-virtual' ); ?></th>
			<td>
				<div class="av-form-row">
					<input type="text" name="schedule_days" class="regular-text" value="<?php echo esc_attr( $av_v( 'schedule_days' ) ); ?>" placeholder="<?php esc_attr_e( 'Martes y jueves', 'aula-virtual' ); ?>" aria-label="<?php esc_attr_e( 'Días', 'aula-virtual' ); ?>">
					<input type="text" name="schedule_time" value="<?php echo esc_attr( $av_v( 'schedule_time' ) ); ?>" placeholder="<?php esc_attr_e( '19:00 - 21:00', 'aula-virtual' ); ?>" aria-label="<?php esc_attr_e( 'Horario', 'aula-virtual' ); ?>">
				</div>
				<p class="description"><?php esc_html_e( 'Texto libre. Se muestra en la landing, en el formulario de inscripción y en el correo de bienvenida.', 'aula-virtual' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="av-timezone"><?php esc_html_e( 'Zona horaria', 'aula-virtual' ); ?></label></th>
			<td>
				<select name="timezone" id="av-timezone">
					<?php echo wp_timezone_choice( $av_tz, get_user_locale() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core returns escaped <option> markup. ?>
				</select>
				<p class="description"><?php esc_html_e( 'Las clases en vivo se programan y se muestran en esta zona horaria.', 'aula-virtual' ); ?></p>
			</td>
		</tr>
	</table>
</div>

<div class="av-form-section">
	<h3><?php esc_html_e( 'Venta y cupo', 'aula-virtual' ); ?></h3>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="av-price"><?php esc_html_e( 'Precio que se muestra', 'aula-virtual' ); ?></label></th>
			<td>
				<input type="text" name="price_display" id="av-price" value="<?php echo esc_attr( $av_v( 'price_display' ) ); ?>" placeholder="<?php esc_attr_e( 'S/ 250', 'aula-virtual' ); ?>">
				<p class="description"><?php esc_html_e( 'Solo informativo. El cobro lo hace el producto de WooCommerce.', 'aula-virtual' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="av-product"><?php esc_html_e( 'Producto de WooCommerce', 'aula-virtual' ); ?></label></th>
			<td>
				<input type="number" name="product_id" id="av-product" min="0" class="small-text" style="width:120px" value="<?php echo esc_attr( '0' === $av_v( 'product_id', '0' ) ? '' : $av_v( 'product_id' ) ); ?>" placeholder="<?php esc_attr_e( 'ID', 'aula-virtual' ); ?>">
				<p class="description"><?php esc_html_e( 'Con producto, la edición es de pago: al aprobar una inscripción se envía el enlace de pago y la matrícula se crea al confirmarse. Sin producto, la edición es gratuita y aprobar matricula al instante.', 'aula-virtual' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="av-capacity"><?php esc_html_e( 'Cupo', 'aula-virtual' ); ?></label></th>
			<td>
				<input type="number" name="capacity" id="av-capacity" min="0" class="small-text" value="<?php echo esc_attr( $av_v( 'capacity', '0' ) ); ?>">
				<p class="description"><?php esc_html_e( '0 significa plazas sin límite.', 'aula-virtual' ); ?></p>
			</td>
		</tr>
	</table>
</div>
