<?php
/**
 * Administrative operations on a student and their enrollments.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Students;

use SIQA\AulaVirtual\Core\AuditLog;
use SIQA\AulaVirtual\Core\Events\EventBus;
use SIQA\AulaVirtual\Core\Events\Events;
use SIQA\AulaVirtual\Editions\EditionRepository;
use SIQA\AulaVirtual\Enrollments\EnrollmentRepository;
use SIQA\AulaVirtual\Enrollments\EnrollmentService;
use SIQA\AulaVirtual\Enrollments\EnrollmentStatus;
use SIQA\AulaVirtual\Progress\ProgressRepository;
use SIQA\AulaVirtual\Security\Sanitizer;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Business rules only: permission checks (capability and course ownership)
 * belong to the screen that calls these methods.
 */
final class StudentService {

	public const MIN_PASSWORD = 8;

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
	 * Edition persistence.
	 *
	 * @var EditionRepository
	 */
	private EditionRepository $editions;

	/**
	 * Progress persistence.
	 *
	 * @var ProgressRepository
	 */
	private ProgressRepository $progress;

	/**
	 * Event bus.
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
	 * @param EnrollmentRepository $enrollments        Enrollment persistence.
	 * @param EnrollmentService    $enrollment_service Enrollment rules.
	 * @param EditionRepository    $editions           Edition persistence.
	 * @param ProgressRepository   $progress           Progress persistence.
	 * @param EventBus             $events             Event bus.
	 * @param AuditLog             $audit              Audit trail.
	 */
	public function __construct(
		EnrollmentRepository $enrollments,
		EnrollmentService $enrollment_service,
		EditionRepository $editions,
		ProgressRepository $progress,
		EventBus $events,
		AuditLog $audit
	) {
		$this->enrollments        = $enrollments;
		$this->enrollment_service = $enrollment_service;
		$this->editions           = $editions;
		$this->progress           = $progress;
		$this->events             = $events;
		$this->audit              = $audit;
	}

	/**
	 * Emails the student a fresh link to create or change their password.
	 *
	 * @param int $user_id Student.
	 * @return true|WP_Error
	 */
	public function send_access_link( int $user_id ) {
		$user = get_userdata( $user_id );

		if ( false === $user ) {
			return new WP_Error( 'av_user_not_found', __( 'La persona no existe.', 'aula-virtual' ), array( 'status' => 404 ) );
		}

		$this->events->dispatch(
			Events::ACCESS_LINK_SENT,
			array(
				'user_id'            => $user_id,
				'with_password_link' => true,
				'force_reset_link'   => true,
			)
		);

		$this->audit->record( AuditLog::ACCESS_LINK_SENT, 'user', $user_id, array(), $user_id );

		return true;
	}

	/**
	 * Validates a password typed by an administrator.
	 *
	 * @param string $password Password.
	 * @param string $confirm  Confirmation.
	 * @return true|WP_Error
	 */
	public static function validate_password( string $password, string $confirm ) {
		if ( strlen( $password ) < self::MIN_PASSWORD ) {
			/* translators: %d: minimum length. */
			return new WP_Error( 'av_password_short', sprintf( __( 'La contraseña debe tener al menos %d caracteres.', 'aula-virtual' ), self::MIN_PASSWORD ), array( 'status' => 400 ) );
		}

		if ( $password !== $confirm ) {
			return new WP_Error( 'av_password_mismatch', __( 'Las dos contraseñas no coinciden.', 'aula-virtual' ), array( 'status' => 400 ) );
		}

		if ( trim( $password ) !== $password ) {
			return new WP_Error( 'av_password_spaces', __( 'La contraseña no puede empezar ni terminar con espacios.', 'aula-virtual' ), array( 'status' => 400 ) );
		}

		return true;
	}

	/**
	 * Sets a password chosen by an administrator. It is never stored in
	 * plain text, logged or emailed; every open session is closed.
	 *
	 * @param int    $user_id  Student.
	 * @param string $password Password.
	 * @param string $confirm  Confirmation.
	 * @return true|WP_Error
	 */
	public function set_password( int $user_id, string $password, string $confirm ) {
		if ( false === get_userdata( $user_id ) ) {
			return new WP_Error( 'av_user_not_found', __( 'La persona no existe.', 'aula-virtual' ), array( 'status' => 404 ) );
		}

		$valid = self::validate_password( $password, $confirm );

		if ( $valid instanceof WP_Error ) {
			return $valid;
		}

		wp_set_password( $password, $user_id );

		if ( class_exists( '\WP_Session_Tokens' ) ) {
			\WP_Session_Tokens::get_instance( $user_id )->destroy_all();
		}

		AccountHelper::on_password_set( $user_id );

		$this->audit->record( AuditLog::PASSWORD_SET, 'user', $user_id, array(), $user_id );

		return true;
	}

