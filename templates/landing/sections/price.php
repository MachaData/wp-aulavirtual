<?php
/**
 * Price section.
 *
 * @package SIQA\AulaVirtual
 *
 * @var array<string, mixed> $section
 * @var array<string, mixed> $vm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$av_price  = '' !== $section['price'] ? $section['price'] : ( $vm['primary']['price'] ?? '' );
$av_button = $vm['buttons']['price'];

if ( '' === $av_price && null === $av_button && empty( $section['includes'] ) ) {
	return;
}
?>
<section class="av-section av-price" id="av-price">
	<div class="av-container av-price__card">
		<h2 class="av-section__title"><?php echo esc_html( $section['title'] ); ?></h2>
		<?php if ( '' !== $av_price ) : ?>
			<p class="av-price__amount">
				<?php if ( '' !== $section['old_price'] ) : ?>
					<s class="av-price__old"><?php echo esc_html( $section['old_price'] ); ?></s>
				<?php endif; ?>
				<span><?php echo esc_html( $av_price ); ?></span>
			</p>
		<?php endif; ?>
		<?php if ( ! empty( $section['includes'] ) ) : ?>
			<ul class="av-checklist av-checklist--compact">
				<?php foreach ( $section['includes'] as $av_item ) : ?>
					<li><?php echo esc_html( $av_item ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<?php if ( null !== $av_button ) : ?>
			<p><a class="av-btn av-btn--primary av-btn--lg" href="<?php echo esc_url( $av_button['url'] ); ?>"><?php echo esc_html( $av_button['text'] ); ?></a></p>
		<?php endif; ?>
		<?php if ( '' !== $section['note'] ) : ?>
			<div class="av-price__note"><?php echo wp_kses_post( $section['note'] ); ?></div>
		<?php endif; ?>
	</div>
</section>
