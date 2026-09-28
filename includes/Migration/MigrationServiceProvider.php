<?php
/**
 * Migration module.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Migration;

use SIQA\AulaVirtual\Core\AuditLog;
use SIQA\AulaVirtual\Core\Container;
use SIQA\AulaVirtual\Core\Logger;
use SIQA\AulaVirtual\Core\ServiceProvider;
use SIQA\AulaVirtual\Curriculum\LessonRepository;
use SIQA\AulaVirtual\Curriculum\ModuleRepository;
use SIQA\AulaVirtual\Editions\EditionService;
use SIQA\AulaVirtual\Enrollments\EnrollmentRepository;
use SIQA\AulaVirtual\Enrollments\EnrollmentService;
use SIQA\AulaVirtual\Materials\MaterialRepository;
use SIQA\AulaVirtual\Progress\ProgressRepository;
use SIQA\AulaVirtual\Progress\ProgressService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the Tutor LMS migration services.
 *
 * The services are always bound (they are cheap); the admin screen only
 * appears when Tutor data is present.
 */
final class MigrationServiceProvider implements ServiceProvider {

	/**
	 * Binds the module services.
	 *
	 * @param Container $container Plugin container.
	 * @return void
	 */
	public function register( Container $container ): void {
		$container->singleton( TutorReader::class, static fn(): TutorReader => new TutorReader() );

		$container->singleton(
			TutorMigrator::class,
			static fn( Container $c ): TutorMigrator => new TutorMigrator(
				$c->get( TutorReader::class ),
				$c->get( EditionService::class ),
				$c->get( ModuleRepository::class ),
				$c->get( LessonRepository::class ),
				$c->get( EnrollmentService::class ),
				$c->get( EnrollmentRepository::class ),
				$c->get( ProgressRepository::class ),
				$c->get( ProgressService::class ),
				$c->get( AuditLog::class ),
				$c->get( Logger::class ),
				$c->get( MaterialRepository::class )
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
