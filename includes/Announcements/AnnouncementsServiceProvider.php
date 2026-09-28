<?php
/**
 * Announcements module.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Announcements;

use SIQA\AulaVirtual\Core\Container;
use SIQA\AulaVirtual\Core\Events\EventBus;
use SIQA\AulaVirtual\Core\ServiceProvider;
use SIQA\AulaVirtual\Database\Schema;
use SIQA\AulaVirtual\Editions\EditionRepository;
use SIQA\AulaVirtual\Enrollments\EnrollmentRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the announcement services.
 */
final class AnnouncementsServiceProvider implements ServiceProvider {

	/**
	 * Binds the module services.
	 *
	 * @param Container $container Plugin container.
	 * @return void
	 */
	public function register( Container $container ): void {
		$container->singleton(
			AnnouncementRepository::class,
			static fn( Container $c ): AnnouncementRepository => new AnnouncementRepository( $c->get( Schema::class ) )
		);

		$container->singleton(
			AnnouncementService::class,
			static fn( Container $c ): AnnouncementService => new AnnouncementService(
				$c->get( AnnouncementRepository::class ),
				$c->get( EditionRepository::class ),
				$c->get( EnrollmentRepository::class ),
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
