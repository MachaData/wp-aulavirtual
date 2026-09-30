<?php
/**
 * Campus: sessions of an edition.
 *
 * @package SIQA\AulaVirtual
 *
 * @var array<string, mixed>             $edition
 * @var WP_Post|null                     $course
 * @var array<int, array<string, mixed>> $items
 * @var array<int, array<string, mixed>> $sections
 * @var float                            $percentage
 * @var array<int, array<string, mixed>> $announcements
 * @var string                           $back_url
 * @var bool                             $can_retake
 * @var string                           $certificate_url
 */

use SIQA\AulaVirtual\Campus\CampusController;
use SIQA\AulaVirtual\Curriculum\LessonType;
use SIQA\AulaVirtual\Landing\LandingRenderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$av_title     = $course instanceof WP_Post ? get_the_title( $course ) : (string) $edition['name'];
$av_percent   = max( 0.0, min( 100.0, (float) $percentage ) );
$av_done      = count( array_filter( $items, static fn( array $i ): bool => ! empty( $i['completed'] ) ) );
$av_total     = count( $items );
$av_next      = null;
$av_type_icon = array(
	LessonType::VIDEO    => 'play',
	LessonType::TEXT     => 'text',
	LessonType::LIVE     => 'live',
	LessonType::MATERIAL => 'file',
);

