<?php
/**
 * Progress business rules.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Progress;

use SIQA\AulaVirtual\Core\Events\EventBus;
use SIQA\AulaVirtual\Core\Events\Events;
use SIQA\AulaVirtual\Curriculum\LessonRepository;
use SIQA\AulaVirtual\Curriculum\LessonType;
use SIQA\AulaVirtual\Curriculum\ReleaseSchedule;
use SIQA\AulaVirtual\Enrollments\EnrollmentRepository;
use SIQA\AulaVirtual\Enrollments\EnrollmentService;
use SIQA\AulaVirtual\Enrollments\EnrollmentStatus;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Records what a student has seen and keeps the cohort percentage in sync.
 *
 * The percentage cached on the enrollment is what the admin listings and the
 * campus cards read, so it is recalculated on every change instead of being
 * derived at display time for each student.
 */
final class ProgressService {

	/**
	 * Progress persistence.
	 *
	 * @var ProgressRepository
	 */
	private ProgressRepository $progress;

	/**
	 * Lesson persistence.
	 *
	 * @var LessonRepository
	 */
	private LessonRepository $lessons;

	/**
	 * Enrollment persistence.
	 *
	 * @var EnrollmentRepository
	 */
	private EnrollmentRepository $enrollments;

	/**
	 * Access rules.
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
	 * Constructor.
	 *
	 * @param ProgressRepository   $progress           Progress persistence.
	 * @param LessonRepository     $lessons            Lesson persistence.
	 * @param EnrollmentRepository $enrollments        Enrollment persistence.
	 * @param EnrollmentService    $enrollment_service Access rules.
	 * @param EventBus             $events             Domain events.
	 */
	public function __construct(
		ProgressRepository $progress,
		LessonRepository $lessons,
		EnrollmentRepository $enrollments,
		EnrollmentService $enrollment_service,
		EventBus $events
	) {
		$this->progress           = $progress;
		$this->lessons            = $lessons;
		$this->enrollments        = $enrollments;
		$this->enrollment_service = $enrollment_service;
		$this->events             = $events;
	}

	/**
	 * Marks a lesson as completed for a student.
	 *
	 * @param int $user_id   Student id.
	 * @param int $lesson_id Lesson id.
	 * @return float|WP_Error New percentage of the edition, or the reason it was rejected.
	 */
	public function complete( int $user_id, int $lesson_id ) {
		$lesson = $this->lessons->find( $lesson_id );

		if ( null === $lesson || LessonType::STATUS_PUBLISH !== $lesson['status'] ) {
			return new WP_Error(
				'av_lesson_not_found',
				__( 'La sesion no existe o no esta publicada.', 'aula-virtual' ),
				array( 'status' => 404 )
			);
		}

		$edition_id = (int) $lesson['edition_id'];

		if ( ! $this->enrollment_service->has_access( $user_id, $edition_id ) ) {
			return new WP_Error(
				'av_no_access',
				__( 'No tienes acceso a esta edicion.', 'aula-virtual' ),
				array( 'status' => 403 )
			);
		}

		$now = current_time( 'mysql', true );

		if ( ! ReleaseSchedule::is_available( $lesson, $this->enrollments->find_for_student( $user_id, $edition_id ), $now ) ) {
			return new WP_Error(
				'av_lesson_locked',
				__( 'Esta sesion todavia no esta disponible.', 'aula-virtual' ),
				array( 'status' => 403 )
			);
		}

		$existing = $this->progress->find_for_lesson( $user_id, $lesson_id );

		$row = array(
			'user_id'       => $user_id,
			'course_id'     => (int) $lesson['course_id'],
			'edition_id'    => $edition_id,
			'lesson_id'     => $lesson_id,
			'status'        => ProgressRepository::STATUS_COMPLETED,
			'percentage'    => 100.0,
			'completed_at'  => $now,
			'last_activity' => $now,
		);

		if ( null === $existing ) {
			$row['started_at'] = $now;
			$this->progress->insert( $row );
		} else {
			$this->progress->update( (int) $existing['id'], $row );
		}

		$percentage = $this->recalculate( $user_id, $edition_id );

		$this->events->dispatch(
			Events::LESSON_COMPLETED,
			array(
				'user_id'    => $user_id,
				'lesson_id'  => $lesson_id,
				'edition_id' => $edition_id,
				'percentage' => $percentage,
			)
		);

		return $percentage;
	}

