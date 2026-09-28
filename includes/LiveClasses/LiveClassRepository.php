<?php
/**
 * Live class persistence.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\LiveClasses;

use SIQA\AulaVirtual\Database\Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and writes the live session attached to a lesson.
 */
final class LiveClassRepository extends Repository {

	public const STATUS_SCHEDULED = 'scheduled';
	public const STATUS_DONE      = 'done';
	public const STATUS_CANCELLED = 'cancelled';

	/**
	 * Logical table name.
	 *
	 * @return string
	 */
	protected function table_name(): string {
		return 'live_classes';
	}

	/**
	 * Writable and filterable columns.
	 *
	 * @return array<string, string>
	 */
	protected function columns(): array {
		return array(
			'id'             => '%d',
			'lesson_id'      => '%d',
			'edition_id'     => '%d',
			'provider'       => '%s',
			'meeting_url'    => '%s',
			'meeting_id'     => '%s',
			'access_code'    => '%s',
			'start_datetime' => '%s',
			'end_datetime'   => '%s',
			'timezone'       => '%s',
			'open_before'    => '%d',
			'close_after'    => '%d',
			'message'        => '%s',
			'recording_url'  => '%s',
			'status'         => '%s',
			'created_at'     => '%s',
			'updated_at'     => '%s',
		);
	}

	/**
	 * The live class of a lesson, if any.
	 *
	 * @param int $lesson_id Lesson id.
	 * @return array<string, mixed>|null
	 */
	public function for_lesson( int $lesson_id ): ?array {
		return $this->first( array( 'lesson_id' => $lesson_id ) );
	}

	/**
	 * Upcoming live classes of an edition, soonest first.
	 *
	 * @param int    $edition_id Edition id.
	 * @param string $from       UTC datetime lower bound.
	 * @param int    $limit      Maximum rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function upcoming( int $edition_id, string $from, int $limit = 5 ): array {
		global $wpdb;

		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name comes from the schema map.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE edition_id = %d AND status = %s AND end_datetime >= %s ORDER BY start_datetime ASC LIMIT %d",
				$edition_id,
				self::STATUS_SCHEDULED,
				$from,
				$limit
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}
}
