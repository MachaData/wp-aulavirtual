<?php
/**
 * Comments module.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Comments;

use SIQA\AulaVirtual\Core\Container;
use SIQA\AulaVirtual\Core\Events\EventBus;
use SIQA\AulaVirtual\Core\ServiceProvider;
use SIQA\AulaVirtual\Curriculum\LessonRepository;
use SIQA\AulaVirtual\Database\Schema;
use SIQA\AulaVirtual\Enrollments\EnrollmentService;
use SIQA\AulaVirtual\Permissions\AccessControl;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the lesson comments services.
 */
final class CommentsServiceProvider implements ServiceProvider {

	/**
	 * Binds the module services.
	 *
	 * @param Container $container Plugin container.
	 * @return void
	 */
	public function register( Container $container ): void {
		$container->singleton(
			CommentRepository::class,
			static fn( Container $c ): CommentRepository => new CommentRepository( $c->get( Schema::class ) )
		);

		$container->singleton(
			CommentService::class,
			static fn( Container $c ): CommentService => new CommentService(
				$c->get( CommentRepository::class ),
				$c->get( LessonRepository::class ),
				$c->get( EnrollmentService::class ),
				$c->get( AccessControl::class ),
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
