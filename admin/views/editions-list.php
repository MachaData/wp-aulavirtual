<?php
/**
 * Editions list.
 *
 * @package SIQA\AulaVirtual
 *
 * @var array{items: array<int, array<string, mixed>>, total: int} $editions
 * @var array{type: string, message: string}|null                 $notice
 */

use SIQA\AulaVirtual\Admin\AdminMenu;
use SIQA\AulaVirtual\Editions\EditionService;
use SIQA\AulaVirtual\Editions\EditionStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$av_modalities = EditionService::modalities();
?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Ediciones', 'aula-virtual' ); ?></h1>
	<a href="<?php echo esc_url( AdminMenu::editions_url( array( 'action' => 'new' ) ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'Nueva edicion', 'aula-virtual' ); ?>
	</a>
	<hr class="wp-header-end">

	<?php require AV_PATH . 'admin/views/notice.php'; ?>

	<p class="description">
		<?php esc_html_e( 'Una edicion es una cohorte concreta de un curso: sus fechas, su cupo y sus alumnos.', 'aula-virtual' ); ?>
	</p>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Edicion', 'aula-virtual' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Curso', 'aula-virtual' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Modalidad', 'aula-virtual' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Inicio', 'aula-virtual' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Cupo', 'aula-virtual' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Estado', 'aula-virtual' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( empty( $editions['items'] ) ) : ?>
			<tr>
				<td colspan="6">
					<?php esc_html_e( 'Todavia no hay ediciones. Crea la primera para empezar a matricular alumnos.', 'aula-virtual' ); ?>
				</td>
			</tr>
		<?php else : ?>
			<?php foreach ( $editions['items'] as $av_edition ) : ?>
				<tr>
					<td>
						<strong>
							<a href="<?php echo esc_url( AdminMenu::editions_url( array( 'edition' => (int) $av_edition['id'] ) ) ); ?>">
								<?php echo esc_html( (string) $av_edition['name'] ); ?>
							</a>
						</strong>
						<div class="row-actions"><span><?php echo esc_html( (string) $av_edition['code'] ); ?></span></div>
					</td>
					<td><?php echo esc_html( get_the_title( (int) $av_edition['course_id'] ) ); ?></td>
					<td><?php echo esc_html( $av_modalities[ $av_edition['modality'] ] ?? (string) $av_edition['modality'] ); ?></td>
					<td>
						<?php
						echo esc_html(
							empty( $av_edition['start_date'] )
								? __( 'Sin fecha', 'aula-virtual' )
								: mysql2date( get_option( 'date_format' ), (string) $av_edition['start_date'] )
						);
						?>
					</td>
					<td>
						<?php
						echo esc_html(
							0 === (int) $av_edition['capacity']
								? __( 'Sin limite', 'aula-virtual' )
								: (string) (int) $av_edition['capacity']
						);
						?>
					</td>
					<td><?php echo esc_html( EditionStatus::label( (string) $av_edition['status'] ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>
</div>
