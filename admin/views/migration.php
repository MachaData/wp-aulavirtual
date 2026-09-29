<?php
/**
 * Tutor LMS migration.
 *
 * @package SIQA\AulaVirtual
 *
 * @var bool                                      $available
 * @var array<int, array<string, mixed>>          $courses
 * @var array<int, array<string, mixed>>          $map
 * @var array<int, WP_Post>                       $targets
 * @var array{type: string, message: string}|null $notice
 * @var array<string, int|string>|null            $report
 */

use SIQA\AulaVirtual\Admin\AdminMenu;
use SIQA\AulaVirtual\Admin\MigrationScreen;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Migrar desde Tutor LMS', 'aula-virtual' ); ?></h1>

	<?php require AV_PATH . 'admin/views/notice.php'; ?>

	<?php if ( ! $available ) : ?>
		<p><?php esc_html_e( 'No se detectan cursos de Tutor LMS en esta instalación.', 'aula-virtual' ); ?></p>
		<?php return; ?>
	<?php endif; ?>

	<div class="notice notice-info inline">
		<p><strong><?php esc_html_e( 'Qué hace la migración', 'aula-virtual' ); ?></strong></p>
		<ul style="list-style:disc;margin-left:20px">
			<li><?php esc_html_e( 'Crea un curso de Aula Virtual por cada curso de Tutor, con su landing, imagen, nivel, beneficios y categorías.', 'aula-virtual' ); ?></li>
			<li><?php esc_html_e( 'Crea una edición "Alumnos actuales" en estado En curso, con el producto de WooCommerce que tuviera el curso.', 'aula-virtual' ); ?></li>
			<li><?php esc_html_e( 'Copia los temas como módulos y las lecciones con su video (YouTube, Vimeo, Bunny, URL, incrustado), contenido y adjuntos como materiales. Los quizzes y tareas de Tutor no se migran.', 'aula-virtual' ); ?></li>
			<li><?php esc_html_e( 'Si en Tutor tienes varias cohortes como cursos separados (G1, G3, G4...), migra la primera como curso nuevo y las demás como "Edición de" ese curso.', 'aula-virtual' ); ?></li>
			<li><?php esc_html_e( 'Matricula a cada alumno conservando su fecha original, sus lecciones completadas y si terminó el curso.', 'aula-virtual' ); ?></li>
			<li><strong><?php esc_html_e( 'No envía ningún correo, no modifica ni borra nada de Tutor, y se puede repetir sin duplicar.', 'aula-virtual' ); ?></strong></li>
		</ul>
		<p><?php esc_html_e( 'Los alumnos se procesan en lotes de 300 por pulsación. Conviene hacerlo primero en un sitio de pruebas.', 'aula-virtual' ); ?></p>
	</div>

	<?php if ( is_array( $report ) ) : ?>
		<h2><?php esc_html_e( 'Resultado del último lote', 'aula-virtual' ); ?></h2>
		<table class="widefat striped" style="max-width:560px">
			<tbody>
				<tr><td><?php esc_html_e( 'Curso creado', 'aula-virtual' ); ?></td><td><?php echo esc_html( (string) $report['course_created'] ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Edición creada', 'aula-virtual' ); ?></td><td><?php echo esc_html( (string) $report['edition_created'] ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Módulos', 'aula-virtual' ); ?></td><td><?php echo esc_html( (string) $report['modules'] ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Lecciones', 'aula-virtual' ); ?></td><td><?php echo esc_html( (string) $report['lessons'] ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Alumnos matriculados', 'aula-virtual' ); ?></td><td><?php echo esc_html( (string) $report['enrolled'] ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Alumnos omitidos (ya migrados o sin usuario)', 'aula-virtual' ); ?></td><td><?php echo esc_html( (string) $report['skipped'] ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Lecciones completadas copiadas', 'aula-virtual' ); ?></td><td><?php echo esc_html( (string) $report['progress_rows'] ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Cursos terminados', 'aula-virtual' ); ?></td><td><?php echo esc_html( (string) $report['completions'] ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Materiales (adjuntos) copiados', 'aula-virtual' ); ?></td><td><?php echo esc_html( (string) ( $report['materials'] ?? 0 ) ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Alumnos pendientes', 'aula-virtual' ); ?></td><td><?php echo esc_html( (string) $report['remaining'] ); ?></td></tr>
			</tbody>
		</table>
		<?php if ( ! empty( $report['edition_id'] ) ) : ?>
			<p><a href="<?php echo esc_url( AdminMenu::editions_url( array( 'edition' => (int) $report['edition_id'] ) ) ); ?>"><?php esc_html_e( 'Ver la edición migrada', 'aula-virtual' ); ?></a></p>
		<?php endif; ?>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Cursos en Tutor LMS', 'aula-virtual' ); ?></h2>
	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Curso', 'aula-virtual' ); ?></th>
				<th scope="col" style="width:90px"><?php esc_html_e( 'Lecciones', 'aula-virtual' ); ?></th>
				<th scope="col" style="width:90px"><?php esc_html_e( 'Alumnos', 'aula-virtual' ); ?></th>
				<th scope="col" style="width:100px"><?php esc_html_e( 'Terminaron', 'aula-virtual' ); ?></th>
				<th scope="col" style="width:200px"><?php esc_html_e( 'Estado', 'aula-virtual' ); ?></th>
				<th scope="col" style="width:200px"></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $courses as $av_course ) : ?>
			<?php $av_entry = $map[ $av_course['id'] ] ?? null; ?>
			<tr>
				<td>
					<strong><?php echo esc_html( (string) $av_course['title'] ); ?></strong>
					<div class="row-actions"><span>Tutor #<?php echo esc_html( (string) $av_course['id'] ); ?> &middot; <?php echo esc_html( (string) $av_course['status'] ); ?><?php echo (int) $av_course['product_id'] > 0 ? ' &middot; ' . esc_html__( 'producto', 'aula-virtual' ) . ' #' . esc_html( (string) $av_course['product_id'] ) : ''; ?></span></div>
				</td>
				<td><?php echo esc_html( (string) $av_course['lessons'] ); ?></td>
				<td><?php echo esc_html( (string) $av_course['enrolled'] ); ?></td>
				<td><?php echo esc_html( (string) $av_course['completed'] ); ?></td>
				<td>
					<?php if ( null === $av_entry ) : ?>
						<?php esc_html_e( 'No migrado', 'aula-virtual' ); ?>
					<?php else : ?>
						<a href="<?php echo esc_url( AdminMenu::editions_url( array( 'edition' => (int) $av_entry['edition_id'] ) ) ); ?>"><?php esc_html_e( 'Migrado: ver edición', 'aula-virtual' ); ?></a>
					<?php endif; ?>
				</td>
				<td>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="<?php echo esc_attr( MigrationScreen::ACTION_MIGRATE ); ?>">
						<input type="hidden" name="tutor_course_id" value="<?php echo esc_attr( (string) $av_course['id'] ); ?>">
						<?php wp_nonce_field( MigrationScreen::ACTION_MIGRATE ); ?>
						<?php if ( null === $av_entry && ! empty( $targets ) ) : ?>
							<select name="target_course_id" style="max-width:180px;margin-bottom:4px">
								<option value="0"><?php esc_html_e( 'Como curso nuevo', 'aula-virtual' ); ?></option>
								<?php foreach ( $targets as $av_target ) : ?>
									<option value="<?php echo esc_attr( (string) $av_target->ID ); ?>"><?php echo esc_html( sprintf( /* translators: %s: course title. */ __( 'Edición de: %s', 'aula-virtual' ), get_the_title( $av_target ) ) ); ?></option>
								<?php endforeach; ?>
							</select>
						<?php endif; ?>
						<button type="submit" class="button <?php echo null === $av_entry ? 'button-primary' : ''; ?>">
							<?php echo esc_html( null === $av_entry ? __( 'Migrar', 'aula-virtual' ) : __( 'Reprocesar alumnos', 'aula-virtual' ) ); ?>
						</button>
					</form>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
