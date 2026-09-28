<?php
/**
 * Admin module.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Admin;

use SIQA\AulaVirtual\Announcements\AnnouncementRepository;
use SIQA\AulaVirtual\Announcements\AnnouncementService;
use SIQA\AulaVirtual\Certificates\CertificateRepository;
use SIQA\AulaVirtual\Certificates\CertificateService;
use SIQA\AulaVirtual\Comments\CommentService;
use SIQA\AulaVirtual\Core\AuditLog;
use SIQA\AulaVirtual\Core\Container;
use SIQA\AulaVirtual\Core\ServiceProvider;
use SIQA\AulaVirtual\Courses\CourseDuplicator;
use SIQA\AulaVirtual\Courses\CoursePostType;
use SIQA\AulaVirtual\Courses\CourseRepository;
use SIQA\AulaVirtual\Curriculum\LessonRepository;
use SIQA\AulaVirtual\Curriculum\LessonService;
use SIQA\AulaVirtual\Editions\EditionDuplicator;
use SIQA\AulaVirtual\Editions\EditionRepository;
use SIQA\AulaVirtual\Editions\EditionService;
use SIQA\AulaVirtual\Emails\EmailNotifier;
use SIQA\AulaVirtual\Emails\EmailTemplateRepository;
use SIQA\AulaVirtual\Enrollments\EnrollmentLinkRepository;
use SIQA\AulaVirtual\Enrollments\EnrollmentRepository;
use SIQA\AulaVirtual\Enrollments\EnrollmentService;
use SIQA\AulaVirtual\Enrollments\RegistrationRequestRepository;
use SIQA\AulaVirtual\Enrollments\RegistrationService;
use SIQA\AulaVirtual\Imports\ImportJobRepository;
use SIQA\AulaVirtual\Imports\ImportService;
use SIQA\AulaVirtual\LiveClasses\LiveClassRepository;
use SIQA\AulaVirtual\LiveClasses\LiveClassService;
use SIQA\AulaVirtual\Materials\MaterialRepository;
use SIQA\AulaVirtual\Materials\MaterialService;
use SIQA\AulaVirtual\Migration\TutorMigrator;
use SIQA\AulaVirtual\Migration\TutorReader;
use SIQA\AulaVirtual\Permissions\AccessControl;
use SIQA\AulaVirtual\Reports\ReportRepository;
use SIQA\AulaVirtual\Reports\ReportService;

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
				$c->get( RegistrationService::class ),
				$c->get( EditionDuplicator::class ),
				$c->get( CourseDuplicator::class )
			)
		);

		$container->singleton(
			ImportScreen::class,
			static fn( Container $c ): ImportScreen => new ImportScreen(
				$c->get( EditionRepository::class ),
				$c->get( ImportService::class ),
				$c->get( ImportJobRepository::class ),
				$c->get( AccessControl::class )
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
				$c->get( AccessControl::class ),
				$c->get( CommentService::class )
			)
		);

		$container->singleton(
			MigrationScreen::class,
			static fn( Container $c ): MigrationScreen => new MigrationScreen(
				$c->get( TutorReader::class ),
				$c->get( TutorMigrator::class ),
				$c->get( CourseRepository::class )
			)
		);

		$container->singleton(
			SettingsScreen::class,
			static fn( Container $c ): SettingsScreen => new SettingsScreen( $c->get( AuditLog::class ), $c->get( MaterialService::class ) )
		);

		$container->singleton(
			AnnouncementsScreen::class,
			static fn( Container $c ): AnnouncementsScreen => new AnnouncementsScreen(
				$c->get( AnnouncementRepository::class ),
				$c->get( AnnouncementService::class ),
				$c->get( EditionRepository::class ),
				$c->get( AccessControl::class )
			)
		);

		$container->singleton(
			CertificatesScreen::class,
			static fn( Container $c ): CertificatesScreen => new CertificatesScreen(
				$c->get( CertificateRepository::class ),
				$c->get( CertificateService::class ),
				$c->get( EditionRepository::class ),
				$c->get( AccessControl::class )
			)
		);

		$container->singleton(
			ReportsScreen::class,
			static fn( Container $c ): ReportsScreen => new ReportsScreen(
				$c->get( ReportRepository::class ),
				$c->get( ReportService::class ),
				$c->get( EditionRepository::class ),
				$c->get( AccessControl::class )
			)
		);

		$container->singleton(
			AdminMenu::class,
			static fn( Container $c ): AdminMenu => new AdminMenu(
				$c->get( EditionsScreen::class ),
				$c->get( RequestsScreen::class ),
				$c->get( EmailsScreen::class ),
				$c->get( MigrationScreen::class ),
				$c->get( AnnouncementsScreen::class ),
				$c->get( SettingsScreen::class ),
				$c->get( ImportScreen::class ),
				$c->get( ReportsScreen::class ),
				$c->get( CertificatesScreen::class )
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
			EditionsScreen::ACTION_DUPLICATE    => array( EditionsScreen::class, 'handle_duplicate' ),
			EditionsScreen::ACTION_MOVE_LESSON  => array( EditionsScreen::class, 'handle_move_lesson' ),
			EditionsScreen::ACTION_DUPLICATE_COURSE => array( EditionsScreen::class, 'handle_duplicate_course' ),
			ImportScreen::ACTION_UPLOAD         => array( ImportScreen::class, 'handle_upload' ),
			ImportScreen::ACTION_MAP            => array( ImportScreen::class, 'handle_map' ),
			ImportScreen::ACTION_CONFIRM        => array( ImportScreen::class, 'handle_confirm' ),
			ImportScreen::ACTION_RUN            => array( ImportScreen::class, 'handle_run' ),
			ReportsScreen::ACTION_EXPORT        => array( ReportsScreen::class, 'handle_export' ),
			CertificatesScreen::ACTION_ISSUE    => array( CertificatesScreen::class, 'handle_issue' ),
			CertificatesScreen::ACTION_REVOKE   => array( CertificatesScreen::class, 'handle_revoke' ),
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
			SettingsScreen::ACTION_SAVE         => array( SettingsScreen::class, 'handle_save' ),
			SettingsScreen::ACTION_PROTECT      => array( SettingsScreen::class, 'handle_protect' ),
			LessonScreen::ACTION_DELETE_COMMENT => array( LessonScreen::class, 'handle_delete_comment' ),
			AnnouncementsScreen::ACTION_CREATE  => array( AnnouncementsScreen::class, 'handle_create' ),
			AnnouncementsScreen::ACTION_DELETE  => array( AnnouncementsScreen::class, 'handle_delete' ),
		);

		foreach ( $handlers as $action => $target ) {
			add_action(
				'admin_post_' . $action,
				static function () use ( $container, $target ): void {
					$container->get( $target[0] )->{$target[1]}();
				}
			);
		}

		// Accion "Duplicar" en la lista de cursos.
		add_filter(
			'post_row_actions',
			static function ( array $actions, \WP_Post $post ): array {
				if ( CoursePostType::POST_TYPE !== $post->post_type || ! current_user_can( 'edit_post', $post->ID ) ) {
					return $actions;
				}

				$actions['av_duplicate'] = sprintf(
					'<a href="%s">%s</a>',
					esc_url( EditionsScreen::duplicate_course_url( (int) $post->ID ) ),
					esc_html__( 'Duplicar', 'aula-virtual' )
				);

				return $actions;
			},
			10,
			2
		);
	}
}
