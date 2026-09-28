<?php
/**
 * Certificates module.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Certificates;

use SIQA\AulaVirtual\Core\AuditLog;
use SIQA\AulaVirtual\Core\Container;
use SIQA\AulaVirtual\Core\Events\EventBus;
use SIQA\AulaVirtual\Core\Events\Events;
use SIQA\AulaVirtual\Core\ServiceProvider;
use SIQA\AulaVirtual\Database\Schema;
use SIQA\AulaVirtual\Editions\EditionRepository;
use SIQA\AulaVirtual\Enrollments\EnrollmentRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the certificate services, the public verification page and the
 * automatic issue on course completion.
 */
final class CertificatesServiceProvider implements ServiceProvider {

	/**
	 * Binds the module services.
	 *
	 * @param Container $container Plugin container.
	 * @return void
	 */
	public function register( Container $container ): void {
		$container->singleton(
			CertificateRepository::class,
			static fn( Container $c ): CertificateRepository => new CertificateRepository( $c->get( Schema::class ) )
		);

		$container->singleton(
			CertificateService::class,
			static fn( Container $c ): CertificateService => new CertificateService(
				$c->get( CertificateRepository::class ),
				$c->get( EnrollmentRepository::class ),
				$c->get( EditionRepository::class ),
				$c->get( EventBus::class ),
				$c->get( AuditLog::class )
			)
		);

		$container->singleton(
			CertificateController::class,
			static fn( Container $c ): CertificateController => new CertificateController(
				$c->get( CertificateService::class ),
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
		add_action( 'init', array( CertificateController::class, 'register_rewrite' ) );
		add_filter( 'query_vars', array( CertificateController::class, 'query_vars' ) );

		add_action(
			'template_redirect',
			static function () use ( $container ): void {
				$container->get( CertificateController::class )->maybe_render();
			}
		);

		// Emision automatica al completar el curso. Prioridad 10: antes del
		// EmailNotifier (20), asi el correo "Curso finalizado" ya puede
		// enlazar el certificado.
		$container->get( EventBus::class )->listen(
			Events::COURSE_COMPLETED,
			static function ( array $payload ) use ( $container ): void {
				$container->get( CertificateService::class )->on_course_completed( $payload );
			},
			10
		);

		// Variable {certificate_url} de los correos: sale del payload del
		// evento certificate_issued o, para cualquier otro evento, del
		// certificado vigente del alumno en la edicion.
		add_filter(
			'aula_virtual/email_variables',
			static function ( array $vars, array $payload ) use ( $container ): array {
				if ( ! empty( $payload['certificate_url'] ) && is_string( $payload['certificate_url'] ) ) {
					$vars['certificate_url'] = $payload['certificate_url'];

					return $vars;
				}

				$user_id    = (int) ( $payload['user_id'] ?? 0 );
				$edition_id = (int) ( $payload['edition_id'] ?? 0 );

				if ( $user_id <= 0 || $edition_id <= 0 ) {
					return $vars;
				}

				$certificate = $container->get( CertificateRepository::class )->find_issued_for( $user_id, $edition_id );

				if ( null !== $certificate ) {
					$vars['certificate_url'] = $container->get( CertificateService::class )->url( $certificate );
				}

				return $vars;
			},
			10,
			2
		);
	}
}
