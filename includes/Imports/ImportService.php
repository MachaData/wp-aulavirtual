<?php
/**
 * Student import rules.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Imports;

use SIQA\AulaVirtual\Core\AuditLog;
use SIQA\AulaVirtual\Core\Events\EventBus;
use SIQA\AulaVirtual\Core\Events\Events;
use SIQA\AulaVirtual\Database\Repository;
use SIQA\AulaVirtual\Editions\EditionRepository;
use SIQA\AulaVirtual\Enrollments\EnrollmentRepository;
use SIQA\AulaVirtual\Enrollments\EnrollmentService;
use SIQA\AulaVirtual\Enrollments\EnrollmentStatus;
use SIQA\AulaVirtual\Permissions\Roles;
use SIQA\AulaVirtual\Security\Sanitizer;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates the mapped rows and enrolls them in batches.
 *
 * Validation never writes anything and never sends email. The import creates
 * missing accounts without a password, enrolls each student and only then,
 * if the administrator asked for it, lets the welcome email go out.
 */
final class ImportService {

	public const BATCH = 200;

	/**
	 * Import job persistence.
	 *
	 * @var ImportJobRepository
	 */
	private ImportJobRepository $jobs;

	/**
	 * Edition persistence.
	 *
	 * @var EditionRepository
	 */
	private EditionRepository $editions;

	/**
	 * Enrollment persistence.
	 *
	 * @var EnrollmentRepository
	 */
	private EnrollmentRepository $enrollments;

	/**
	 * Enrollment rules.
	 *
	 * @var EnrollmentService
	 */
	private EnrollmentService $enrollment_service;

	/**
	 * Domain events.
	 *
	 * @var EventBus
	 */
	private EventBus $events;

	/**
	 * Audit trail.
	 *
	 * @var AuditLog
	 */
	private AuditLog $audit;

	/**
	 * Constructor.
	 *
	 * @param ImportJobRepository  $jobs               Import job persistence.
	 * @param EditionRepository    $editions           Edition persistence.
	 * @param EnrollmentRepository $enrollments        Enrollment persistence.
	 * @param EnrollmentService    $enrollment_service Enrollment rules.
	 * @param EventBus             $events             Domain events.
	 * @param AuditLog             $audit              Audit trail.
	 */
	public function __construct(
		ImportJobRepository $jobs,
		EditionRepository $editions,
		EnrollmentRepository $enrollments,
		EnrollmentService $enrollment_service,
		EventBus $events,
		AuditLog $audit
	) {
		$this->jobs               = $jobs;
		$this->editions           = $editions;
		$this->enrollments        = $enrollments;
		$this->enrollment_service = $enrollment_service;
		$this->events             = $events;
		$this->audit              = $audit;
	}

	/**
	 * Applies a column mapping to raw rows.
	 *
	 * @param array<int, array<int, string>> $rows    Data rows.
	 * @param array<string, int>             $mapping Field => column index.
	 * @return array<int, array{first_name: string, last_name: string, email: string, phone: string, document: string, line: int}>
	 */
	public static function map_rows( array $rows, array $mapping ): array {
		$mapped = array();

		foreach ( $rows as $i => $row ) {
			$pick = static fn( string $field ): string => isset( $mapping[ $field ] ) ? (string) ( $row[ $mapping[ $field ] ] ?? '' ) : '';

			// Filas totalmente vacias (habituales al final de un Excel) se ignoran.
			if ( '' === trim( implode( '', array_map( 'strval', (array) $row ) ) ) ) {
				continue;
			}

			$mapped[] = array(
				'first_name' => Sanitizer::text( $pick( 'first_name' ) ),
				'last_name'  => Sanitizer::text( $pick( 'last_name' ) ),
				'email'      => strtolower( Sanitizer::email( $pick( 'email' ) ) ),
				'phone'      => Sanitizer::text( $pick( 'phone' ) ),
				'document'   => Sanitizer::text( $pick( 'document' ) ),
				'line'       => $i + 2,
			);
		}

		return $mapped;
	}

