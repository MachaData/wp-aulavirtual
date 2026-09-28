<?php
/**
 * Course landing: sections in order.
 *
 * @package SIQA\AulaVirtual
 *
 * @var int                                 $course_id
 * @var array<string, array<string, mixed>> $landing
 * @var array<string, mixed>                $vm
 * @var \SIQA\AulaVirtual\Landing\LandingRenderer $renderer
 */

use SIQA\AulaVirtual\Landing\LandingData;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<main class="av-landing" id="av-landing">
	<?php
	foreach ( LandingData::sections() as $av_section ) {
		if ( empty( $landing[ $av_section ]['enabled'] ) ) {
			continue;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- partials escape their own output.
		echo $renderer->template(
			'sections/' . $av_section,
			array(
				'course_id' => $course_id,
				'landing'   => $landing,
				'vm'        => $vm,
				'section'   => $landing[ $av_section ],
			)
		);
	}
	?>
	<?php if ( ! empty( $vm['buttons']['hero'] ) ) : ?>
		<div class="av-sticky-cta">
			<span class="av-sticky-cta__price"><?php echo esc_html( $vm['primary']['price'] ?? $landing['price']['price'] ); ?></span>
			<a class="av-btn av-btn--primary" href="<?php echo esc_url( $vm['buttons']['hero']['url'] ); ?>"><?php echo esc_html( $vm['buttons']['hero']['text'] ); ?></a>
		</div>
	<?php endif; ?>
</main>
