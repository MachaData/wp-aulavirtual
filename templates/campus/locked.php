<?php
/**
 * Campus: a session that is not released yet.
 *
 * @package SIQA\AulaVirtual
 *
 * @var array<string, mixed> $lesson
 * @var string               $available_at
 * @var string               $timezone
 * @var string               $back_url
 */

use SIQA\AulaVirtual\Landing\LandingRenderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="av-campus av-campus--locked">
	<a class="av-c-back" href="<?php echo esc_url( $back_url ); ?>"><?php echo LandingRenderer::icon( 'back' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?><?php esc_html_e( 'Volver al temario', 'aula-virtual' ); ?></a>

	<div class="av-c-empty">
		<?php echo LandingRenderer::icon( 'calendar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?>
		<h1 class="av-c-title"><?php echo esc_html( (string) $lesson['title'] ); ?></h1>
		<p class="av-locked__message">
			<?php
			printf(
				/* translators: 1: date and time, 2: timezone. */
				esc_html__( 'Esta sesión se abre el %1$s (%2$s). Te avisaremos aquí mismo cuando esté disponible.', 'aula-virtual' ),
				'<strong>' . esc_html( $available_at ) . '</strong>',
				esc_html( $timezone )
			);
			?>
		</p>
	</div>
</div>
