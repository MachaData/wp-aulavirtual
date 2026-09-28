<?php
/**
 * Enrollments module.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Enrollments;

use SIQA\AulaVirtual\Core\AuditLog;
use SIQA\AulaVirtual\Core\Container;
use SIQA\AulaVirtual\Core\Events\EventBus;
use SIQA\AulaVirtual\Core\ServiceProvider;
use SIQA\AulaVirtual\Database\Schema;
use SIQA\AulaVirtual\Editions\EditionRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the enrollment services and answers the content access question.
 */
final class EnrollmentsServiceProvider implements ServiceProvider {

	/**
	 * Binds the module services.
	 *
	 * @param Container $container Plugin container.
	 * @return void
	 */
	public function register( Container $container ): void {
		$container->singleton(
			EnrollmentRepository::class,
			static fn( Container $c ): EnrollmentRepository => new EnrollmentRepository( $c->get( Schema::class ) )
		);

		$container->singleton(
			EnrollmentService::class,
			static fn( Container $c ): EnrollmentService => new EnrollmentService(
				$c->get( EnrollmentRepository::class ),
				$c->get( EditionRepository::class ),
				$c->get( EventBus::class ),
				$c->get( AuditLog::class )
			)
		);

		$this->register_registration( $container );
	}

	/**
	 * Binds the registration flow services.
	 *
	 * @param Container $container Plugin container.
	 * @return void
	 */
	private function register_registration( Container $container ): void {
		$container->singleton(
			EnrollmentLinkRepository::class,
			static fn( Container $c ): EnrollmentLinkRepository => new EnrollmentLinkRepository( $c->get( Schema::class ) )
		);

		$container->singleton(
			RegistrationRequestRepository::class,
			static fn( Container $c ): RegistrationRequestRepository => new RegistrationRequestRepository( $c->get( Schema::class ) )
		);

		$container->singleton(
			RegistrationService::class,
			static fn( Container $c ): RegistrationService => new RegistrationService(
				$c->get( EnrollmentLinkRepository::class ),
				$c->get( RegistrationRequestRepository::class ),
				$c->get( EditionRepository::class ),
				$c->get( EnrollmentService::class ),
				$c->get( EventBus::class ),
				$c->get( AuditLog::class )
			)
		);

		$container->singleton(
			RegistrationController::class,
			static fn( Container $c ): RegistrationController => new RegistrationController( $c->get( RegistrationService::class ) )
		);
	}

	/**
	 * Registers the module hooks.
	 *
	 * This is where AccessControl gets its answer for students: the core knows
	 * that managers pass, and this module adds "or has an active enrollment".
	 *
	 * @param Container $container Plugin container.
	 * @return void
	 */
	public function boot( Container $container ): void {
		add_filter(
			'aula_virtual/can_access_edition',
			static function ( bool $allowed, int $edition_id, int $user_id ) use ( $container ): bool {
				if ( $allowed || $user_id <= 0 ) {
					return $allowed;
				}

				return $container->get( EnrollmentService::class )->has_access( $user_id, $edition_id );
			},
			10,
			3
		);

		add_action( 'init', array( RegistrationController::class, 'register_rewrite' ) );

		add_action(
			'template_redirect',
			static function () use ( $container ): void {
				$container->get( RegistrationController::class )->maybe_render();
			}
		);

		foreach ( array( 'admin_post_', 'admin_post_nopriv_' ) as $prefix ) {
			add_action(
				$prefix . RegistrationController::ACTION_SUBMIT,
				static function () use ( $container ): void {
					$container->get( RegistrationController::class )->handle_submit();
				}
			);
		}
	}
}
