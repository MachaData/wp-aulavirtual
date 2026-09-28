<?php
/**
 * Registration requests list.
 *
 * @package SIQA\AulaVirtual
 *
 * @var array{items: array<int, array<string, mixed>>, total: int, pages: int, page: int} $result
 * @var array<int, array<string, mixed>|null>                                              $editions
 * @var string                                                                             $status
 * @var array{pending: int}                                                                $counts
 * @var array{type: string, message: string}|null                                          $notice
 */

use SIQA\AulaVirtual\Admin\RequestsScreen;
use SIQA\AulaVirtual\Enrollments\RegistrationRequestRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$av_labels = RegistrationRequestRepository::labels();
$av_base   = add_query_arg( 'page', RequestsScreen::SLUG, admin_url( 'admin.php' ) );
?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Solicitudes de inscripcion', 'aula-virtual' ); ?></h1>
	<hr class="wp-header-end">

	<?php require AV_PATH . 'admin/views/notice.php'; ?>

	<ul class="subsubsub">
		<li><a href="<?php echo esc_url( add_query_arg( 'estado', 'pending', $av_base ) ); ?>" <?php echo 'pending' === $status ? 'class="current"' : ''; ?>>
			<?php esc_html_e( 'Pendientes', 'aula-virtual' ); ?> <span class="count">(<?php echo esc_html( (string) $counts['pending'] ); ?>)</span></a> |</li>
		<li><a href="<?php echo esc_url( add_query_arg( 'estado', 'approved', $av_base ) ); ?>" <?php echo 'approved' === $status ? 'class="current"' : ''; ?>><?php esc_html_e( 'Pago pendiente', 'aula-virtual' ); ?></a> |</li>
		<li><a href="<?php echo esc_url( add_query_arg( 'estado', 'enrolled', $av_base ) ); ?>" <?php echo 'enrolled' === $status ? 'class="current"' : ''; ?>><?php esc_html_e( 'Matriculadas', 'aula-virtual' ); ?></a> |</li>
		<li><a href="<?php echo esc_url( add_query_arg( 'estado', 'rejected', $av_base ) ); ?>" <?php echo 'rejected' === $status ? 'class="current"' : ''; ?>><?php esc_html_e( 'Rechazadas', 'aula-virtual' ); ?></a> |</li>
		<li><a href="<?php echo esc_url( add_query_arg( 'estado', 'all', $av_base ) ); ?>" <?php echo 'all' === $status ? 'class="current"' : ''; ?>><?php esc_html_e( 'Todas', 'aula-virtual' ); ?></a></li>
	</ul>

	<table class="wp-list-table widefat fixed striped" style="margin-top:12px">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Solicitante', 'aula-virtual' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Curso / edicion', 'aula-virtual' ); ?></th>
				<th scope="col" style="width:150px"><?php esc_html_e( 'Fecha', 'aula-virtual' ); ?></th>
				<th scope="col" style="width:170px"><?php esc_html_e( 'Estado', 'aula-virtual' ); ?></th>
				<th scope="col" style="width:280px"><?php esc_html_e( 'Acciones', 'aula-virtual' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( empty( $result['items'] ) ) : ?>
			<tr><td colspan="5"><?php esc_html_e( 'No hay solicitudes en este estado.', 'aula-virtual' ); ?></td></tr>
		<?php else : ?>
			<?php foreach ( $result['items'] as $av_request ) : ?>
				<?php
				$av_edition = $editions[ (int) $av_request['edition_id'] ] ?? null;
				$av_is_paid = null !== $av_edition && (int) $av_edition['product_id'] > 0;
				?>
				<tr>
					<td>
						<strong><?php echo esc_html( trim( $av_request['first_name'] . ' ' . $av_request['last_name'] ) ); ?></strong><br>
						<a href="mailto:<?php echo esc_attr( (string) $av_request['email'] ); ?>"><?php echo esc_html( (string) $av_request['email'] ); ?></a>
						<?php if ( ! empty( $av_request['phone'] ) ) : ?><br><?php echo esc_html( (string) $av_request['phone'] ); ?><?php endif; ?>
					</td>
					<td>
						<?php echo esc_html( get_the_title( (int) $av_request['course_id'] ) ); ?><br>
						<span class="description"><?php echo esc_html( null === $av_edition ? '' : (string) $av_edition['name'] ); ?></span>
					</td>
					<td><?php echo esc_html( mysql2date( (string) get_option( 'date_format' ) . ' H:i', (string) $av_request['created_at'] ) ); ?></td>
					<td><?php echo esc_html( $av_labels[ $av_request['status'] ] ?? (string) $av_request['status'] ); ?></td>
					<td>
						<?php if ( RegistrationRequestRepository::STATUS_PENDING === $av_request['status'] ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
								<input type="hidden" name="action" value="<?php echo esc_attr( RequestsScreen::ACTION_APPROVE ); ?>">
								<input type="hidden" name="request_id" value="<?php echo esc_attr( (string) (int) $av_request['id'] ); ?>">
								<?php wp_nonce_field( RequestsScreen::ACTION_APPROVE ); ?>
								<button type="submit" class="button button-primary button-small">
									<?php echo esc_html( $av_is_paid ? __( 'Aprobar y enviar pago', 'aula-virtual' ) : __( 'Aprobar y matricular', 'aula-virtual' ) ); ?>
								</button>
							</form>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
								<input type="hidden" name="action" value="<?php echo esc_attr( RequestsScreen::ACTION_REJECT ); ?>">
								<input type="hidden" name="request_id" value="<?php echo esc_attr( (string) (int) $av_request['id'] ); ?>">
								<input type="hidden" name="reason" value="">
								<?php wp_nonce_field( RequestsScreen::ACTION_REJECT ); ?>
								<button type="submit" class="button button-small" onclick="var r=prompt('<?php echo esc_js( __( 'Motivo del rechazo (se envia al solicitante):', 'aula-virtual' ) ); ?>'); if(r===null){return false;} this.form.reason.value=r;">
									<?php esc_html_e( 'Rechazar', 'aula-virtual' ); ?>
								</button>
							</form>
						<?php elseif ( RegistrationRequestRepository::STATUS_APPROVED === $av_request['status'] ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
								<input type="hidden" name="action" value="<?php echo esc_attr( RequestsScreen::ACTION_MARK_PAID ); ?>">
								<input type="hidden" name="request_id" value="<?php echo esc_attr( (string) (int) $av_request['id'] ); ?>">
								<?php wp_nonce_field( RequestsScreen::ACTION_MARK_PAID ); ?>
								<button type="submit" class="button button-primary button-small"><?php esc_html_e( 'Marcar pagado y matricular', 'aula-virtual' ); ?></button>
							</form>
						<?php else : ?>
							&mdash;
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>

	<?php if ( $result['pages'] > 1 ) : ?>
		<p>
			<?php
			echo wp_kses_post(
				paginate_links(
					array(
						'base'    => add_query_arg( 'paged', '%#%', add_query_arg( 'estado', $status, $av_base ) ),
						'format'  => '',
						'current' => $result['page'],
						'total'   => $result['pages'],
					)
				) ?? ''
			);
			?>
		</p>
	<?php endif; ?>
</div>
