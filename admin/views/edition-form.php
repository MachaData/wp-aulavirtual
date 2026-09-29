<?php
/**
 * New edition form.
 *
 * @package SIQA\AulaVirtual
 *
 * @var array<int, WP_Post>                       $courses
 * @var array{type: string, message: string}|null $notice
 */

use SIQA\AulaVirtual\Admin\AdminMenu;
use SIQA\AulaVirtual\Admin\EditionsScreen;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap av-admin">
	<a class="av-back" href="<?php echo esc_url( AdminMenu::editions_url() ); ?>"><span class="dashicons dashicons-arrow-left-alt2"></span><?php esc_html_e( 'Ediciones', 'aula-virtual' ); ?></a>

	<div class="av-header">
		<div>
			<h1><?php esc_html_e( 'Nueva edición', 'aula-virtual' ); ?></h1>
			<p class="av-header__meta"><?php esc_html_e( 'Una edición es una cohorte concreta de un curso: sus fechas, su cupo y sus alumnos. Después podrás añadir las sesiones.', 'aula-virtual' ); ?></p>
		</div>
	</div>

	<?php require AV_PATH . 'admin/views/notice.php'; ?>

	<?php if ( empty( $courses ) ) : ?>
		<div class="av-empty">
			<span class="dashicons dashicons-welcome-learn-more"></span>
			<h3><?php esc_html_e( 'Primero crea un curso', 'aula-virtual' ); ?></h3>
			<p><?php esc_html_e( 'La edición siempre pertenece a un curso existente.', 'aula-virtual' ); ?></p>
			<p style="margin-top:12px"><a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=av_course' ) ); ?>"><?php esc_html_e( 'Crear curso', 'aula-virtual' ); ?></a></p>
		</div>
		<?php return; ?>
	<?php endif; ?>

	<div class="av-panel" style="border-top:1px solid var(--av-border);border-radius:8px">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( EditionsScreen::ACTION_SAVE_EDITION ); ?>">
			<?php wp_nonce_field( EditionsScreen::ACTION_SAVE_EDITION ); ?>
			<?php
			$av_values  = null;
			$av_courses = $courses;
			require AV_PATH . 'admin/views/partials/edition-fields.php';
			?>
			<p class="submit">
				<?php submit_button( __( 'Crear edición', 'aula-virtual' ), 'primary', 'submit', false ); ?>
				<a class="button button-link" href="<?php echo esc_url( AdminMenu::editions_url() ); ?>"><?php esc_html_e( 'Cancelar', 'aula-virtual' ); ?></a>
			</p>
		</form>
	</div>
</div>