	/**
	 * Records that a student opened a lesson.
	 *
	 * @param int $user_id   Student id.
	 * @param int $lesson_id Lesson id.
	 * @return void
	 */
	public function start( int $user_id, int $lesson_id ): void {
		$lesson = $this->lessons->find( $lesson_id );

		if ( null === $lesson || LessonType::STATUS_PUBLISH !== $lesson['status'] || ! $this->enrollment_service->has_access( $user_id, (int) $lesson['edition_id'] ) ) {
			return;
		}

		$now      = current_time( 'mysql', true );
		$existing = $this->progress->find_for_lesson( $user_id, $lesson_id );

		if ( null !== $existing ) {
			$this->progress->update( (int) $existing['id'], array( 'last_activity' => $now ) );

			return;
		}

		$this->progress->insert(
			array(
				'user_id'       => $user_id,
				'course_id'     => (int) $lesson['course_id'],
				'edition_id'    => (int) $lesson['edition_id'],
				'lesson_id'     => $lesson_id,
				'status'        => ProgressRepository::STATUS_STARTED,
				'percentage'    => 0.0,
				'started_at'    => $now,
				'last_activity' => $now,
			)
		);

		$this->events->dispatch(
			Events::LESSON_STARTED,
			array(
				'user_id'    => $user_id,
				'lesson_id'  => $lesson_id,
				'edition_id' => (int) $lesson['edition_id'],
			)
		);
	}

	/**
	 * Restarts an edition for a student: deletes the progress rows and puts
	 * the enrollment back to active with 0 %. The certificate, if any, is
	 * kept. No event is dispatched.
	 *
	 * @param int $user_id    Student id.
	 * @param int $edition_id Edition id.
	 * @return int|WP_Error Progress rows deleted.
	 */
	public function reset( int $user_id, int $edition_id ) {
		if ( ! get_option( 'av_allow_retake', true ) ) {
			return new WP_Error( 'av_retake_disabled', __( 'Repetir el curso no esta habilitado.', 'aula-virtual' ), array( 'status' => 403 ) );
		}

		$enrollment = $this->enrollments->find_for_student( $user_id, $edition_id );

		if ( null === $enrollment || ! in_array( $enrollment['status'], array( EnrollmentStatus::ACTIVE, EnrollmentStatus::COMPLETED ), true ) ) {
			return new WP_Error( 'av_retake_forbidden', __( 'No tienes una matricula activa en esta edicion.', 'aula-virtual' ), array( 'status' => 403 ) );
		}

		$deleted = $this->progress->delete_for_edition( $user_id, $edition_id );
		$now     = current_time( 'mysql', true );

		$this->enrollments->update(
			(int) $enrollment['id'],
			array(
				'status'              => EnrollmentStatus::ACTIVE,
				'progress_percentage' => 0,
				'completed_at'        => null,
				'last_activity'       => $now,
				'updated_at'          => $now,
			)
		);

		return $deleted;
	}

	/**
	 * Recalculates the percentage of an enrollment and marks it completed
	 * when every published lesson is done.
	 *
	 * @param int  $user_id    Student id.
	 * @param int  $edition_id Edition id.
	 * @param bool $silent     Whether to mark the completion event as silent (no emails).
	 * @return float
	 */
	public function recalculate( int $user_id, int $edition_id, bool $silent = false ): float {
		$total      = $this->lessons->count_published( $edition_id );
		$completed  = $this->progress->count_completed( $user_id, $edition_id );
		$percentage = ProgressCalculator::percentage( $completed, $total );
		$enrollment = $this->enrollments->find_for_student( $user_id, $edition_id );

		if ( null === $enrollment ) {
			return $percentage;
		}

		$now     = current_time( 'mysql', true );
		$changes = array(
			'progress_percentage' => $percentage,
			'last_activity'       => $now,
			'updated_at'          => $now,
		);

		$finished = ProgressCalculator::is_complete( $completed, $total );

		if ( $finished && EnrollmentStatus::COMPLETED !== $enrollment['status'] ) {
			$changes['status']       = EnrollmentStatus::COMPLETED;
			$changes['completed_at'] = $now;
		}

		$this->enrollments->update( (int) $enrollment['id'], $changes );

		if ( $finished && EnrollmentStatus::COMPLETED !== $enrollment['status'] ) {
			$this->events->dispatch(
				Events::COURSE_COMPLETED,
				array(
					'user_id'       => $user_id,
					'edition_id'    => $edition_id,
					'course_id'     => (int) $enrollment['course_id'],
					'enrollment_id' => (int) $enrollment['id'],
					'silent'        => $silent,
				)
			);
		}

		return $percentage;
	}
}
