<?php
/**
 * Benefits section.
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

$av_image = $vm['image']( (int) $section['image_id'] );
?>
<section class="av-section av-benefits">
	<div class="av-container<?php echo '' !== $av_image ? ' av-benefits__grid' : ''; ?>">
		<div>
			<h2 class="av-section__title"><?php echo esc_html( $section['title'] ); ?></h2>
			<ul class="av-checklist">
				<?php foreach ( $section['items'] as $av_item ) : ?>
					<li><?php echo esc_html( $av_item ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php if ( '' !== $av_image ) : ?>
			<div class="av-benefits__media"><img src="<?php echo esc_url( $av_image ); ?>" alt="" loading="lazy"></div>
		<?php endif; ?>
	</div>
</section>
