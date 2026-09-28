<?php
/**
 * Admin module.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Admin;

use SIQA\AulaVirtual\Core\Container;
use SIQA\AulaVirtual\Core\ServiceProvider;
use SIQA\AulaVirtual\Courses\CourseRepository;
use SIQA\AulaVirtual\Curriculum\LessonRepository;
use SIQA\AulaVirtual\Curriculum\LessonService;
use SIQA\AulaVirtual\Editions\EditionRepository;
use SIQA\AulaVirtual\Editions\EditionService;
use SIQA\AulaVirtual\Emails\EmailNotifier;
use SIQA\AulaVirtual\Emails\EmailTemplateRepository;
use SIQA\AulaVirtual\Enrollments\EnrollmentLinkRepository;
use SIQA\AulaVirtual\Enrollments\EnrollmentRepository;
use SIQA\AulaVirtual\Enrollments\EnrollmentService;
use SIQA\AulaVirtual\Enrollments\RegistrationRequestRepository;
use SIQA\AulaVirtual\Enrollments\RegistrationService;
use SIQA\AulaVirtual\LiveClasses\LiveClassRepository;
use SIQA\AulaVirtual\LiveClasses\LiveClassService;
use SIQA\AulaVirtual\Materials\MaterialRepository;
use SIQA\AulaVirtual\Materials\MaterialService;
use SIQA\AulaVirtual\Migration\TutorMigrator;
use SIQA\AulaVirtual\Migration\TutorReader;
use SIQA\AulaVirtual\Permissions\AccessControl;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the administration screens and their form handlers.
 */
final class AdminServiceProvider implements ServiceProvider {

	/**
	 * Binds the module services.
	 *
	 * @param Container $container Plugin container.
	 * @return void
	 */
	public function register( Container $container ): void {
		$container->singleton(
			EditionsScreen::class,
			static fn( Container $c ): EditionsScreen => new EditionsScreen(
				$c->get( EditionRepository::class ),
				$c->get( EditionService::class ),
				$c->get( LessonRepository::class ),
				$c->get( LessonService::class ),
				$c->get( EnrollmentRepository::class ),
				$c->get( EnrollmentService::class ),
				$c->get( CourseRepository::class ),
				$c->get( AccessControl::class ),
				$c->get( EnrollmentLinkRepository::class ),
				$c->get( RegistrationService::class )
			)
		);

		$container->singleton(
			RequestsScreen::class,
			static fn( Container $c ): RequestsScreen => new RequestsScreen(
				$c->get( RegistrationRequestRepository::class ),
				$c->get( RegistrationService::class ),
				$c->get( EditionRepository::class ),
				$c->get( AccessControl::class )
			)
		);

		$container->singleton(
			EmailsScreen::class,
			static fn( Container $c ): EmailsScreen => new EmailsScreen(
				$c->get( EmailTemplateRepository::class ),
				$c->get( EmailNotifier::class )
			)
		);

		$container->singleton(
			LessonScreen::class,
			static fn( Container $c ): LessonScreen => new LessonScreen(
				$c->get( LessonRepository::class ),
				$c->get( LessonService::class ),
				$c->get( EditionRepository::class ),
				$c->get( LiveClassRepository::class ),
				$c->get( LiveClassService::class ),
				$c->get( MaterialRepository::class ),
				$c->get( MaterialService::class ),
				$c->get( AccessControl::class )
			)
		);

		$container->singleton(
			MigrationScreen::class,
			static fn( Container $c ): MigrationScreen => new MigrationScreen(
				$c->get( TutorReader::class ),
				$c->get( TutorMigrator::class )
			)
		);

		$container->singleton(
			AdminMenu::class,
			static fn( Container $c ): AdminMenu => new AdminMenu(
				$c->get( EditionsScreen::class ),
				$c->get( RequestsScreen::class ),
				$c->get( EmailsScreen::class ),
				$c->get( MigrationScreen::class )
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
		if ( ! is_admin() ) {
			return;
		}

		add_action(
			'admin_menu',
			static function () use ( $container ): void {
				$container->get( AdminMenu::class )->register();

				// Editor de sesion: pagina sin entrada de menu, se llega desde la edicion.
				add_submenu_page(
					'',
					__( 'Sesion', 'aula-virtual' ),
					__( 'Sesion', 'aula-virtual' ),
					\SIQA\AulaVirtual\Permissions\Capabilities::MANAGE_CURRICULUM,
					LessonScreen::SLUG,
					static function () use ( $container ): void {
						$container->get( LessonScreen::class )->render();
					}
				);
			}
		);

		$handlers = array(
			EditionsScreen::ACTION_SAVE_EDITION => array( EditionsScreen::class, 'handle_save_edition' ),
			EditionsScreen::ACTION_ADD_LESSON   => array( EditionsScreen::class, 'handle_add_lesson' ),
			EditionsScreen::ACTION_ENROLL       => array( EditionsScreen::class, 'handle_enroll' ),
			EditionsScreen::ACTION_CREATE_LINK  => array( EditionsScreen::class, 'handle_create_link' ),
			RequestsScreen::ACTION_APPROVE      => array( RequestsScreen::class, 'handle_approve' ),
			RequestsScreen::ACTION_REJECT       => array( RequestsScreen::class, 'handle_reject' ),
			RequestsScreen::ACTION_MARK_PAID    => array( RequestsScreen::class, 'handle_mark_paid' ),
			EmailsScreen::ACTION_SAVE           => array( EmailsScreen::class, 'handle_save' ),
			EmailsScreen::ACTION_TEST           => array( EmailsScreen::class, 'handle_test' ),
			MigrationScreen::ACTION_MIGRATE     => array( MigrationScreen::class, 'handle_migrate' ),
			LessonScreen::ACTION_SAVE           => array( LessonScreen::class, 'handle_save' ),
			LessonScreen::ACTION_DELETE         => array( LessonScreen::class, 'handle_delete' ),
			LessonScreen::ACTION_SAVE_LIVE      => array( LessonScreen::class, 'handle_save_live' ),
			LessonScreen::ACTION_REMOVE_LIVE    => array( LessonScreen::class, 'handle_remove_live' ),
			LessonScreen::ACTION_ADD_MATERIAL   => array( LessonScreen::class, 'handle_add_material' ),
			LessonScreen::ACTION_DELETE_MATERIAL => array( LessonScreen::class, 'handle_delete_material' ),
		);

		foreach ( $handlers as $action => $target ) {
			add_action(
				'admin_post_' . $action,
				static function () use ( $container, $target ): void {
					$container->get( $target[0] )->{$target[1]}();
				}
			);
		}
	}
}
