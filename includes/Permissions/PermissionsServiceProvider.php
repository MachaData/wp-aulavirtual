<?php
/**
 * Permissions module.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Permissions;

use SIQA\AulaVirtual\Core\Container;
use SIQA\AulaVirtual\Core\ServiceProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps roles in sync and keeps students out of wp-admin.
 */
final class PermissionsServiceProvider implements ServiceProvider {

	/**
	 * Option toggling the wp-admin redirect for students.
	 */
	public const BLOCK_ADMIN_OPTION = 'av_block_wp_admin';

	/**
	 * Binds the module services.
	 *
	 * @param Container $container Plugin container.
	 * @return void
	 */
	public function register( Container $container ): void {
		$container->singleton( AccessControl::class, static fn(): AccessControl => new AccessControl() );
	}

	/**
	 * Registers the module hooks.
	 *
	 * @param Container $container Plugin container.
	 * @return void
	 */
	public function boot( Container $container ): void {
		add_action( 'admin_init', array( Roles::class, 'maybe_sync' ), 5 );
		add_action( 'admin_init', array( $this, 'block_wp_admin_for_students' ), 1 );
		add_filter( 'show_admin_bar', array( $this, 'hide_admin_bar_for_students' ) );
	}

	/**
	 * Redirects LMS students away from wp-admin.
	 *
	 * Only accounts whose role is the LMS student role are redirected, so
	 * subscribers and any other existing role on the site keep their current
	 * behaviour.
	 *
	 * @return void
	 */
	public function block_wp_admin_for_students(): void {
		if ( wp_doing_ajax() || wp_doing_cron() || ! is_user_logged_in() ) {
			return;
		}

		if ( ! $this->is_student_only() ) {
			return;
		}

		if ( ! get_option( self::BLOCK_ADMIN_OPTION, true ) ) {
			return;
		}

		/**
		 * Filters the URL LMS students are sent to instead of wp-admin.
		 *
		 * @param string $url Redirect target.
		 */
		$redirect = apply_filters( 'aula_virtual/campus_redirect_url', home_url( '/campus/' ) );

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Hides the admin bar for LMS students.
	 *
	 * @param bool $show Whether to show the admin bar.
	 * @return bool
	 */
	public function hide_admin_bar_for_students( $show ) {
		return $this->is_student_only() ? false : (bool) $show;
	}

	/**
	 * Whether the current user only holds the LMS student role.
	 *
	 * @return bool
	 */
	private function is_student_only(): bool {
		$user = wp_get_current_user();

		if ( ! $user instanceof \WP_User || 0 === $user->ID ) {
			return false;
		}

		return array( Roles::STUDENT ) === array_values( (array) $user->roles );
	}
}