	/**
	 * Updates the personal data of a student.
	 *
	 * @param int                  $user_id Student.
	 * @param array<string, mixed> $input   first_name, last_name, email, phone, document.
	 * @return true|WP_Error
	 */
	public function update_profile( int $user_id, array $input ) {
		$user = get_userdata( $user_id );

		if ( false === $user ) {
			return new WP_Error( 'av_user_not_found', __( 'La persona no existe.', 'aula-virtual' ), array( 'status' => 404 ) );
		}

		$first = Sanitizer::text( $input['first_name'] ?? $user->first_name );
		$last  = Sanitizer::text( $input['last_name'] ?? $user->last_name );
		$email = Sanitizer::email( $input['email'] ?? $user->user_email );

		if ( '' === $email ) {
			return new WP_Error( 'av_invalid_email', __( 'Indica un correo electrónico válido.', 'aula-virtual' ), array( 'status' => 400 ) );
		}

		$owner = email_exists( $email );

		if ( false !== $owner && (int) $owner !== $user_id ) {
			return new WP_Error( 'av_email_taken', __( 'Ese correo ya pertenece a otra cuenta.', 'aula-virtual' ), array( 'status' => 409 ) );
		}

		$changed = array();
		$data    = array( 'ID' => $user_id );

		if ( $first !== $user->first_name ) {
			$data['first_name'] = $first;
			$changed[]          = 'first_name';
		}

		if ( $last !== $user->last_name ) {
			$data['last_name'] = $last;
			$changed[]         = 'last_name';
		}

		if ( array() !== array_intersect( array( 'first_name', 'last_name' ), $changed ) ) {
			$display              = trim( $first . ' ' . $last );
			$data['display_name'] = '' === $display ? $user->display_name : $display;
		}

		if ( strtolower( $email ) !== strtolower( $user->user_email ) ) {
			$data['user_email'] = $email;
			$changed[]          = 'email';
		}

		if ( count( $data ) > 1 ) {
			$result = wp_update_user( $data );

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		foreach ( array( 'phone' => 'av_phone', 'document' => 'av_document' ) as $field => $meta ) {
			if ( ! array_key_exists( $field, $input ) ) {
				continue;
			}

			$value = Sanitizer::text( $input[ $field ] );

			if ( $value !== (string) get_user_meta( $user_id, $meta, true ) ) {
				update_user_meta( $user_id, $meta, $value );
				$changed[] = $field;
			}
		}

		if ( array() !== $changed ) {
			// Solo los nombres de los campos: el historial no guarda datos personales.
			$this->audit->record( AuditLog::STUDENT_UPDATED, 'user', $user_id, array( 'fields' => $changed ), $user_id );
		}

		return true;
	}

	/**
	 * Sets (or removes) the personal access deadline of an enrollment.
	 *
	 * @param int    $enrollment_id Enrollment.
	 * @param string $date          Y-m-d, or empty for "same as the edition".
	 * @return true|WP_Error
	 */
	public function set_access_until( int $enrollment_id, string $date ) {
		$enrollment = $this->enrollments->find( $enrollment_id );

		if ( null === $enrollment ) {
			return new WP_Error( 'av_enrollment_not_found', __( 'La matrícula no existe.', 'aula-virtual' ), array( 'status' => 404 ) );
		}

		$until = '' === trim( $date ) ? null : Sanitizer::datetime( trim( $date ) . ' 23:59:59' );

		if ( '' !== trim( $date ) && null === $until ) {
			return new WP_Error( 'av_invalid_date', __( 'La fecha no es válida.', 'aula-virtual' ), array( 'status' => 400 ) );
		}

		$this->enrollments->update(
			$enrollment_id,
			array(
				'expires_at' => $until,
				'updated_at' => current_time( 'mysql', true ),
			)
		);

		$this->audit->record( AuditLog::ACCESS_EXTENDED, 'enrollment', $enrollment_id, array( 'until' => $until ), (int) $enrollment['user_id'] );

		return true;
	}

	/**
	 * Clears the progress of an enrollment (administrative restart).
	 *
	 * @param int $enrollment_id Enrollment.
	 * @return true|WP_Error
	 */
	public function reset_progress( int $enrollment_id ) {
		$enrollment = $this->enrollments->find( $enrollment_id );

		if ( null === $enrollment ) {
			return new WP_Error( 'av_enrollment_not_found', __( 'La matrícula no existe.', 'aula-virtual' ), array( 'status' => 404 ) );
		}

		$this->progress->delete_for_edition( (int) $enrollment['user_id'], (int) $enrollment['edition_id'] );

		$changes = array(
			'progress_percentage' => 0,
			'completed_at'        => null,
			'updated_at'          => current_time( 'mysql', true ),
		);

		if ( EnrollmentStatus::COMPLETED === $enrollment['status'] ) {
			$changes['status'] = EnrollmentStatus::ACTIVE;
		}

		$this->enrollments->update( $enrollment_id, $changes );

		$this->audit->record( AuditLog::PROGRESS_RESET, 'enrollment', $enrollment_id, array(), (int) $enrollment['user_id'] );

		return true;
	}

	/**
	 * Moves an enrollment to another edition (another cohort or course).
	 * Progress does not carry over: the sessions are different.
	 *
	 * @param int $enrollment_id Enrollment.
	 * @param int $edition_id    Target edition.
	 * @return true|WP_Error
	 */
	public function move( int $enrollment_id, int $edition_id ) {
		$enrollment = $this->enrollments->find( $enrollment_id );
		$target     = $this->editions->find( $edition_id );

		if ( null === $enrollment ) {
			return new WP_Error( 'av_enrollment_not_found', __( 'La matrícula no existe.', 'aula-virtual' ), array( 'status' => 404 ) );
		}

		if ( null === $target ) {
			return new WP_Error( 'av_edition_not_found', __( 'La edición de destino no existe.', 'aula-virtual' ), array( 'status' => 404 ) );
		}

		$from    = (int) $enrollment['edition_id'];
		$user_id = (int) $enrollment['user_id'];

		if ( $from === $edition_id ) {
			return new WP_Error( 'av_same_edition', __( 'La matrícula ya está en esa edición.', 'aula-virtual' ), array( 'status' => 400 ) );
		}

		if ( null !== $this->enrollments->find_for_student( $user_id, $edition_id ) ) {
			return new WP_Error( 'av_already_enrolled', __( 'La persona ya está matriculada en la edición de destino.', 'aula-virtual' ), array( 'status' => 409 ) );
		}

		$capacity = (int) $target['capacity'];

		if ( $capacity > 0 && in_array( (string) $enrollment['status'], EnrollmentStatus::occupying_seat(), true ) && $this->enrollments->count_seats_taken( $edition_id ) >= $capacity ) {
			return new WP_Error( 'av_edition_full', __( 'La edición de destino alcanzó su cupo máximo.', 'aula-virtual' ), array( 'status' => 409 ) );
		}

		$this->progress->delete_for_edition( $user_id, $from );

		$this->enrollments->update(
			$enrollment_id,
			array(
				'edition_id'          => $edition_id,
				'course_id'           => (int) $target['course_id'],
				'status'              => EnrollmentStatus::COMPLETED === $enrollment['status'] ? EnrollmentStatus::ACTIVE : (string) $enrollment['status'],
				'progress_percentage' => 0,
				'completed_at'        => null,
				'updated_at'          => current_time( 'mysql', true ),
			)
		);

		$this->audit->record( AuditLog::ENROLLMENT_MOVED, 'enrollment', $enrollment_id, array( 'from' => $from, 'to' => $edition_id ), $user_id );

		return true;
	}

	/**
	 * Enrolls an existing person in another edition.
	 *
	 * @param int  $user_id    Person.
	 * @param int  $edition_id Edition.
	 * @param bool $notify     Send the welcome email.
	 * @return int|WP_Error Enrollment id.
	 */
	public function enroll( int $user_id, int $edition_id, bool $notify ) {
		if ( null !== $this->enrollments->find_for_student( $user_id, $edition_id ) ) {
			return new WP_Error( 'av_already_enrolled', __( 'La persona ya está matriculada en esa edición.', 'aula-virtual' ), array( 'status' => 409 ) );
		}

		return $this->enrollment_service->enroll(
			$user_id,
			$edition_id,
			EnrollmentStatus::SOURCE_ADMIN,
			array(
				'status' => EnrollmentStatus::ACTIVE,
				'silent' => ! $notify,
			)
		);
	}
}
