<?php
/**
 * Announcement persistence.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Announcements;

use SIQA\AulaVirtual\Database\Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and writes announcements for a course or one of its editions.
 */
final class AnnouncementRepository extends Repository {

	/**
	 * Logical table name.
	 *
	 * @return string
	 */
	protected function table_name(): string {
		return 'announcements';
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
			'author_id'  => '%d',
			'title'      => '%s',
			'content'    => '%s',
			'send_email' => '%d',
			'status'     => '%s',
			'created_at' => '%s',
		);
	}

	/**
	 * Announcements visible to a student of an edition: the edition's own and
	 * the course-wide ones (edition_id = 0), newest first.
	 *
	 * @param int $course_id  Course id.
	 * @param int $edition_id Edition id.
	 * @param int $limit      Maximum rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function for_student( int $course_id, int $edition_id, int $limit = 10 ): array {
		global $wpdb;

		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name comes from the schema map.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE course_id = %d AND edition_id IN (0, %d) AND status = %s ORDER BY created_at DESC LIMIT %d",
				$course_id,
				$edition_id,
				'publish',
				$limit
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}
}
