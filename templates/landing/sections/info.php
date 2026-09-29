<?php
/**
 * Dates: one card per edition that accepts enrollments, plus manual rows.
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

$av_icon = $vm['icon'];
?>
<section class="av-section av-info" id="av-info">
	<div class="av-container">
		<header class="av-section__head">
			<p class="av-eyebrow"><?php esc_html_e( 'Próximas fechas', 'aula-virtual' ); ?></p>
			<h2 class="av-section__title"><?php echo esc_html( $section['title'] ); ?></h2>
		</header>

		<?php if ( array() !== $av_editions ) : ?>
			<div class="av-editions<?php echo 1 === count( $av_editions ) ? ' av-editions--one' : ''; ?>">
				<?php foreach ( $av_editions as $av_edition ) : ?>
					<?php
					$av_schedule = trim( $av_edition['schedule_days'] . ' ' . $av_edition['schedule_time'] );
					$av_dates    = '' !== $av_edition['end_date'] ? $av_edition['start_date'] . ' – ' . $av_edition['end_date'] : $av_edition['start_date'];
					$av_left     = $av_edition['seats_left'];
					$av_sold_out = 0 === $av_left;
					?>
					<article class="av-edition<?php echo $av_sold_out ? ' is-sold-out' : ''; ?>">
						<header class="av-edition__head">
							<span class="av-badge<?php echo 'open' === $av_edition['status_key'] ? ' av-badge--open' : ''; ?>"><?php echo esc_html( $av_sold_out ? __( 'Cupos agotados', 'aula-virtual' ) : $av_edition['status'] ); ?></span>
							<h3 class="av-edition__name"><?php echo esc_html( $av_edition['name'] ); ?></h3>
						</header>
						<ul class="av-edition__facts">
							<?php if ( '' !== $av_dates ) : ?>
								<li><?php echo $av_icon( 'calendar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><span><?php echo esc_html( $av_dates ); ?></span></li>
							<?php endif; ?>
							<li><?php echo $av_icon( 'video' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><span><?php echo esc_html( $av_edition['modality'] ); ?></span></li>
							<?php if ( '' !== $av_schedule ) : ?>
								<li><?php echo $av_icon( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><span><?php echo esc_html( $av_schedule ); ?><?php echo '' !== $av_edition['timezone'] ? ' <small>(' . esc_html( str_replace( '_', ' ', $av_edition['timezone'] ) ) . ')</small>' : ''; ?></span></li>
							<?php endif; ?>
							<?php if ( null !== $av_left ) : ?>
								<li><?php echo $av_icon( 'users' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><span><?php echo esc_html( $av_sold_out ? __( 'Sin cupos disponibles', 'aula-virtual' ) : sprintf( /* translators: 1: left, 2: capacity. */ __( 'Quedan %1$d de %2$d cupos', 'aula-virtual' ), $av_left, $av_edition['capacity'] ) ); ?></span></li>
							<?php endif; ?>
						</ul>
						<?php if ( null !== $av_left && $av_edition['capacity'] > 0 ) : ?>
							<div class="av-seats" role="img" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: percent taken. */ __( '%d%% de cupos ocupados', 'aula-virtual' ), (int) round( 100 * ( $av_edition['capacity'] - $av_left ) / $av_edition['capacity'] ) ) ); ?>"><span style="width:<?php echo esc_attr( (string) round( 100 * ( $av_edition['capacity'] - $av_left ) / $av_edition['capacity'] ) ); ?>%"></span></div>
						<?php endif; ?>
						<footer class="av-edition__foot">
							<?php if ( '' !== $av_edition['price'] ) : ?>
								<p class="av-edition__price"><?php echo esc_html( $av_edition['price'] ); ?></p>
							<?php endif; ?>
							<?php if ( ! $av_sold_out ) : ?>
								<div class="av-edition__actions">
									<?php if ( '' !== $av_edition['buy_url'] ) : ?>
										<a class="av-btn av-btn--primary" href="<?php echo esc_url( $av_edition['buy_url'] ); ?>"><?php esc_html_e( 'Comprar', 'aula-virtual' ); ?></a>
									<?php endif; ?>
									<?php if ( '' !== $av_edition['register_url'] ) : ?>
										<a class="av-btn <?php echo '' !== $av_edition['buy_url'] ? 'av-btn--outline' : 'av-btn--primary'; ?>" href="<?php echo esc_url( $av_edition['register_url'] ); ?>"><?php esc_html_e( 'Inscribirme', 'aula-virtual' ); ?></a>
									<?php endif; ?>
								</div>
							<?php endif; ?>
						</footer>
					</article>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $section['items'] ) ) : ?>
			<dl class="av-details">
				<?php foreach ( $section['items'] as $av_row ) : ?>
					<div><dt><?php echo esc_html( $av_row['label'] ); ?></dt><dd><?php echo esc_html( $av_row['value'] ); ?></dd></div>
				<?php endforeach; ?>
			</dl>
		<?php endif; ?>
	</div>
</section>
