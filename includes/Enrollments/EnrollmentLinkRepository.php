<?php
/**
 * Registration link persistence.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Enrollments;

use SIQA\AulaVirtual\Database\Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and writes the private registration links of an edition.
 */
final class EnrollmentLinkRepository extends Repository {

	public const STATUS_ACTIVE   = 'active';
	public const STATUS_DISABLED = 'disabled';

	/**
	 * Logical table name.
	 *
	 * @return string
	 */
	protected function table_name(): string {
		return 'enrollment_links';
	}

	/**
	 * Writable and filterable columns.
	 *
	 * @return array<string, string>
	 */
	protected function columns(): array {
		return array(
			'id'                => '%d',
			'course_id'         => '%d',
			'edition_id'        => '%d',
			'token'             => '%s',
			'label'             => '%s',
			'status'            => '%s',
			'requires_approval' => '%d',
			'max_uses'          => '%d',
			'uses'              => '%d',
			'fields'            => '%s',
			'expires_at'        => '%s',
			'created_by'        => '%d',
			'created_at'        => '%s',
		);
	}

	/**
	 * Finds a link by its token.
	 *
	 * @param string $token Token.
	 * @return array<string, mixed>|null
	 */
	public function find_by_token( string $token ): ?array {
		return $this->first( array( 'token' => $token ) );
	}

	/**
	 * Returns the links of an edition, newest first.
	 *
	 * @param int $edition_id Edition id.
	 * @return array<int, array<string, mixed>>
	 */
	public function for_edition( int $edition_id ): array {
		return $this->all(
			array(
				'where' => array( 'edition_id' => $edition_id ),
				'limit' => 50,
			)
		);
	}

	/**
	 * Increments the use counter of a link.
	 *
	 * @param int $link_id Link id.
	 * @return void
	 */
	public function increment_uses( int $link_id ): void {
		global $wpdb;

		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name comes from the schema map.
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET uses = uses + 1 WHERE id = %d", $link_id ) );
	}
}
