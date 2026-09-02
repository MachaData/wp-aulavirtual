<?php
/**
 * Editions module.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Editions;

use SIQA\AulaVirtual\Core\AuditLog;
use SIQA\AulaVirtual\Core\Container;
use SIQA\AulaVirtual\Core\ServiceProvider;
use SIQA\AulaVirtual\Database\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the cohort services.
 */
final class EditionsServiceProvider implements ServiceProvider {

	/**
	 * Binds the module services.
	 *
	 * @param Container $container Plugin container.
	 * @return void
	 */
	public function register( Container $container ): void {
		$container->singleton(
			EditionRepository::class,
			static fn( Container $c ): EditionRepository => new EditionRepository( $c->get( Schema::class ) )
		);

		$container->singleton(
			EditionService::class,
			static fn( Container $c ): EditionService => new EditionService(
				$c->get( EditionRepository::class ),
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
