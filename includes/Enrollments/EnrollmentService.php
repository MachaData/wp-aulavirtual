<?php
/**
 * Enrollment business rules.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Enrollments;

use SIQA\AulaVirtual\Core\AuditLog;
use SIQA\AulaVirtual\Core\Events\EventBus;
use SIQA\AulaVirtual\Core\Events\Events;
use SIQA\AulaVirtual\Editions\EditionRepository;
use SIQA\AulaVirtual\Editions\EditionStatus;
use SIQA\AulaVirtual\Permissions\Roles;
use SIQA\AulaVirtual\Security\Sanitizer;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Puts a student into a cohort and takes them out of it.
 *
 * This service never sends email. It dispatches domain events and the
 * notifications module decides what, if anything, reaches the student.
 */
final class EnrollmentService {

	/**
	 * Enrollment persistence.
	 *
	 * @var EnrollmentRepository
	 */
	private EnrollmentRepository $enrollments;

	/**
	 * Edition persistence.
	 *
	 * @var EditionRepository
	 */
	private EditionRepository $editions;

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
	 * @param EnrollmentRepository $enrollments Enrollment persistence.
	 * @param EditionRepository    $editions    Edition persistence.
	 * @param EventBus             $events      Domain events.
	 * @param AuditLog             $audit       Audit trail.
	 */
	public function __construct(
		EnrollmentRepository $enrollments,
		EditionRepository $editions,
		EventBus $events,
		AuditLog $audit
	) {
		$this->enrollments = $enrollments;
		$this->editions    = $editions;
		$this->events      = $events;
		$this->audit       = $audit;
	}

	/**
	 * Enrolls a student in an edition.
	 *
	 * The operation is idempotent: enrolling somebody who is already in the
	 * cohort returns the existing enrollment instead of failing, which is what
	 * makes it safe to call from a payment webhook that may fire twice.
	 *
	 * @param int                  $user_id    Student id.
	 * @param int                  $edition_id Edition id.
	 * @param string               $source     Enrollment source.
	 * @param array<string, mixed> $args       Optional order id and notes.
	 * @return int|WP_Error Enrollment id, or the reason it was rejected.
	 */
	public function enroll( int $user_id, int $edition_id, string $source = EnrollmentStatus::SOURCE_MANUAL, array $args = array() ) {
		$user = get_userdata( $user_id );

		if ( false === $user ) {
			return new WP_Error(
				'av_user_not_found',
				__( 'El usuario indicado no existe.', 'aula-virtual' ),
				array( 'status' => 404 )
			);
		}

		$edition = $this->editions->find( $edition_id );

		if ( null === $edition ) {
			return new WP_Error(
				'av_edition_not_found',
				__( 'La edicion no existe.', 'aula-virtual' ),
				array( 'status' => 404 )
			);
		}

		$existing = $this->enrollments->find_for_student( $user_id, $edition_id );

		if ( null !== $existing ) {
			return (int) $existing['id'];
		}

		if ( ! EditionStatus::accepts_enrollments( (string) $edition['status'] ) ) {
			return new WP_Error(
				'av_edition_closed',
				sprintf(
					/* translators: %s: edition state label. */
					__( 'La edicion esta en estado "%s" y no admite matriculas.', 'aula-virtual' ),
					EditionStatus::label( (string) $edition['status'] )
				),
				array( 'status' => 409 )
			);
		}

		$capacity = (int) $edition['capacity'];

		if ( $capacity > 0 && $this->enrollments->count_seats_taken( $edition_id ) >= $capacity ) {
			return new WP_Error(
				'av_edition_full',
				__( 'La edicion alcanzo su cupo maximo.', 'aula-virtual' ),
				array( 'status' => 409 )
			);
		}

		$now    = current_time( 'mysql', true );
		$status = isset( $args['status'] ) && in_array( $args['status'], EnrollmentStatus::all(), true )
			? (string) $args['status']
			: $this->initial_status( $source );

		$enrollment_id = $this->enrollments->insert(
			array(
				'user_id'     => $user_id,
				'course_id'   => (int) $edition['course_id'],
				'edition_id'  => $edition_id,
				'status'      => $status,
				'source'      => Sanitizer::enum( $source, EnrollmentStatus::sources(), EnrollmentStatus::SOURCE_MANUAL ),
				'order_id'    => Sanitizer::int( $args['order_id'] ?? 0 ),
				'notes'       => Sanitizer::textarea( $args['notes'] ?? '' ),
				'enrolled_at' => Sanitizer::datetime( $args['enrolled_at'] ?? '' ) ?? $now,
				'approved_at' => EnrollmentStatus::PENDING === $status ? null : $now,
				'approved_by' => EnrollmentStatus::PENDING === $status ? 0 : get_current_user_id(),
				'expires_at'  => $edition['access_end'],
				'created_at'  => $now,
				'updated_at'  => $now,
			)
		);

		if ( 0 === $enrollment_id ) {
			// The unique index on (user_id, edition_id) is the last line of
			// defence against a duplicate created by a concurrent request.
			$duplicate = $this->enrollments->find_for_student( $user_id, $edition_id );

			if ( null !== $duplicate ) {
				return (int) $duplicate['id'];
			}

			return new WP_Error(
				'av_enrollment_not_created',
				__( 'No se pudo registrar la matricula.', 'aula-virtual' )
			);
		}

		$this->ensure_student_role( $user_id );

		$this->audit->record(
			AuditLog::ENROLLMENT_CREATED,
			'enrollment',
			$enrollment_id,
			array(
				'edition_id' => $edition_id,
				'source'     => $source,
			),
			$user_id
		);

		$payload = array(
			'enrollment_id'      => $enrollment_id,
			'user_id'            => $user_id,
			'course_id'          => (int) $edition['course_id'],
			'edition_id'         => $edition_id,
			'source'             => $source,
			'status'             => $status,
			// La bienvenida ofrece crear o restablecer la contrasena: el alumno
			// nunca recibe una contrasena por correo, solo este enlace de un uso.
			'with_password_link' => true,
			// Una migracion no debe disparar cientos de bienvenidas.
			'silent'             => ! empty( $args['silent'] ),
		);

		$this->events->dispatch( Events::ENROLLMENT_CREATED, $payload );

		if ( EnrollmentStatus::grants_access( $status ) ) {
			$this->events->dispatch( Events::ENROLLMENT_APPROVED, $payload );
		}

		return $enrollment_id;
	}

