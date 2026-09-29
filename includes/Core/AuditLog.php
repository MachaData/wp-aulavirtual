<?php
/**
 * Administrative audit trail.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Core;

use SIQA\AulaVirtual\Database\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Records who performed sensitive administrative actions.
 *
 * The audit trail answers "who enrolled this student", "who approved this
 * request" and "who changed this edition". It is intentionally separate from
 * the general log: audit rows are never pruned by the log retention job.
 */
final class AuditLog {

	public const ENROLLMENT_CREATED = 'enrollment.created';
	public const ENROLLMENT_UPDATED = 'enrollment.updated';
	public const ENROLLMENT_DELETED = 'enrollment.deleted';
	public const REQUEST_APPROVED   = 'request.approved';
	public const REQUEST_REJECTED   = 'request.rejected';
	public const EDITION_UPDATED    = 'edition.updated';
	public const EDITION_DELETED    = 'edition.deleted';
	public const ACCESS_EXTENDED    = 'access.extended';
	public const IMPORT_EXECUTED    = 'import.executed';
	public const CERTIFICATE_ISSUED = 'certificate.issued';
	public const SETTINGS_UPDATED   = 'settings.updated';
	public const STUDENT_UPDATED    = 'student.updated';
	public const PASSWORD_SET       = 'student.password_set';
	public const ACCESS_LINK_SENT   = 'student.access_link';
	public const ENROLLMENT_MOVED   = 'enrollment.moved';
	public const PROGRESS_RESET     = 'progress.reset';

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
	 * Records an administrative action.
	 *
	 * @param string               $action        Action constant.
	 * @param string               $object_type   Affected object type, e.g. "edition".
	 * @param int                  $object_id     Affected object id.
	 * @param array<string, mixed> $data          Extra context, ids only.
	 * @param int                  $target_user   User affected by the action, when any.
	 * @return void
	 */
	public function record( string $action, string $object_type, int $object_id, array $data = array(), int $target_user = 0 ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- audit table write.
		$wpdb->insert(
			$this->schema->table( 'audit_log' ),
			array(
				'actor_id'       => get_current_user_id(),
				'action'         => sanitize_text_field( $action ),
				'object_type'    => sanitize_key( $object_type ),
				'object_id'      => $object_id,
				'target_user_id' => $target_user,
				'data'           => wp_json_encode( $data ),
				'created_at'     => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%d', '%d', '%s', '%s' )
		);
	}

	/**
	 * Latest actions that affected a user, newest first.
	 *
	 * @param int $user_id User id.
	 * @param int $limit   Maximum rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function for_user( int $user_id, int $limit = 50 ): array {
		global $wpdb;

		if ( $user_id <= 0 ) {
			return array();
		}

		$table = $this->schema->table( 'audit_log' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from the schema.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE target_user_id = %d ORDER BY id DESC LIMIT %d", $user_id, max( 1, $limit ) ), ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}
}
