<?php
/**
 * Curriculum module.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Curriculum;

use SIQA\AulaVirtual\Core\Container;
use SIQA\AulaVirtual\Core\ServiceProvider;
use SIQA\AulaVirtual\Database\Schema;
use SIQA\AulaVirtual\Editions\EditionRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the lesson services.
 */
final class CurriculumServiceProvider implements ServiceProvider {

	/**
	 * Binds the module services.
	 *
	 * @param Container $container Plugin container.
	 * @return void
	 */
	public function register( Container $container ): void {
		$container->singleton(
			LessonRepository::class,
			static fn( Container $c ): LessonRepository => new LessonRepository( $c->get( Schema::class ) )
		);

		$container->singleton(
			LessonService::class,
			static fn( Container $c ): LessonService => new LessonService(
				$c->get( LessonRepository::class ),
				$c->get( EditionRepository::class )
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
