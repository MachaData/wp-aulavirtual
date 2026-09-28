<?php
/**
 * Emails module.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Emails;

use SIQA\AulaVirtual\Core\Container;
use SIQA\AulaVirtual\Core\Events\EventBus;
use SIQA\AulaVirtual\Core\Logger;
use SIQA\AulaVirtual\Core\ServiceProvider;
use SIQA\AulaVirtual\Curriculum\LessonRepository;
use SIQA\AulaVirtual\Database\Schema;
use SIQA\AulaVirtual\Editions\EditionRepository;
use SIQA\AulaVirtual\Enrollments\RegistrationRequestRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the editable email system and subscribes it to the events.
 */
final class EmailsServiceProvider implements ServiceProvider {

	/**
	 * Binds the module services.
	 *
	 * @param Container $container Plugin container.
	 * @return void
	 */
	public function register( Container $container ): void {
		$container->singleton(
			EmailTemplateRepository::class,
			static fn( Container $c ): EmailTemplateRepository => new EmailTemplateRepository( $c->get( Schema::class ) )
		);

		$container->singleton(
			VariableResolver::class,
			static fn( Container $c ): VariableResolver => new VariableResolver(
				$c->get( EditionRepository::class ),
				$c->get( RegistrationRequestRepository::class ),
				$c->get( LessonRepository::class )
			)
		);

		$container->singleton(
			Mailer::class,
			static fn( Container $c ): Mailer => new Mailer( $c->get( Logger::class ) )
		);

		$container->singleton(
			EmailNotifier::class,
			static fn( Container $c ): EmailNotifier => new EmailNotifier(
				$c->get( EmailTemplateRepository::class ),
				$c->get( VariableResolver::class ),
				$c->get( Mailer::class )
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
		$container->get( EmailNotifier::class )->subscribe( $container->get( EventBus::class ) );

		add_action(
			'aula_virtual/schema_migrated',
			static function () use ( $container ): void {
				EmailDefaults::seed( $container->get( EmailTemplateRepository::class ) );
			}
		);
	}
}
