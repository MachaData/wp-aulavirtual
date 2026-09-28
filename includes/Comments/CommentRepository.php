<?php
/**
 * Lesson comments persistence.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Comments;

use SIQA\AulaVirtual\Database\Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Table `av_lesson_comments`.
 */
final class CommentRepository extends Repository {

	public const STATUS_APPROVED = 'approved';
	public const STATUS_HIDDEN   = 'hidden';

	/**
	 * Logical table name.
	 *
	 * @return string
	 */
	protected function table_name(): string {
		return 'lesson_comments';
	}

	/**
	 * Writable and filterable columns.
	 *
	 * @return array<string, string>
	 */
	protected function columns(): array {
		return array(
			'id'         => '%d',
			'course_id'  => '%d',
			'edition_id' => '%d',
			'lesson_id'  => '%d',
			'user_id'    => '%d',
			'parent_id'  => '%d',
			'is_staff'   => '%d',
			'content'    => '%s',
			'status'     => '%s',
			'created_at' => '%s',
		);
	}

	/**
	 * Returns the visible comments of a lesson, oldest first.
	 *
	 * @param int $lesson_id Lesson id.
	 * @param int $limit     Maximum rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function for_lesson( int $lesson_id, int $limit = 300 ): array {
		return $this->all(
			array(
				'where'    => array(
					'lesson_id' => $lesson_id,
					'status'    => self::STATUS_APPROVED,
				),
				'order_by' => 'created_at',
				'order'    => 'ASC',
				'limit'    => $limit,
			)
		);
	}

	/**
	 * Returns the latest comments of an edition, newest first.
	 *
	 * @param int $edition_id Edition id.
	 * @param int $limit      Maximum rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function latest_for_edition( int $edition_id, int $limit = 50 ): array {
		return $this->all(
			array(
				'where'    => array(
					'edition_id' => $edition_id,
					'status'     => self::STATUS_APPROVED,
				),
				'order_by' => 'created_at',
				'order'    => 'DESC',
				'limit'    => $limit,
			)
		);
	}

	/**
	 * Counts the visible comments of a lesson.
	 *
	 * @param int $lesson_id Lesson id.
	 * @return int
	 */
	public function count_for_lesson( int $lesson_id ): int {
		return $this->count(
			array(
				'lesson_id' => $lesson_id,
				'status'    => self::STATUS_APPROVED,
			)
		);
	}
}