	/**
	 * Classifies mapped rows without writing anything.
	 *
	 * @param array<int, array<string, mixed>> $rows       Mapped rows.
	 * @param int                              $edition_id Target edition.
	 * @return array{valid: array<int, array<string, mixed>>, invalid: array<int, array<string, mixed>>, duplicates: array<int, array<string, mixed>>, existing_users: int, new_users: int, already_enrolled: int}
	 */
	public function validate( array $rows, int $edition_id ): array {
		$seen   = array();
		$result = array(
			'valid'            => array(),
			'invalid'          => array(),
			'duplicates'       => array(),
			'existing_users'   => 0,
			'new_users'        => 0,
			'already_enrolled' => 0,
		);

		foreach ( $rows as $row ) {
			if ( '' === $row['email'] ) {
				$row['reason']       = __( 'Correo vacío o inválido', 'aula-virtual' );
				$result['invalid'][] = $row;
				continue;
			}

			if ( isset( $seen[ $row['email'] ] ) ) {
				$row['reason']          = __( 'Correo repetido en el archivo', 'aula-virtual' );
				$result['duplicates'][] = $row;
				continue;
			}

			$seen[ $row['email'] ] = true;

			$user = get_user_by( 'email', $row['email'] );

			if ( false !== $user ) {
				++$result['existing_users'];

				if ( null !== $this->enrollments->find_for_student( (int) $user->ID, $edition_id ) ) {
					++$result['already_enrolled'];
					$row['already_enrolled'] = true;
				}
			} else {
				++$result['new_users'];
			}

			$result['valid'][] = $row;
		}

		return $result;
	}

	/**
	 * Creates the job record for a validated import.
	 *
	 * @param int                              $edition_id   Target edition.
	 * @param string                           $filename     Original file name.
	 * @param array<int, array<string, mixed>> $rows         Valid rows.
	 * @param array<string, int>               $mapping      Mapping used.
	 * @param bool                             $send_welcome Whether to email students.
	 * @return int|WP_Error Job id.
	 */
	public function create_job( int $edition_id, string $filename, array $rows, array $mapping, bool $send_welcome ) {
		$edition = $this->editions->find( $edition_id );

		if ( null === $edition ) {
			return new WP_Error( 'av_edition_not_found', __( 'La edición no existe.', 'aula-virtual' ), array( 'status' => 404 ) );
		}

		$now = current_time( 'mysql', true );

		$job_id = $this->jobs->insert(
			array(
				'course_id'      => (int) $edition['course_id'],
				'edition_id'     => $edition_id,
				'user_id'        => get_current_user_id(),
				'filename'       => sanitize_file_name( $filename ),
				'status'         => 'pending',
				'total_rows'     => count( $rows ),
				'processed_rows' => 0,
				'send_welcome'   => $send_welcome ? 1 : 0,
				'mapping'        => wp_json_encode( $mapping ),
				'errors'         => wp_json_encode( array() ),
				'created_at'     => $now,
				'updated_at'     => $now,
			)
		);

		if ( 0 === $job_id ) {
			return new WP_Error( 'av_import_job', __( 'No se pudo registrar la importación.', 'aula-virtual' ) );
		}

		set_transient( 'av_import_rows_' . $job_id, $rows, DAY_IN_SECONDS );

		return $job_id;
	}

