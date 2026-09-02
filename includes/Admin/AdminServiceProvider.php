<?php
/**
 * Admin module.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Admin;

use SIQA\AulaVirtual\Core\Container;
use SIQA\AulaVirtual\Core\ServiceProvider;
use SIQA\AulaVirtual\Courses\CourseRepository;
use SIQA\AulaVirtual\Curriculum\LessonRepository;
use SIQA\AulaVirtual\Curriculum\LessonService;
use SIQA\AulaVirtual\Editions\EditionRepository;
use SIQA\AulaVirtual\Editions\EditionService;
use SIQA\AulaVirtual\Enrollments\EnrollmentRepository;
use SIQA\AulaVirtual\Enrollments\EnrollmentService;
use SIQA\AulaVirtual\Permissions\AccessControl;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the administration screens and their form handlers.
 */
final class AdminServiceProvider implements ServiceProvider {

	/**
	 * Binds the module services.
	 *
	 * @param Container $container Plugin container.
	 * @return void
	 */
	public function register( Container $container ): void {
		$container->singleton(
			EditionsScreen::class,
			static fn( Container $c ): EditionsScreen => new EditionsScreen(
				$c->get( EditionRepository::class ),
				$c->get( EditionService::class ),
				$c->get( LessonRepository::class ),
				$c->get( LessonService::class ),
				$c->get( EnrollmentRepository::class ),
				$c->get( EnrollmentService::class ),
				$c->get( CourseRepository::class ),
				$c->get( AccessControl::class )
			)
		);

		$container->singleton(
			AdminMenu::class,
			static fn( Container $c ): AdminMenu => new AdminMenu( $c->get( EditionsScreen::class ) )
		);
	}

	/**
	 * Registers the module hooks.
	 *
	 * @param Container $container Plugin container.
	 * @return void
	 */
	public function boot( Container $container ): void {
		if ( ! is_admin() ) {
			return;
		}

		add_action(
			'admin_menu',
			static function () use ( $container ): void {
				$container->get( AdminMenu::class )->register();
			}
		);

		$handlers = array(
			EditionsScreen::ACTION_SAVE_EDITION => 'handle_save_edition',
			EditionsScreen::ACTION_ADD_LESSON   => 'handle_add_lesson',
			EditionsScreen::ACTION_ENROLL       => 'handle_enroll',
		);

		foreach ( $handlers as $action => $method ) {
			add_action(
				'admin_post_' . $action,
				static function () use ( $container, $method ): void {
					$container->get( EditionsScreen::class )->$method();
				}
			);
		}
	}
}
