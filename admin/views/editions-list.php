<?php
/**
 * Editions list.
 *
 * @package SIQA\AulaVirtual
 *
 * @var array{items: array<int, array<string, mixed>>, total: int} $editions
 * @var array<int, int>                                            $seats
 * @var array{type: string, message: string}|null                 $notice
 */

use SIQA\AulaVirtual\Admin\AdminMenu;
use SIQA\AulaVirtual\Editions\EditionService;
use SIQA\AulaVirtual\Editions\EditionStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$av_modalities = EditionService::modalities();
$av_tone       = array(
	EditionStatus::DRAFT    => 'gray',
	EditionStatus::UPCOMING => 'blue',
	EditionStatus::OPEN     => 'green',
	EditionStatus::RUNNING  => 'blue',
	EditionStatus::FINISHED => 'gray',
	EditionStatus::ARCHIVED => 'gray',
);
?>
<div class="wrap av-admin">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Ediciones', 'aula-virtual' ); ?></h1>
	<a href="<?php echo esc_url( AdminMenu::editions_url( array( 'action' => 'new' ) ) ); ?>" class="page-title-action"><?php esc_html_e( 'Nueva edición', 'aula-virtual' ); ?></a>
	<hr class="wp-header-end">

	<?php require AV_PATH . 'admin/views/notice.php'; ?>

	<p class="description" style="margin:8px 0 16px"><?php esc_html_e( 'Cada edición es una cohorte de un curso: sus fechas, su cupo, sus sesiones y sus alumnos.', 'aula-virtual' ); ?></p>

	<?php if ( empty( $editions['items'] ) ) : ?>
		<div class="av-empty">
			<span class="dashicons dashicons-calendar-alt"></span>
			<h3><?php esc_html_e( 'Todavía no hay ediciones', 'aula-virtual' ); ?></h3>
			<p><?php esc_html_e( 'Crea la primera para añadir sesiones y empezar a recibir inscripciones.', 'aula-virtual' ); ?></p>
			<p style="margin-top:12px"><a class="button button-primary" href="<?php echo esc_url( AdminMenu::editions_url( array( 'action' => 'new' ) ) ); ?>"><?php esc_html_e( 'Nueva edición', 'aula-virtual' ); ?></a></p>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat striped av-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Edición', 'aula-virtual' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Curso', 'aula-virtual' ); ?></th>
					<th scope="col" class="av-hide-sm"><?php esc_html_e( 'Modalidad', 'aula-virtual' ); ?></th>
					<th scope="col" class="av-hide-sm"><?php esc_html_e( 'Inicio', 'aula-virtual' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Alumnos', 'aula-virtual' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Estado', 'aula-virtual' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $editions['items'] as $av_edition ) : ?>
				<?php
				$av_url  = AdminMenu::editions_url( array( 'edition' => (int) $av_edition['id'] ) );
				$av_cap  = (int) $av_edition['capacity'];
				$av_seat = (int) ( $seats[ (int) $av_edition['id'] ] ?? 0 );
				?>
				<tr>
					<td>
						<strong><a class="row-title" href="<?php echo esc_url( $av_url ); ?>"><?php echo esc_html( (string) $av_edition['name'] ); ?></a></strong>
						<span class="av-sub"><code><?php echo esc_html( (string) $av_edition['code'] ); ?></code></span>
						<div class="row-actions">
							<span><a href="<?php echo esc_url( add_query_arg( 'tab', 'sesiones', $av_url ) ); ?>"><?php esc_html_e( 'Sesiones', 'aula-virtual' ); ?></a> | </span>
							<span><a href="<?php echo esc_url( add_query_arg( 'tab', 'alumnos', $av_url ) ); ?>"><?php esc_html_e( 'Alumnos', 'aula-virtual' ); ?></a> | </span>
							<span><a href="<?php echo esc_url( add_query_arg( 'tab', 'inscripcion', $av_url ) ); ?>"><?php esc_html_e( 'Inscripción', 'aula-virtual' ); ?></a> | </span>
							<span><a href="<?php echo esc_url( add_query_arg( 'tab', 'ajustes', $av_url ) ); ?>"><?php esc_html_e( 'Ajustes', 'aula-virtual' ); ?></a></span>
						</div>
					</td>
					<td><?php echo esc_html( get_the_title( (int) $av_edition['course_id'] ) ); ?></td>
					<td class="av-hide-sm"><?php echo esc_html( $av_modalities[ $av_edition['modality'] ] ?? (string) $av_edition['modality'] ); ?></td>
					<td class="av-hide-sm">
						<?php
						echo esc_html(
							empty( $av_edition['start_date'] )
								? '—'
								: date_i18n( (string) get_option( 'date_format' ), (int) strtotime( $av_edition['start_date'] . ' UTC' ) )
						);
						?>
					</td>
					<td><?php echo esc_html( 0 === $av_cap ? (string) $av_seat : $av_seat . ' / ' . $av_cap ); ?></td>
					<td><span class="av-badge av-badge--<?php echo esc_attr( $av_tone[ $av_edition['status'] ] ?? 'gray' ); ?>"><?php echo esc_html( EditionStatus::label( (string) $av_edition['status'] ) ); ?></span></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
