<?php
/**
 * Reports module.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Reports;

use SIQA\AulaVirtual\Core\Container;
use SIQA\AulaVirtual\Core\ServiceProvider;
use SIQA\AulaVirtual\Database\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the report services.
 */
final class ReportsServiceProvider implements ServiceProvider {

	/**
	 * Binds the module services.
	 *
	 * @param Container $container Plugin container.
	 * @return void
	 */
	public function register( Container $container ): void {
		$container->singleton(
			ReportRepository::class,
			static fn( Container $c ): ReportRepository => new ReportRepository( $c->get( Schema::class ) )
		);

		$container->singleton(
			ReportService::class,
			static fn( Container $c ): ReportService => new ReportService( $c->get( ReportRepository::class ) )
		);
	}

	/**
	 * Registers the module hooks.
	 *
	 * The admin screen and its export handler are wired by the Admin module.
	 *
	 * @param Container $container Plugin container.
	 * @return void
	 */
	public function boot( Container $container ): void {
	}
}
