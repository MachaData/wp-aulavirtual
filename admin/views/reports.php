<?php
/**
 * Reports: site overview or edition report.
 *
 * @package SIQA\AulaVirtual
 *
 * @var string                                    $mode     "overview" or "edition".
 * @var array<string, mixed>                      $overview Overview totals (overview mode).
 * @var array<string, mixed>                      $edition  Edition row (edition mode).
 * @var WP_Post|null                              $course   Course post (edition mode).
 * @var array<string, mixed>                      $summary  Edition summary (edition mode).
 * @var array<int, array<string, mixed>>          $lessons  Lesson completion rows (edition mode).
 * @var array{type: string, message: string}|null $notice
 */

use SIQA\AulaVirtual\Admin\AdminMenu;
use SIQA\AulaVirtual\Admin\ReportsScreen;
use SIQA\AulaVirtual\Editions\EditionStatus;
use SIQA\AulaVirtual\Enrollments\EnrollmentStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$av_card_grid  = 'display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:16px;margin:16px 0 24px';
$av_card_style = 'background:#fff;border:1px solid #c3c4c7;box-shadow:0 1px 1px rgba(0,0,0,.04);padding:14px 16px';
$av_card_value = 'display:block;font-size:26px;font-weight:600;line-height:1.2;margin-top:4px';
?>
<div class="wrap">
<?php if ( 'edition' === $mode ) : ?>
	<?php $av_edition_id = (int) $edition['id']; ?>
	<h1 class="wp-heading-inline">
		<?php
		/* translators: %s: edition name. */
		echo esc_html( sprintf( __( 'Reporte: %s', 'aula-virtual' ), (string) $edition['name'] ) );
		?>
	</h1>
	<a href="<?php echo esc_url( ReportsScreen::export_url( $av_edition_id ) ); ?>" class="page-title-action"><?php esc_html_e( 'Exportar alumnos (CSV)', 'aula-virtual' ); ?></a>
	<hr class="wp-header-end">

	<?php require AV_PATH . 'admin/views/notice.php'; ?>

	<p>
		<a href="<?php echo esc_url( add_query_arg( 'page', ReportsScreen::SLUG, admin_url( 'admin.php' ) ) ); ?>">&larr; <?php esc_html_e( 'Todos los reportes', 'aula-virtual' ); ?></a>
		&nbsp;|&nbsp;
		<a href="<?php echo esc_url( AdminMenu::editions_url( array( 'edition' => $av_edition_id ) ) ); ?>"><?php esc_html_e( 'Ver edicion', 'aula-virtual' ); ?></a>
	</p>

	<p class="description">
		<?php echo esc_html( $course instanceof WP_Post ? get_the_title( $course ) : __( 'Curso eliminado', 'aula-virtual' ) ); ?>
		&middot; <code><?php echo esc_html( (string) $edition['code'] ); ?></code>
		&middot; <?php echo esc_html( EditionStatus::label( (string) $edition['status'] ) ); ?>
	</p>

	<div style="<?php echo esc_attr( $av_card_grid ); ?>">
		<div style="<?php echo esc_attr( $av_card_style ); ?>">
			<span class="description"><?php esc_html_e( 'Matriculas', 'aula-virtual' ); ?></span>
			<span style="<?php echo esc_attr( $av_card_value ); ?>"><?php echo esc_html( number_format_i18n( (int) $summary['enrollments_total'] ) ); ?></span>
		</div>
		<div style="<?php echo esc_attr( $av_card_style ); ?>">
			<span class="description"><?php esc_html_e( 'Completaron', 'aula-virtual' ); ?></span>
			<span style="<?php echo esc_attr( $av_card_value ); ?>"><?php echo esc_html( number_format_i18n( (int) $summary['completed'] ) ); ?></span>
		</div>
		<div style="<?php echo esc_attr( $av_card_style ); ?>">
			<span class="description"><?php esc_html_e( 'Tasa de finalizacion', 'aula-virtual' ); ?></span>
			<span style="<?php echo esc_attr( $av_card_value ); ?>"><?php echo esc_html( number_format_i18n( (float) $summary['completion_rate'], 1 ) ); ?>%</span>
		</div>
		<div style="<?php echo esc_attr( $av_card_style ); ?>">
			<span class="description"><?php esc_html_e( 'Progreso promedio', 'aula-virtual' ); ?></span>
			<span style="<?php echo esc_attr( $av_card_value ); ?>"><?php echo esc_html( number_format_i18n( (float) $summary['avg_progress'], 1 ) ); ?>%</span>
		</div>
		<div style="<?php echo esc_attr( $av_card_style ); ?>">
			<span class="description"><?php esc_html_e( 'Activos ultimos 7 dias', 'aula-virtual' ); ?></span>
			<span style="<?php echo esc_attr( $av_card_value ); ?>"><?php echo esc_html( number_format_i18n( (int) $summary['active_last_7_days'] ) ); ?></span>
		</div>
		<div style="<?php echo esc_attr( $av_card_style ); ?>">
			<span class="description"><?php esc_html_e( 'Comentarios', 'aula-virtual' ); ?></span>
			<span style="<?php echo esc_attr( $av_card_value ); ?>"><?php echo esc_html( number_format_i18n( (int) $summary['comments'] ) ); ?></span>
		</div>
	</div>

	<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,360px));gap:28px;align-items:start;margin-bottom:24px">
		<div>
			<h2><?php esc_html_e( 'Matriculas por estado', 'aula-virtual' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Estado', 'aula-virtual' ); ?></th>
						<th scope="col" style="width:90px;text-align:right"><?php esc_html_e( 'Alumnos', 'aula-virtual' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $summary['by_status'] ) ) : ?>
					<tr><td colspan="2"><?php esc_html_e( 'Sin matriculas.', 'aula-virtual' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $summary['by_status'] as $av_status => $av_count ) : ?>
						<tr>
							<td><?php echo esc_html( EnrollmentStatus::label( (string) $av_status ) ); ?></td>
							<td style="text-align:right"><?php echo esc_html( number_format_i18n( (int) $av_count ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>

		<div>
			<h2><?php esc_html_e( 'Matriculas por origen', 'aula-virtual' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Origen', 'aula-virtual' ); ?></th>
						<th scope="col" style="width:90px;text-align:right"><?php esc_html_e( 'Alumnos', 'aula-virtual' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $summary['by_source'] ) ) : ?>
					<tr><td colspan="2"><?php esc_html_e( 'Sin matriculas.', 'aula-virtual' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $summary['by_source'] as $av_source => $av_count ) : ?>
						<tr>
							<td><code><?php echo esc_html( (string) $av_source ); ?></code></td>
							<td style="text-align:right"><?php echo esc_html( number_format_i18n( (int) $av_count ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>

	<h2><?php esc_html_e( 'Avance por sesion', 'aula-virtual' ); ?></h2>
	<table class="widefat striped">
		<thead>
			<tr>
				<th scope="col" style="width:50px">#</th>
				<th scope="col"><?php esc_html_e( 'Sesion', 'aula-virtual' ); ?></th>
				<th scope="col" style="width:100px;text-align:right"><?php esc_html_e( 'Iniciaron', 'aula-virtual' ); ?></th>
				<th scope="col" style="width:100px;text-align:right"><?php esc_html_e( 'Completaron', 'aula-virtual' ); ?></th>
				<th scope="col" style="width:260px"><?php esc_html_e( 'Finalizacion', 'aula-virtual' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( empty( $lessons ) ) : ?>
			<tr><td colspan="5"><?php esc_html_e( 'La edicion no tiene sesiones publicadas.', 'aula-virtual' ); ?></td></tr>
		<?php else : ?>
			<?php foreach ( $lessons as $av_index => $av_lesson ) : ?>
				<?php $av_rate = max( 0, min( 100, (float) $av_lesson['rate'] ) ); ?>
				<tr>
					<td><?php echo esc_html( (string) ( $av_index + 1 ) ); ?></td>
					<td><?php echo esc_html( (string) $av_lesson['title'] ); ?></td>
					<td style="text-align:right"><?php echo esc_html( number_format_i18n( (int) $av_lesson['started'] ) ); ?></td>
					<td style="text-align:right"><?php echo esc_html( number_format_i18n( (int) $av_lesson['completed'] ) ); ?></td>
					<td>
						<div style="display:flex;align-items:center;gap:8px">
							<div style="flex:1;height:10px;background:#dcdcde;border-radius:5px;overflow:hidden">
								<div style="width:<?php echo esc_attr( number_format( $av_rate, 2, '.', '' ) ); ?>%;height:100%;background:#2271b1"></div>
							</div>
							<span style="width:52px;text-align:right"><?php echo esc_html( number_format_i18n( $av_rate, 1 ) ); ?>%</span>
						</div>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>
	<p class="description"><?php esc_html_e( 'El porcentaje de finalizacion se calcula sobre el total de matriculas de la edicion.', 'aula-virtual' ); ?></p>

<?php else : ?>
	<h1><?php esc_html_e( 'Reportes', 'aula-virtual' ); ?></h1>

	<?php require AV_PATH . 'admin/views/notice.php'; ?>

	<?php
	$av_enrollments_total = array_sum( array_map( 'intval', (array) $overview['enrollments_by_status'] ) );
	$av_editions_total    = array_sum( array_map( 'intval', (array) $overview['editions_by_status'] ) );
	?>
	<div style="<?php echo esc_attr( $av_card_grid ); ?>">
		<div style="<?php echo esc_attr( $av_card_style ); ?>">
			<span class="description"><?php esc_html_e( 'Ediciones', 'aula-virtual' ); ?></span>
			<span style="<?php echo esc_attr( $av_card_value ); ?>"><?php echo esc_html( number_format_i18n( $av_editions_total ) ); ?></span>
		</div>
		<div style="<?php echo esc_attr( $av_card_style ); ?>">
			<span class="description"><?php esc_html_e( 'Matriculas', 'aula-virtual' ); ?></span>
			<span style="<?php echo esc_attr( $av_card_value ); ?>"><?php echo esc_html( number_format_i18n( $av_enrollments_total ) ); ?></span>
		</div>
		<div style="<?php echo esc_attr( $av_card_style ); ?>">
			<span class="description"><?php esc_html_e( 'Alumnos distintos', 'aula-virtual' ); ?></span>
			<span style="<?php echo esc_attr( $av_card_value ); ?>"><?php echo esc_html( number_format_i18n( (int) $overview['students'] ) ); ?></span>
		</div>
		<div style="<?php echo esc_attr( $av_card_style ); ?>">
			<span class="description"><?php esc_html_e( 'Cursos completados', 'aula-virtual' ); ?></span>
			<span style="<?php echo esc_attr( $av_card_value ); ?>"><?php echo esc_html( number_format_i18n( (int) $overview['completions'] ) ); ?></span>
		</div>
	</div>

	<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,360px));gap:28px;align-items:start;margin-bottom:24px">
		<div>
			<h2><?php esc_html_e( 'Ediciones por estado', 'aula-virtual' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Estado', 'aula-virtual' ); ?></th>
						<th scope="col" style="width:90px;text-align:right"><?php esc_html_e( 'Ediciones', 'aula-virtual' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $overview['editions_by_status'] ) ) : ?>
					<tr><td colspan="2"><?php esc_html_e( 'Todavia no hay ediciones.', 'aula-virtual' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $overview['editions_by_status'] as $av_status => $av_count ) : ?>
						<tr>
							<td><?php echo esc_html( EditionStatus::label( (string) $av_status ) ); ?></td>
							<td style="text-align:right"><?php echo esc_html( number_format_i18n( (int) $av_count ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>

		<div>
			<h2><?php esc_html_e( 'Matriculas por estado', 'aula-virtual' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Estado', 'aula-virtual' ); ?></th>
						<th scope="col" style="width:90px;text-align:right"><?php esc_html_e( 'Alumnos', 'aula-virtual' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $overview['enrollments_by_status'] ) ) : ?>
					<tr><td colspan="2"><?php esc_html_e( 'Todavia no hay matriculas.', 'aula-virtual' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $overview['enrollments_by_status'] as $av_status => $av_count ) : ?>
						<tr>
							<td><?php echo esc_html( EnrollmentStatus::label( (string) $av_status ) ); ?></td>
							<td style="text-align:right"><?php echo esc_html( number_format_i18n( (int) $av_count ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>

	<h2><?php esc_html_e( 'Ediciones con mas matriculas', 'aula-virtual' ); ?></h2>
	<table class="widefat striped">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Edicion', 'aula-virtual' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Curso', 'aula-virtual' ); ?></th>
				<th scope="col" style="width:110px;text-align:right"><?php esc_html_e( 'Matriculas', 'aula-virtual' ); ?></th>
				<th scope="col" style="width:150px;text-align:right"><?php esc_html_e( 'Progreso promedio', 'aula-virtual' ); ?></th>
				<th scope="col" style="width:90px"></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( empty( $overview['top_editions'] ) ) : ?>
			<tr><td colspan="5"><?php esc_html_e( 'Todavia no hay ediciones con matriculas.', 'aula-virtual' ); ?></td></tr>
		<?php else : ?>
			<?php foreach ( $overview['top_editions'] as $av_top ) : ?>
				<tr>
					<td>
						<a href="<?php echo esc_url( ReportsScreen::edition_url( (int) $av_top['edition_id'] ) ); ?>"><strong><?php echo esc_html( (string) $av_top['name'] ); ?></strong></a>
					</td>
					<td><?php echo esc_html( get_the_title( (int) $av_top['course_id'] ) ); ?></td>
					<td style="text-align:right"><?php echo esc_html( number_format_i18n( (int) $av_top['enrollments'] ) ); ?></td>
					<td style="text-align:right"><?php echo esc_html( number_format_i18n( (float) $av_top['avg_progress'], 1 ) ); ?>%</td>
					<td><a href="<?php echo esc_url( ReportsScreen::edition_url( (int) $av_top['edition_id'] ) ); ?>"><?php esc_html_e( 'Ver reporte', 'aula-virtual' ); ?></a></td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>
<?php endif; ?>
</div>
