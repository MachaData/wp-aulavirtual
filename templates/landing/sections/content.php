<?php
/**
 * Curriculum: modules and sessions of the next edition, or a manual list.
 *
 * @package SIQA\AulaVirtual
 *
 * @var array<string, mixed> $section
 * @var array<string, mixed> $vm
 */

use SIQA\AulaVirtual\Curriculum\LessonType;
use SIQA\AulaVirtual\Landing\LandingRenderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$av_groups = ! empty( $section['from_curriculum'] ) ? $vm['curriculum'] : array();

if ( array() === $av_groups && ! empty( $section['items'] ) ) {
	$av_groups = array(
		array(
			'title'   => '',
			'lessons' => array_map( static fn( string $t ): array => array( 'title' => $t, 'type' => '', 'duration' => 0 ), $section['items'] ),
		),
	);
}

$av_video = '' !== $section['video_url'] ? $vm['video']( $section['video_url'] ) : '';

if ( array() === $av_groups && '' === $section['text'] && '' === $av_video ) {
	return;
}

$av_icon  = $vm['icon'];
$av_types = array(
	LessonType::VIDEO    => 'play',
	LessonType::LIVE     => 'live',
	LessonType::MATERIAL => 'file',
	LessonType::TEXT     => 'text',
);
$av_count = array_sum( array_map( static fn( array $g ): int => count( $g['lessons'] ), $av_groups ) );
$av_time  = LandingRenderer::duration_label( (int) $vm['stats']['minutes'] );
$av_n     = 0;
?>
<section class="av-section av-section--soft av-content" id="av-content">
	<div class="av-container av-container--narrow">
		<header class="av-section__head">
			<p class="av-eyebrow"><?php esc_html_e( 'Temario', 'aula-virtual' ); ?></p>
			<h2 class="av-section__title"><?php echo esc_html( $section['title'] ); ?></h2>
			<?php if ( $av_count > 0 ) : ?>
				<p class="av-section__meta">
					<?php
					echo esc_html(
						sprintf( /* translators: %d: sessions. */ _n( '%d sesión', '%d sesiones', $av_count, 'aula-virtual' ), $av_count )
						. ( $vm['stats']['modules'] > 1 ? ' · ' . sprintf( /* translators: %d: modules. */ __( '%d módulos', 'aula-virtual' ), (int) $vm['stats']['modules'] ) : '' )
						. ( '' !== $av_time ? ' · ' . $av_time : '' )
					);
					?>
				</p>
			<?php endif; ?>
		</header>

		<?php if ( '' !== $section['text'] ) : ?>
			<div class="av-prose av-content__text"><?php echo wp_kses_post( $section['text'] ); ?></div>
		<?php endif; ?>

		<?php if ( '' !== $av_video ) : ?>
			<div class="av-media av-media--video av-content__video"><?php echo $av_video; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitised embed. ?></div>
		<?php endif; ?>

		<?php if ( array() !== $av_groups ) : ?>
			<div class="av-curriculum">
				<?php foreach ( $av_groups as $av_index => $av_group ) : ?>
					<?php $av_has_title = '' !== $av_group['title']; ?>
					<details class="av-module"<?php echo 0 === $av_index || ! $av_has_title ? ' open' : ''; ?>>
						<summary class="av-module__head">
							<span class="av-module__title"><?php echo esc_html( $av_has_title ? $av_group['title'] : ( count( $av_groups ) > 1 ? __( 'Otras sesiones', 'aula-virtual' ) : __( 'Sesiones del curso', 'aula-virtual' ) ) ); ?></span>
							<span class="av-module__count"><?php echo esc_html( sprintf( /* translators: %d: sessions. */ _n( '%d sesión', '%d sesiones', count( $av_group['lessons'] ), 'aula-virtual' ), count( $av_group['lessons'] ) ) ); ?></span>
							<span class="av-module__chevron"><?php echo $av_icon( 'down' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
						</summary>
						<ol class="av-lessons">
							<?php foreach ( $av_group['lessons'] as $av_lesson ) : ?>
								<?php ++$av_n; ?>
								<li class="av-lesson">
									<span class="av-lesson__num"><?php echo esc_html( str_pad( (string) $av_n, 2, '0', STR_PAD_LEFT ) ); ?></span>
									<span class="av-lesson__title"><?php echo esc_html( $av_lesson['title'] ); ?></span>
									<?php if ( isset( $av_types[ $av_lesson['type'] ] ) ) : ?>
										<span class="av-lesson__type" title="<?php echo esc_attr( LessonType::label( $av_lesson['type'] ) ); ?>"><?php echo $av_icon( $av_types[ $av_lesson['type'] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><span class="av-sr"><?php echo esc_html( LessonType::label( $av_lesson['type'] ) ); ?></span></span>
									<?php endif; ?>
									<?php if ( $av_lesson['duration'] > 0 ) : ?>
										<span class="av-lesson__time"><?php echo esc_html( LandingRenderer::duration_label( (int) $av_lesson['duration'] ) ); ?></span>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ol>
					</details>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
