<?php
/**
 * Administrative audit trail.
 *
 * @package SEV\LMS
 */

declare( strict_types = 1 );

namespace SEV\LMS\Core;

use SEV\LMS\Database\Schema;

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
}