foreach ( $items as $av_candidate ) {
	if ( empty( $av_candidate['completed'] ) && ! empty( $av_candidate['available'] ) ) {
		$av_next = $av_candidate;
		break;
	}
}
?>
<div class="av-campus av-campus--edition">
	<a class="av-c-back" href="<?php echo esc_url( $back_url ); ?>"><?php echo LandingRenderer::icon( 'back' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?><?php esc_html_e( 'Mis cursos', 'aula-virtual' ); ?></a>

	<div class="av-c-hero">
		<div class="av-c-hero__text">
			<p class="av-c-eyebrow"><?php echo esc_html( (string) $edition['name'] ); ?></p>
			<h1 class="av-c-title"><?php echo esc_html( $av_title ); ?></h1>
			<div class="av-c-progress av-c-progress--large">
				<span class="av-c-progress__bar"><span style="width:<?php echo esc_attr( (string) round( $av_percent, 1 ) ); ?>%"></span></span>
				<span class="av-c-progress__label">
					<?php
					printf(
						/* translators: 1: percentage, 2: completed sessions, 3: total sessions. */
						esc_html__( '%1$s completado · %2$d de %3$d sesiones', 'aula-virtual' ),
						esc_html( number_format_i18n( $av_percent, 0 ) . '%' ),
						(int) $av_done,
						(int) $av_total
					);
					?>
				</span>
			</div>
		</div>
		<div class="av-c-hero__action">
			<?php if ( null !== $av_next ) : ?>
				<p class="av-c-hero__label"><?php echo esc_html( 0 === $av_done ? __( 'Tu primera sesión', 'aula-virtual' ) : __( 'Tu siguiente sesión', 'aula-virtual' ) ); ?></p>
				<p class="av-c-hero__next"><?php echo esc_html( (string) $av_next['lesson']['title'] ); ?></p>
				<a class="av-c-btn av-c-btn--block" href="<?php echo esc_url( (string) $av_next['url'] ); ?>"><?php echo esc_html( 0 === $av_done ? __( 'Empezar', 'aula-virtual' ) : __( 'Continuar', 'aula-virtual' ) ); ?><?php echo LandingRenderer::icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?></a>
			<?php elseif ( $av_total > 0 && $av_done >= $av_total ) : ?>
				<p class="av-c-hero__label"><?php esc_html_e( '¡Felicidades!', 'aula-virtual' ); ?></p>
				<p class="av-c-hero__next"><?php esc_html_e( 'Completaste todas las sesiones.', 'aula-virtual' ); ?></p>
				<?php if ( ! empty( $certificate_url ) ) : ?>
					<a class="av-c-btn av-c-btn--block" href="<?php echo esc_url( $certificate_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo LandingRenderer::icon( 'award' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?><?php esc_html_e( 'Ver mi certificado', 'aula-virtual' ); ?></a>
				<?php endif; ?>
			<?php else : ?>
				<p class="av-c-hero__label"><?php esc_html_e( 'Próximamente', 'aula-virtual' ); ?></p>
				<p class="av-c-hero__next"><?php esc_html_e( 'Las sesiones se irán abriendo según el calendario del curso.', 'aula-virtual' ); ?></p>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( ! empty( $announcements ) ) : ?>
		<section class="av-c-section">
			<h2 class="av-c-section__title"><?php esc_html_e( 'Anuncios', 'aula-virtual' ); ?></h2>
			<div class="av-c-news">
				<?php foreach ( $announcements as $av_index => $av_news ) : ?>
					<details class="av-c-news__item av-c-news__item--toggle"<?php echo 0 === $av_index ? ' open' : ''; ?>>
						<summary>
							<span class="av-c-news__icon"><?php echo LandingRenderer::icon( 'megaphone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?></span>
							<span>
								<span class="av-c-news__meta"><?php echo esc_html( mysql2date( (string) get_option( 'date_format' ), (string) $av_news['created_at'] ) ); ?></span>
								<strong><?php echo esc_html( (string) $av_news['title'] ); ?></strong>
							</span>
						</summary>
						<div class="av-c-prose"><?php echo wp_kses_post( wpautop( (string) $av_news['content'] ) ); ?></div>
					</details>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<section class="av-c-section">
		<h2 class="av-c-section__title"><?php esc_html_e( 'Temario', 'aula-virtual' ); ?></h2>

		<?php if ( empty( $items ) ) : ?>
			<div class="av-c-empty av-c-empty--small">
				<?php echo LandingRenderer::icon( 'calendar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?>
				<h3><?php esc_html_e( 'Las sesiones se publicarán pronto', 'aula-virtual' ); ?></h3>
				<p><?php esc_html_e( 'Te avisaremos por correo cuando estén disponibles.', 'aula-virtual' ); ?></p>
			</div>
		<?php else : ?>
			<?php
			$av_sections = isset( $sections ) && is_array( $sections ) && array() !== $sections ? $sections : array( array( 'title' => '', 'items' => array_map( static fn( array $i, int $n ): array => $i + array( 'number' => $n + 1 ), $items, array_keys( $items ) ), 'done' => $av_done, 'total' => $av_total, 'minutes' => 0 ) );
			?>
			<?php foreach ( $av_sections as $av_s_index => $av_section ) : ?>
				<?php if ( '' !== $av_section['title'] ) : ?>
					<h3 class="av-c-module">
						<span><?php echo esc_html( sprintf( /* translators: 1: section number, 2: section title. */ __( 'Sección %1$d: %2$s', 'aula-virtual' ), $av_s_index + 1, $av_section['title'] ) ); ?></span>
						<small><?php echo esc_html( (int) $av_section['done'] . ' / ' . (int) $av_section['total'] . ( (int) $av_section['minutes'] > 0 ? ' · ' . LandingRenderer::duration_label( (int) $av_section['minutes'] ) : '' ) ); ?></small>
					</h3>
				<?php endif; ?>
			<ol class="av-c-lessons">
				<?php foreach ( $av_section['items'] as $av_item ) : ?>
						<?php
						$av_lesson    = $av_item['lesson'];
						$av_type      = (string) $av_lesson['lesson_type'];
						$av_duration  = LandingRenderer::duration_label( (int) ( $av_lesson['duration'] ?? 0 ) );
						$av_available = ! empty( $av_item['available'] );
						$av_state     = $av_item['completed'] ? 'is-completed' : ( $av_available ? 'is-open' : 'is-locked' );
						$av_is_next   = null !== $av_next && (int) $av_next['lesson']['id'] === (int) $av_lesson['id'];
						?>
						<li class="av-c-lesson <?php echo esc_attr( $av_state ); ?><?php echo $av_is_next ? ' is-next' : ''; ?>">
							<span class="av-c-lesson__status" aria-hidden="true">
								<?php
								if ( $av_item['completed'] ) {
									echo LandingRenderer::icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG.
								} elseif ( ! $av_available ) {
									echo LandingRenderer::icon( 'lock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG.
								} else {
									echo esc_html( (string) (int) $av_item['number'] );
								}
								?>
							</span>
							<span class="av-c-lesson__main">
								<?php if ( $av_available ) : ?>
									<a class="av-c-lesson__title" href="<?php echo esc_url( (string) $av_item['url'] ); ?>"><?php echo esc_html( (string) $av_lesson['title'] ); ?></a>
								<?php else : ?>
									<span class="av-c-lesson__title"><?php echo esc_html( (string) $av_lesson['title'] ); ?></span>
								<?php endif; ?>
								<span class="av-c-lesson__meta">
									<?php echo LandingRenderer::icon( $av_type_icon[ $av_type ] ?? 'text' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?>
									<?php echo esc_html( LessonType::label( $av_type ) ); ?>
									<?php if ( '' !== $av_duration ) : ?>
										&middot; <?php echo esc_html( $av_duration ); ?>
									<?php endif; ?>
								</span>
							</span>
							<span class="av-c-lesson__side">
								<?php if ( $av_item['completed'] ) : ?>
									<span class="av-c-badge av-c-badge--done"><?php esc_html_e( 'Completada', 'aula-virtual' ); ?></span>
								<?php elseif ( ! $av_available ) : ?>
									<span class="av-c-lesson__date">
										<?php
										printf(
											/* translators: %s: date. */
											esc_html__( 'Se abre el %s', 'aula-virtual' ),
											esc_html( (string) $av_item['available_at'] )
										);
										?>
									</span>
								<?php else : ?>
									<a class="av-c-lesson__go" href="<?php echo esc_url( (string) $av_item['url'] ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: session title. */ __( 'Abrir %s', 'aula-virtual' ), (string) $av_lesson['title'] ) ); ?>"><?php echo LandingRenderer::icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?></a>
								<?php endif; ?>
							</span>
						</li>
				<?php endforeach; ?>
			</ol>
			<?php endforeach; ?>
		<?php endif; ?>
	</section>

	<?php if ( ! empty( $certificate_url ) || ! empty( $can_retake ) ) : ?>
		<div class="av-c-foot">
			<?php if ( ! empty( $certificate_url ) ) : ?>
				<a class="av-c-link" href="<?php echo esc_url( $certificate_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo LandingRenderer::icon( 'award' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?><?php esc_html_e( 'Ver mi certificado', 'aula-virtual' ); ?></a>
			<?php endif; ?>

			<?php if ( ! empty( $can_retake ) ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="av-retake"
					onsubmit="return confirm('<?php echo esc_js( __( 'Se borrará tu progreso en esta edición y empezarás desde la primera sesión. Tu certificado, si lo tienes, se conserva.', 'aula-virtual' ) ); ?>');">
					<input type="hidden" name="action" value="<?php echo esc_attr( CampusController::ACTION_RETAKE ); ?>">
					<input type="hidden" name="edition_id" value="<?php echo esc_attr( (string) (int) $edition['id'] ); ?>">
					<?php wp_nonce_field( CampusController::ACTION_RETAKE ); ?>
					<button type="submit" class="av-c-btn av-c-btn--ghost"><?php echo esc_html( $av_percent >= 100 ? __( 'Volver a hacer el curso', 'aula-virtual' ) : __( 'Reiniciar mi progreso', 'aula-virtual' ) ); ?></button>
				</form>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
