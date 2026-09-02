<?php
/**
 * Database module.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Database;

use SIQA\AulaVirtual\Core\Container;
use SIQA\AulaVirtual\Core\ServiceProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps the schema up to date and exposes the persistence services.
 */
final class DatabaseServiceProvider implements ServiceProvider {

	/**
	 * Binds the module services.
	 *
	 * The persistence services shared by every module (Schema, Migrator,
	 * Logger, AuditLog) are bound by the plugin core, so this provider only
	 * has hooks to register.
	 *
	 * @param Container $container Plugin container.
	 * @return void
	 */
	public function register( Container $container ): void {
	}

	/**
	 * Registers the module hooks.
	 *
	 * @param Container $container Plugin container.
	 * @return void
	 */
	public function boot( Container $container ): void {
		add_action(
			'admin_init',
			static function () use ( $container ): void {
				$container->get( Migrator::class )->maybe_migrate();
			},
			1
		);
	}
}
