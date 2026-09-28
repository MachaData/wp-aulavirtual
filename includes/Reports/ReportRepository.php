<?php
/**
 * Aggregate queries for the reports module.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Reports;

use SIQA\AulaVirtual\Comments\CommentRepository;
use SIQA\AulaVirtual\Curriculum\LessonType;
use SIQA\AulaVirtual\Database\Schema;
use SIQA\AulaVirtual\Enrollments\EnrollmentStatus;
use SIQA\AulaVirtual\Progress\ProgressRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads the numbers behind the admin reports.
 *
 * This class does not extend the base Repository on purpose: reports are
 * aggregates across several tables (enrollments, progress, lessons, comments,
 * users) and the per-table column map of the base class would only get in the
 * way. Every value is bound through $wpdb->prepare(); table names come from
 * the schema map.
 */
final class ReportRepository {

	/**
	 * User meta key holding the phone saved from the campus profile.
	 */
	public const PHONE_META = 'av_phone';

	/**
	 * Table definitions.
	 *
	 * @var Schema
	 */
	private Schema $schema;

	/**
	 * Constructor.
	 *
	 * @param Schema $schema Table definitions.
	 */
	public function __construct( Schema $schema ) {
		$this->schema = $schema;
	}

	/**
	 * Summary numbers of one edition.
	 *
	 * @param int $edition_id Edition id.
	 * @return array{enrollments_total: int, by_status: array<string, int>, by_source: array<string, int>, avg_progress: float, completed: int, completion_rate: float, active_last_7_days: int, comments: int}
	 */
	public function edition_summary( int $edition_id ): array {
		global $wpdb;

		$enrollments = $this->schema->table( 'enrollments' );
		$comments    = $this->schema->table( 'lesson_comments' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from the schema map.
		$totals = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS total, COALESCE(AVG(progress_percentage), 0) AS avg_progress
				FROM {$enrollments} WHERE edition_id = %d",
				$edition_id
			),
			ARRAY_A
		);

		$total = (int) ( $totals['total'] ?? 0 );

