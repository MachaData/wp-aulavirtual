<?php
/**
 * Access state of student accounts.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Students;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Remembers whether a student still has to create a password and when
 * they last signed in, and sets how long password links last.
 */
final class AccountHelper {

	public const META_NEEDS_PASSWORD = 'av_needs_password';
	public const META_LAST_LOGIN     = 'av_last_login';
	public const OPTION_LINK_HOURS   = 'av_password_link_hours';
	public const DEFAULT_LINK_HOURS  = 72;

	/**
	 * Registers the WordPress hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'wp_login', array( self::class, 'on_login' ), 10, 2 );
		add_action( 'after_password_reset', array( self::class, 'on_password_set' ) );
		add_filter( 'password_reset_expiration', array( self::class, 'link_lifetime' ) );
	}

	/**
	 * Marks an account created by the plugin: its password is random and
	 * unknown, so the student must create one.
	 *
	 * @param int $user_id User id.
	 * @return void
	 */
	public static function mark_needs_password( int $user_id ): void {
		if ( $user_id > 0 ) {
			update_user_meta( $user_id, self::META_NEEDS_PASSWORD, 1 );
		}
	}

	/**
	 * Whether the student has not created a password yet.
	 *
	 * @param int $user_id User id.
	 * @return bool
	 */
	public static function needs_password( int $user_id ): bool {
		return $user_id > 0 && (bool) get_user_meta( $user_id, self::META_NEEDS_PASSWORD, true );
	}

	/**
	 * Last sign-in (UTC), or an empty string.
	 *
	 * @param int $user_id User id.
	 * @return string
	 */
	public static function last_login( int $user_id ): string {
		return (string) get_user_meta( $user_id, self::META_LAST_LOGIN, true );
	}

	/**
	 * Records a sign-in; anyone who signs in knows their password.
	 *
	 * @param string   $login Login name.
	 * @param \WP_User $user  User.
	 * @return void
	 */
	public static function on_login( $login, $user = null ): void {
		if ( ! $user instanceof \WP_User ) {
			return;
		}

		update_user_meta( $user->ID, self::META_LAST_LOGIN, current_time( 'mysql', true ) );
		delete_user_meta( $user->ID, self::META_NEEDS_PASSWORD );
	}

	/**
	 * Clears the flag once a password has been chosen.
	 *
	 * @param \WP_User|int $user User.
	 * @return void
	 */
	public static function on_password_set( $user ): void {
		$user_id = $user instanceof \WP_User ? $user->ID : (int) $user;

		if ( $user_id > 0 ) {
			delete_user_meta( $user_id, self::META_NEEDS_PASSWORD );
		}
	}

	/**
	 * Hours a password link stays valid (1 to 168).
	 *
	 * @return int
	 */
	public static function link_hours(): int {
		return max( 1, min( 168, (int) get_option( self::OPTION_LINK_HOURS, self::DEFAULT_LINK_HOURS ) ) );
	}

	/**
	 * Lifetime of password reset keys, in seconds.
	 *
	 * @param int $seconds WordPress default.
	 * @return int
	 */
	public static function link_lifetime( $seconds ): int {
		return self::link_hours() * HOUR_IN_SECONDS;
	}

	/**
	 * Human text for the link lifetime ("72 horas", "3 días").
	 *
	 * @param int $hours Hours.
	 * @return string
	 */
	public static function lifetime_label( int $hours ): string {
		if ( 0 === $hours % 24 && $hours >= 48 ) {
			/* translators: %d: days. */
			return sprintf( __( '%d días', 'aula-virtual' ), intdiv( $hours, 24 ) );
		}

		/* translators: %d: hours. */
		return sprintf( _n( '%d hora', '%d horas', $hours, 'aula-virtual' ), $hours );
	}
}