	/**
	 * Changes the state of an enrollment.
	 *
	 * @param int    $enrollment_id Enrollment id.
	 * @param string $status        New state.
	 * @return true|WP_Error
	 */
	public function change_status( int $enrollment_id, string $status ) {
		$enrollment = $this->enrollments->find( $enrollment_id );

		if ( null === $enrollment ) {
			return new WP_Error(
				'av_enrollment_not_found',
				__( 'La matricula no existe.', 'aula-virtual' ),
				array( 'status' => 404 )
			);
		}

		if ( ! in_array( $status, EnrollmentStatus::all(), true ) ) {
			return new WP_Error(
				'av_invalid_status',
				__( 'Estado de matricula no valido.', 'aula-virtual' ),
				array( 'status' => 400 )
			);
		}

		$this->enrollments->update(
			$enrollment_id,
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql', true ),
			)
		);

		$this->audit->record(
			AuditLog::ENROLLMENT_UPDATED,
			'enrollment',
			$enrollment_id,
			array(
				'from' => $enrollment['status'],
				'to'   => $status,
			),
			(int) $enrollment['user_id']
		);

		$event = match ( $status ) {
			EnrollmentStatus::SUSPENDED => Events::ENROLLMENT_SUSPENDED,
			EnrollmentStatus::CANCELLED => Events::ENROLLMENT_CANCELLED,
			EnrollmentStatus::EXPIRED   => Events::ENROLLMENT_EXPIRED,
			EnrollmentStatus::APPROVED, EnrollmentStatus::ACTIVE => Events::ENROLLMENT_APPROVED,
			default                     => '',
		};

		if ( '' !== $event ) {
			$this->events->dispatch(
				$event,
				array(
					'enrollment_id' => $enrollment_id,
					'user_id'       => (int) $enrollment['user_id'],
					'edition_id'    => (int) $enrollment['edition_id'],
					'status'        => $status,
				)
			);
		}

		return true;
	}

	/**
	 * Returns the enrollment of a student in an edition, if any.
	 *
	 * @param int $user_id    Student id.
	 * @param int $edition_id Edition id.
	 * @return array<string, mixed>|null
	 */
	public function find_enrollment( int $user_id, int $edition_id ): ?array {
		return $this->enrollments->find_for_student( $user_id, $edition_id );
	}

	/**
	 * Whether a student may open the content of an edition right now.
	 *
	 * @param int $user_id    Student id.
	 * @param int $edition_id Edition id.
	 * @return bool
	 */
	public function has_access( int $user_id, int $edition_id ): bool {
		$enrollment = $this->enrollments->find_for_student( $user_id, $edition_id );

		if ( null === $enrollment || ! EnrollmentStatus::grants_access( (string) $enrollment['status'] ) ) {
			return false;
		}

		$edition = $this->editions->find( $edition_id );

		if ( null === $edition ) {
			return false;
		}

		return EditionStatus::window_is_open(
			$edition['access_start'],
			$enrollment['expires_at'] ?? $edition['access_end'],
			current_time( 'mysql', true )
		);
	}

	/**
	 * Decides the state a new enrollment starts in.
	 *
	 * @param string $source Enrollment source.
	 * @return string
	 */
	private function initial_status( string $source ): string {
		$needs_approval = in_array( $source, array( EnrollmentStatus::SOURCE_PRIVATE_LINK, EnrollmentStatus::SOURCE_FREE ), true )
			&& ! get_option( 'av_enrollment_auto_approve', false );

		return $needs_approval ? EnrollmentStatus::PENDING : EnrollmentStatus::ACTIVE;
	}

	/**
	 * Gives the student role to a user who has no LMS role yet.
	 *
	 * Existing roles are preserved: an instructor enrolled in a colleague's
	 * course must not be demoted to student.
	 *
	 * @param int $user_id Student id.
	 * @return void
	 */
	private function ensure_student_role( int $user_id ): void {
		$user = get_userdata( $user_id );

		if ( false === $user ) {
			return;
		}

		$lms_roles = array( Roles::ADMINISTRATOR, Roles::INSTRUCTOR, Roles::STUDENT );

		if ( array() !== array_intersect( $lms_roles, (array) $user->roles ) ) {
			return;
		}

		$user->add_role( Roles::STUDENT );
	}
}
