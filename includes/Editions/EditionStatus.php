<?php
/**
 * Edition states and access window.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Editions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * States of a cohort and the rule that decides when its content is reachable.
 *
 * The access window is deliberately separate from the state: an edition can be
 * "en curso" while a particular student's access has not opened yet, and the
 * content must stay closed until the date says otherwise.
 */
final class EditionStatus {

	public const DRAFT      = 'draft';
	public const UPCOMING   = 'upcoming';
	public const OPEN       = 'open';
	public const RUNNING    = 'running';
	public const FINISHED   = 'finished';
	public const ARCHIVED   = 'archived';

	/**
	 * Every valid state.
	 *
	 * @return array<int, string>
	 */
	public static function all(): array {
		return array( self::DRAFT, self::UPCOMING, self::OPEN, self::RUNNING, self::FINISHED, self::ARCHIVED );
	}

	/**
	 * Human labels for the admin screens.
	 *
	 * @return array<string, string>
	 */
	public static function labels(): array {
		return array(
			self::DRAFT    => __( 'Borrador', 'aula-virtual' ),
			self::UPCOMING => __( 'Proxima', 'aula-virtual' ),
			self::OPEN     => __( 'Matricula abierta', 'aula-virtual' ),
			self::RUNNING  => __( 'En curso', 'aula-virtual' ),
			self::FINISHED => __( 'Finalizada', 'aula-virtual' ),
			self::ARCHIVED => __( 'Archivada', 'aula-virtual' ),
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

	/**
	 * States in which new students may be enrolled.
	 *
	 * A cohort already running still accepts enrollments: late admissions are
	 * routine. Drafts, finished and archived cohorts do not.
	 *
	 * @return array<int, string>
	 */
	public static function enrollable(): array {
		return array( self::UPCOMING, self::OPEN, self::RUNNING );
	}

	/**
	 * Whether new enrollments are accepted in a given state.
	 *
	 * @param string $status State.
	 * @return bool
	 */
	public static function accepts_enrollments( string $status ): bool {
		return in_array( $status, self::enrollable(), true );
	}

	/**
	 * Whether the content of the edition is reachable at a given moment.
	 *
	 * Both bounds are optional: an edition with no `access_start` is open from
	 * the beginning, and one with no `access_end` never expires. Dates are
	 * compared as UTC strings, which is safe because every date is stored in
	 * UTC and the format sorts lexicographically.
	 *
	 * @param string|null $access_start Opening date, MySQL UTC format.
	 * @param string|null $access_end   Closing date, MySQL UTC format.
	 * @param string      $now          Moment to evaluate, MySQL UTC format.
	 * @return bool
	 */
	public static function window_is_open( ?string $access_start, ?string $access_end, string $now ): bool {
		if ( null !== $access_start && '' !== $access_start && $now < $access_start ) {
			return false;
		}

		if ( null !== $access_end && '' !== $access_end && $now > $access_end ) {
			return false;
		}

		return true;
	}
}
