<?php
/**
 * Focus mode: a lesson without the theme's header and footer.
 *
 * wp_head() and wp_footer() still run so scripts (HLS player, embeds) load.
 *
 * @package SIQA\AulaVirtual
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$av_color = get_option( 'av_brand_color', '#1d4ed8' );
$av_color = is_string( $av_color ) && preg_match( '/^#[0-9a-f]{6}$/i', $av_color ) ? $av_color : '#1d4ed8';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( wp_get_document_title() ); ?></title>
	<?php wp_head(); ?>
	<style>
		body.av-focus{margin:0;background:#0f172a;color:#e2e8f0;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
		.av-focus__bar{display:flex;align-items:center;justify-content:space-between;padding:12px 20px;background:#111827;border-bottom:1px solid #1f2937}
		.av-focus__bar a{color:#cbd5e1;text-decoration:none;font-weight:600}
		.av-focus__bar strong{color:#fff}
		.av-focus__main{max-width:1100px;margin:0 auto;padding:24px 20px 60px}
		.av-focus__main .av-campus{color:#e2e8f0}
		.av-focus__main .av-campus a{color:#93c5fd}
		.av-focus__main h2{color:#fff}
		.av-focus__main .av-video iframe,.av-focus__main .av-video video{width:100%;aspect-ratio:16/9;height:auto;border:0;border-radius:12px;background:#000}
		.av-focus__main .av-live__join,.av-focus__main button[type=submit]{background:<?php echo esc_attr( $av_color ); ?>;color:#fff;border:0;border-radius:999px;padding:12px 24px;font-weight:700;cursor:pointer;text-decoration:none;display:inline-block}
		.av-focus__main .av-materials li{margin:6px 0}
	</style>
</head>
<body <?php body_class( 'av-focus' ); ?>>
	<?php wp_body_open(); ?>
	<div class="av-focus__bar">
		<a href="<?php echo esc_url( remove_query_arg( array( 'av_leccion' ) ) ); ?>">&larr; <?php esc_html_e( 'Volver al temario', 'aula-virtual' ); ?></a>
		<strong><?php bloginfo( 'name' ); ?></strong>
		<a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Salir', 'aula-virtual' ); ?></a>
	</div>
	<main class="av-focus__main">
		<?php echo do_shortcode( '[av_campus]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the shortcode escapes its output. ?>
	</main>
	<?php wp_footer(); ?>
</body>
</html>
