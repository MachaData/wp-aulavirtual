<?php
/**
 * Live classes module.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\LiveClasses;

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
 * Registers the live class services.
 */
final class LiveClassesServiceProvider implements ServiceProvider {

	/**
	 * Binds the module services.
	 *
	 * @param Container $container Plugin container.
	 * @return void
	 */
	public function register( Container $container ): void {
		$container->singleton(
			LiveClassRepository::class,
			static fn( Container $c ): LiveClassRepository => new LiveClassRepository( $c->get( Schema::class ) )
		);

		$container->singleton(
			LiveClassService::class,
			static fn( Container $c ): LiveClassService => new LiveClassService(
				$c->get( LiveClassRepository::class ),
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
