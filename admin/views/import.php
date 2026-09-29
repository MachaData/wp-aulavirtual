<?php
/**
 * Student import wizard.
 *
 * @package SIQA\AulaVirtual
 *
 * @var string                                    $step
 * @var array{type: string, message: string}|null $notice
 * @var array<int, array<string, mixed>>          $editions
 * @var array<string, mixed>|null                 $edition
 * @var string                                    $token
 * @var array<string, mixed>                      $stash
 * @var array<string, mixed>                      $review
 * @var array<string, mixed>                      $job
 * @var array<int, array<string, mixed>>          $errors
 */

use SIQA\AulaVirtual\Admin\AdminMenu;
use SIQA\AulaVirtual\Admin\ImportScreen;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$av_post   = admin_url( 'admin-post.php' );
$av_fields = array(
	'email'      => __( 'Correo (obligatorio)', 'aula-virtual' ),
	'first_name' => __( 'Nombre', 'aula-virtual' ),
	'last_name'  => __( 'Apellido', 'aula-virtual' ),
	'phone'      => __( 'Teléfono', 'aula-virtual' ),
	'document'   => __( 'Documento', 'aula-virtual' ),
);
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Importar alumnos', 'aula-virtual' ); ?></h1>

	<?php require AV_PATH . 'admin/views/notice.php'; ?>

	<p class="description">
		<?php
		$av_steps = array( 'upload' => __( '1. Archivo', 'aula-virtual' ), 'map' => __( '2. Columnas', 'aula-virtual' ), 'review' => __( '3. Revisión', 'aula-virtual' ), 'run' => __( '4. Importar', 'aula-virtual' ) );
		foreach ( $av_steps as $av_key => $av_label ) {
			echo $av_key === $step ? '<strong>' . esc_html( $av_label ) . '</strong>' : esc_html( $av_label );
			echo 'run' === $av_key ? '' : ' &rarr; ';
		}
		?>
	</p>

	<?php if ( 'upload' === $step ) : ?>
		<form method="post" action="<?php echo esc_url( $av_post ); ?>" enctype="multipart/form-data">
			<input type="hidden" name="action" value="<?php echo esc_attr( ImportScreen::ACTION_UPLOAD ); ?>">
			<?php wp_nonce_field( ImportScreen::ACTION_UPLOAD ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="av-edition"><?php esc_html_e( 'Edición destino', 'aula-virtual' ); ?></label></th>
					<td>
						<select name="edition_id" id="av-edition" required>
							<option value=""><?php esc_html_e( 'Elige una edición', 'aula-virtual' ); ?></option>
							<?php foreach ( $editions as $av_edition ) : ?>
								<option value="<?php echo esc_attr( (string) (int) $av_edition['id'] ); ?>"><?php echo esc_html( get_the_title( (int) $av_edition['course_id'] ) . ' — ' . $av_edition['name'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="av-file"><?php esc_html_e( 'Archivo', 'aula-virtual' ); ?></label></th>
					<td>
						<input type="file" name="import_file" id="av-file" accept=".csv,.xlsx,.txt" required>
						<p class="description"><?php esc_html_e( 'CSV o XLSX con una fila de cabecera. Columnas sugeridas: email, nombre, apellido, teléfono, documento. Máximo 5000 filas.', 'aula-virtual' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Subir y continuar', 'aula-virtual' ) ); ?>
		</form>

	<?php elseif ( 'map' === $step ) : ?>
		<h2><?php echo esc_html( sprintf( /* translators: 1: file name, 2: row count. */ __( '%1$s: %2$d filas', 'aula-virtual' ), $stash['filename'], count( $stash['rows'] ) ) ); ?></h2>
		<form method="post" action="<?php echo esc_url( $av_post ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( ImportScreen::ACTION_MAP ); ?>">
			<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
			<?php wp_nonce_field( ImportScreen::ACTION_MAP ); ?>
			<table class="form-table" role="presentation">
				<?php foreach ( $av_fields as $av_field => $av_label ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $av_label ); ?></th>
						<td>
							<select name="map[<?php echo esc_attr( $av_field ); ?>]">
								<option value=""><?php esc_html_e( '— no importar —', 'aula-virtual' ); ?></option>
								<?php foreach ( $stash['headers'] as $av_i => $av_header ) : ?>
									<option value="<?php echo esc_attr( (string) $av_i ); ?>" <?php selected( $stash['mapping'][ $av_field ] ?? -1, $av_i ); ?>><?php echo esc_html( '' === $av_header ? sprintf( /* translators: %d: column number. */ __( 'Columna %d', 'aula-virtual' ), $av_i + 1 ) : $av_header ); ?></option>
								<?php endforeach; ?>
							</select>
							<span class="description"><?php echo esc_html( isset( $stash['rows'][0][ $stash['mapping'][ $av_field ] ?? -1 ] ) ? __( 'Ejemplo:', 'aula-virtual' ) . ' ' . $stash['rows'][0][ $stash['mapping'][ $av_field ] ] : '' ); ?></span>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
			<?php submit_button( __( 'Revisar', 'aula-virtual' ) ); ?>
		</form>

	<?php elseif ( 'review' === $step ) : ?>
		<h2><?php echo esc_html( null === $edition ? '' : get_the_title( (int) $edition['course_id'] ) . ' — ' . $edition['name'] ); ?></h2>
		<table class="widefat striped" style="max-width:560px">
			<tbody>
				<tr><td><?php esc_html_e( 'Filas válidas', 'aula-virtual' ); ?></td><td><strong><?php echo esc_html( (string) count( $review['valid'] ) ); ?></strong></td></tr>
				<tr><td><?php esc_html_e( 'Usuarios nuevos (se creará la cuenta)', 'aula-virtual' ); ?></td><td><?php echo esc_html( (string) $review['new_users'] ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Usuarios existentes', 'aula-virtual' ); ?></td><td><?php echo esc_html( (string) $review['existing_users'] ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Ya matriculados en esta edición (se omiten)', 'aula-virtual' ); ?></td><td><?php echo esc_html( (string) $review['already_enrolled'] ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Repetidos en el archivo (se omiten)', 'aula-virtual' ); ?></td><td><?php echo esc_html( (string) count( $review['duplicates'] ) ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Filas inválidas (se omiten)', 'aula-virtual' ); ?></td><td><?php echo esc_html( (string) count( $review['invalid'] ) ); ?></td></tr>
			</tbody>
		</table>

		<?php if ( ! empty( $review['invalid'] ) ) : ?>
			<h3><?php esc_html_e( 'Filas inválidas', 'aula-virtual' ); ?></h3>
			<table class="widefat striped" style="max-width:760px">
				<thead><tr><th><?php esc_html_e( 'Línea', 'aula-virtual' ); ?></th><th><?php esc_html_e( 'Correo', 'aula-virtual' ); ?></th><th><?php esc_html_e( 'Motivo', 'aula-virtual' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( array_slice( $review['invalid'], 0, 50 ) as $av_row ) : ?>
					<tr><td><?php echo esc_html( (string) $av_row['line'] ); ?></td><td><?php echo esc_html( (string) $av_row['email'] ); ?></td><td><?php echo esc_html( (string) $av_row['reason'] ); ?></td></tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( $av_post ); ?>" style="margin-top:16px">
			<input type="hidden" name="action" value="<?php echo esc_attr( ImportScreen::ACTION_CONFIRM ); ?>">
			<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
			<?php wp_nonce_field( ImportScreen::ACTION_CONFIRM ); ?>
			<p><label><input type="checkbox" name="send_welcome" value="1"> <?php esc_html_e( 'Enviar el correo de bienvenida a cada alumno importado (con el enlace para crear su contraseña)', 'aula-virtual' ); ?></label></p>
			<p class="description"><?php esc_html_e( 'Hasta ahora no se ha escrito nada ni se ha enviado ningún correo. Al confirmar, se procesan 200 alumnos por pulsación.', 'aula-virtual' ); ?></p>
			<?php submit_button( sprintf( /* translators: %d: rows. */ __( 'Importar %d alumnos', 'aula-virtual' ), count( $review['valid'] ) ), 'primary', 'submit', true, array() === $review['valid'] ? array( 'disabled' => 'disabled' ) : array() ); ?>
		</form>

	<?php elseif ( 'run' === $step ) : ?>
		<h2><?php echo esc_html( (string) $job['filename'] ); ?> &rarr; <?php echo esc_html( null === $edition ? '' : $edition['name'] ); ?></h2>
		<?php $av_done = 'completed' === $job['status']; ?>
		<table class="widefat striped" style="max-width:560px">
			<tbody>
				<tr><td><?php esc_html_e( 'Procesadas', 'aula-virtual' ); ?></td><td><?php echo esc_html( $job['processed_rows'] . ' / ' . $job['total_rows'] ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Cuentas creadas', 'aula-virtual' ); ?></td><td><?php echo esc_html( (string) $job['created_users'] ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Matriculados', 'aula-virtual' ); ?></td><td><?php echo esc_html( (string) $job['enrolled'] ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Omitidos (ya matriculados)', 'aula-virtual' ); ?></td><td><?php echo esc_html( (string) $job['skipped'] ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Fallidos', 'aula-virtual' ); ?></td><td><?php echo esc_html( (string) $job['failed'] ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Estado', 'aula-virtual' ); ?></td><td><?php echo esc_html( $av_done ? __( 'Completada', 'aula-virtual' ) : __( 'En curso', 'aula-virtual' ) ); ?></td></tr>
			</tbody>
		</table>

		<?php if ( ! $av_done ) : ?>
			<form method="post" action="<?php echo esc_url( $av_post ); ?>" style="margin-top:12px">
				<input type="hidden" name="action" value="<?php echo esc_attr( ImportScreen::ACTION_RUN ); ?>">
				<input type="hidden" name="job_id" value="<?php echo esc_attr( (string) (int) $job['id'] ); ?>">
				<?php wp_nonce_field( ImportScreen::ACTION_RUN ); ?>
				<?php submit_button( 0 === (int) $job['processed_rows'] ? __( 'Comenzar', 'aula-virtual' ) : __( 'Continuar con el siguiente lote', 'aula-virtual' ) ); ?>
			</form>
		<?php else : ?>
			<p><a class="button" href="<?php echo esc_url( AdminMenu::editions_url( array( 'edition' => (int) $job['edition_id'] ) ) ); ?>"><?php esc_html_e( 'Ver la edición', 'aula-virtual' ); ?></a>
			<a class="button" href="<?php echo esc_url( add_query_arg( 'page', ImportScreen::SLUG, admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Nueva importación', 'aula-virtual' ); ?></a></p>
		<?php endif; ?>

		<?php if ( ! empty( $errors ) ) : ?>
			<h3><?php esc_html_e( 'Errores', 'aula-virtual' ); ?></h3>
			<table class="widefat striped" style="max-width:760px">
				<thead><tr><th><?php esc_html_e( 'Línea', 'aula-virtual' ); ?></th><th><?php esc_html_e( 'Correo', 'aula-virtual' ); ?></th><th><?php esc_html_e( 'Error', 'aula-virtual' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $errors as $av_err ) : ?>
					<tr><td><?php echo esc_html( (string) ( $av_err['line'] ?? '' ) ); ?></td><td><?php echo esc_html( (string) ( $av_err['email'] ?? '' ) ); ?></td><td><?php echo esc_html( (string) ( $av_err['error'] ?? '' ) ); ?></td></tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	<?php endif; ?>
</div>
