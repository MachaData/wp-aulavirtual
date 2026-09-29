<?php
/**
 * Landing editor.
 *
 * @package SIQA\AulaVirtual
 *
 * @var array<string, array<string, mixed>> $landing
 * @var array<string, string>               $labels
 * @var array<string, string>               $button_types
 * @var string                              $field
 * @var int                                 $course_id
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Field name helper.
 *
 * @param string $section Section key.
 * @param string $key     Field key.
 * @return string
 */
$av_name = static fn( string $section, string $key ): string => $field . '[' . $section . '][' . $key . ']';

/**
 * Text input.
 */
$av_text = static function ( string $section, string $key, string $label, string $placeholder = '' ) use ( $landing, $av_name ): void {
	printf(
		'<p class="av-field"><label>%1$s<br><input type="text" class="widefat" name="%2$s" value="%3$s" placeholder="%4$s"></label></p>',
		esc_html( $label ),
		esc_attr( $av_name( $section, $key ) ),
		esc_attr( (string) ( $landing[ $section ][ $key ] ?? '' ) ),
		esc_attr( $placeholder )
	);
};

/**
 * Textarea (plain or HTML).
 */
$av_area = static function ( string $section, string $key, string $label, string $help = '', int $rows = 4 ) use ( $landing, $av_name ): void {
	printf(
		'<p class="av-field"><label>%1$s<br><textarea class="widefat" rows="%4$d" name="%2$s">%3$s</textarea></label>%5$s</p>',
		esc_html( $label ),
		esc_attr( $av_name( $section, $key ) ),
		esc_textarea( is_array( $landing[ $section ][ $key ] ?? null ) ? implode( "\n", $landing[ $section ][ $key ] ) : (string) ( $landing[ $section ][ $key ] ?? '' ) ),
		$rows,
		'' === $help ? '' : '<span class="description">' . esc_html( $help ) . '</span>'
	);
};

/**
 * Image picker.
 */
$av_image = static function ( string $section, string $key, string $label ) use ( $landing, $av_name ): void {
	$id  = (int) ( $landing[ $section ][ $key ] ?? 0 );
	$url = $id > 0 ? wp_get_attachment_image_url( $id, 'medium' ) : '';
	?>
	<div class="av-field av-image-field">
		<span class="av-field__label"><?php echo esc_html( $label ); ?></span>
		<input type="hidden" name="<?php echo esc_attr( $av_name( $section, $key ) ); ?>" value="<?php echo esc_attr( (string) $id ); ?>" class="av-image-field__id">
		<div class="av-image-field__preview"><?php echo is_string( $url ) && '' !== $url ? '<img src="' . esc_url( $url ) . '" alt="">' : ''; ?></div>
		<button type="button" class="button av-image-field__choose"><?php esc_html_e( 'Elegir imagen', 'aula-virtual' ); ?></button>
		<button type="button" class="button-link-delete av-image-field__remove" <?php echo $id > 0 ? '' : 'style="display:none"'; ?>><?php esc_html_e( 'Quitar', 'aula-virtual' ); ?></button>
	</div>
	<?php
};

/**
 * Button configuration.
 */
