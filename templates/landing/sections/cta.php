<?php
/**
 * Closing call to action.
 *
 * @package SIQA\AulaVirtual
 *
 * @var array<string, mixed> $section
 * @var array<string, mixed> $vm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$av_button = $vm['buttons']['cta'];
$av_bg     = $vm['image']( (int) $section['background_id'], 'full' );
$av_style  = '';

if ( '' !== $av_bg ) {
	$av_style .= 'background-image:url(' . esc_url( $av_bg ) . ');';
}

if ( '' !== $section['bg_color'] ) {
	$av_style .= 'background-color:' . esc_attr( $section['bg_color'] ) . ';';
}

if ( null === $av_button && '' === $section['whatsapp'] ) {
	return;
}
?>
<section class="av-section av-cta<?php echo '' !== $av_bg ? ' av-cta--has-bg' : ''; ?>" id="av-cta" style="<?php echo esc_attr( $av_style ); ?>">
	<div class="av-container av-cta__inner">
		<h2 class="av-section__title"><?php echo esc_html( $section['title'] ); ?></h2>
		<?php if ( '' !== $section['text'] ) : ?>
			<div class="av-prose"><?php echo wp_kses_post( $section['text'] ); ?></div>
		<?php endif; ?>
		<p class="av-cta__actions">
			<?php if ( null !== $av_button ) : ?>
				<a class="av-btn av-btn--primary av-btn--lg" href="<?php echo esc_url( $av_button['url'] ); ?>"><?php echo esc_html( $av_button['text'] ); ?></a>
			<?php endif; ?>
			<?php if ( '' !== $section['whatsapp'] && ( null === $av_button || ! str_starts_with( $av_button['url'], 'https://wa.me/' ) ) ) : ?>
				<a class="av-btn av-btn--whatsapp" href="<?php echo esc_url( 'https://wa.me/' . ltrim( $section['whatsapp'], '+' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Escríbenos por WhatsApp', 'aula-virtual' ); ?></a>
			<?php endif; ?>
		</p>
	</div>
</section>
