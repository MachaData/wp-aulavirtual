<?php
/**
 * REST module.
 *
 * @package SEV\LMS
 */

declare( strict_types = 1 );

namespace SEV\LMS\REST;

use SEV\LMS\Core\Container;
use SEV\LMS\Core\ServiceProvider;
use SEV\LMS\Courses\CourseRepository;
use SEV\LMS\Permissions\AccessControl;
use WP_REST_Controller;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the REST controllers under the sev-lms/v1 namespace.
 */
final class RestServiceProvider implements ServiceProvider {

	/**
	 * Binds the controllers.
	 *
	 * @param Container $container Plugin container.
	 * @return void
	 */
	public function register( Container $container ): void {
		$container->singleton(
			CoursesController::class,
			static fn( Container $c ): CoursesController => new CoursesController(
				$c->get( CourseRepository::class ),
				$c->get( AccessControl::class )
			)
		);
	}

	/**
	 * Registers the routes on rest_api_init.
	 *
	 * @param Container $container Plugin container.
	 * @return void
	 */
	public function boot( Container $container ): void {
		add_action(
			'rest_api_init',
			static function () use ( $container ): void {
				$controllers = array( CoursesController::class );

				/**
				 * Filters the REST controllers registered by SEV LMS.
				 *
				 * @param array<int, string> $controllers Controller class names resolvable from the container.
				 */
				$controllers = apply_filters( 'sev_lms/rest_controllers', $controllers );

				foreach ( $controllers as $controller_class ) {
					if ( ! is_string( $controller_class ) || ! $container->has( $controller_class ) ) {
						continue;
					}

					$controller = $container->get( $controller_class );

					if ( $controller instanceof WP_REST_Controller ) {
						$controller->register_routes();
					}
				}
			}
		);
	}
}
