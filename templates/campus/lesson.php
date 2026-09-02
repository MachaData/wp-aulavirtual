<?php
/**
 * Campus: one session.
 *
 * @package SIQA\AulaVirtual
 *
 * @var array<string, mixed> $lesson
 * @var bool                 $completed
 * @var string               $back_url
 */

use SIQA\AulaVirtual\Campus\CampusController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="av-campus av-campus--lesson">
	<p><a href="<?php echo esc_url( $back_url ); ?>">&larr; <?php esc_html_e( 'Volver al temario', 'aula-virtual' ); ?></a></p>

	<h2><?php echo esc_html( (string) $lesson['title'] ); ?></h2>

	<?php if ( ! empty( $lesson['video_url'] ) ) : ?>
		<div class="av-lesson__video">
			<?php
			// wp_oembed_get() covers YouTube and Vimeo; anything else falls back to a link.
			$av_embed = wp_oembed_get( (string) $lesson['video_url'] );

			if ( false === $av_embed ) {
				printf(
					'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
					esc_url( (string) $lesson['video_url'] ),
					esc_html__( 'Abrir el video', 'aula-virtual' )
				);
			} else {
				echo wp_kses_post( $av_embed );
			}
			?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $lesson['description'] ) ) : ?>
		<p class="av-lesson__description"><?php echo esc_html( (string) $lesson['description'] ); ?></p>
	<?php endif; ?>

	<?php if ( ! empty( $lesson['content'] ) ) : ?>
		<div class="av-lesson__content"><?php echo wp_kses_post( (string) $lesson['content'] ); ?></div>
	<?php endif; ?>

	<?php if ( $completed ) : ?>
		<p class="av-lesson__done"><?php esc_html_e( 'Ya marcaste esta sesion como completada.', 'aula-virtual' ); ?></p>
	<?php else : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( CampusController::ACTION_COMPLETE ); ?>">
			<input type="hidden" name="lesson_id" value="<?php echo esc_attr( (string) (int) $lesson['id'] ); ?>">
			<?php wp_nonce_field( CampusController::ACTION_COMPLETE ); ?>
			<button type="submit"><?php esc_html_e( 'Marcar como completada', 'aula-virtual' ); ?></button>
		</form>
	<?php endif; ?>
</div>
