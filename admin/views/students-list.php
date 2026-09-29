<?php
/**
 * Students list.
 *
 * @package SIQA\AulaVirtual
 *
 * @var array<int, array<string, mixed>>          $rows
 * @var int                                       $total
 * @var int                                       $page
 * @var array<string, mixed>                      $filters
 * @var array<int, array<string, mixed>>          $editions
 * @var array{type: string, message: string}|null $notice
 */

use SIQA\AulaVirtual\Admin\StudentsScreen;
use SIQA\AulaVirtual\Enrollments\EnrollmentStatus;
use SIQA\AulaVirtual\Students\AccountHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$av_pages = max( 1, (int) ceil( $total / 30 ) );
$av_date  = static fn( $v ): string => empty( $v ) || str_starts_with( (string) $v, '0000' ) ? '—' : date_i18n( (string) get_option( 'date_format' ), (int) strtotime( $v . ' UTC' ) );
?>
<div class="wrap av-admin">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Alumnos', 'aula-virtual' ); ?></h1>
	<hr class="wp-header-end">

	<?php require AV_PATH . 'admin/views/notice.php'; ?>

	<p class="description" style="margin:8px 0 16px"><?php esc_html_e( 'Todas las personas con al menos una matrícula. Abre una ficha para gestionar sus matrículas, su acceso y sus datos.', 'aula-virtual' ); ?></p>

	<form method="get" class="av-card av-card--white" style="margin:0 0 16px">
		<input type="hidden" name="page" value="<?php echo esc_attr( StudentsScreen::SLUG ); ?>">
		<div class="av-inline-form" style="grid-template-columns:minmax(220px,2fr) minmax(200px,2fr) minmax(160px,1fr) auto">
			<div>
				<label for="av-s"><?php esc_html_e( 'Buscar', 'aula-virtual' ); ?></label>
				<input type="search" id="av-s" name="s" value="<?php echo esc_attr( (string) $filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'Nombre o correo', 'aula-virtual' ); ?>" style="width:100%">
			</div>
			<div>
				<label for="av-f-edition"><?php esc_html_e( 'Edición', 'aula-virtual' ); ?></label>
				<select id="av-f-edition" name="edition">
					<option value="0"><?php esc_html_e( 'Todas', 'aula-virtual' ); ?></option>
					<?php foreach ( $editions as $av_e ) : ?>
						<option value="<?php echo esc_attr( (string) (int) $av_e['id'] ); ?>" <?php selected( (int) $filters['edition_id'], (int) $av_e['id'] ); ?>><?php echo esc_html( get_the_title( (int) $av_e['course_id'] ) . ' · ' . $av_e['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div>
				<label for="av-f-status"><?php esc_html_e( 'Estado', 'aula-virtual' ); ?></label>
				<select id="av-f-status" name="status">
					<option value=""><?php esc_html_e( 'Todos', 'aula-virtual' ); ?></option>
					<?php foreach ( EnrollmentStatus::labels() as $av_k => $av_l ) : ?>
						<option value="<?php echo esc_attr( $av_k ); ?>" <?php selected( (string) $filters['status'], $av_k ); ?>><?php echo esc_html( $av_l ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div><?php submit_button( __( 'Filtrar', 'aula-virtual' ), 'secondary', '', false ); ?></div>
		</div>
	</form>

	<?php if ( empty( $rows ) ) : ?>
		<div class="av-empty">
			<span class="dashicons dashicons-groups"></span>
			<h3><?php esc_html_e( 'No hay alumnos con esos filtros', 'aula-virtual' ); ?></h3>
			<p><?php esc_html_e( 'Prueba con otro nombre o correo, o quita los filtros.', 'aula-virtual' ); ?></p>
		</div>
	<?php else : ?>
		<p class="description" style="margin:0 0 8px">
			<?php echo esc_html( sprintf( /* translators: %d: number of people. */ _n( '%d persona', '%d personas', $total, 'aula-virtual' ), $total ) ); ?>
		</p>
		<table class="widefat striped av-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Alumno', 'aula-virtual' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Matrículas', 'aula-virtual' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Acceso', 'aula-virtual' ); ?></th>
					<th scope="col" class="av-hide-sm"><?php esc_html_e( 'Último ingreso', 'aula-virtual' ); ?></th>
					<th scope="col" class="av-hide-sm"><?php esc_html_e( 'Última actividad', 'aula-virtual' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $rows as $av_row ) : ?>
				<?php $av_uid = (int) $av_row['user_id']; ?>
				<tr>
					<td>
						<strong><a class="row-title" href="<?php echo esc_url( StudentsScreen::url( $av_uid ) ); ?>"><?php echo esc_html( (string) $av_row['display_name'] ); ?></a></strong>
						<span class="av-sub"><?php echo esc_html( (string) $av_row['user_email'] ); ?></span>
					</td>
					<td>
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: enrollments with access, 2: total enrollments. */
								_n( '%1$d activa de %2$d', '%1$d activas de %2$d', (int) $av_row['with_access'], 'aula-virtual' ),
								(int) $av_row['with_access'],
								(int) $av_row['enrollments']
							)
						);
						?>
					</td>
					<td>
						<?php if ( AccountHelper::needs_password( $av_uid ) ) : ?>
							<span class="av-badge av-badge--yellow"><?php esc_html_e( 'Sin contraseña aún', 'aula-virtual' ); ?></span>
						<?php else : ?>
							<span class="av-badge av-badge--green"><?php esc_html_e( 'Con contraseña', 'aula-virtual' ); ?></span>
						<?php endif; ?>
					</td>
					<td class="av-hide-sm"><?php echo esc_html( $av_date( AccountHelper::last_login( $av_uid ) ) ); ?></td>
					<td class="av-hide-sm"><?php echo esc_html( $av_date( $av_row['last_activity'] ?? '' ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $av_pages > 1 ) : ?>
			<div class="tablenav"><div class="tablenav-pages">
				<?php
				echo wp_kses_post(
					(string) paginate_links(
						array(
							'base'    => add_query_arg( 'paged', '%#%' ),
							'format'  => '',
							'current' => $page,
							'total'   => $av_pages,
						)
					)
				);
				?>
			</div></div>
		<?php endif; ?>
	<?php endif; ?>
</div>
