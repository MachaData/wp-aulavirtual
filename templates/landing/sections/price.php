<?php
/**
 * Price: a single highlighted card with what is included.
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

$av_icon    = $vm['icon'];
$av_primary = $vm['primary'];
?>
<section class="av-section av-section--soft av-price" id="av-price">
	<div class="av-container">
		<div class="av-price__card">
			<div class="av-price__main">
				<p class="av-eyebrow"><?php echo esc_html( $section['title'] ); ?></p>
				<h2 class="av-price__course"><?php echo esc_html( $vm['course'] ? get_the_title( $vm['course'] ) : '' ); ?></h2>
				<?php if ( null !== $av_primary ) : ?>
					<p class="av-price__edition"><?php echo esc_html( $av_primary['name'] . ( '' !== $av_primary['start_date'] ? ' · ' . sprintf( /* translators: %s: date. */ __( 'inicia el %s', 'aula-virtual' ), $av_primary['start_date'] ) : '' ) ); ?></p>
				<?php endif; ?>
				<?php if ( '' !== $av_price ) : ?>
					<p class="av-price__amount">
						<?php if ( '' !== $section['old_price'] ) : ?>
							<s class="av-price__old"><?php echo esc_html( $section['old_price'] ); ?></s>
						<?php endif; ?>
						<span><?php echo esc_html( $av_price ); ?></span>
					</p>
				<?php endif; ?>
				<?php if ( null !== $av_button ) : ?>
					<a class="av-btn av-btn--primary av-btn--lg av-btn--block" href="<?php echo esc_url( $av_button['url'] ); ?>"><?php echo esc_html( $av_button['text'] ); ?><?php echo $av_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></a>
				<?php endif; ?>
				<?php if ( '' !== $section['note'] ) : ?>
					<div class="av-price__note"><?php echo wp_kses_post( $section['note'] ); ?></div>
				<?php endif; ?>
			</div>
			<?php if ( ! empty( $section['includes'] ) ) : ?>
				<div class="av-price__includes">
					<p class="av-price__label"><?php esc_html_e( 'Incluye', 'aula-virtual' ); ?></p>
					<ul class="av-checklist">
						<?php foreach ( $section['includes'] as $av_item ) : ?>
							<li><?php echo $av_icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><span><?php echo esc_html( $av_item ); ?></span></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>
