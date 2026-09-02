<?php
/**
 * Progress module.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Progress;

use SIQA\AulaVirtual\Core\Container;
use SIQA\AulaVirtual\Core\Events\EventBus;
use SIQA\AulaVirtual\Core\ServiceProvider;
use SIQA\AulaVirtual\Curriculum\LessonRepository;
use SIQA\AulaVirtual\Database\Schema;
use SIQA\AulaVirtual\Enrollments\EnrollmentRepository;
use SIQA\AulaVirtual\Enrollments\EnrollmentService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the progress services.
 */
final class ProgressServiceProvider implements ServiceProvider {

	/**
	 * Binds the module services.
	 *
	 * @param Container $container Plugin container.
	 * @return void
	 */
	public function register( Container $container ): void {
		$container->singleton(
			ProgressRepository::class,
			static fn( Container $c ): ProgressRepository => new ProgressRepository( $c->get( Schema::class ) )
		);

		$container->singleton(
			ProgressService::class,
			static fn( Container $c ): ProgressService => new ProgressService(
				$c->get( ProgressRepository::class ),
				$c->get( LessonRepository::class ),
				$c->get( EnrollmentRepository::class ),
				$c->get( EnrollmentService::class ),
				$c->get( EventBus::class )
			)
		);
	}

	/**
	 * Registers the module hooks.
	 *
	 * @param Container $container Plugin container.
	 * @return void
	 */
	public function boot( Container $container ): void {
	}
}
