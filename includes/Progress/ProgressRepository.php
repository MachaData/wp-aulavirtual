<?php
/**
 * Progress persistence.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Progress;

use SIQA\AulaVirtual\Database\Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and writes the per-lesson progress of a student.
 */
final class ProgressRepository extends Repository {

	public const STATUS_STARTED   = 'started';
	public const STATUS_COMPLETED = 'completed';

	/**
	 * Logical table name.
	 *
	 * @return string
	 */
	protected function table_name(): string {
		return 'progress';
	}

	/**
	 * Writable and filterable columns.
	 *
	 * @return array<string, string>
	 */
	protected function columns(): array {
		return array(
			'id'            => '%d',
			'user_id'       => '%d',
			'course_id'     => '%d',
			'edition_id'    => '%d',
			'lesson_id'     => '%d',
			'status'        => '%s',
			'percentage'    => '%f',
			'time_spent'    => '%d',
			'started_at'    => '%s',
			'completed_at'  => '%s',
			'last_activity' => '%s',
		);
	}

	/**
	 * Finds the progress row of a student for one lesson.
	 *
	 * @param int $user_id   Student id.
	 * @param int $lesson_id Lesson id.
	 * @return array<string, mixed>|null
	 */
	public function find_for_lesson( int $user_id, int $lesson_id ): ?array {
		return $this->first(
			array(
				'user_id'   => $user_id,
				'lesson_id' => $lesson_id,
			)
		);
	}

	/**
	 * Returns every progress row of a student inside an edition.
	 *
	 * @param int $user_id    Student id.
	 * @param int $edition_id Edition id.
	 * @return array<int, array<string, mixed>>
	 */
	public function for_edition( int $user_id, int $edition_id ): array {
		return $this->all(
			array(
				'where'    => array(
					'user_id'    => $user_id,
					'edition_id' => $edition_id,
				),
				'order_by' => 'lesson_id',
				'order'    => 'ASC',
				'limit'    => 500,
			)
		);
	}

	/**
	 * Counts the lessons a student completed in an edition.
	 *
	 * @param int $user_id    Student id.
	 * @param int $edition_id Edition id.
	 * @return int
	 */
	public function count_completed( int $user_id, int $edition_id ): int {
		return $this->count(
			array(
				'user_id'    => $user_id,
				'edition_id' => $edition_id,
				'status'     => self::STATUS_COMPLETED,
			)
		);
	}

	/**
	 * Returns the ids of the lessons a student completed in an edition.
	 *
	 * One query for the whole edition instead of one per lesson.
	 *
	 * @param int $user_id    Student id.
	 * @param int $edition_id Edition id.
	 * @return array<int, int>
	 */
	public function completed_lesson_ids( int $user_id, int $edition_id ): array {
		$rows = $this->all(
			array(
				'where' => array(
					'user_id'    => $user_id,
					'edition_id' => $edition_id,
					'status'     => self::STATUS_COMPLETED,
				),
				'limit' => 500,
			)
		);

		return array_map( 'intval', wp_list_pluck( $rows, 'lesson_id' ) );
	}
}