	/**
	 * Processes the next batch of a job.
	 *
	 * @param int $job_id Job id.
	 * @return array<string, mixed>|WP_Error Job row after the batch.
	 */
	public function run_batch( int $job_id ) {
		$job = $this->jobs->find( $job_id );

		if ( null === $job ) {
			return new WP_Error( 'av_import_not_found', __( 'La importación no existe.', 'aula-virtual' ), array( 'status' => 404 ) );
		}

		if ( 'completed' === $job['status'] ) {
			return $job;
		}

		$rows = get_transient( 'av_import_rows_' . $job_id );

		if ( ! is_array( $rows ) ) {
			$this->jobs->update( $job_id, array( 'status' => 'failed', 'updated_at' => current_time( 'mysql', true ) ) );

			return new WP_Error( 'av_import_expired', __( 'Los datos de la importación caducaron. Vuelve a subir el archivo.', 'aula-virtual' ) );
		}

		$offset   = (int) $job['processed_rows'];
		$batch    = array_slice( $rows, $offset, self::BATCH );
		$errors   = json_decode( (string) $job['errors'], true );
		$errors   = is_array( $errors ) ? $errors : array();
		$counters = array(
			'created_users' => (int) $job['created_users'],
			'enrolled'      => (int) $job['enrolled'],
			'skipped'       => (int) $job['skipped'],
			'failed'        => (int) $job['failed'],
		);

		$edition_id = (int) $job['edition_id'];
		$welcome    = (int) $job['send_welcome'] > 0;

		foreach ( $batch as $row ) {
			$user_id = $this->find_or_create_user( $row );

			if ( $user_id instanceof WP_Error ) {
				++$counters['failed'];
				$errors[] = array( 'line' => $row['line'], 'email' => $row['email'], 'error' => $user_id->get_error_message() );
				continue;
			}

			if ( $user_id['created'] ) {
				++$counters['created_users'];
			}

			if ( null !== $this->enrollments->find_for_student( $user_id['id'], $edition_id ) ) {
				++$counters['skipped'];
				continue;
			}

			$result = $this->enrollment_service->enroll(
				$user_id['id'],
				$edition_id,
				EnrollmentStatus::SOURCE_EXCEL,
				array(
					'status' => EnrollmentStatus::ACTIVE,
					'notes'  => 'Importacion #' . $job_id,
					'silent' => ! $welcome,
				)
			);

			if ( $result instanceof WP_Error ) {
				++$counters['failed'];
				$errors[] = array( 'line' => $row['line'], 'email' => $row['email'], 'error' => $result->get_error_message() );
				continue;
			}

			++$counters['enrolled'];
		}

		$processed = min( count( $rows ), $offset + count( $batch ) );
		$done      = $processed >= count( $rows );

		$this->jobs->update(
			$job_id,
			array_merge(
				$counters,
				array(
					'processed_rows' => $processed,
					'status'         => $done ? 'completed' : 'running',
					'errors'         => wp_json_encode( array_slice( $errors, 0, 500 ) ),
					'updated_at'     => current_time( 'mysql', true ),
				)
			)
		);

		if ( $done ) {
			delete_transient( 'av_import_rows_' . $job_id );

			$this->audit->record( AuditLog::IMPORT_EXECUTED, 'import_job', $job_id, array_merge( $counters, array( 'edition_id' => $edition_id ) ) );

			$this->events->dispatch(
				Events::IMPORT_COMPLETED,
				array_merge( $counters, array( 'job_id' => $job_id, 'edition_id' => $edition_id, 'silent' => true ) )
			);
		}

		return $this->jobs->find( $job_id ) ?? $job;
	}

	/**
	 * Finds or creates the account for a row.
	 *
	 * @param array<string, mixed> $row Mapped row.
	 * @return array{id: int, created: bool}|WP_Error
	 */
	private function find_or_create_user( array $row ) {
		$user = get_user_by( 'email', $row['email'] );

		if ( false !== $user ) {
			return array( 'id' => (int) $user->ID, 'created' => false );
		}

		$base  = sanitize_user( strstr( $row['email'], '@', true ) ?: $row['email'], true );
		$base  = '' === $base ? 'alumno' : $base;
		$login = $base;
		$i     = 2;

		while ( username_exists( $login ) ) {
			$login = $base . $i;
			++$i;
		}

		$user_id = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_email'   => $row['email'],
				'user_pass'    => wp_generate_password( 32, true, true ),
				'first_name'   => $row['first_name'],
				'last_name'    => $row['last_name'],
				'display_name' => '' === trim( $row['first_name'] . ' ' . $row['last_name'] ) ? strstr( $row['email'], '@', true ) : trim( $row['first_name'] . ' ' . $row['last_name'] ),
				'role'         => Roles::STUDENT,
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		if ( '' !== $row['phone'] ) {
			update_user_meta( (int) $user_id, 'av_phone', $row['phone'] );
		}

		if ( '' !== $row['document'] ) {
			update_user_meta( (int) $user_id, 'av_document', $row['document'] );
		}

		return array( 'id' => (int) $user_id, 'created' => true );
	}
}
