<?php
/**
 * Public certificate page: printable A4 landscape and verification.
 *
 * Override it from the theme at aula-virtual/certificates/certificate.php.
 *
 * @package SIQA\AulaVirtual
 *
 * @var bool                      $valid
 * @var array<string, mixed>|null $certificate
 * @var string                    $code
 * @var string                    $student_name
 * @var string                    $course_title
 * @var string                    $edition_name
 * @var string                    $issued_date
 * @var string                    $verification_url
 * @var string                    $message
 * @var string                    $site_name
 * @var string                    $brand_color
 * @var string                    $signature_name
 * @var string                    $signature_title
 * @var string                    $logo_url
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$av_title = $valid
	/* translators: 1: course title, 2: student name. */
	? sprintf( __( 'Certificado: %1$s - %2$s', 'aula-virtual' ), $course_title, $student_name )
	: __( 'Certificado no válido', 'aula-virtual' );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( $av_title . ' | ' . $site_name ); ?></title>
	<style>
		:root { --av-brand: <?php echo esc_attr( $brand_color ); ?>; }
		* { box-sizing: border-box; }
		html, body { margin: 0; padding: 0; }
		body {
			font-family: Georgia, "Times New Roman", serif;
			background: #e5e7eb;
			color: #111827;
			-webkit-print-color-adjust: exact;
			print-color-adjust: exact;
		}
		.av-toolbar {
			display: flex;
			justify-content: center;
			gap: 12px;
			padding: 16px;
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
		}
		.av-toolbar button, .av-toolbar a {
			background: var(--av-brand);
			color: #fff;
			border: 0;
			border-radius: 6px;
			padding: 10px 18px;
			font-size: 15px;
			cursor: pointer;
			text-decoration: none;
		}
		.av-toolbar a { background: #4b5563; }
		.av-sheet {
			width: 297mm;
			min-height: 210mm;
			margin: 0 auto 24px;
			background: #fff;
			padding: 14mm;
			box-shadow: 0 10px 30px rgba(0,0,0,.15);
			position: relative;
		}
		.av-frame {
			border: 3px solid var(--av-brand);
			outline: 1px solid var(--av-brand);
			outline-offset: 4px;
			height: 182mm;
			padding: 12mm 18mm;
			display: flex;
			flex-direction: column;
			text-align: center;
		}
		.av-head { display: flex; align-items: center; justify-content: center; gap: 16px; margin-bottom: 8mm; }
		.av-head img { max-height: 22mm; max-width: 70mm; }
		.av-site { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; letter-spacing: .18em; text-transform: uppercase; font-size: 12px; color: #4b5563; }
		.av-kicker { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; letter-spacing: .3em; text-transform: uppercase; font-size: 14px; color: var(--av-brand); margin: 0 0 6mm; }
		.av-heading { font-size: 34px; margin: 0 0 8mm; font-weight: normal; }
		.av-lead { font-size: 15px; color: #4b5563; margin: 0 0 3mm; }
		.av-student { font-size: 38px; margin: 0 0 6mm; color: var(--av-brand); border-bottom: 1px solid #d1d5db; display: inline-block; padding: 0 12mm 2mm; }
		.av-course { font-size: 24px; margin: 0 0 2mm; }
		.av-edition { font-size: 15px; color: #4b5563; margin: 0; }
		.av-spacer { flex: 1; }
		.av-foot { display: flex; justify-content: space-between; align-items: flex-end; gap: 20mm; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 12px; color: #4b5563; text-align: left; }
		.av-foot .av-sig { text-align: center; min-width: 60mm; }
		.av-foot .av-sig .av-line { border-top: 1px solid #111827; margin-bottom: 3px; }
		.av-foot .av-sig strong { display: block; color: #111827; font-size: 13px; }
		.av-foot .av-verify { text-align: right; }
		.av-foot .av-verify code { font-size: 13px; color: #111827; font-family: "SFMono-Regular", Menlo, Consolas, monospace; }
		.av-foot a { color: var(--av-brand); word-break: break-all; }
		.av-invalid {
			max-width: 640px;
			margin: 48px auto;
			background: #fff;
			border-radius: 12px;
			padding: 40px;
			text-align: center;
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
			box-shadow: 0 10px 30px rgba(0,0,0,.1);
			border-top: 6px solid #dc2626;
		}
		.av-invalid h1 { margin: 0 0 12px; color: #dc2626; font-size: 26px; }
		.av-invalid p { margin: 0 0 10px; color: #374151; font-size: 16px; }
		.av-invalid code { background: #f3f4f6; padding: 2px 6px; border-radius: 4px; }
		@page { size: A4 landscape; margin: 0; }
		@media print {
			body { background: #fff; }
			.av-toolbar { display: none !important; }
			.av-sheet { margin: 0; box-shadow: none; width: 297mm; height: 210mm; page-break-after: avoid; }
			.av-invalid { box-shadow: none; }
		}
		@media screen and (max-width: 320mm) {
			.av-sheet { width: 100%; min-height: 0; padding: 6mm; }
			.av-frame { height: auto; padding: 8mm 6mm; }
			.av-heading { font-size: 26px; }
			.av-student { font-size: 28px; }
			.av-foot { flex-direction: column; align-items: stretch; gap: 8mm; }
			.av-foot .av-verify { text-align: left; }
		}
	</style>
</head>
<body>
<?php if ( ! $valid ) : ?>
	<div class="av-invalid" role="alert">
		<h1><?php esc_html_e( 'Este certificado no es válido', 'aula-virtual' ); ?></h1>
		<p><?php echo esc_html( $message ); ?></p>
		<?php if ( '' !== $code ) : ?>
			<p><?php esc_html_e( 'Código consultado:', 'aula-virtual' ); ?> <code><?php echo esc_html( $code ); ?></code></p>
		<?php endif; ?>
		<p><?php echo esc_html( $site_name ); ?></p>
	</div>
	<div class="av-toolbar">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Ir al sitio', 'aula-virtual' ); ?></a>
	</div>
<?php else : ?>
	<div class="av-toolbar">
		<button type="button" onclick="window.print()"><?php esc_html_e( 'Imprimir / Guardar como PDF', 'aula-virtual' ); ?></button>
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Ir al sitio', 'aula-virtual' ); ?></a>
	</div>

	<div class="av-sheet">
		<div class="av-frame">
			<div class="av-head">
				<?php if ( '' !== $logo_url ) : ?>
					<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $site_name ); ?>">
				<?php else : ?>
					<div class="av-site"><?php echo esc_html( $site_name ); ?></div>
				<?php endif; ?>
			</div>

			<p class="av-kicker"><?php esc_html_e( 'Certificado', 'aula-virtual' ); ?></p>
			<h1 class="av-heading"><?php esc_html_e( 'Certificado de finalización', 'aula-virtual' ); ?></h1>

			<p class="av-lead"><?php esc_html_e( 'Se otorga el presente certificado a', 'aula-virtual' ); ?></p>
			<div><span class="av-student"><?php echo esc_html( $student_name ); ?></span></div>

			<p class="av-lead"><?php esc_html_e( 'por haber completado satisfactoriamente el curso', 'aula-virtual' ); ?></p>
			<p class="av-course"><?php echo esc_html( $course_title ); ?></p>
			<?php if ( '' !== $edition_name ) : ?>
				<p class="av-edition"><?php echo esc_html( $edition_name ); ?></p>
			<?php endif; ?>

			<div class="av-spacer"></div>

			<div class="av-foot">
				<div>
					<?php if ( '' !== $issued_date ) : ?>
						<div><?php esc_html_e( 'Fecha de emisión:', 'aula-virtual' ); ?> <strong><?php echo esc_html( $issued_date ); ?></strong></div>
					<?php endif; ?>
					<div><?php echo esc_html( $site_name ); ?></div>
				</div>

				<?php if ( '' !== $signature_name ) : ?>
					<div class="av-sig">
						<div class="av-line"></div>
						<strong><?php echo esc_html( $signature_name ); ?></strong>
						<?php if ( '' !== $signature_title ) : ?>
							<span><?php echo esc_html( $signature_title ); ?></span>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<div class="av-verify">
					<div>
						<?php
						printf(
							/* translators: 1: certificate code, 2: issue date. */
							esc_html__( 'Verificado: código %1$s emitido el %2$s', 'aula-virtual' ),
							'<code>' . esc_html( $code ) . '</code>',
							esc_html( $issued_date )
						);
						?>
					</div>
					<div><a href="<?php echo esc_url( $verification_url ); ?>"><?php echo esc_html( $verification_url ); ?></a></div>
				</div>
			</div>
		</div>
	</div>
<?php endif; ?>
</body>
</html>
