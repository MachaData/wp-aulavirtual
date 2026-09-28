<?php
/**
 * Email templates list.
 *
 * @package SIQA\AulaVirtual
 *
 * @var array<int, array<string, mixed>>          $templates
 * @var array<string, string>                     $events
 * @var array{type: string, message: string}|null $notice
 */

use SIQA\AulaVirtual\Admin\EmailsScreen;
use SIQA\AulaVirtual\Emails\EmailDefaults;
use SIQA\AulaVirtual\Emails\EmailTemplateRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Emails', 'aula-virtual' ); ?></h1>

	<?php require AV_PATH . 'admin/views/notice.php'; ?>

	<p class="description">
		<?php esc_html_e( 'Cada correo que recibe un alumno o el administrador sale de una de estas plantillas. Puedes editar asunto y contenido, desactivarlas y enviarte una prueba.', 'aula-virtual' ); ?>
	</p>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Cuando se envia', 'aula-virtual' ); ?></th>
				<th scope="col" style="width:130px"><?php esc_html_e( 'Destinatario', 'aula-virtual' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Asunto', 'aula-virtual' ); ?></th>
				<th scope="col" style="width:100px"><?php esc_html_e( 'Estado', 'aula-virtual' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $templates as $av_template ) : ?>
			<tr>
				<td>
					<strong>
						<a href="<?php echo esc_url( add_query_arg( array( 'page' => EmailsScreen::SLUG, 'template' => (int) $av_template['id'] ), admin_url( 'admin.php' ) ) ); ?>">
							<?php echo esc_html( $events[ $av_template['event'] ] ?? (string) $av_template['event'] ); ?>
						</a>
					</strong>
				</td>
				<td><?php echo esc_html( EmailDefaults::recipients()[ (string) $av_template['recipient'] ] ?? (string) $av_template['recipient'] ); ?></td>
				<td><?php echo esc_html( (string) $av_template['subject'] ); ?></td>
				<td><?php echo esc_html( (int) $av_template['enabled'] ? __( 'Activa', 'aula-virtual' ) : __( 'Desactivada', 'aula-virtual' ) ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
