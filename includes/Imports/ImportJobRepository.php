<?php
/**
 * Import job persistence.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Imports;

use SIQA\AulaVirtual\Database\Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and writes import jobs.
 */
final class ImportJobRepository extends Repository {

	/**
	 * Logical table name.
	 *
	 * @return string
	 */
	protected function table_name(): string {
		return 'import_jobs';
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
			'user_id'        => '%d',
			'filename'       => '%s',
			'status'         => '%s',
			'total_rows'     => '%d',
			'processed_rows' => '%d',
			'created_users'  => '%d',
			'enrolled'       => '%d',
			'skipped'        => '%d',
			'failed'         => '%d',
			'send_welcome'   => '%d',
			'mapping'        => '%s',
			'errors'         => '%s',
			'created_at'     => '%s',
			'updated_at'     => '%s',
		);
	}
}
