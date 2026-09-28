<?php
/**
 * Module persistence.
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
 * Reads and writes the modules that group the lessons of an edition.
 */
final class ModuleRepository extends Repository {

	/**
	 * Logical table name.
	 *
	 * @return string
	 */
	protected function table_name(): string {
		return 'modules';
	}

	/**
	 * Writable and filterable columns.
	 *
	 * @return array<string, string>
	 */
	protected function columns(): array {
		return array(
			'id'          => '%d',
			'course_id'   => '%d',
			'edition_id'  => '%d',
			'title'       => '%s',
			'description' => '%s',
			'position'    => '%d',
			'status'      => '%s',
			'created_at'  => '%s',
			'updated_at'  => '%s',
		);
	}

	/**
	 * Returns the modules of an edition in order.
	 *
	 * @param int $edition_id Edition id.
	 * @return array<int, array<string, mixed>>
	 */
	public function for_edition( int $edition_id ): array {
		return $this->all(
			array(
				'where'    => array( 'edition_id' => $edition_id ),
				'order_by' => 'position',
				'order'    => 'ASC',
				'limit'    => 200,
			)
		);
	}
}
