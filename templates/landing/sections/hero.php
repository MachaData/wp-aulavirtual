<?php
/**
 * Hero: title, key facts, price and the main button, with the intro video,
 * the image or a decorative card so the column is never empty.
 *
 * @package SIQA\AulaVirtual
 *
 * @var array<string, mixed>                $section
 * @var array<string, mixed>                $vm
 * @var array<string, array<string, mixed>> $landing
 */

use SIQA\AulaVirtual\Landing\LandingRenderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$av_icon    = $vm['icon'];
$av_primary = $vm['primary'];
$av_button  = $vm['buttons']['hero'];
$av_bg      = $vm['image']( (int) $section['background_id'], 'full' );
$av_image   = $vm['image']( (int) $section['image_id'], 'large' );
$av_video   = '' !== $section['video_url'] ? $vm['video']( $section['video_url'] ) : '';
$av_price   = (string) ( $av_primary['price'] ?? '' );
$av_price   = '' === $av_price ? (string) $landing['price']['price'] : $av_price;
$av_old     = (string) $landing['price']['old_price'];
$av_style   = '';

if ( '' !== $av_bg ) {
	$av_style .= '--av-hero-image:url(' . esc_url( $av_bg ) . ');';
}

if ( '' !== $section['bg_color'] ) {
	$av_style .= '--av-hero-bg:' . $section['bg_color'] . ';';
}

$av_facts = array();

if ( null !== $av_primary ) {
	if ( '' !== $av_primary['start_date'] ) {
		$av_facts[] = array( 'calendar', __( 'Inicio', 'aula-virtual' ), $av_primary['start_date'] );
	}

	$av_facts[] = array( 'video', __( 'Modalidad', 'aula-virtual' ), $av_primary['modality'] );

	$av_schedule = trim( $av_primary['schedule_days'] . ' ' . $av_primary['schedule_time'] );

	if ( '' !== $av_schedule ) {
		$av_facts[] = array( 'clock', __( 'Horario', 'aula-virtual' ), $av_schedule );
	}
}

if ( $vm['stats']['sessions'] > 0 ) {
	$av_duration = LandingRenderer::duration_label( (int) $vm['stats']['minutes'] );
	$av_facts[]  = array(
		'book',
		__( 'Contenido', 'aula-virtual' ),
		sprintf( /* translators: %d: number of sessions. */ _n( '%d sesión', '%d sesiones', (int) $vm['stats']['sessions'], 'aula-virtual' ), (int) $vm['stats']['sessions'] ) . ( '' !== $av_duration ? ' · ' . $av_duration : '' ),
	);
}

