<?php
/**
 * Frequently asked questions (accordion).
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

$av_icon = $vm['icon'];
?>
<section class="av-section av-faq" id="av-faq">
	<div class="av-container av-container--narrow">
		<header class="av-section__head av-section__head--center">
			<p class="av-eyebrow"><?php esc_html_e( 'Resolvemos tus dudas', 'aula-virtual' ); ?></p>
			<h2 class="av-section__title"><?php echo esc_html( $section['title'] ); ?></h2>
		</header>
		<div class="av-faq__list">
			<?php foreach ( $section['items'] as $av_item ) : ?>
				<details class="av-faq__item">
					<summary><span><?php echo esc_html( $av_item['question'] ); ?></span><span class="av-faq__icon"><?php echo $av_icon( 'plus' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span></summary>
					<div class="av-prose"><?php echo wp_kses_post( wpautop( $av_item['answer'] ) ); ?></div>
				</details>
			<?php endforeach; ?>
		</div>
	</div>
</section>
