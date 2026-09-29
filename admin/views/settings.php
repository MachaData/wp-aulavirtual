<?php
/**
 * Settings screen.
 *
 * @package SIQA\AulaVirtual
 *
 * @var array<string, array{label: string, fields: array<string, array<string, mixed>>}> $tabs
 * @var string                                                                          $current
 * @var array<string, mixed>                                                            $values
 * @var array{type: string, message: string}|null                                       $notice
 * @var string                                                                          $campus_url
 * @var string                                                                          $campus_edit
 */

use SIQA\AulaVirtual\Admin\SettingsScreen;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Configuración de Aula Virtual', 'aula-virtual' ); ?></h1>

	<?php require AV_PATH . 'admin/views/notice.php'; ?>

	<nav class="nav-tab-wrapper">
		<?php foreach ( $tabs as $av_key => $av_tab ) : ?>
			<a class="nav-tab <?php echo $av_key === $current ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => SettingsScreen::SLUG, 'tab' => $av_key ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $av_tab['label'] ); ?></a>
		<?php endforeach; ?>
	</nav>

	<?php if ( 'general' === $current && '' !== $campus_url ) : ?>
		<p class="description" style="margin-top:12px">
			<?php esc_html_e( 'Página del campus:', 'aula-virtual' ); ?>
			<a href="<?php echo esc_url( $campus_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $campus_url ); ?></a>
			<?php if ( '' !== $campus_edit ) : ?>
				&middot; <a href="<?php echo esc_url( $campus_edit ); ?>"><?php esc_html_e( 'cambiar su slug o contenido', 'aula-virtual' ); ?></a>
			<?php endif; ?>
		</p>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="<?php echo esc_attr( SettingsScreen::ACTION_SAVE ); ?>">
		<input type="hidden" name="tab" value="<?php echo esc_attr( $current ); ?>">
		<?php wp_nonce_field( SettingsScreen::ACTION_SAVE ); ?>

		<table class="form-table" role="presentation">
			<?php foreach ( $tabs[ $current ]['fields'] as $av_option => $av_field ) : ?>
				<?php $av_value = $values[ $av_option ] ?? $av_field['default']; ?>
				<tr>
					<th scope="row"><label for="<?php echo esc_attr( $av_option ); ?>"><?php echo esc_html( $av_field['label'] ); ?></label></th>
					<td>
						<?php switch ( $av_field['type'] ) :
							case 'bool': ?>
								<input type="hidden" name="<?php echo esc_attr( $av_option ); ?>" value="0">
								<label><input type="checkbox" id="<?php echo esc_attr( $av_option ); ?>" name="<?php echo esc_attr( $av_option ); ?>" value="1" <?php checked( (bool) $av_value ); ?>> <?php esc_html_e( 'Activado', 'aula-virtual' ); ?></label>
								<?php break; ?>
							<?php case 'select': ?>
								<select id="<?php echo esc_attr( $av_option ); ?>" name="<?php echo esc_attr( $av_option ); ?>">
									<?php foreach ( $av_field['options'] as $av_k => $av_label ) : ?>
										<option value="<?php echo esc_attr( (string) $av_k ); ?>" <?php selected( (string) $av_value, (string) $av_k ); ?>><?php echo esc_html( $av_label ); ?></option>
									<?php endforeach; ?>
								</select>
								<?php break; ?>
							<?php case 'secret': ?>
								<input type="password" id="<?php echo esc_attr( $av_option ); ?>" name="<?php echo esc_attr( $av_option ); ?>" class="regular-text" value="" autocomplete="new-password" placeholder="<?php echo '' !== (string) $av_value ? esc_attr__( '(guardada: escribe para reemplazar)', 'aula-virtual' ) : ''; ?>">
								<?php break; ?>
							<?php case 'color': ?>
								<input type="color" id="<?php echo esc_attr( $av_option ); ?>" name="<?php echo esc_attr( $av_option ); ?>" value="<?php echo esc_attr( (string) $av_value ); ?>">
								<code><?php echo esc_html( (string) $av_value ); ?></code>
								<?php break; ?>
							<?php case 'int': ?>
								<input type="number" id="<?php echo esc_attr( $av_option ); ?>" name="<?php echo esc_attr( $av_option ); ?>" class="small-text" value="<?php echo esc_attr( (string) (int) $av_value ); ?>" <?php echo isset( $av_field['min'] ) ? 'min="' . esc_attr( (string) $av_field['min'] ) . '"' : ''; ?>>
								<?php break; ?>
							<?php case 'email': ?>
								<input type="email" id="<?php echo esc_attr( $av_option ); ?>" name="<?php echo esc_attr( $av_option ); ?>" class="regular-text" value="<?php echo esc_attr( (string) $av_value ); ?>">
								<?php break; ?>
							<?php case 'action': ?>
								<a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'action', $av_field['action'], admin_url( 'admin-post.php' ) ), $av_field['action'] ) ); ?>"><?php echo esc_html( $av_field['button'] ); ?></a>
								<?php break; ?>
							<?php default: ?>
								<input type="text" id="<?php echo esc_attr( $av_option ); ?>" name="<?php echo esc_attr( $av_option ); ?>" class="regular-text" value="<?php echo esc_attr( (string) $av_value ); ?>">
						<?php endswitch; ?>
						<?php if ( ! empty( $av_field['help'] ) ) : ?>
							<p class="description"><?php echo esc_html( $av_field['help'] ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>

		<?php submit_button( __( 'Guardar', 'aula-virtual' ) ); ?>
	</form>
</div>
