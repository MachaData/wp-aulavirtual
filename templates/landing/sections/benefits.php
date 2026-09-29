<?php
/**
 * Benefits: what the student will achieve.
 *
 * @package SIQA\AulaVirtual
 *
 * @var array<string, mixed> $section
 * @var array<string, mixed> $vm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $section['items'] ) ) {
	return;
}

$av_icon  = $vm['icon'];
$av_image = $vm['image']( (int) $section['image_id'], 'large' );
?>
<section class="av-section av-benefits" id="av-benefits">
	<div class="av-container<?php echo '' !== $av_image ? ' av-split' : ''; ?>">
		<div>
			<header class="av-section__head">
				<p class="av-eyebrow"><?php esc_html_e( 'Resultados', 'aula-virtual' ); ?></p>
				<h2 class="av-section__title"><?php echo esc_html( $section['title'] ); ?></h2>
			</header>
			<ul class="av-benefits__grid<?php echo '' !== $av_image ? ' av-benefits__grid--one' : ''; ?>">
				<?php foreach ( $section['items'] as $av_item ) : ?>
					<li class="av-benefit">
						<span class="av-benefit__icon"><?php echo $av_icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
						<span><?php echo esc_html( $av_item ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php if ( '' !== $av_image ) : ?>
			<div class="av-media av-benefits__image"><img src="<?php echo esc_url( $av_image ); ?>" alt="" loading="lazy"></div>
		<?php endif; ?>
	</div>
</section>
