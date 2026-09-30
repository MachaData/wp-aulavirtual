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
		body.av-focus{margin:0;background:#f3f0eb;color:#111}
		.av-focus__bar{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:14px 24px;background:#fff;border-bottom:1px solid #e6e0d6}
		.av-focus__bar a{color:#6f6a62;text-decoration:none;font-size:14px}
		.av-focus__bar a:hover{color:#111}
		.av-focus__bar strong{font-weight:500;letter-spacing:.02em}
		.av-focus__main{padding:32px 20px 60px}
	</style>
</head>
<body <?php body_class( 'av-focus' ); ?>>
	<?php wp_body_open(); ?>
	<div class="av-focus__bar">
		<a href="<?php echo esc_url( \SIQA\AulaVirtual\Campus\CampusController::page_id() > 0 ? (string) get_permalink( \SIQA\AulaVirtual\Campus\CampusController::page_id() ) : home_url( '/' ) ); ?>">&larr; <?php esc_html_e( 'Mis cursos', 'aula-virtual' ); ?></a>
		<strong><?php bloginfo( 'name' ); ?></strong>
		<a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Salir', 'aula-virtual' ); ?></a>
	</div>
	<main class="av-focus__main">
		<?php echo do_shortcode( '[av_campus]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the shortcode escapes its output. ?>
	</main>
	<?php wp_footer(); ?>
</body>
</html>
