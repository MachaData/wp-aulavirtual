<?php
/**
 * FAQ section.
 *
 * @package SIQA\AulaVirtual
 *
 * @var array<string, mixed> $section
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $section['items'] ) ) {
	return;
}
?>
<section class="av-section av-faq">
	<div class="av-container av-faq__inner">
		<h2 class="av-section__title"><?php echo esc_html( $section['title'] ); ?></h2>
		<?php foreach ( $section['items'] as $av_item ) : ?>
			<details class="av-faq__item">
				<summary><?php echo esc_html( $av_item['question'] ); ?></summary>
				<div class="av-prose"><?php echo wp_kses_post( wpautop( $av_item['answer'] ) ); ?></div>
			</details>
		<?php endforeach; ?>
	</div>
</section>
