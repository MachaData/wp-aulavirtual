<?php
/**
 * Material persistence.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Materials;

use SIQA\AulaVirtual\Database\Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and writes downloadable materials, attached to a lesson or to the
 * edition as a whole (lesson_id = 0).
 */
final class MaterialRepository extends Repository {

	/**
	 * Logical table name.
	 *
	 * @return string
	 */
	protected function table_name(): string {
		return 'materials';
	}

	/**
	 * Writable and filterable columns.
	 *
	 * @return array<string, string>
	 */
	protected function columns(): array {
		return array(
			'id'            => '%d',
			'course_id'     => '%d',
			'edition_id'    => '%d',
			'lesson_id'     => '%d',
			'title'         => '%s',
			'description'   => '%s',
			'attachment_id' => '%d',
			'external_url'  => '%s',
			'file_type'     => '%s',
			'downloadable'  => '%d',
			'position'      => '%d',
			'created_by'    => '%d',
			'created_at'    => '%s',
		);
	}

	/**
	 * Materials of a lesson in order.
	 *
	 * @param int $lesson_id Lesson id.
	 * @return array<int, array<string, mixed>>
	 */
	public function for_lesson( int $lesson_id ): array {
		return $this->all(
			array(
				'where'    => array( 'lesson_id' => $lesson_id ),
				'order_by' => 'position',
				'order'    => 'ASC',
				'limit'    => 100,
			)
		);
	}

	/**
	 * General materials of an edition (not tied to a lesson).
	 *
	 * @param int $edition_id Edition id.
	 * @return array<int, array<string, mixed>>
	 */
	public function for_edition( int $edition_id ): array {
		return $this->all(
			array(
				'where'    => array(
					'edition_id' => $edition_id,
					'lesson_id'  => 0,
				),
				'order_by' => 'position',
				'order'    => 'ASC',
				'limit'    => 100,
			)
		);
	}
}
