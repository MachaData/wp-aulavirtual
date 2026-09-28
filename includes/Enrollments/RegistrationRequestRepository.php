<?php
/**
 * Registration request persistence.
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
 * Reads and writes the requests submitted through a registration link.
 */
final class RegistrationRequestRepository extends Repository {

	public const STATUS_PENDING  = 'pending';
	public const STATUS_APPROVED = 'approved';
	public const STATUS_ENROLLED = 'enrolled';
	public const STATUS_REJECTED = 'rejected';

	/**
	 * Logical table name.
	 *
	 * @return string
	 */
	protected function table_name(): string {
		return 'registration_requests';
	}

	/**
	 * Writable and filterable columns.
	 *
	 * @return array<string, string>
	 */
	protected function columns(): array {
		return array(
			'id'               => '%d',
			'course_id'        => '%d',
			'edition_id'       => '%d',
			'link_id'          => '%d',
			'first_name'       => '%s',
			'last_name'        => '%s',
			'email'            => '%s',
			'phone'            => '%s',
			'document'         => '%s',
			'custom_data'      => '%s',
			'status'           => '%s',
			'user_id'          => '%d',
			'rejection_reason' => '%s',
			'created_at'       => '%s',
			'approved_at'      => '%s',
			'approved_by'      => '%d',
		);
	}

	/**
	 * Every valid state.
	 *
	 * @return array<int, string>
	 */
	public static function statuses(): array {
		return array( self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_ENROLLED, self::STATUS_REJECTED );
	}

	/**
	 * Human labels for the admin screens.
	 *
	 * @return array<string, string>
	 */
	public static function labels(): array {
		return array(
			self::STATUS_PENDING  => __( 'Pendiente', 'aula-virtual' ),
			self::STATUS_APPROVED => __( 'Aprobada, pago pendiente', 'aula-virtual' ),
			self::STATUS_ENROLLED => __( 'Matriculada', 'aula-virtual' ),
			self::STATUS_REJECTED => __( 'Rechazada', 'aula-virtual' ),
		);
	}

	/**
	 * Finds an open request of an email for an edition.
	 *
	 * @param string $email      Email address.
	 * @param int    $edition_id Edition id.
	 * @return array<string, mixed>|null
	 */
	public function find_open( string $email, int $edition_id ): ?array {
		return $this->first(
			array(
				'email'      => $email,
				'edition_id' => $edition_id,
				'status'     => array( self::STATUS_PENDING, self::STATUS_APPROVED ),
			)
		);
	}

	/**
	 * Counts pending requests, optionally for one edition.
	 *
	 * @param int $edition_id Edition id, 0 for all.
	 * @return int
	 */
	public function count_pending( int $edition_id = 0 ): int {
		$where = array( 'status' => self::STATUS_PENDING );

		if ( $edition_id > 0 ) {
			$where['edition_id'] = $edition_id;
		}

		return $this->count( $where );
	}
}
