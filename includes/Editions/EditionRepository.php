<?php
/**
 * Edition persistence.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Editions;

use SIQA\AulaVirtual\Database\Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and writes cohorts.
 */
final class EditionRepository extends Repository {

	/**
	 * Logical table name.
	 *
	 * @return string
	 */
	protected function table_name(): string {
		return 'editions';
	}

	/**
	 * Writable and filterable columns.
	 *
	 * @return array<string, string>
	 */
	protected function columns(): array {
		return array(
			'id'                 => '%d',
			'course_id'          => '%d',
			'name'               => '%s',
			'code'               => '%s',
			'status'             => '%s',
			'modality'           => '%s',
			'start_date'         => '%s',
			'end_date'           => '%s',
			'access_start'       => '%s',
			'access_end'         => '%s',
			'timezone'           => '%s',
			'schedule_days'      => '%s',
			'schedule_time'      => '%s',
			'price_display'      => '%s',
			'capacity'           => '%d',
			'enrollment_methods' => '%s',
			'settings'           => '%s',
			'product_id'         => '%d',
			'created_by'         => '%d',
			'created_at'         => '%s',
			'updated_at'         => '%s',
		);
	}

	/**
	 * Returns the editions of a course, oldest start first.
	 *
	 * @param int $course_id Course post id.
	 * @return array<int, array<string, mixed>>
	 */
	public function for_course( int $course_id ): array {
		return $this->all(
			array(
				'where'    => array( 'course_id' => $course_id ),
				'order_by' => 'start_date',
				'order'    => 'ASC',
				'limit'    => 200,
			)
		);
	}

	/**
	 * Finds an edition by its unique code.
	 *
	 * @param string $code Edition code.
	 * @return array<string, mixed>|null
	 */
	public function find_by_code( string $code ): ?array {
		return $this->first( array( 'code' => $code ) );
	}

	/**
	 * Whether a code is already taken by another edition.
	 *
	 * @param string $code    Code to check.
	 * @param int    $exclude Edition id to ignore, when updating.
	 * @return bool
	 */
	public function code_exists( string $code, int $exclude = 0 ): bool {
		$found = $this->find_by_code( $code );

		if ( null === $found ) {
			return false;
		}

		return (int) $found['id'] !== $exclude;
	}
}
