<?php
/**
 * Hero section.
 *
 * @package SIQA\AulaVirtual
 *
 * @var array<string, mixed> $section
 * @var array<string, mixed> $vm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$av_bg    = $vm['image']( (int) $section['background_id'], 'full' );
$av_style = '';

if ( '' !== $av_bg ) {
	$av_style .= 'background-image:url(' . esc_url( $av_bg ) . ');';
}

if ( '' !== $section['bg_color'] ) {
	$av_style .= 'background-color:' . esc_attr( $section['bg_color'] ) . ';';
}

$av_image  = $vm['image']( (int) $section['image_id'] );
$av_video  = '' !== $section['video_url'] ? $vm['video']( $section['video_url'] ) : '';
$av_button = $vm['buttons']['hero'];
?>
<section class="av-section av-hero<?php echo '' !== $av_bg ? ' av-hero--has-bg' : ''; ?>" style="<?php echo esc_attr( $av_style ); ?>">
	<div class="av-container av-hero__grid">
		<div class="av-hero__copy">
			<?php if ( '' !== $section['kicker'] ) : ?>
				<p class="av-kicker"><?php echo esc_html( $section['kicker'] ); ?></p>
			<?php endif; ?>
			<h1 class="av-hero__title"><?php echo esc_html( $section['title'] ); ?></h1>
			<?php if ( '' !== $section['subtitle'] ) : ?>
				<p class="av-hero__subtitle"><?php echo esc_html( $section['subtitle'] ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $section['text'] ) : ?>
				<div class="av-hero__text"><?php echo wp_kses_post( $section['text'] ); ?></div>
			<?php endif; ?>
			<?php if ( null !== $vm['primary'] ) : ?>
				<ul class="av-hero__facts">
					<?php if ( '' !== $vm['primary']['start_date'] ) : ?>
						<li><strong><?php esc_html_e( 'Inicio', 'aula-virtual' ); ?></strong> <?php echo esc_html( $vm['primary']['start_date'] ); ?></li>
					<?php endif; ?>
					<li><strong><?php esc_html_e( 'Modalidad', 'aula-virtual' ); ?></strong> <?php echo esc_html( $vm['primary']['modality'] ); ?></li>
					<?php if ( '' !== $vm['primary']['schedule_days'] || '' !== $vm['primary']['schedule_time'] ) : ?>
						<li><strong><?php esc_html_e( 'Horario', 'aula-virtual' ); ?></strong> <?php echo esc_html( trim( $vm['primary']['schedule_days'] . ' ' . $vm['primary']['schedule_time'] ) ); ?></li>
					<?php endif; ?>
				</ul>
			<?php endif; ?>
			<?php if ( null !== $av_button ) : ?>
				<p class="av-hero__actions">
					<a class="av-btn av-btn--primary av-btn--lg" href="<?php echo esc_url( $av_button['url'] ); ?>"><?php echo esc_html( $av_button['text'] ); ?></a>
					<?php if ( '' !== ( $vm['primary']['price'] ?? '' ) ) : ?>
						<span class="av-hero__price"><?php echo esc_html( $vm['primary']['price'] ); ?></span>
					<?php endif; ?>
				</p>
			<?php endif; ?>
		</div>
		<div class="av-hero__media">
			<?php if ( '' !== $av_video ) : ?>
				<div class="av-video"><?php echo $av_video; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitised embed. ?></div>
			<?php elseif ( '' !== $av_image ) : ?>
				<img src="<?php echo esc_url( $av_image ); ?>" alt="<?php echo esc_attr( $section['title'] ); ?>" loading="eager">
			<?php endif; ?>
		</div>
	</div>
</section>
