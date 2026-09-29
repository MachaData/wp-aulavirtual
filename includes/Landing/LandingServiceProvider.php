<?php
/**
 * Landing module.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Landing;

use SIQA\AulaVirtual\Core\Container;
use SIQA\AulaVirtual\Core\ServiceProvider;
use SIQA\AulaVirtual\Curriculum\LessonRepository;
use SIQA\AulaVirtual\Curriculum\ModuleRepository;
use SIQA\AulaVirtual\Editions\EditionRepository;
use SIQA\AulaVirtual\Enrollments\EnrollmentLinkRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the course landing: editor, template, SEO tags and shortcodes.
 */
final class LandingServiceProvider implements ServiceProvider {

	/**
	 * Binds the module services.
	 *
	 * @param Container $container Plugin container.
	 * @return void
	 */
	public function register( Container $container ): void {
		$container->singleton(
			LandingRenderer::class,
			static fn( Container $c ): LandingRenderer => new LandingRenderer(
				$c->get( EditionRepository::class ),
				$c->get( EnrollmentLinkRepository::class ),
				$c->get( ModuleRepository::class ),
				$c->get( LessonRepository::class ),
				$c->get( \SIQA\AulaVirtual\Enrollments\EnrollmentRepository::class )
			)
		);

		$container->singleton(
			LandingTemplate::class,
			static fn( Container $c ): LandingTemplate => new LandingTemplate( $c->get( LandingRenderer::class ) )
		);

		$container->singleton( LandingMetaBox::class, static fn(): LandingMetaBox => new LandingMetaBox() );
	}

	/**
	 * Registers the module hooks.
	 *
	 * @param Container $container Plugin container.
	 * @return void
	 */
	public function boot( Container $container ): void {
		$template = static fn(): LandingTemplate => $container->get( LandingTemplate::class );

		add_filter( 'template_include', static fn( $current ): string => $template()->template_include( (string) $current ), 20 );
		add_action( 'wp_enqueue_scripts', static function () use ( $template ): void { $template()->enqueue(); } );
		add_action( 'wp_head', static function () use ( $template ): void { $template()->head(); }, 5 );

		add_shortcode( 'av_course_landing', static fn( $atts ): string => $template()->shortcode_landing( $atts ) );
		add_shortcode( 'av_course_editions', static fn( $atts ): string => $template()->shortcode_editions( $atts ) );

		if ( is_admin() ) {
			$container->get( LandingMetaBox::class )->hooks();
		}
	}
}