$av_seats = null === $av_primary ? null : $av_primary['seats_left'];
?>
<section class="av-hero<?php echo '' !== $av_bg ? ' av-hero--image' : ''; ?>" id="av-hero"<?php echo '' !== $av_style ? ' style="' . esc_attr( $av_style ) . '"' : ''; ?>>
	<div class="av-container av-hero__grid">
		<div class="av-hero__copy">
			<?php if ( '' !== $section['kicker'] ) : ?>
				<p class="av-kicker"><?php echo esc_html( $section['kicker'] ); ?></p>
			<?php elseif ( null !== $av_primary ) : ?>
				<p class="av-kicker"><?php echo esc_html( $av_primary['status'] . ' · ' . $av_primary['name'] ); ?></p>
			<?php endif; ?>

			<h1 class="av-hero__title"><?php echo esc_html( $section['title'] ); ?></h1>

			<?php if ( '' !== $section['subtitle'] ) : ?>
				<p class="av-hero__lead"><?php echo esc_html( $section['subtitle'] ); ?></p>
			<?php endif; ?>

			<?php if ( '' !== $section['text'] ) : ?>
				<div class="av-hero__text"><?php echo wp_kses_post( $section['text'] ); ?></div>
			<?php endif; ?>

			<?php if ( array() !== $av_facts ) : ?>
				<ul class="av-facts">
					<?php foreach ( $av_facts as $av_fact ) : ?>
						<li class="av-fact">
							<span class="av-fact__icon"><?php echo $av_icon( $av_fact[0] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
							<span class="av-fact__body"><span class="av-fact__label"><?php echo esc_html( $av_fact[1] ); ?></span><strong class="av-fact__value"><?php echo esc_html( $av_fact[2] ); ?></strong></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<?php if ( null !== $av_button || '' !== $av_price ) : ?>
				<div class="av-hero__buy">
					<?php if ( '' !== $av_price ) : ?>
						<p class="av-hero__price">
							<?php if ( '' !== $av_old ) : ?>
								<s><?php echo esc_html( $av_old ); ?></s>
							<?php endif; ?>
							<strong><?php echo esc_html( $av_price ); ?></strong>
						</p>
					<?php endif; ?>
					<?php if ( null !== $av_button ) : ?>
						<a class="av-btn av-btn--primary av-btn--lg" href="<?php echo esc_url( $av_button['url'] ); ?>"><?php echo esc_html( $av_button['text'] ); ?><?php echo $av_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></a>
					<?php endif; ?>
					<?php if ( $vm['stats']['sessions'] > 0 ) : ?>
						<a class="av-btn av-btn--ghost av-btn--lg" href="#av-content"><?php esc_html_e( 'Ver temario', 'aula-virtual' ); ?></a>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( null !== $av_seats ) : ?>
				<p class="av-hero__note">
					<?php
					echo esc_html(
						0 === $av_seats
							? __( 'Cupos agotados para esta edición.', 'aula-virtual' )
							: sprintf( /* translators: %d: seats left. */ _n( 'Queda %d cupo', 'Quedan %d cupos', $av_seats, 'aula-virtual' ), $av_seats )
					);
					?>
				</p>
			<?php endif; ?>
		</div>

		<div class="av-hero__media">
			<?php if ( '' !== $av_video ) : ?>
				<div class="av-media av-media--video"><?php echo $av_video; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitised embed. ?></div>
			<?php elseif ( '' !== $av_image ) : ?>
				<div class="av-media"><img src="<?php echo esc_url( $av_image ); ?>" alt="<?php echo esc_attr( $section['title'] ); ?>" loading="eager" fetchpriority="high"></div>
			<?php else : ?>
				<div class="av-media av-media--art" aria-hidden="true">
					<svg class="av-art" viewBox="0 0 400 300" fill="none">
						<g stroke="currentColor" stroke-width="1" opacity=".55"><path d="M60 220 120 170 170 190 230 110 300 140 340 70"/><path d="M230 110 250 60"/><path d="M170 190 190 250"/></g>
						<g fill="currentColor"><circle cx="60" cy="220" r="3"/><circle cx="120" cy="170" r="4"/><circle cx="170" cy="190" r="3"/><circle cx="230" cy="110" r="5"/><circle cx="300" cy="140" r="3"/><circle cx="340" cy="70" r="4"/><circle cx="250" cy="60" r="3"/><circle cx="190" cy="250" r="3"/></g>
						<g fill="currentColor" opacity=".35"><circle cx="40" cy="60" r="1.5"/><circle cx="90" cy="100" r="1"/><circle cx="150" cy="40" r="1.5"/><circle cx="370" cy="200" r="1.5"/><circle cx="320" cy="260" r="1"/><circle cx="270" cy="220" r="1.5"/><circle cx="20" cy="150" r="1"/><circle cx="360" cy="20" r="1"/></g>
					</svg>
					<?php if ( null !== $av_primary ) : ?>
						<p class="av-media__caption"><span><?php echo esc_html( $av_primary['name'] ); ?></span><?php echo '' !== $av_primary['start_date'] ? esc_html( $av_primary['start_date'] ) : ''; ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>
