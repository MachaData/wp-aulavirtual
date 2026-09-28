<?php
/**
 * Course landing wrapper: theme header + landing + theme footer.
 *
 * @package SIQA\AulaVirtual
 */

use SIQA\AulaVirtual\Landing\LandingRenderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the renderer escapes every field.
	echo \SIQA\AulaVirtual\aula_virtual()->container()->get( LandingRenderer::class )->render( (int) get_the_ID() );
endwhile;

get_footer();
