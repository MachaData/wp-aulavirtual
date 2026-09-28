<?php
/**
 * Imports module.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Imports;

use SIQA\AulaVirtual\Core\AuditLog;
use SIQA\AulaVirtual\Core\Container;
use SIQA\AulaVirtual\Core\Events\EventBus;
use SIQA\AulaVirtual\Core\ServiceProvider;
use SIQA\AulaVirtual\Database\Schema;
use SIQA\AulaVirtual\Editions\EditionRepository;
use SIQA\AulaVirtual\Enrollments\EnrollmentRepository;
use SIQA\AulaVirtual\Enrollments\EnrollmentService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the student import services.
 */
final class ImportsServiceProvider implements ServiceProvider {

	/**
	 * Binds the module services.
	 *
	 * @param Container $container Plugin container.
	 * @return void
	 */
	public function register( Container $container ): void {
		$container->singleton(
			ImportJobRepository::class,
			static fn( Container $c ): ImportJobRepository => new ImportJobRepository( $c->get( Schema::class ) )
		);

		$container->singleton(
			ImportService::class,
			static fn( Container $c ): ImportService => new ImportService(
				$c->get( ImportJobRepository::class ),
				$c->get( EditionRepository::class ),
				$c->get( EnrollmentRepository::class ),
				$c->get( EnrollmentService::class ),
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
	}
}
