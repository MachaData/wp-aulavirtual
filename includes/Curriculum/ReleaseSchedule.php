<?php
/**
 * Content drip: when a lesson becomes available to a student.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Curriculum;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure functions over the lesson's release fields and the enrollment.
 *
 * Three modes, stored in `release_type`:
 * - `immediate`: always available.
 * - `date`: available from `release_date` (UTC).
 * - `offset`: available `release_offset` days after `enrolled_at`.
 */
final class ReleaseSchedule {

	public const IMMEDIATE = 'immediate';
	public const DATE      = 'date';
	public const OFFSET    = 'offset';

	/**
	 * Returns the UTC datetime from which the lesson is available, or null
	 * when it is available right away.
	 *
	 * @param array<string, mixed>      $lesson     Lesson row.
	 * @param array<string, mixed>|null $enrollment Enrollment row (needed for offsets).
	 * @return string|null
	 */
	public static function available_at( array $lesson, ?array $enrollment ): ?string {
		$type = (string) ( $lesson['release_type'] ?? self::IMMEDIATE );

		if ( self::DATE === $type ) {
			$date = (string) ( $lesson['release_date'] ?? '' );

			return '' === $date || '0000-00-00 00:00:00' === $date ? null : $date;
		}

		if ( self::OFFSET === $type ) {
			$days = (int) ( $lesson['release_offset'] ?? 0 );
			$from = (string) ( $enrollment['enrolled_at'] ?? '' );

			if ( $days <= 0 || '' === $from ) {
				return null;
			}

			$timestamp = strtotime( $from . ' UTC' );

			return false === $timestamp ? null : gmdate( 'Y-m-d H:i:s', $timestamp + $days * DAY_IN_SECONDS );
		}

		return null;
	}

	/**
	 * Whether the lesson is open for the student at a given moment.
	 *
	 * @param array<string, mixed>      $lesson     Lesson row.
	 * @param array<string, mixed>|null $enrollment Enrollment row.
	 * @param string                    $now        UTC datetime.
	 * @return bool
	 */
	public static function is_available( array $lesson, ?array $enrollment, string $now ): bool {
		$at = self::available_at( $lesson, $enrollment );

		return null === $at || strcmp( $at, $now ) <= 0;
	}
}
