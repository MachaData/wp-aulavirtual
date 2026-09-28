<?php
/**
 * Tutor LMS migration.
 *
 * @package SIQA\AulaVirtual
 *
 * @var bool                                      $available
 * @var array<int, array<string, mixed>>          $courses
 * @var array<int, array<string, mixed>>          $map
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
		<p><?php esc_html_e( 'No se detectan cursos de Tutor LMS en esta instalacion.', 'aula-virtual' ); ?></p>
		<?php return; ?>
	<?php endif; ?>

	<div class="notice notice-info inline">
		<p><strong><?php esc_html_e( 'Que hace la migracion', 'aula-virtual' ); ?></strong></p>
		<ul style="list-style:disc;margin-left:20px">
			<li><?php esc_html_e( 'Crea un curso de Aula Virtual por cada curso de Tutor, con su landing, imagen, nivel, beneficios y categorias.', 'aula-virtual' ); ?></li>
			<li><?php esc_html_e( 'Crea una edicion "Alumnos actuales" en estado En curso, con el producto de WooCommerce que tuviera el curso.', 'aula-virtual' ); ?></li>
			<li><?php esc_html_e( 'Copia los temas como modulos y las lecciones con su video y contenido. Los quizzes y tareas de Tutor no se migran.', 'aula-virtual' ); ?></li>
			<li><?php esc_html_e( 'Matricula a cada alumno conservando su fecha original, sus lecciones completadas y si termino el curso.', 'aula-virtual' ); ?></li>
			<li><strong><?php esc_html_e( 'No envia ningun correo, no modifica ni borra nada de Tutor, y se puede repetir sin duplicar.', 'aula-virtual' ); ?></strong></li>
		</ul>
		<p><?php esc_html_e( 'Los alumnos se procesan en lotes de 300 por pulsacion. Conviene hacerlo primero en un sitio de pruebas.', 'aula-virtual' ); ?></p>
	</div>

	<?php if ( is_array( $report ) ) : ?>
		<h2><?php esc_html_e( 'Resultado del ultimo lote', 'aula-virtual' ); ?></h2>
		<table class="widefat striped" style="max-width:560px">
			<tbody>
				<tr><td><?php esc_html_e( 'Curso creado', 'aula-virtual' ); ?></td><td><?php echo esc_html( (string) $report['course_created'] ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Edicion creada', 'aula-virtual' ); ?></td><td><?php echo esc_html( (string) $report['edition_created'] ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Modulos', 'aula-virtual' ); ?></td><td><?php echo esc_html( (string) $report['modules'] ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Lecciones', 'aula-virtual' ); ?></td><td><?php echo esc_html( (string) $report['lessons'] ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Alumnos matriculados', 'aula-virtual' ); ?></td><td><?php echo esc_html( (string) $report['enrolled'] ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Alumnos omitidos (ya migrados o sin usuario)', 'aula-virtual' ); ?></td><td><?php echo esc_html( (string) $report['skipped'] ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Lecciones completadas copiadas', 'aula-virtual' ); ?></td><td><?php echo esc_html( (string) $report['progress_rows'] ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Cursos terminados', 'aula-virtual' ); ?></td><td><?php echo esc_html( (string) $report['completions'] ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Alumnos pendientes', 'aula-virtual' ); ?></td><td><?php echo esc_html( (string) $report['remaining'] ); ?></td></tr>
			</tbody>
		</table>
		<?php if ( ! empty( $report['edition_id'] ) ) : ?>
			<p><a href="<?php echo esc_url( AdminMenu::editions_url( array( 'edition' => (int) $report['edition_id'] ) ) ); ?>"><?php esc_html_e( 'Ver la edicion migrada', 'aula-virtual' ); ?></a></p>
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
						<a href="<?php echo esc_url( AdminMenu::editions_url( array( 'edition' => (int) $av_entry['edition_id'] ) ) ); ?>"><?php esc_html_e( 'Migrado: ver edicion', 'aula-virtual' ); ?></a>
					<?php endif; ?>
				</td>
				<td>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="<?php echo esc_attr( MigrationScreen::ACTION_MIGRATE ); ?>">
						<input type="hidden" name="tutor_course_id" value="<?php echo esc_attr( (string) $av_course['id'] ); ?>">
						<?php wp_nonce_field( MigrationScreen::ACTION_MIGRATE ); ?>
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
