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

if ( null === $av_button && '' === $section['whatsapp'] ) {
	return;
}

$av_icon  = $vm['icon'];
$av_bg    = $vm['image']( (int) $section['background_id'], 'full' );
$av_style = '';

if ( '' !== $av_bg ) {
	$av_style .= '--av-cta-image:url(' . esc_url( $av_bg ) . ');';
}

if ( '' !== $section['bg_color'] ) {
	$av_style .= '--av-cta-bg:' . $section['bg_color'] . ';';
}

$av_primary = $vm['primary'];
$av_wa      = '' !== $section['whatsapp'] && ( null === $av_button || ! str_starts_with( $av_button['url'], 'https://wa.me/' ) );
?>
<section class="av-cta<?php echo '' !== $av_bg ? ' av-cta--has-bg' : ''; ?>" id="av-cta"<?php echo '' !== $av_style ? ' style="' . esc_attr( $av_style ) . '"' : ''; ?>>
	<div class="av-container av-cta__inner">
		<div class="av-cta__copy">
			<h2 class="av-cta__title"><?php echo esc_html( $section['title'] ); ?></h2>
			<?php if ( '' !== $section['text'] ) : ?>
				<div class="av-prose"><?php echo wp_kses_post( $section['text'] ); ?></div>
			<?php elseif ( null !== $av_primary && '' !== $av_primary['start_date'] ) : ?>
				<p><?php echo esc_html( sprintf( /* translators: 1: edition, 2: date. */ __( '%1$s comienza el %2$s. Asegura tu cupo hoy.', 'aula-virtual' ), $av_primary['name'], $av_primary['start_date'] ) ); ?></p>
			<?php endif; ?>
		</div>
		<div class="av-cta__actions">
			<?php if ( null !== $av_button ) : ?>
				<a class="av-btn av-btn--primary av-btn--lg" href="<?php echo esc_url( $av_button['url'] ); ?>"><?php echo esc_html( $av_button['text'] ); ?><?php echo $av_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></a>
			<?php endif; ?>
			<?php if ( $av_wa ) : ?>
				<a class="av-btn av-btn--light av-btn--lg" href="<?php echo esc_url( 'https://wa.me/' . ltrim( $section['whatsapp'], '+' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo $av_icon( 'chat' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Escríbenos por WhatsApp', 'aula-virtual' ); ?></a>
			<?php endif; ?>
		</div>
	</div>
</section>
