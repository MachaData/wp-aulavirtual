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
use SIQA\AulaVirtual\Curriculum\LessonRepository;
use SIQA\AulaVirtual\Curriculum\ModuleRepository;
use SIQA\AulaVirtual\Database\Schema;
use SIQA\AulaVirtual\LiveClasses\LiveClassRepository;
use SIQA\AulaVirtual\Materials\MaterialRepository;

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

		// Se resuelve en boot, cuando Curriculum, Materials y LiveClasses ya registraron sus repositorios.
		$container->singleton(
			EditionDuplicator::class,
			static fn( Container $c ): EditionDuplicator => new EditionDuplicator(
				$c->get( EditionRepository::class ),
				$c->get( EditionService::class ),
				$c->get( ModuleRepository::class ),
				$c->get( LessonRepository::class ),
				$c->get( MaterialRepository::class ),
				$c->get( LiveClassRepository::class ),
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
