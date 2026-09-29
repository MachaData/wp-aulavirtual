<?php
/**
 * Instructor.
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

$av_image    = $vm['image']( (int) $section['image_id'], 'medium_large' );
$av_initials = strtoupper( implode( '', array_map( static fn( string $w ): string => mb_substr( $w, 0, 1 ), array_slice( preg_split( '/\s+/', trim( (string) $section['name'] ) ) ?: array(), 0, 2 ) ) ) );
?>
<section class="av-section av-instructor" id="av-instructor">
	<div class="av-container">
		<div class="av-instructor__card">
			<div class="av-instructor__photo">
				<?php if ( '' !== $av_image ) : ?>
					<img src="<?php echo esc_url( $av_image ); ?>" alt="<?php echo esc_attr( $section['name'] ); ?>" loading="lazy">
				<?php else : ?>
					<span class="av-instructor__initials" aria-hidden="true"><?php echo esc_html( $av_initials ); ?></span>
				<?php endif; ?>
			</div>
			<div class="av-instructor__body">
				<p class="av-eyebrow"><?php echo esc_html( $section['title'] ); ?></p>
				<h2 class="av-instructor__name"><?php echo esc_html( $section['name'] ); ?></h2>
				<?php if ( '' !== $section['bio'] ) : ?>
					<div class="av-prose"><?php echo wp_kses_post( wpautop( $section['bio'] ) ); ?></div>
				<?php endif; ?>
				<?php if ( ! empty( $section['links'] ) ) : ?>
					<ul class="av-pills">
						<?php foreach ( $section['links'] as $av_link ) : ?>
							<li><a href="<?php echo esc_url( $av_link['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $av_link['label'] ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>
