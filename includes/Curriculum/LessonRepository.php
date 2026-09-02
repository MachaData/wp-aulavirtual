<?php
/**
 * Lesson persistence.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Curriculum;

use SIQA\AulaVirtual\Database\Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and writes the lessons of an edition.
 */
final class LessonRepository extends Repository {

	/**
	 * Logical table name.
	 *
	 * @return string
	 */
	protected function table_name(): string {
		return 'lessons';
	}

	/**
	 * Writable and filterable columns.
	 *
	 * @return array<string, string>
	 */
	protected function columns(): array {
		return array(
			'id'             => '%d',
			'course_id'      => '%d',
			'edition_id'     => '%d',
			'module_id'      => '%d',
			'title'          => '%s',
			'description'    => '%s',
			'content'        => '%s',
			'lesson_type'    => '%s',
			'video_provider' => '%s',
			'video_url'      => '%s',
			'video_meta'     => '%s',
			'duration'       => '%d',
			'featured_image' => '%d',
			'is_preview'     => '%d',
			'position'       => '%d',
			'release_type'   => '%s',
			'release_date'   => '%s',
			'release_offset' => '%d',
			'status'         => '%s',
			'created_at'     => '%s',
			'updated_at'     => '%s',
		);
	}

	/**
	 * Returns the lessons of an edition in presentation order.
	 *
	 * @param int  $edition_id     Edition id.
	 * @param bool $published_only Whether to exclude drafts.
	 * @return array<int, array<string, mixed>>
	 */
	public function for_edition( int $edition_id, bool $published_only = true ): array {
		$where = array( 'edition_id' => $edition_id );

		if ( $published_only ) {
			$where['status'] = LessonType::STATUS_PUBLISH;
		}

		return $this->all(
			array(
				'where'    => $where,
				'order_by' => 'position',
				'order'    => 'ASC',
				'limit'    => 500,
			)
		);
	}

	/**
	 * Counts the published lessons of an edition.
	 *
	 * @param int $edition_id Edition id.
	 * @return int
	 */
	public function count_published( int $edition_id ): int {
		return $this->count(
			array(
				'edition_id' => $edition_id,
				'status'     => LessonType::STATUS_PUBLISH,
			)
		);
	}

	/**
	 * Returns the position for a new lesson at the end of the edition.
	 *
	 * @param int $edition_id Edition id.
	 * @return int
	 */
	public function next_position( int $edition_id ): int {
		$last = $this->all(
			array(
				'where'    => array( 'edition_id' => $edition_id ),
				'order_by' => 'position',
				'order'    => 'DESC',
				'limit'    => 1,
			)
		);

		return array() === $last ? 1 : (int) $last[0]['position'] + 1;
	}
}
