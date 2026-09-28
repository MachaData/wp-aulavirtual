<?php
/**
 * Campus module.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Campus;

use SIQA\AulaVirtual\Announcements\AnnouncementRepository;
use SIQA\AulaVirtual\Comments\CommentService;
use SIQA\AulaVirtual\Core\Container;
use SIQA\AulaVirtual\Core\ServiceProvider;
use SIQA\AulaVirtual\Curriculum\LessonRepository;
use SIQA\AulaVirtual\Editions\EditionRepository;
use SIQA\AulaVirtual\Enrollments\EnrollmentRepository;
use SIQA\AulaVirtual\Enrollments\EnrollmentService;
use SIQA\AulaVirtual\LiveClasses\LiveClassRepository;
use SIQA\AulaVirtual\Materials\MaterialRepository;
use SIQA\AulaVirtual\Progress\ProgressRepository;
use SIQA\AulaVirtual\Progress\ProgressService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the campus shortcode and its form handler.
 */
final class CampusServiceProvider implements ServiceProvider {

	/**
	 * Binds the module services.
	 *
	 * @param Container $container Plugin container.
	 * @return void
	 */
	public function register( Container $container ): void {
		$container->singleton(
			CampusController::class,
			static fn( Container $c ): CampusController => new CampusController(
				$c->get( EnrollmentRepository::class ),
				$c->get( EnrollmentService::class ),
				$c->get( EditionRepository::class ),
				$c->get( LessonRepository::class ),
				$c->get( ProgressRepository::class ),
				$c->get( ProgressService::class ),
				$c->get( LiveClassRepository::class ),
				$c->get( MaterialRepository::class ),
				$c->get( AnnouncementRepository::class ),
				$c->get( CommentService::class )
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
		add_action( 'init', array( CampusController::class, 'register_rewrite' ), 20 );
		add_filter( 'query_vars', array( CampusController::class, 'query_vars' ) );

		foreach ( array( CampusController::ACTION_COMMENT => 'handle_comment', CampusController::ACTION_DELETE_COMMENT => 'handle_delete_comment' ) as $action => $method ) {
			add_action(
				'admin_post_' . $action,
				static function () use ( $container, $method ): void {
					$container->get( CampusController::class )->{$method}();
				}
			);
		}

		add_filter(
			'template_include',
			static fn( $template ): string => $container->get( CampusController::class )->focus_template( (string) $template ),
			30
		);

		add_shortcode(
			'av_campus',
			static fn(): string => $container->get( CampusController::class )->render()
		);

		add_action(
			'admin_post_' . CampusController::ACTION_COMPLETE,
			static function () use ( $container ): void {
				$container->get( CampusController::class )->handle_complete();
			}
		);

		add_action(
			'admin_post_' . CampusController::ACTION_RETAKE,
			static function () use ( $container ): void {
				$container->get( CampusController::class )->handle_retake();
			}
		);

		add_action(
			'admin_post_' . CampusController::ACTION_PROFILE,
			static function () use ( $container ): void {
				$container->get( CampusController::class )->handle_profile();
			}
		);
	}
}
