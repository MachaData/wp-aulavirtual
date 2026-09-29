<?php
/**
 * Course landing: renders each enabled section, builds the section menu
 * from the ones that have content, and adds the mobile purchase bar.
 *
 * Themes can override any part under aula-virtual/landing/.
 *
 * @package SIQA\AulaVirtual
 *
 * @var int                                       $course_id
 * @var array<string, array<string, mixed>>       $landing
 * @var array<string, mixed>                      $vm
 * @var \SIQA\AulaVirtual\Landing\LandingRenderer $renderer
 */

use SIQA\AulaVirtual\Landing\LandingData;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$av_html = array();

foreach ( LandingData::sections() as $av_section ) {
	if ( empty( $landing[ $av_section ]['enabled'] ) ) {
		continue;
	}

	$av_html[ $av_section ] = trim(
		$renderer->template(
			'sections/' . $av_section,
			array(
				'course_id' => $course_id,
				'landing'   => $landing,
				'vm'        => $vm,
				'section'   => $landing[ $av_section ],
			)
		)
	);
}

$av_html = array_filter( $av_html, static fn( string $h ): bool => '' !== $h );

// Menu de secciones: solo las que tienen contenido.
$av_menu = array(
	'benefits'   => __( 'Qué aprenderás', 'aula-virtual' ),
	'content'    => __( 'Temario', 'aula-virtual' ),
	'instructor' => __( 'Instructor', 'aula-virtual' ),
	'info'       => __( 'Fechas', 'aula-virtual' ),
	'price'      => __( 'Inversión', 'aula-virtual' ),
	'faq'        => __( 'Preguntas', 'aula-virtual' ),
);
$av_menu = array_intersect_key( $av_menu, $av_html );

$av_primary_button = $vm['buttons']['hero'] ?? $vm['buttons']['price'] ?? $vm['buttons']['cta'] ?? null;
$av_price          = (string) ( $vm['primary']['price'] ?? '' );
$av_price          = '' === $av_price ? (string) $landing['price']['price'] : $av_price;
$av_style          = '--av-accent:' . $vm['accent'] . ';--av-on-accent:' . $vm['on_accent'] . ';';
?>
<main class="av-landing" id="av-landing" style="<?php echo esc_attr( $av_style ); ?>">
	<?php
	if ( isset( $av_html['hero'] ) ) {
		echo $av_html['hero']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- partials escape their own output.
		unset( $av_html['hero'] );
	}
	?>

	<?php if ( count( $av_menu ) >= 2 ) : ?>
		<nav class="av-subnav" aria-label="<?php esc_attr_e( 'Secciones del curso', 'aula-virtual' ); ?>">
			<div class="av-container av-subnav__inner">
				<ul class="av-subnav__links">
					<?php foreach ( $av_menu as $av_key => $av_label ) : ?>
						<li><a href="#av-<?php echo esc_attr( $av_key ); ?>"><?php echo esc_html( $av_label ); ?></a></li>
					<?php endforeach; ?>
				</ul>
				<?php if ( null !== $av_primary_button ) : ?>
					<a class="av-btn av-btn--primary av-btn--sm av-subnav__cta" href="<?php echo esc_url( $av_primary_button['url'] ); ?>"><?php echo esc_html( $av_primary_button['text'] ); ?></a>
				<?php endif; ?>
			</div>
		</nav>
	<?php endif; ?>

	<?php
	foreach ( $av_html as $av_part ) {
		echo $av_part; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- partials escape their own output.
	}
	?>

	<?php if ( null !== $av_primary_button ) : ?>
		<div class="av-sticky-cta" role="region" aria-label="<?php esc_attr_e( 'Inscripción', 'aula-virtual' ); ?>">
			<div class="av-sticky-cta__info">
				<?php if ( '' !== $av_price ) : ?>
					<strong class="av-sticky-cta__price"><?php echo esc_html( $av_price ); ?></strong>
				<?php endif; ?>
				<?php if ( null !== $vm['primary'] && '' !== $vm['primary']['start_date'] ) : ?>
					<span class="av-sticky-cta__date"><?php echo esc_html( sprintf( /* translators: %s: date. */ __( 'Inicia el %s', 'aula-virtual' ), $vm['primary']['start_date'] ) ); ?></span>
				<?php endif; ?>
			</div>
			<a class="av-btn av-btn--primary" href="<?php echo esc_url( $av_primary_button['url'] ); ?>"><?php echo esc_html( $av_primary_button['text'] ); ?></a>
		</div>
	<?php endif; ?>
</main>
