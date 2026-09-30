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

use SIQA\AulaVirtual\Campus\CampusController;
use SIQA\AulaVirtual\Editions\EditionStatus;
use SIQA\AulaVirtual\Landing\LandingRenderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$av_first = '' !== trim( (string) $user->first_name ) ? (string) $user->first_name : (string) $user->display_name;
?>
<div class="av-campus av-campus--dashboard">
	<div class="av-c-head">
		<div>
			<p class="av-c-eyebrow"><?php esc_html_e( 'Mi campus', 'aula-virtual' ); ?></p>
			<h1 class="av-c-title">
				<?php
				printf(
					/* translators: %s: student first name. */
					esc_html__( 'Hola, %s', 'aula-virtual' ),
					esc_html( $av_first )
				);
				?>
			</h1>
			<p class="av-c-lead"><?php esc_html_e( 'Aquí están tus cursos. Continúa donde lo dejaste.', 'aula-virtual' ); ?></p>
		</div>
		<nav class="av-c-menu" aria-label="<?php esc_attr_e( 'Menú del campus', 'aula-virtual' ); ?>">
			<a class="is-active" href="<?php echo esc_url( $controller->campus_url() ); ?>"><?php echo LandingRenderer::icon( 'book' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?><?php esc_html_e( 'Mis cursos', 'aula-virtual' ); ?></a>
			<a href="<?php echo esc_url( $controller->campus_url( array( CampusController::QUERY_PROFILE => 1 ) ) ); ?>"><?php echo LandingRenderer::icon( 'user' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?><?php esc_html_e( 'Mis datos', 'aula-virtual' ); ?></a>
			<a href="<?php echo esc_url( wp_logout_url( $controller->campus_url() ) ); ?>"><?php echo LandingRenderer::icon( 'logout' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?><?php esc_html_e( 'Salir', 'aula-virtual' ); ?></a>
		</nav>
	</div>

	<?php if ( empty( $cards ) ) : ?>
		<div class="av-c-empty">
			<?php echo LandingRenderer::icon( 'book' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?>
			<h2><?php esc_html_e( 'Todavía no tienes cursos', 'aula-virtual' ); ?></h2>
			<p><?php esc_html_e( 'Cuando tu matrícula quede confirmada, tu curso aparecerá aquí.', 'aula-virtual' ); ?></p>
			<a class="av-c-btn" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Ver cursos', 'aula-virtual' ); ?></a>
		</div>
	<?php else : ?>
		<ul class="av-c-courses">
			<?php foreach ( $cards as $av_card ) : ?>
				<?php
				$av_course   = $av_card['course'] instanceof WP_Post ? $av_card['course'] : null;
				$av_title    = null !== $av_course ? get_the_title( $av_course ) : __( 'Curso', 'aula-virtual' );
				$av_cover    = null !== $av_course ? (string) get_the_post_thumbnail_url( $av_course, 'medium_large' ) : '';
				$av_percent  = max( 0.0, min( 100.0, (float) $av_card['enrollment']['progress_percentage'] ) );
				$av_action   = $av_percent <= 0 ? __( 'Empezar el curso', 'aula-virtual' ) : ( $av_percent >= 100 ? __( 'Repasar el curso', 'aula-virtual' ) : __( 'Continuar', 'aula-virtual' ) );
				?>
				<li class="av-c-course">
					<a class="av-c-course__cover" href="<?php echo esc_url( (string) $av_card['url'] ); ?>" tabindex="-1" aria-hidden="true">
						<?php if ( '' !== $av_cover ) : ?>
							<img src="<?php echo esc_url( $av_cover ); ?>" alt="" loading="lazy">
						<?php else : ?>
							<span class="av-c-course__placeholder"><?php echo LandingRenderer::icon( 'star' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?></span>
						<?php endif; ?>
					</a>
					<div class="av-c-course__body">
						<p class="av-c-course__meta">
							<span><?php echo esc_html( (string) $av_card['edition']['name'] ); ?></span>
							<span class="av-c-badge"><?php echo esc_html( EditionStatus::label( (string) $av_card['edition']['status'] ) ); ?></span>
						</p>
						<h2 class="av-c-course__title"><a href="<?php echo esc_url( (string) $av_card['url'] ); ?>"><?php echo esc_html( $av_title ); ?></a></h2>
						<div class="av-c-progress" role="img" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: percentage. */ __( '%s completado', 'aula-virtual' ), number_format_i18n( $av_percent, 0 ) . '%' ) ); ?>">
							<span class="av-c-progress__bar"><span style="width:<?php echo esc_attr( (string) round( $av_percent, 1 ) ); ?>%"></span></span>
							<span class="av-c-progress__label"><?php echo esc_html( sprintf( /* translators: %s: percentage. */ __( '%s completado', 'aula-virtual' ), number_format_i18n( $av_percent, 0 ) . '%' ) ); ?></span>
						</div>
						<div class="av-c-course__actions">
							<a class="av-c-btn" href="<?php echo esc_url( (string) $av_card['url'] ); ?>"><?php echo esc_html( $av_action ); ?></a>
							<?php if ( ! empty( $av_card['certificate_url'] ) ) : ?>
								<a class="av-c-link" href="<?php echo esc_url( (string) $av_card['certificate_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo LandingRenderer::icon( 'award' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?><?php esc_html_e( 'Ver certificado', 'aula-virtual' ); ?></a>
							<?php endif; ?>
						</div>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<?php if ( ! empty( $announcements ) ) : ?>
		<section class="av-c-section">
			<h2 class="av-c-section__title"><?php esc_html_e( 'Anuncios recientes', 'aula-virtual' ); ?></h2>
			<div class="av-c-news">
				<?php foreach ( $announcements as $av_news ) : ?>
					<article class="av-c-news__item">
						<span class="av-c-news__icon"><?php echo LandingRenderer::icon( 'megaphone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?></span>
						<div>
							<p class="av-c-news__meta"><?php echo esc_html( (string) $av_news['course_title'] ); ?> &middot; <?php echo esc_html( mysql2date( (string) get_option( 'date_format' ), (string) $av_news['created_at'] ) ); ?></p>
							<h3><?php echo esc_html( (string) $av_news['title'] ); ?></h3>
							<div class="av-c-prose"><?php echo wp_kses_post( wpautop( (string) $av_news['content'] ) ); ?></div>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>
</div>
