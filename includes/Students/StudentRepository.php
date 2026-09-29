<?php
/**
 * Student listing across editions.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Students;

use SIQA\AulaVirtual\Database\Schema;
use SIQA\AulaVirtual\Enrollments\EnrollmentStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One row per person with at least one enrollment.
 */
final class StudentRepository {

	/**
	 * Schema.
	 *
	 * @var Schema
	 */
	private Schema $schema;

	/**
	 * Constructor.
	 *
	 * @param Schema $schema Schema.
	 */
	public function __construct( Schema $schema ) {
		$this->schema = $schema;
	}

	/**
	 * Returns a page of students.
	 *
	 * @param array{search?: string, edition_id?: int, status?: string, course_ids?: array<int, int>|null} $filters Filters. course_ids null = every course.
	 * @param int                                                                                           $page    Page (1-based).
	 * @param int                                                                                           $per_page Rows per page.
	 * @return array{items: array<int, array<string, mixed>>, total: int}
	 */
	public function search( array $filters, int $page = 1, int $per_page = 30 ): array {
		global $wpdb;

		$table  = $this->schema->table( 'enrollments' );
		$where  = array( '1 = 1' );
		$values = array();

		if ( ! empty( $filters['edition_id'] ) ) {
			$where[]  = 'e.edition_id = %d';
			$values[] = (int) $filters['edition_id'];
		}

		if ( ! empty( $filters['status'] ) && in_array( $filters['status'], EnrollmentStatus::all(), true ) ) {
			$where[]  = 'e.status = %s';
			$values[] = (string) $filters['status'];
		}

		if ( isset( $filters['course_ids'] ) && is_array( $filters['course_ids'] ) ) {
			$ids = array_values( array_filter( array_map( 'intval', $filters['course_ids'] ) ) );

			if ( array() === $ids ) {
				return array( 'items' => array(), 'total' => 0 );
			}

			$where[] = 'e.course_id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')';
			$values  = array_merge( $values, $ids );
		}

		$search = trim( (string) ( $filters['search'] ?? '' ) );

		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '(u.display_name LIKE %s OR u.user_email LIKE %s OR u.user_login LIKE %s)';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}

		$from  = "FROM {$table} e INNER JOIN {$wpdb->users} u ON u.ID = e.user_id WHERE " . implode( ' AND ', $where );
		$grant = "'" . implode( "','", array_map( 'esc_sql', EnrollmentStatus::with_access() ) ) . "'";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- placeholders built above.
		$total = (int) $wpdb->get_var( array() === $values ? "SELECT COUNT(DISTINCT e.user_id) {$from}" : $wpdb->prepare( "SELECT COUNT(DISTINCT e.user_id) {$from}", $values ) );

		$sql = "SELECT e.user_id, u.display_name, u.user_email, COUNT(*) AS enrollments,
				SUM( CASE WHEN e.status IN ({$grant}) THEN 1 ELSE 0 END ) AS with_access,
				MAX(e.last_activity) AS last_activity, MAX(e.enrolled_at) AS last_enrolled
			{$from}
			GROUP BY e.user_id, u.display_name, u.user_email
			ORDER BY u.display_name ASC
			LIMIT %d OFFSET %d";

		$values[] = max( 1, $per_page );
		$values[] = max( 0, ( max( 1, $page ) - 1 ) * $per_page );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- placeholders built above.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A );

		return array(
			'items' => is_array( $rows ) ? $rows : array(),
			'total' => $total,
		);
	}
}
