<?php
/**
 * Campus dashboard: the courses of the student.
 *
 * @package SIQA\AulaVirtual
 *
 * @var array<int, array<string, mixed>> $cards
 * @var WP_User                          $user
 * @var array<int, array<string, mixed>> $announcements
 * @var \SIQA\AulaVirtual\Campus\CampusController $controller
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

	<p class="av-campus__nav">
		<a href="<?php echo esc_url( $controller->campus_url( array( \SIQA\AulaVirtual\Campus\CampusController::QUERY_PROFILE => 1 ) ) ); ?>"><?php esc_html_e( 'Mis datos', 'aula-virtual' ); ?></a>
		&middot;
		<a href="<?php echo esc_url( wp_logout_url( $controller->campus_url() ) ); ?>"><?php esc_html_e( 'Salir', 'aula-virtual' ); ?></a>
	</p>

	<?php if ( ! empty( $announcements ) ) : ?>
		<section class="av-announcements">
			<h3><?php esc_html_e( 'Anuncios recientes', 'aula-virtual' ); ?></h3>
			<?php foreach ( $announcements as $av_news ) : ?>
				<article class="av-announcement">
					<h4><?php echo esc_html( (string) $av_news['title'] ); ?></h4>
					<p class="av-announcement__meta"><?php echo esc_html( (string) $av_news['course_title'] ); ?> &middot; <?php echo esc_html( mysql2date( (string) get_option( 'date_format' ), (string) $av_news['created_at'] ) ); ?></p>
					<div class="av-announcement__body"><?php echo wp_kses_post( wpautop( (string) $av_news['content'] ) ); ?></div>
				</article>
			<?php endforeach; ?>
		</section>
	<?php endif; ?>

	<?php if ( empty( $cards ) ) : ?>
		<p><?php esc_html_e( 'Todavía no estás matriculado en ningún curso.', 'aula-virtual' ); ?></p>
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
						<?php if ( ! empty( $av_card['certificate_url'] ) ) : ?>
							&middot; <a href="<?php echo esc_url( (string) $av_card['certificate_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Ver certificado', 'aula-virtual' ); ?></a>
						<?php endif; ?>
					</p>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
