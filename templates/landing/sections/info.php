<?php
/**
 * Course information: one card per open edition, plus manual rows.
 *
 * @package SIQA\AulaVirtual
 *
 * @var array<string, mixed> $section
 * @var array<string, mixed> $vm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$av_editions = ! empty( $section['from_editions'] ) ? $vm['editions'] : array();

if ( array() === $av_editions && empty( $section['items'] ) ) {
	return;
}
?>
<section class="av-section av-info" id="av-info">
	<div class="av-container">
		<h2 class="av-section__title"><?php echo esc_html( $section['title'] ); ?></h2>
		<?php if ( array() !== $av_editions ) : ?>
			<div class="av-editions">
				<?php foreach ( $av_editions as $av_edition ) : ?>
					<article class="av-edition-card">
						<h3 class="av-edition-card__name"><?php echo esc_html( $av_edition['name'] ); ?></h3>
						<dl class="av-edition-card__facts">
							<div><dt><?php esc_html_e( 'Modalidad', 'aula-virtual' ); ?></dt><dd><?php echo esc_html( $av_edition['modality'] ); ?></dd></div>
							<?php if ( '' !== $av_edition['start_date'] ) : ?>
								<div><dt><?php esc_html_e( 'Inicio', 'aula-virtual' ); ?></dt><dd><?php echo esc_html( $av_edition['start_date'] ); ?></dd></div>
							<?php endif; ?>
							<?php if ( '' !== $av_edition['end_date'] ) : ?>
								<div><dt><?php esc_html_e( 'Fin', 'aula-virtual' ); ?></dt><dd><?php echo esc_html( $av_edition['end_date'] ); ?></dd></div>
							<?php endif; ?>
							<?php if ( '' !== $av_edition['schedule_days'] ) : ?>
								<div><dt><?php esc_html_e( 'Días', 'aula-virtual' ); ?></dt><dd><?php echo esc_html( $av_edition['schedule_days'] ); ?></dd></div>
							<?php endif; ?>
							<?php if ( '' !== $av_edition['schedule_time'] ) : ?>
								<div><dt><?php esc_html_e( 'Horario', 'aula-virtual' ); ?></dt><dd><?php echo esc_html( $av_edition['schedule_time'] ); ?><?php echo '' !== $av_edition['timezone'] ? ' <small>(' . esc_html( $av_edition['timezone'] ) . ')</small>' : ''; ?></dd></div>
							<?php endif; ?>
							<?php if ( $av_edition['capacity'] > 0 ) : ?>
								<div><dt><?php esc_html_e( 'Cupo', 'aula-virtual' ); ?></dt><dd><?php echo esc_html( (string) $av_edition['capacity'] ); ?></dd></div>
							<?php endif; ?>
						</dl>
						<p class="av-edition-card__actions">
							<?php if ( '' !== $av_edition['buy_url'] ) : ?>
								<a class="av-btn av-btn--primary" href="<?php echo esc_url( $av_edition['buy_url'] ); ?>"><?php echo esc_html( '' !== $av_edition['price'] ? sprintf( /* translators: %s: price. */ __( 'Comprar %s', 'aula-virtual' ), $av_edition['price'] ) : __( 'Comprar', 'aula-virtual' ) ); ?></a>
							<?php endif; ?>
							<?php if ( '' !== $av_edition['register_url'] ) : ?>
								<a class="av-btn av-btn--secondary" href="<?php echo esc_url( $av_edition['register_url'] ); ?>"><?php esc_html_e( 'Inscribirme', 'aula-virtual' ); ?></a>
							<?php endif; ?>
						</p>
					</article>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<?php if ( ! empty( $section['items'] ) ) : ?>
			<dl class="av-info__extra">
				<?php foreach ( $section['items'] as $av_row ) : ?>
					<div><dt><?php echo esc_html( $av_row['label'] ); ?></dt><dd><?php echo esc_html( $av_row['value'] ); ?></dd></div>
				<?php endforeach; ?>
			</dl>
		<?php endif; ?>
	</div>
</section>
