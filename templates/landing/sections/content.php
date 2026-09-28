<?php
/**
 * Content section: curriculum of the next edition or a manual list.
 *
 * @package SIQA\AulaVirtual
 *
 * @var array<string, mixed> $section
 * @var array<string, mixed> $vm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$av_groups = ! empty( $section['from_curriculum'] ) ? $vm['curriculum'] : array();

if ( array() === $av_groups && ! empty( $section['items'] ) ) {
	$av_groups = array( array( 'title' => '', 'lessons' => $section['items'] ) );
}

$av_video = '' !== $section['video_url'] ? $vm['video']( $section['video_url'] ) : '';

if ( array() === $av_groups && '' === $section['text'] && '' === $av_video ) {
	return;
}
?>
<section class="av-section av-content">
	<div class="av-container">
		<h2 class="av-section__title"><?php echo esc_html( $section['title'] ); ?></h2>
		<?php if ( '' !== $section['text'] ) : ?>
			<div class="av-prose"><?php echo wp_kses_post( $section['text'] ); ?></div>
		<?php endif; ?>
		<?php if ( '' !== $av_video ) : ?>
			<div class="av-video"><?php echo $av_video; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitised embed. ?></div>
		<?php endif; ?>
		<?php if ( array() !== $av_groups ) : ?>
			<div class="av-curriculum">
				<?php foreach ( $av_groups as $av_index => $av_group ) : ?>
					<details class="av-curriculum__module" <?php echo 0 === $av_index ? 'open' : ''; ?>>
						<summary>
							<?php echo esc_html( '' !== $av_group['title'] ? $av_group['title'] : __( 'Sesiones', 'aula-virtual' ) ); ?>
							<span class="av-curriculum__count"><?php echo esc_html( (string) count( $av_group['lessons'] ) ); ?></span>
						</summary>
						<ol>
							<?php foreach ( $av_group['lessons'] as $av_lesson ) : ?>
								<li><?php echo esc_html( $av_lesson ); ?></li>
							<?php endforeach; ?>
						</ol>
					</details>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