$av_button = static function ( string $section ) use ( $landing, $av_name, $button_types ): void {
	?>
	<div class="av-field av-button-field">
		<span class="av-field__label"><?php esc_html_e( 'Botón', 'aula-virtual' ); ?></span>
		<input type="text" name="<?php echo esc_attr( $av_name( $section, 'button_text' ) ); ?>" value="<?php echo esc_attr( (string) $landing[ $section ]['button_text'] ); ?>" placeholder="<?php esc_attr_e( 'Texto del botón', 'aula-virtual' ); ?>">
		<select name="<?php echo esc_attr( $av_name( $section, 'button_type' ) ); ?>" class="av-button-field__type">
			<?php foreach ( $button_types as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $landing[ $section ]['button_type'], $value ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<input type="url" name="<?php echo esc_attr( $av_name( $section, 'button_url' ) ); ?>" value="<?php echo esc_attr( (string) $landing[ $section ]['button_url'] ); ?>" placeholder="<?php esc_attr_e( 'URL (solo para enlace personalizado)', 'aula-virtual' ); ?>" class="av-button-field__url">
	</div>
	<?php
};

/**
 * Repeater of two-field rows.
 */
$av_rows = static function ( string $section, string $key, string $label, string $first_key, string $first_label, string $second_key, string $second_label ) use ( $landing, $field ): void {
	$rows = is_array( $landing[ $section ][ $key ] ?? null ) ? $landing[ $section ][ $key ] : array();
	$base = $field . '[' . $section . '][' . $key . ']';
	?>
	<div class="av-field av-repeater" data-base="<?php echo esc_attr( $base ); ?>" data-first="<?php echo esc_attr( $first_key ); ?>" data-second="<?php echo esc_attr( $second_key ); ?>">
		<span class="av-field__label"><?php echo esc_html( $label ); ?></span>
		<div class="av-repeater__rows">
			<?php foreach ( $rows as $i => $row ) : ?>
				<div class="av-repeater__row">
					<input type="text" name="<?php echo esc_attr( $base . '[' . (int) $i . '][' . $first_key . ']' ); ?>" value="<?php echo esc_attr( (string) ( $row[ $first_key ] ?? '' ) ); ?>" placeholder="<?php echo esc_attr( $first_label ); ?>">
					<textarea rows="2" name="<?php echo esc_attr( $base . '[' . (int) $i . '][' . $second_key . ']' ); ?>" placeholder="<?php echo esc_attr( $second_label ); ?>"><?php echo esc_textarea( (string) ( $row[ $second_key ] ?? '' ) ); ?></textarea>
					<button type="button" class="button-link-delete av-repeater__remove" aria-label="<?php esc_attr_e( 'Quitar', 'aula-virtual' ); ?>">&times;</button>
				</div>
			<?php endforeach; ?>
		</div>
		<button type="button" class="button av-repeater__add" data-first-label="<?php echo esc_attr( $first_label ); ?>" data-second-label="<?php echo esc_attr( $second_label ); ?>"><?php esc_html_e( 'Añadir', 'aula-virtual' ); ?></button>
	</div>
	<?php
};

/**
 * Section wrapper.
 */
$av_open = static function ( string $section, bool $open = false ) use ( $landing, $labels, $av_name ): void {
	?>
	<details class="av-landing-section" <?php echo $open ? 'open' : ''; ?>>
		<summary>
			<label class="av-landing-section__switch" onclick="event.stopPropagation()">
				<input type="hidden" name="<?php echo esc_attr( $av_name( $section, 'enabled' ) ); ?>" value="0">
				<input type="checkbox" name="<?php echo esc_attr( $av_name( $section, 'enabled' ) ); ?>" value="1" <?php checked( ! empty( $landing[ $section ]['enabled'] ) ); ?>>
			</label>
			<span class="av-landing-section__title"><?php echo esc_html( $labels[ $section ] ); ?></span>
			<span class="av-landing-section__hint"><?php esc_html_e( 'mostrar en la landing', 'aula-virtual' ); ?></span>
		</summary>
		<div class="av-landing-section__body">
	<?php
};
$av_close = static function (): void {
	echo '</div></details>';
};
?>
<div class="av-landing-editor">
	<p class="description">
		<?php esc_html_e( 'La landing se compone de estas secciones, en este orden. Apaga las que no necesites. Las fechas, horarios y botones de compra e inscripción salen de las ediciones abiertas del curso.', 'aula-virtual' ); ?>
		<a href="<?php echo esc_url( get_permalink( $course_id ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Ver landing', 'aula-virtual' ); ?></a>
	</p>

	<?php $av_open( 'hero', true ); ?>
		<?php $av_text( 'hero', 'kicker', __( 'Antetítulo', 'aula-virtual' ), __( 'Certificación oficial', 'aula-virtual' ) ); ?>
		<?php $av_text( 'hero', 'title', __( 'Título', 'aula-virtual' ) ); ?>
		<?php $av_text( 'hero', 'subtitle', __( 'Subtítulo', 'aula-virtual' ) ); ?>
		<?php $av_area( 'hero', 'text', __( 'Texto', 'aula-virtual' ), __( 'Admite HTML básico.', 'aula-virtual' ) ); ?>
		<?php $av_text( 'hero', 'video_url', __( 'Video (YouTube, Vimeo o MP4)', 'aula-virtual' ), 'https://' ); ?>
		<div class="av-grid-2">
			<?php $av_image( 'hero', 'image_id', __( 'Imagen principal (si no hay video)', 'aula-virtual' ) ); ?>
			<?php $av_image( 'hero', 'background_id', __( 'Imagen de fondo', 'aula-virtual' ) ); ?>
		</div>
		<?php $av_text( 'hero', 'bg_color', __( 'Color de fondo (hex)', 'aula-virtual' ), '#f3f4f6' ); ?>
		<?php $av_button( 'hero' ); ?>
	<?php $av_close(); ?>

	<?php $av_open( 'benefits' ); ?>
		<?php $av_text( 'benefits', 'title', __( 'Título', 'aula-virtual' ) ); ?>
		<?php $av_area( 'benefits', 'items', __( 'Beneficios', 'aula-virtual' ), __( 'Uno por línea.', 'aula-virtual' ), 6 ); ?>
		<?php $av_image( 'benefits', 'image_id', __( 'Imagen lateral', 'aula-virtual' ) ); ?>
	<?php $av_close(); ?>

	<?php $av_open( 'content' ); ?>
		<?php $av_text( 'content', 'title', __( 'Título', 'aula-virtual' ) ); ?>
		<?php $av_area( 'content', 'text', __( 'Texto introductorio', 'aula-virtual' ), __( 'Admite HTML básico.', 'aula-virtual' ) ); ?>
		<?php $av_text( 'content', 'video_url', __( 'Video (opcional)', 'aula-virtual' ), 'https://' ); ?>
		<p class="av-field"><label><input type="hidden" name="<?php echo esc_attr( $av_name( 'content', 'from_curriculum' ) ); ?>" value="0"><input type="checkbox" name="<?php echo esc_attr( $av_name( 'content', 'from_curriculum' ) ); ?>" value="1" <?php checked( ! empty( $landing['content']['from_curriculum'] ) ); ?>> <?php esc_html_e( 'Mostrar el temario de la próxima edición (módulos y sesiones)', 'aula-virtual' ); ?></label></p>
		<?php $av_area( 'content', 'items', __( 'Temario manual', 'aula-virtual' ), __( 'Uno por línea. Se usa si no hay temario en la edición o si desactivas la opción anterior.', 'aula-virtual' ), 6 ); ?>
	<?php $av_close(); ?>

	<?php $av_open( 'instructor' ); ?>
		<?php $av_text( 'instructor', 'title', __( 'Título', 'aula-virtual' ) ); ?>
		<?php $av_text( 'instructor', 'name', __( 'Nombre', 'aula-virtual' ) ); ?>
		<?php $av_area( 'instructor', 'bio', __( 'Biografía', 'aula-virtual' ), __( 'Admite HTML básico.', 'aula-virtual' ), 5 ); ?>
		<?php $av_image( 'instructor', 'image_id', __( 'Foto', 'aula-virtual' ) ); ?>
		<?php $av_rows( 'instructor', 'links', __( 'Redes y enlaces', 'aula-virtual' ), 'label', __( 'Etiqueta', 'aula-virtual' ), 'url', 'https://' ); ?>
	<?php $av_close(); ?>

	<?php $av_open( 'info' ); ?>
		<?php $av_text( 'info', 'title', __( 'Título', 'aula-virtual' ) ); ?>
		<p class="av-field"><label><input type="hidden" name="<?php echo esc_attr( $av_name( 'info', 'from_editions' ) ); ?>" value="0"><input type="checkbox" name="<?php echo esc_attr( $av_name( 'info', 'from_editions' ) ); ?>" value="1" <?php checked( ! empty( $landing['info']['from_editions'] ) ); ?>> <?php esc_html_e( 'Mostrar una tarjeta por cada edición abierta, con fechas, horario y botones', 'aula-virtual' ); ?></label></p>
		<?php $av_rows( 'info', 'items', __( 'Datos adicionales', 'aula-virtual' ), 'label', __( 'Dato (Plataforma, Duración...)', 'aula-virtual' ), 'value', __( 'Valor', 'aula-virtual' ) ); ?>
	<?php $av_close(); ?>

	<?php $av_open( 'price' ); ?>
		<?php $av_text( 'price', 'title', __( 'Título', 'aula-virtual' ) ); ?>
		<div class="av-grid-2">
			<?php $av_text( 'price', 'price', __( 'Precio', 'aula-virtual' ), __( 'S/ 250 (vacío: usa el de la edición)', 'aula-virtual' ) ); ?>
			<?php $av_text( 'price', 'old_price', __( 'Precio tachado', 'aula-virtual' ), 'S/ 350' ); ?>
		</div>
		<?php $av_area( 'price', 'includes', __( 'Qué incluye', 'aula-virtual' ), __( 'Uno por línea.', 'aula-virtual' ), 5 ); ?>
		<?php $av_area( 'price', 'note', __( 'Nota', 'aula-virtual' ), __( 'Cuotas, garantía, medios de pago. Admite HTML básico.', 'aula-virtual' ), 2 ); ?>
		<?php $av_button( 'price' ); ?>
	<?php $av_close(); ?>

	<?php $av_open( 'faq' ); ?>
		<?php $av_text( 'faq', 'title', __( 'Título', 'aula-virtual' ) ); ?>
		<?php $av_rows( 'faq', 'items', __( 'Preguntas', 'aula-virtual' ), 'question', __( 'Pregunta', 'aula-virtual' ), 'answer', __( 'Respuesta', 'aula-virtual' ) ); ?>
	<?php $av_close(); ?>

	<?php $av_open( 'cta' ); ?>
		<?php $av_text( 'cta', 'title', __( 'Título', 'aula-virtual' ) ); ?>
		<?php $av_area( 'cta', 'text', __( 'Texto', 'aula-virtual' ), __( 'Admite HTML básico.', 'aula-virtual' ), 3 ); ?>
		<?php $av_button( 'cta' ); ?>
		<?php $av_text( 'cta', 'whatsapp', __( 'WhatsApp (número con código de país)', 'aula-virtual' ), '51999999999' ); ?>
		<?php $av_image( 'cta', 'background_id', __( 'Imagen de fondo', 'aula-virtual' ) ); ?>
		<?php $av_text( 'cta', 'bg_color', __( 'Color de fondo (hex)', 'aula-virtual' ), '#1d4ed8' ); ?>
	<?php $av_close(); ?>
</div>
