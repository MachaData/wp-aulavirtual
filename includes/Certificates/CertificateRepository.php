<?php
/**
 * Certificate persistence.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Certificates;

use SIQA\AulaVirtual\Database\Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and writes the certificates issued to students.
 */
final class CertificateRepository extends Repository {

	public const STATUS_ISSUED  = 'issued';
	public const STATUS_REVOKED = 'revoked';

	/**
	 * Logical table name.
	 *
	 * @return string
	 */
	protected function table_name(): string {
		return 'certificates';
	}

	/**
	 * Writable and filterable columns.
	 *
	 * @return array<string, string>
	 */
	protected function columns(): array {
		return array(
			'id'                => '%d',
			'user_id'           => '%d',
			'course_id'         => '%d',
			'edition_id'        => '%d',
			'certificate_code'  => '%s',
			'verification_hash' => '%s',
			'file_url'          => '%s',
			'status'            => '%s',
			'issued_at'         => '%s',
			'issued_by'         => '%d',
			'revoked_at'        => '%s',
		);
	}

	/**
	 * Finds a certificate by its public code, whatever its status.
	 *
	 * @param string $code Certificate code.
	 * @return array<string, mixed>|null
	 */
	public function find_by_code( string $code ): ?array {
		$code = trim( $code );

		if ( '' === $code ) {
			return null;
		}

		return $this->first( array( 'certificate_code' => $code ) );
	}

	/**
	 * Returns every certificate of a student, newest first.
	 *
	 * @param int $user_id Student id.
	 * @return array<int, array<string, mixed>>
	 */
	public function for_student( int $user_id ): array {
		return $this->all(
			array(
				'where'    => array( 'user_id' => $user_id ),
				'order_by' => 'issued_at',
				'order'    => 'DESC',
				'limit'    => 100,
			)
		);
	}

	/**
	 * Returns every certificate of an edition, newest first.
	 *
	 * @param int $edition_id Edition id.
	 * @return array<int, array<string, mixed>>
	 */
	public function for_edition( int $edition_id ): array {
		return $this->all(
			array(
				'where'    => array( 'edition_id' => $edition_id ),
				'order_by' => 'issued_at',
				'order'    => 'DESC',
				'limit'    => 500,
			)
		);
	}

	/**
	 * Finds the certificate of a student in an edition.
	 *
	 * When several exist (a revoked one followed by a new issue), the most
	 * recent row wins.
	 *
	 * @param int $user_id    Student id.
	 * @param int $edition_id Edition id.
	 * @return array<string, mixed>|null
	 */
	public function find_for( int $user_id, int $edition_id ): ?array {
		$rows = $this->all(
			array(
				'where'    => array(
					'user_id'    => $user_id,
					'edition_id' => $edition_id,
				),
				'order_by' => 'id',
				'order'    => 'DESC',
				'limit'    => 1,
			)
		);

		return $rows[0] ?? null;
	}

	/**
	 * Finds the issued (valid) certificate of a student in an edition.
	 *
	 * @param int $user_id    Student id.
	 * @param int $edition_id Edition id.
	 * @return array<string, mixed>|null
	 */
	public function find_issued_for( int $user_id, int $edition_id ): ?array {
		return $this->first(
			array(
				'user_id'    => $user_id,
				'edition_id' => $edition_id,
				'status'     => self::STATUS_ISSUED,
			)
		);
	}
}
