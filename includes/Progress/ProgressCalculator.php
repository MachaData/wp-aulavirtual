<?php
/**
 * Progress arithmetic.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Progress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns "lessons completed out of lessons published" into a percentage.
 *
 * Kept apart from the database so the rounding rules can be tested directly
 * and reused wherever a percentage is shown.
 */
final class ProgressCalculator {

	/**
	 * Percentage of an edition completed by a student.
	 *
	 * An edition with no published lessons is 0%, never 100%: an empty cohort
	 * has not been completed, it has not started.
	 *
	 * @param int $completed Lessons completed.
	 * @param int $total     Published lessons in the edition.
	 * @return float Percentage between 0 and 100, two decimals.
	 */
	public static function percentage( int $completed, int $total ): float {
		if ( $total <= 0 || $completed <= 0 ) {
			return 0.0;
		}

		$completed = min( $completed, $total );

		return round( ( $completed / $total ) * 100, 2 );
	}

	/**
	 * Whether a student finished every published lesson.
	 *
	 * @param int $completed Lessons completed.
	 * @param int $total     Published lessons in the edition.
	 * @return bool
	 */
	public static function is_complete( int $completed, int $total ): bool {
		return $total > 0 && $completed >= $total;
	}
}
