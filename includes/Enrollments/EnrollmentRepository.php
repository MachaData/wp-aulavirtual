<?php
/**
 * Enrollment persistence.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Enrollments;

use SIQA\AulaVirtual\Database\Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and writes enrollments.
 */
final class EnrollmentRepository extends Repository {

	/**
	 * Logical table name.
	 *
	 * @return string
	 */
	protected function table_name(): string {
		return 'enrollments';
	}

	/**
	 * Writable and filterable columns.
	 *
	 * @return array<string, string>
	 */
	protected function columns(): array {
		return array(
			'id'                  => '%d',
			'user_id'             => '%d',
			'course_id'           => '%d',
			'edition_id'          => '%d',
			'status'              => '%s',
			'source'              => '%s',
			'order_id'            => '%d',
			'progress_percentage' => '%f',
			'enrolled_at'         => '%s',
			'approved_at'         => '%s',
			'completed_at'        => '%s',
			'expires_at'          => '%s',
			'last_activity'       => '%s',
			'approved_by'         => '%d',
			'notes'               => '%s',
			'created_at'          => '%s',
			'updated_at'          => '%s',
		);
	}

	/**
	 * Finds the enrollment of a student in an edition.
	 *
	 * @param int $user_id    Student id.
	 * @param int $edition_id Edition id.
	 * @return array<string, mixed>|null
	 */
	public function find_for_student( int $user_id, int $edition_id ): ?array {
		return $this->first(
			array(
				'user_id'    => $user_id,
				'edition_id' => $edition_id,
			)
		);
	}

	/**
	 * Returns the enrollments of a student that grant access, newest first.
	 *
	 * @param int $user_id Student id.
	 * @return array<int, array<string, mixed>>
	 */
	public function active_for_student( int $user_id ): array {
		return $this->all(
			array(
				'where'    => array(
					'user_id' => $user_id,
					'status'  => EnrollmentStatus::with_access(),
				),
				'order_by' => 'enrolled_at',
				'order'    => 'DESC',
				'limit'    => 100,
			)
		);
	}

	/**
	 * Returns the students of an edition.
	 *
	 * @param int $edition_id Edition id.
	 * @return array<int, array<string, mixed>>
	 */
	public function for_edition( int $edition_id ): array {
		return $this->all(
			array(
				'where'    => array( 'edition_id' => $edition_id ),
				'order_by' => 'enrolled_at',
				'order'    => 'DESC',
				'limit'    => 500,
			)
		);
	}

	/**
	 * Counts the students occupying a seat of an edition.
	 *
	 * @param int $edition_id Edition id.
	 * @return int
	 */
	public function count_seats_taken( int $edition_id ): int {
		return $this->count(
			array(
				'edition_id' => $edition_id,
				'status'     => EnrollmentStatus::occupying_seat(),
			)
		);
	}
}
