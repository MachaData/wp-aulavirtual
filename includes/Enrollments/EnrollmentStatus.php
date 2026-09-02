<?php
/**
 * Enrollment states and sources.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Enrollments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The lifecycle of a student inside a cohort.
 *
 * States are never deleted, only moved: a cancelled enrollment keeps its
 * history so an institution can still answer who was in which cohort.
 */
final class EnrollmentStatus {

	public const PENDING   = 'pending';
	public const APPROVED  = 'approved';
	public const ACTIVE    = 'active';
	public const COMPLETED = 'completed';
	public const SUSPENDED = 'suspended';
	public const EXPIRED   = 'expired';
	public const CANCELLED = 'cancelled';
	public const REJECTED  = 'rejected';

	public const SOURCE_MANUAL       = 'manual';
	public const SOURCE_WOOCOMMERCE  = 'woocommerce';
	public const SOURCE_EXCEL        = 'excel';
	public const SOURCE_PRIVATE_LINK = 'private_link';
	public const SOURCE_FREE         = 'free';
	public const SOURCE_ADMIN        = 'admin';

	/**
	 * Every valid state.
	 *
	 * @return array<int, string>
	 */
	public static function all(): array {
		return array(
			self::PENDING,
			self::APPROVED,
			self::ACTIVE,
			self::COMPLETED,
			self::SUSPENDED,
			self::EXPIRED,
			self::CANCELLED,
			self::REJECTED,
		);
	}

	/**
	 * Every valid source.
	 *
	 * @return array<int, string>
	 */
	public static function sources(): array {
		return array(
			self::SOURCE_MANUAL,
			self::SOURCE_WOOCOMMERCE,
			self::SOURCE_EXCEL,
			self::SOURCE_PRIVATE_LINK,
			self::SOURCE_FREE,
			self::SOURCE_ADMIN,
		);
	}

	/**
	 * States that grant access to the content of the edition.
	 *
	 * A completed student keeps access: finishing a course is not a reason to
	 * lose the material.
	 *
	 * @return array<int, string>
	 */
	public static function with_access(): array {
		return array( self::APPROVED, self::ACTIVE, self::COMPLETED );
	}

	/**
	 * Whether a state grants access to the content.
	 *
	 * @param string $status State.
	 * @return bool
	 */
	public static function grants_access( string $status ): bool {
		return in_array( $status, self::with_access(), true );
	}

	/**
	 * States that occupy a seat of the cohort.
	 *
	 * @return array<int, string>
	 */
	public static function occupying_seat(): array {
		return array( self::PENDING, self::APPROVED, self::ACTIVE, self::COMPLETED );
	}

	/**
	 * Human labels for the admin screens.
	 *
	 * @return array<string, string>
	 */
	public static function labels(): array {
		return array(
			self::PENDING   => __( 'Pendiente', 'aula-virtual' ),
			self::APPROVED  => __( 'Aprobada', 'aula-virtual' ),
			self::ACTIVE    => __( 'Activa', 'aula-virtual' ),
			self::COMPLETED => __( 'Completada', 'aula-virtual' ),
			self::SUSPENDED => __( 'Suspendida', 'aula-virtual' ),
			self::EXPIRED   => __( 'Expirada', 'aula-virtual' ),
			self::CANCELLED => __( 'Cancelada', 'aula-virtual' ),
			self::REJECTED  => __( 'Rechazada', 'aula-virtual' ),
		);
	}

	/**
	 * Returns the label of a state.
	 *
	 * @param string $status State.
	 * @return string
	 */
	public static function label( string $status ): string {
		return self::labels()[ $status ] ?? $status;
	}
}
