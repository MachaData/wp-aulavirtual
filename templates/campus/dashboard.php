<?php
/**
 * Campus dashboard: the courses of the student.
 *
 * @package SIQA\AulaVirtual
 *
 * @var array<int, array<string, mixed>> $cards
 * @var WP_User                          $user
 */

use SIQA\AulaVirtual\Editions\EditionStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="av-campus av-campus--dashboard">
	<h2>
		<?php
		printf(
			/* translators: %s: student display name. */
			esc_html__( 'Hola, %s', 'aula-virtual' ),
			esc_html( $user->display_name )
		);
		?>
	</h2>

	<?php if ( empty( $cards ) ) : ?>
		<p><?php esc_html_e( 'Todavia no estas matriculado en ningun curso.', 'aula-virtual' ); ?></p>
	<?php else : ?>
		<ul class="av-course-list">
			<?php foreach ( $cards as $av_card ) : ?>
				<li class="av-course-card">
					<h3>
						<a href="<?php echo esc_url( (string) $av_card['url'] ); ?>">
							<?php echo esc_html( $av_card['course'] instanceof WP_Post ? get_the_title( $av_card['course'] ) : __( 'Curso', 'aula-virtual' ) ); ?>
						</a>
					</h3>
					<p class="av-course-card__edition">
						<?php echo esc_html( (string) $av_card['edition']['name'] ); ?>
						&middot;
						<?php echo esc_html( EditionStatus::label( (string) $av_card['edition']['status'] ) ); ?>
					</p>
					<p class="av-course-card__progress">
						<progress max="100" value="<?php echo esc_attr( (string) (float) $av_card['enrollment']['progress_percentage'] ); ?>"></progress>
						<?php echo esc_html( number_format_i18n( (float) $av_card['enrollment']['progress_percentage'], 0 ) . '%' ); ?>
					</p>
					<p>
						<a href="<?php echo esc_url( (string) $av_card['url'] ); ?>">
							<?php esc_html_e( 'Continuar', 'aula-virtual' ); ?>
						</a>
					</p>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
