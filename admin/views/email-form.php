<?php
/**
 * Email template editor.
 *
 * @package SIQA\AulaVirtual
 *
 * @var array<string, mixed>                      $template
 * @var array<string, string>                     $events
 * @var array<string, string>                     $variables
 * @var array{type: string, message: string}|null $notice
 */

use SIQA\AulaVirtual\Admin\EmailsScreen;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php echo esc_html( $events[ $template['event'] ] ?? (string) $template['event'] ); ?></h1>

	<?php require AV_PATH . 'admin/views/notice.php'; ?>

	<p><a href="<?php echo esc_url( add_query_arg( 'page', EmailsScreen::SLUG, admin_url( 'admin.php' ) ) ); ?>">&larr; <?php esc_html_e( 'Todas las plantillas', 'aula-virtual' ); ?></a></p>

	<div style="display:flex;gap:32px;flex-wrap:wrap">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="flex:1 1 560px;max-width:760px">
			<input type="hidden" name="action" value="<?php echo esc_attr( EmailsScreen::ACTION_SAVE ); ?>">
			<input type="hidden" name="template_id" value="<?php echo esc_attr( (string) (int) $template['id'] ); ?>">
			<?php wp_nonce_field( EmailsScreen::ACTION_SAVE ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Activa', 'aula-virtual' ); ?></th>
					<td><label><input type="checkbox" name="enabled" value="1" <?php checked( (int) $template['enabled'], 1 ); ?>> <?php esc_html_e( 'Enviar este correo cuando ocurra el evento', 'aula-virtual' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><label for="av-subject"><?php esc_html_e( 'Asunto', 'aula-virtual' ); ?></label></th>
					<td><input type="text" id="av-subject" name="subject" class="large-text" value="<?php echo esc_attr( (string) $template['subject'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="av-body"><?php esc_html_e( 'Contenido', 'aula-virtual' ); ?></label></th>
					<td>
						<?php
						wp_editor(
							(string) $template['body'],
							'av-body',
							array(
								'textarea_name' => 'body',
								'textarea_rows' => 16,
								'media_buttons' => false,
								'teeny'         => false,
							)
						);
						?>
					</td>
				</tr>
			</table>

			<?php submit_button( __( 'Guardar plantilla', 'aula-virtual' ) ); ?>
		</form>

		<div style="flex:0 1 320px">
			<h2><?php esc_html_e( 'Variables disponibles', 'aula-virtual' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Escríbelas tal cual, con las llaves. Si un dato no aplica al evento queda vacío.', 'aula-virtual' ); ?></p>
			<table class="widefat striped">
				<tbody>
				<?php foreach ( $variables as $av_name => $av_description ) : ?>
					<tr>
						<td><code>{{<?php echo esc_html( $av_name ); ?>}}</code></td>
						<td><?php echo esc_html( $av_description ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:16px">
				<input type="hidden" name="action" value="<?php echo esc_attr( EmailsScreen::ACTION_TEST ); ?>">
				<input type="hidden" name="template_id" value="<?php echo esc_attr( (string) (int) $template['id'] ); ?>">
				<?php wp_nonce_field( EmailsScreen::ACTION_TEST ); ?>
				<?php submit_button( __( 'Enviarme una prueba', 'aula-virtual' ), 'secondary', 'submit', false ); ?>
				<p class="description"><?php esc_html_e( 'Usa datos de ejemplo y la versión guardada de la plantilla.', 'aula-virtual' ); ?></p>
			</form>
		</div>
	</div>
</div>
