<?php
/**
 * Courses module.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Courses;

use SIQA\AulaVirtual\Core\Container;
use SIQA\AulaVirtual\Core\ServiceProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the course post type, taxonomies and meta fields.
 */
final class CoursesServiceProvider implements ServiceProvider {

	/**
	 * Binds the module services.
	 *
	 * @param Container $container Plugin container.
	 * @return void
	 */
	public function register( Container $container ): void {
		$container->singleton( CourseRepository::class, static fn(): CourseRepository => new CourseRepository() );
	}

	/**
	 * Registers the module hooks.
	 *
	 * @param Container $container Plugin container.
	 * @return void
	 */
	public function boot( Container $container ): void {
		add_action( 'init', array( CoursePostType::class, 'register' ), 5 );
		add_action( 'init', array( CourseMeta::class, 'register' ), 6 );
	}
}