		$by_status = $this->group_count( $enrollments, 'status', 'edition_id = %d', array( $edition_id ) );
		$by_source = $this->group_count( $enrollments, 'source', 'edition_id = %d', array( $edition_id ) );
		$completed = (int) ( $by_status[ EnrollmentStatus::COMPLETED ] ?? 0 );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from the schema map.
		$active = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$enrollments} WHERE edition_id = %d AND last_activity >= %s",
				$edition_id,
				$this->days_ago( 7 )
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from the schema map.
		$comment_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$comments} WHERE edition_id = %d AND status = %s",
				$edition_id,
				CommentRepository::STATUS_APPROVED
			)
		);

		return array(
			'enrollments_total'  => $total,
			'by_status'          => $by_status,
			'by_source'          => $by_source,
			'avg_progress'       => round( (float) ( $totals['avg_progress'] ?? 0 ), 2 ),
			'completed'          => $completed,
			'completion_rate'    => self::rate( $completed, $total ),
			'active_last_7_days' => $active,
			'comments'           => $comment_count,
		);
	}

	/**
	 * Completion of every published lesson of an edition.
	 *
	 * @param int $edition_id Edition id.
	 * @return array<int, array{lesson_id: int, title: string, position: int, completed: int, started: int, rate: float}>
	 */
	public function lesson_completion( int $edition_id ): array {
		global $wpdb;

		$lessons     = $this->schema->table( 'lessons' );
		$progress    = $this->schema->table( 'progress' );
		$enrollments = $this->schema->table( 'enrollments' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from the schema map.
		$total = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$enrollments} WHERE edition_id = %d", $edition_id )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names from the schema map.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT l.id AS lesson_id, l.title, l.position,
					COUNT(p.id) AS started,
					SUM(CASE WHEN p.status = %s THEN 1 ELSE 0 END) AS completed
				FROM {$lessons} l
				LEFT JOIN {$progress} p ON p.lesson_id = l.id AND p.edition_id = l.edition_id
				WHERE l.edition_id = %d AND l.status = %s
				GROUP BY l.id, l.title, l.position
				ORDER BY l.position ASC, l.id ASC",
				ProgressRepository::STATUS_COMPLETED,
				$edition_id,
				LessonType::STATUS_PUBLISH
			),
			ARRAY_A
		);

		$result = array();

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$completed = (int) $row['completed'];

			$result[] = array(
				'lesson_id' => (int) $row['lesson_id'],
				'title'     => (string) $row['title'],
				'position'  => (int) $row['position'],
				'completed' => $completed,
				'started'   => (int) $row['started'],
				'rate'      => self::rate( $completed, $total ),
			);
		}

		return $result;
	}

	/**
	 * One row per enrollment of an edition, with the student's identity.
	 *
	 * @param int $edition_id Edition id.
	 * @return array<int, array{user_id: int, display_name: string, email: string, first_name: string, last_name: string, phone: string, status: string, source: string, progress_percentage: float, enrolled_at: string|null, completed_at: string|null, last_activity: string|null}>
	 */
	public function students( int $edition_id ): array {
		global $wpdb;

		$enrollments = $this->schema->table( 'enrollments' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names from the schema map and $wpdb.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT e.user_id, e.status, e.source, e.progress_percentage, e.enrolled_at, e.completed_at, e.last_activity,
					COALESCE(u.display_name, '') AS display_name,
					COALESCE(u.user_email, '') AS email,
					COALESCE(mf.meta_value, '') AS first_name,
					COALESCE(ml.meta_value, '') AS last_name,
					COALESCE(mp.meta_value, '') AS phone
				FROM {$enrollments} e
				LEFT JOIN {$wpdb->users} u ON u.ID = e.user_id
				LEFT JOIN {$wpdb->usermeta} mf ON mf.user_id = e.user_id AND mf.meta_key = 'first_name'
				LEFT JOIN {$wpdb->usermeta} ml ON ml.user_id = e.user_id AND ml.meta_key = 'last_name'
				LEFT JOIN {$wpdb->usermeta} mp ON mp.user_id = e.user_id AND mp.meta_key = %s
				WHERE e.edition_id = %d
				ORDER BY u.display_name ASC, e.id ASC",
				self::PHONE_META,
				$edition_id
			),
			ARRAY_A
		);

		$result = array();

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$result[] = array(
				'user_id'             => (int) $row['user_id'],
				'display_name'        => (string) $row['display_name'],
				'email'               => (string) $row['email'],
				'first_name'          => (string) $row['first_name'],
				'last_name'           => (string) $row['last_name'],
				'phone'               => (string) $row['phone'],
				'status'              => (string) $row['status'],
				'source'              => (string) $row['source'],
				'progress_percentage' => (float) $row['progress_percentage'],
				'enrolled_at'         => self::nullable_date( $row['enrolled_at'] ),
				'completed_at'        => self::nullable_date( $row['completed_at'] ),
				'last_activity'       => self::nullable_date( $row['last_activity'] ),
			);
		}

		return $result;
	}

	/**
	 * Site-wide totals.
	 *
	 * @return array{editions_by_status: array<string, int>, enrollments_by_status: array<string, int>, students: int, completions: int, top_editions: array<int, array{edition_id: int, name: string, course_id: int, enrollments: int, avg_progress: float}>}
	 */
	public function overview(): array {
		global $wpdb;

		$editions    = $this->schema->table( 'editions' );
		$enrollments = $this->schema->table( 'enrollments' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from the schema map.
		$students = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM {$enrollments}" );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from the schema map.
		$completions = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$enrollments} WHERE status = %s", EnrollmentStatus::COMPLETED )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names from the schema map.
		$top = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ed.id AS edition_id, ed.name, ed.course_id,
					COUNT(e.id) AS enrollments,
					COALESCE(AVG(e.progress_percentage), 0) AS avg_progress
				FROM {$editions} ed
				INNER JOIN {$enrollments} e ON e.edition_id = ed.id
				GROUP BY ed.id, ed.name, ed.course_id
				ORDER BY enrollments DESC, ed.id DESC
				LIMIT %d",
				10
			),
			ARRAY_A
		);

		$top_editions = array();

		foreach ( is_array( $top ) ? $top : array() as $row ) {
			$top_editions[] = array(
				'edition_id'   => (int) $row['edition_id'],
				'name'         => (string) $row['name'],
				'course_id'    => (int) $row['course_id'],
				'enrollments'  => (int) $row['enrollments'],
				'avg_progress' => round( (float) $row['avg_progress'], 2 ),
			);
		}

		return array(
			'editions_by_status'    => $this->group_count( $editions, 'status' ),
			'enrollments_by_status' => $this->group_count( $enrollments, 'status' ),
			'students'              => $students,
			'completions'           => $completions,
			'top_editions'          => $top_editions,
		);
	}

	/**
	 * Counts rows of a table grouped by one column.
	 *
	 * The column name is a literal chosen by this class, never user input.
	 *
	 * @param string            $table  Fully qualified table name.
	 * @param string            $column Column to group by ("status" or "source").
	 * @param string            $where  Optional WHERE fragment with placeholders.
	 * @param array<int, mixed> $values Values for the placeholders.
	 * @return array<string, int>
	 */
	private function group_count( string $table, string $column, string $where = '', array $values = array() ): array {
		global $wpdb;

		$column = 'source' === $column ? 'source' : 'status';
		$sql    = "SELECT {$column} AS k, COUNT(*) AS n FROM {$table}";

		if ( '' !== $where ) {
			$sql .= " WHERE {$where}";
		}

		$sql .= " GROUP BY {$column}";

		if ( array() !== $values ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders written by this class.
			$sql = $wpdb->prepare( $sql, $values );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- prepared above, table name from the schema map.
		$rows = $wpdb->get_results( $sql, ARRAY_A );

		$result = array();

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$result[ (string) $row['k'] ] = (int) $row['n'];
		}

		return $result;
	}

	/**
	 * GMT datetime of N days ago, in the format stored by the plugin.
	 *
	 * @param int $days Days to subtract.
	 * @return string
	 */
	private function days_ago( int $days ): string {
		return gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
	}

	/**
	 * Percentage of part over total, rounded to two decimals, 0 when total is 0.
	 *
	 * @param int $part  Numerator.
	 * @param int $total Denominator.
	 * @return float
	 */
	public static function rate( int $part, int $total ): float {
		if ( $total <= 0 ) {
			return 0.0;
		}

		return round( $part * 100 / $total, 2 );
	}

	/**
	 * Normalises an empty or zero datetime to null.
	 *
	 * @param mixed $value Raw column value.
	 * @return string|null
	 */
	private static function nullable_date( mixed $value ): ?string {
		$value = is_string( $value ) ? trim( $value ) : '';

		if ( '' === $value || str_starts_with( $value, '0000-00-00' ) ) {
			return null;
		}

		return $value;
	}
}
