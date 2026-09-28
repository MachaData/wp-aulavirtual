<?php
/**
 * Instructor section.
 *
 * @package SIQA\AulaVirtual
 *
 * @var array<string, mixed> $section
 * @var array<string, mixed> $vm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( '' === $section['name'] ) {
	return;
}

$av_image = $vm['image']( (int) $section['image_id'], 'medium' );
?>
<section class="av-section av-instructor">
	<div class="av-container av-instructor__grid">
		<?php if ( '' !== $av_image ) : ?>
			<div class="av-instructor__photo"><img src="<?php echo esc_url( $av_image ); ?>" alt="<?php echo esc_attr( $section['name'] ); ?>" loading="lazy"></div>
		<?php endif; ?>
		<div>
			<h2 class="av-section__title"><?php echo esc_html( $section['title'] ); ?></h2>
			<p class="av-instructor__name"><?php echo esc_html( $section['name'] ); ?></p>
			<?php if ( '' !== $section['bio'] ) : ?>
				<div class="av-prose"><?php echo wp_kses_post( $section['bio'] ); ?></div>
			<?php endif; ?>
			<?php if ( ! empty( $section['links'] ) ) : ?>
				<p class="av-instructor__links">
					<?php foreach ( $section['links'] as $av_link ) : ?>
						<a href="<?php echo esc_url( $av_link['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( '' !== $av_link['label'] ? $av_link['label'] : $av_link['url'] ); ?></a>
					<?php endforeach; ?>
				</p>
			<?php endif; ?>
		</div>
	</div>
</section>
