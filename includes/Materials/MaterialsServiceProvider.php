<?php
/**
 * Materials module.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Materials;

use SIQA\AulaVirtual\Core\Container;
use SIQA\AulaVirtual\Core\Events\EventBus;
use SIQA\AulaVirtual\Core\ServiceProvider;
use SIQA\AulaVirtual\Curriculum\LessonRepository;
use SIQA\AulaVirtual\Database\Schema;
use SIQA\AulaVirtual\Editions\EditionRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the material services.
 */
final class MaterialsServiceProvider implements ServiceProvider {

	/**
	 * Binds the module services.
	 *
	 * @param Container $container Plugin container.
	 * @return void
	 */
	public function register( Container $container ): void {
		$container->singleton(
			MaterialRepository::class,
			static fn( Container $c ): MaterialRepository => new MaterialRepository( $c->get( Schema::class ) )
		);

		$container->singleton(
			MaterialService::class,
			static fn( Container $c ): MaterialService => new MaterialService(
				$c->get( MaterialRepository::class ),
				$c->get( LessonRepository::class ),
				$c->get( EditionRepository::class ),
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
