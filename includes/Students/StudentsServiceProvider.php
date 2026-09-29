<?php
/**
 * Students module.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Students;

use SIQA\AulaVirtual\Core\AuditLog;
use SIQA\AulaVirtual\Core\Container;
use SIQA\AulaVirtual\Core\Events\EventBus;
use SIQA\AulaVirtual\Core\ServiceProvider;
use SIQA\AulaVirtual\Database\Schema;
use SIQA\AulaVirtual\Editions\EditionRepository;
use SIQA\AulaVirtual\Enrollments\EnrollmentRepository;
use SIQA\AulaVirtual\Enrollments\EnrollmentService;
use SIQA\AulaVirtual\Progress\ProgressRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the student management services and account hooks.
 */
final class StudentsServiceProvider implements ServiceProvider {

	/**
	 * Binds the module services.
	 *
	 * @param Container $container Plugin container.
	 * @return void
	 */
	public function register( Container $container ): void {
		$container->singleton(
			StudentRepository::class,
			static fn( Container $c ): StudentRepository => new StudentRepository( $c->get( Schema::class ) )
		);

		$container->singleton(
			StudentService::class,
			static fn( Container $c ): StudentService => new StudentService(
				$c->get( EnrollmentRepository::class ),
				$c->get( EnrollmentService::class ),
				$c->get( EditionRepository::class ),
				$c->get( ProgressRepository::class ),
				$c->get( EventBus::class ),
				$c->get( AuditLog::class )
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
		AccountHelper::register();
	}
}
