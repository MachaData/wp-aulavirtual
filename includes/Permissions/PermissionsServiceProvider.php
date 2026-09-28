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

		// El campus envia sus formularios (completar, comentar, perfil, descargar)
		// a admin-post.php, que tambien dispara admin_init. Cada manejador
		// comprueba sesion, nonce y permisos por su cuenta.
		if ( self::is_admin_post_request() ) {
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
		$redirect = apply_filters( 'aula_virtual/campus_redirect_url', $this->campus_url() );

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
	 * Returns the campus page created on activation.
	 *
	 * Falling back to /campus/ keeps the redirect working if the page was
	 * deleted, instead of sending the student to a blank screen.
	 *
	 * @return string
	 */
	private function campus_url(): string {
		$pages   = get_option( 'av_pages', array() );
		$page_id = is_array( $pages ) && isset( $pages['campus'] ) ? (int) $pages['campus'] : 0;

		if ( $page_id > 0 ) {
			$permalink = get_permalink( $page_id );

			if ( is_string( $permalink ) && '' !== $permalink ) {
				return $permalink;
			}
		}

		return home_url( '/campus/' );
	}

	/**
	 * Whether the request is wp-admin/admin-post.php (form handlers).
	 *
	 * @return bool
	 */
	public static function is_admin_post_request(): bool {
		$page = $GLOBALS['pagenow'] ?? '';

		if ( 'admin-post.php' === $page ) {
			return true;
		}

		$script = isset( $_SERVER['SCRIPT_NAME'] ) ? basename( (string) $_SERVER['SCRIPT_NAME'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- compared with a literal.

		return 'admin-post.php' === $script;
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
