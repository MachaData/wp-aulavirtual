<?php
/**
 * Role installation and synchronisation.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Permissions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates the LMS roles and keeps their capabilities in sync.
 *
 * Roles are stored in the database by WordPress, so capabilities added in a
 * later version would not reach existing sites unless they are re-applied. The
 * version option makes that sync cheap and idempotent.
 */
final class Roles {

	public const ADMINISTRATOR = 'av_admin';
	public const INSTRUCTOR    = 'av_instructor';
	public const STUDENT       = 'av_student';

	/**
	 * Option holding the role definition version already applied.
	 */
	public const VERSION_OPTION = 'av_roles_version';

	/**
	 * Bump this when the capability map changes.
	 */
	public const VERSION = '1.0.0';

	/**
	 * Creates the roles and grants the LMS capabilities to site administrators.
	 *
	 * @return void
	 */
	public static function install(): void {
		self::add_role(
			self::ADMINISTRATOR,
			__( 'Administrador LMS', 'aula-virtual' ),
			Capabilities::administrator_capabilities()
		);

		self::add_role(
			self::INSTRUCTOR,
			__( 'Instructor LMS', 'aula-virtual' ),
			Capabilities::instructor_capabilities()
		);

		self::add_role(
			self::STUDENT,
			__( 'Estudiante LMS', 'aula-virtual' ),
			Capabilities::student_capabilities()
		);

		$administrator = get_role( 'administrator' );

		if ( $administrator instanceof \WP_Role ) {
			foreach ( Capabilities::all() as $capability ) {
				$administrator->add_cap( $capability );
			}
		}

		update_option( self::VERSION_OPTION, self::VERSION, false );
	}

	/**
	 * Re-applies the role definitions when the capability map changed.
	 *
	 * @return bool True when a sync ran.
	 */
	public static function maybe_sync(): bool {
		$installed = get_option( self::VERSION_OPTION, '' );

		if ( is_string( $installed ) && version_compare( $installed, self::VERSION, '>=' ) ) {
			return false;
		}

		self::install();

		return true;
	}

	/**
	 * Removes the LMS roles and capabilities. Only used on uninstall.
	 *
	 * @return void
	 */
	public static function remove(): void {
		foreach ( array( self::ADMINISTRATOR, self::INSTRUCTOR, self::STUDENT ) as $role ) {
			remove_role( $role );
		}

		$administrator = get_role( 'administrator' );

		if ( $administrator instanceof \WP_Role ) {
			foreach ( Capabilities::all() as $capability ) {
				$administrator->remove_cap( $capability );
			}
		}

		delete_option( self::VERSION_OPTION );
	}

	/**
	 * Creates or refreshes a single role.
	 *
	 * @param string            $role         Role slug.
	 * @param string            $label        Display name.
	 * @param array<int, string> $capabilities Capabilities granted to the role.
	 * @return void
	 */
	private static function add_role( string $role, string $label, array $capabilities ): void {
		$granted = array_fill_keys( $capabilities, true );

		if ( null === get_role( $role ) ) {
			add_role( $role, $label, $granted );

			return;
		}

		$existing = get_role( $role );

		foreach ( array_keys( $granted ) as $capability ) {
			$existing->add_cap( $capability );
		}
	}
}
