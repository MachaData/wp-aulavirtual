<?php
/**
 * Tutor LMS to Aula Virtual migration.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Migration;

use SIQA\AulaVirtual\Core\AuditLog;
use SIQA\AulaVirtual\Core\Logger;
use SIQA\AulaVirtual\Courses\CourseMeta;
use SIQA\AulaVirtual\Courses\CoursePostType;
use SIQA\AulaVirtual\Curriculum\LessonRepository;
use SIQA\AulaVirtual\Curriculum\LessonType;
use SIQA\AulaVirtual\Curriculum\ModuleRepository;
use SIQA\AulaVirtual\Editions\EditionService;
use SIQA\AulaVirtual\Editions\EditionStatus;
use SIQA\AulaVirtual\Enrollments\EnrollmentRepository;
use SIQA\AulaVirtual\Enrollments\EnrollmentService;
use SIQA\AulaVirtual\Enrollments\EnrollmentStatus;
use SIQA\AulaVirtual\Progress\ProgressRepository;
use SIQA\AulaVirtual\Progress\ProgressService;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Copies a Tutor course, its curriculum, its students and their progress
 * into the plugin, one course per run.
 *
 * Rules that protect the students' data:
 * - Tutor is never modified or deleted. The migration is additive.
 * - Every run is idempotent: the mapping option remembers what was created,
 *   and enrollment/progress unique indexes stop duplicates.
 * - No email leaves during a migration. Enrollments and completions are
 *   dispatched as silent events.
 * - Enrollment dates are preserved from Tutor.
 */
final class TutorMigrator {

	public const MAP_OPTION       = 'av_tutor_migration_map';
	public const META_SOURCE      = '_av_migrated_from_tutor';
	public const BATCH            = 300;

	/**
	 * Tutor reader.
	 *
	 * @var TutorReader
	 */
	private TutorReader $tutor;

	/**
	 * Edition rules.
	 *
	 * @var EditionService
	 */
	private EditionService $editions;

	/**
	 * Module persistence.
	 *
	 * @var ModuleRepository
	 */
	private ModuleRepository $modules;

	/**
	 * Lesson persistence.
	 *
	 * @var LessonRepository
	 */
	private LessonRepository $lessons;

	/**
	 * Enrollment rules.
	 *
	 * @var EnrollmentService
	 */
	private EnrollmentService $enrollments;

	/**
	 * Enrollment persistence.
	 *
	 * @var EnrollmentRepository
	 */
	private EnrollmentRepository $enrollment_rows;

	/**
	 * Progress persistence.
	 *
	 * @var ProgressRepository
	 */
	private ProgressRepository $progress;

	/**
	 * Progress rules.
	 *
	 * @var ProgressService
	 */
	private ProgressService $progress_service;

	/**
	 * Audit trail.
	 *
	 * @var AuditLog
	 */
	private AuditLog $audit;

	/**
	 * Logger.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Constructor.
	 *
	 * @param TutorReader          $tutor            Tutor reader.
	 * @param EditionService       $editions         Edition rules.
	 * @param ModuleRepository     $modules          Module persistence.
	 * @param LessonRepository     $lessons          Lesson persistence.
	 * @param EnrollmentService    $enrollments      Enrollment rules.
	 * @param EnrollmentRepository $enrollment_rows  Enrollment persistence.
	 * @param ProgressRepository   $progress         Progress persistence.
	 * @param ProgressService      $progress_service Progress rules.
	 * @param AuditLog             $audit            Audit trail.
	 * @param Logger               $logger           Logger.
	 */
	public function __construct(
		TutorReader $tutor,
		EditionService $editions,
		ModuleRepository $modules,
		LessonRepository $lessons,
		EnrollmentService $enrollments,
		EnrollmentRepository $enrollment_rows,
		ProgressRepository $progress,
		ProgressService $progress_service,
		AuditLog $audit,
		Logger $logger
	) {
		$this->tutor            = $tutor;
		$this->editions         = $editions;
		$this->modules          = $modules;
		$this->lessons          = $lessons;
		$this->enrollments      = $enrollments;
		$this->enrollment_rows  = $enrollment_rows;
		$this->progress         = $progress;
		$this->progress_service = $progress_service;
		$this->audit            = $audit;
		$this->logger           = $logger;
	}

	/**
	 * Mapping of Tutor course ids to created ids.
	 *
	 * @return array<int, array{course_id: int, edition_id: int, modules: array<int, int>, lessons: array<int, int>}>
	 */
	public function map(): array {
		$map = get_option( self::MAP_OPTION, array() );

		return is_array( $map ) ? $map : array();
	}

	/**
	 * Migrates one Tutor course: content first, then a batch of students.
	 *
	 * @param int $tutor_course_id Tutor course id.
	 * @return array<string, int|string>|WP_Error Report of what happened.
	 */
	public function migrate_course( int $tutor_course_id ) {
		$tutor_course = $this->tutor->course( $tutor_course_id );

		if ( null === $tutor_course ) {
			return new WP_Error( 'av_tutor_course_not_found', __( 'El curso de Tutor LMS no existe.', 'aula-virtual' ) );
		}

		$map   = $this->map();
		$entry = $map[ $tutor_course_id ] ?? null;

		$report = array(
			'course_created'  => 0,
			'edition_created' => 0,
			'modules'         => 0,
			'lessons'         => 0,
			'enrolled'        => 0,
			'skipped'         => 0,
			'completions'     => 0,
			'progress_rows'   => 0,
			'remaining'       => 0,
		);

		if ( null === $entry || null === get_post( (int) $entry['course_id'] ) ) {
			$entry = $this->create_course_and_edition( $tutor_course, $report );

			if ( $entry instanceof WP_Error ) {
				return $entry;
			}

			$map[ $tutor_course_id ] = $entry;
			update_option( self::MAP_OPTION, $map, false );
		}

		$entry = $this->migrate_curriculum( $tutor_course_id, $entry, $report );

		$map[ $tutor_course_id ] = $entry;
		update_option( self::MAP_OPTION, $map, false );

		$this->migrate_students( $tutor_course_id, $entry, $report );

		$report['course_id']  = (int) $entry['course_id'];
		$report['edition_id'] = (int) $entry['edition_id'];

		$this->audit->record(
			AuditLog::IMPORT_EXECUTED,
			'tutor_course',
			$tutor_course_id,
			array(
				'course_id'  => (int) $entry['course_id'],
				'edition_id' => (int) $entry['edition_id'],
				'enrolled'   => $report['enrolled'],
			)
		);

		$this->logger->info( 'Migracion desde Tutor LMS ejecutada.', array_merge( array( 'object_type' => 'tutor_course', 'object_id' => $tutor_course_id ), $report ), 'migration' );

		return $report;
	}

	/**
	 * Creates the course post and its edition.
	 *
	 * @param \WP_Post              $tutor_course Tutor course.
	 * @param array<string, int|string> $report   Report, by reference.
	 * @return array{course_id: int, edition_id: int, modules: array<int, int>, lessons: array<int, int>}|WP_Error
	 */
	private function create_course_and_edition( \WP_Post $tutor_course, array &$report ) {
		$course_id = wp_insert_post(
			array(
				'post_type'    => CoursePostType::POST_TYPE,
				'post_title'   => $tutor_course->post_title,
				'post_name'    => $tutor_course->post_name,
				'post_content' => $tutor_course->post_content,
				'post_excerpt' => $tutor_course->post_excerpt,
				'post_status'  => 'publish' === $tutor_course->post_status ? 'publish' : 'draft',
				'post_author'  => (int) $tutor_course->post_author,
			),
			true
		);

		if ( is_wp_error( $course_id ) ) {
			return $course_id;
		}

		$course_id = (int) $course_id;
		$tutor_id  = (int) $tutor_course->ID;

		update_post_meta( $course_id, self::META_SOURCE, $tutor_id );

		$thumbnail = get_post_thumbnail_id( $tutor_id );

		if ( $thumbnail > 0 ) {
			set_post_thumbnail( $course_id, $thumbnail );
		}

		$intro = TutorMapping::video( get_post_meta( $tutor_id, '_video', true ) );

		update_post_meta( $course_id, CourseMeta::LEVEL, TutorMapping::level( (string) get_post_meta( $tutor_id, '_tutor_course_level', true ) ) );
		update_post_meta( $course_id, CourseMeta::DURATION, TutorMapping::duration( get_post_meta( $tutor_id, '_course_duration', true ) ) );
		update_post_meta( $course_id, CourseMeta::INSTRUCTOR_ID, (int) $tutor_course->post_author );
		update_post_meta( $course_id, CourseMeta::INTRO_PROVIDER, $intro['provider'] );
		update_post_meta( $course_id, CourseMeta::INTRO_URL, $intro['url'] );
		update_post_meta( $course_id, CourseMeta::BENEFITS, TutorMapping::lines( get_post_meta( $tutor_id, '_tutor_course_benefits', true ) ) );
		update_post_meta( $course_id, CourseMeta::REQUIREMENTS, TutorMapping::lines( get_post_meta( $tutor_id, '_tutor_course_requirements', true ) ) );
		update_post_meta( $course_id, CourseMeta::TARGET_AUDIENCE, TutorMapping::lines( get_post_meta( $tutor_id, '_tutor_course_target_audience', true ) ) );
		update_post_meta( $course_id, CourseMeta::INCLUDED_MATERIAL, TutorMapping::lines( get_post_meta( $tutor_id, '_tutor_course_material_includes', true ) ) );

		$product_id = (int) get_post_meta( $tutor_id, '_tutor_course_product_id', true );

		if ( $product_id > 0 ) {
			update_post_meta( $course_id, CourseMeta::PRODUCT_ID, $product_id );
		}

		$this->copy_categories( $tutor_id, $course_id );

		++$report['course_created'];

		$edition_id = $this->editions->create(
			array(
				'course_id'  => $course_id,
				'name'       => __( 'Alumnos actuales', 'aula-virtual' ),
				'status'     => EditionStatus::RUNNING,
				'modality'   => EditionService::MODALITY_RECORDED,
				'product_id' => $product_id,
			)
		);

		if ( $edition_id instanceof WP_Error ) {
			return $edition_id;
		}

		++$report['edition_created'];

		return array(
			'course_id'  => $course_id,
			'edition_id' => (int) $edition_id,
			'modules'    => array(),
			'lessons'    => array(),
		);
	}

	/**
	 * Copies topics as modules and lessons as lessons, skipping the ones
	 * already mapped.
	 *
	 * @param int                                                                                    $tutor_course_id Tutor course id.
	 * @param array{course_id: int, edition_id: int, modules: array<int, int>, lessons: array<int, int>} $entry        Mapping entry.
	 * @param array<string, int|string>                                                                  $report       Report, by reference.
	 * @return array{course_id: int, edition_id: int, modules: array<int, int>, lessons: array<int, int>}
	 */
	private function migrate_curriculum( int $tutor_course_id, array $entry, array &$report ): array {
		$now      = current_time( 'mysql', true );
		$position = 0;

		foreach ( $this->tutor->topics( $tutor_course_id ) as $index => $topic ) {
			$topic_id = (int) $topic->ID;

			if ( ! isset( $entry['modules'][ $topic_id ] ) ) {
				$module_id = $this->modules->insert(
					array(
						'course_id'   => (int) $entry['course_id'],
						'edition_id'  => (int) $entry['edition_id'],
						'title'       => $topic->post_title,
						'description' => wp_kses_post( $topic->post_content ),
						'position'    => $index + 1,
						'status'      => 'publish',
						'created_at'  => $now,
						'updated_at'  => $now,
					)
				);

				if ( $module_id > 0 ) {
					$entry['modules'][ $topic_id ] = $module_id;
					++$report['modules'];
				}
			}

			$module_id = (int) ( $entry['modules'][ $topic_id ] ?? 0 );

			foreach ( $this->tutor->topic_lessons( $topic_id ) as $lesson ) {
				++$position;
				$lesson_tutor_id = (int) $lesson->ID;

				if ( isset( $entry['lessons'][ $lesson_tutor_id ] ) ) {
					continue;
				}

				$video = TutorMapping::video( get_post_meta( $lesson_tutor_id, '_video', true ) );

				$lesson_id = $this->lessons->insert(
					array(
						'course_id'      => (int) $entry['course_id'],
						'edition_id'     => (int) $entry['edition_id'],
						'module_id'      => $module_id,
						'title'          => $lesson->post_title,
						'description'    => sanitize_textarea_field( $lesson->post_excerpt ),
						'content'        => wp_kses_post( $lesson->post_content ),
						'lesson_type'    => '' === $video['url'] ? LessonType::TEXT : LessonType::VIDEO,
						'video_provider' => $video['provider'],
						'video_url'      => $video['url'],
						'duration'       => $video['minutes'],
						'featured_image' => (int) get_post_thumbnail_id( $lesson_tutor_id ),
						'is_preview'     => (int) get_post_meta( $lesson_tutor_id, '_is_preview', true ) > 0 ? 1 : 0,
						'position'       => $position,
						'status'         => 'publish' === $lesson->post_status ? LessonType::STATUS_PUBLISH : LessonType::STATUS_DRAFT,
						'created_at'     => $now,
						'updated_at'     => $now,
					)
				);

				if ( $lesson_id > 0 ) {
					$entry['lessons'][ $lesson_tutor_id ] = $lesson_id;
					++$report['lessons'];
				}
			}
		}

		return $entry;
	}

	/**
	 * Enrolls a batch of Tutor students and copies their progress.
	 *
	 * @param int                                                                                    $tutor_course_id Tutor course id.
	 * @param array{course_id: int, edition_id: int, modules: array<int, int>, lessons: array<int, int>} $entry        Mapping entry.
	 * @param array<string, int|string>                                                                  $report       Report, by reference.
	 * @return void
	 */
	private function migrate_students( int $tutor_course_id, array $entry, array &$report ): void {
		$edition_id = (int) $entry['edition_id'];
		$total      = $this->tutor->count_enrollments( $tutor_course_id );
		$offset     = 0;
		$processed  = 0;

		while ( $offset < $total && $processed < self::BATCH ) {
			$batch = $this->tutor->enrollments( $tutor_course_id, 100, $offset );

			if ( array() === $batch ) {
				break;
			}

			foreach ( $batch as $tutor_enrollment ) {
				$user_id = $tutor_enrollment['user_id'];

				if ( $user_id <= 0 || false === get_userdata( $user_id ) ) {
					++$report['skipped'];
					continue;
				}

				if ( null !== $this->enrollment_rows->find_for_student( $user_id, $edition_id ) ) {
					++$report['skipped'];
					continue;
				}

				$status = TutorMapping::enrollment_status( $tutor_enrollment['status'] );

				$result = $this->enrollments->enroll(
					$user_id,
					$edition_id,
					EnrollmentStatus::SOURCE_MIGRATION,
					array(
						'status'      => $status,
						'order_id'    => $tutor_enrollment['order_id'],
						'enrolled_at' => $tutor_enrollment['date'],
						'notes'       => 'Tutor LMS #' . $tutor_enrollment['id'],
						'silent'      => true,
					)
				);

				++$processed;

				if ( $result instanceof WP_Error ) {
					++$report['skipped'];
					$this->logger->warning( 'Matricula de Tutor no migrada.', array( 'user_id' => $user_id, 'code' => $result->get_error_code() ), 'migration' );
					continue;
				}

				++$report['enrolled'];

				if ( EnrollmentStatus::ACTIVE === $status ) {
					$this->migrate_progress( $user_id, $tutor_course_id, $entry, $report );
				}

				if ( $processed >= self::BATCH ) {
					break;
				}
			}

			$offset += 100;
		}

		$report['remaining'] = max( 0, $total - $offset );

		if ( $processed >= self::BATCH ) {
			$report['remaining'] = max( $report['remaining'], 1 );
		}
	}

	/**
	 * Copies completed lessons and course completion for one student.
	 *
	 * @param int                                                                                    $user_id         Student id.
	 * @param int                                                                                    $tutor_course_id Tutor course id.
	 * @param array{course_id: int, edition_id: int, modules: array<int, int>, lessons: array<int, int>} $entry        Mapping entry.
	 * @param array<string, int|string>                                                                  $report       Report, by reference.
	 * @return void
	 */
	private function migrate_progress( int $user_id, int $tutor_course_id, array $entry, array &$report ): void {
		$edition_id = (int) $entry['edition_id'];
		$touched    = false;

		foreach ( $entry['lessons'] as $tutor_lesson_id => $lesson_id ) {
			$completed_at = $this->tutor->completed_lesson_at( $user_id, (int) $tutor_lesson_id );

			if ( '' === $completed_at || null !== $this->progress->find_for_lesson( $user_id, (int) $lesson_id ) ) {
				continue;
			}

			$this->progress->insert(
				array(
					'user_id'       => $user_id,
					'course_id'     => (int) $entry['course_id'],
					'edition_id'    => $edition_id,
					'lesson_id'     => (int) $lesson_id,
					'status'        => ProgressRepository::STATUS_COMPLETED,
					'percentage'    => 100.0,
					'started_at'    => $completed_at,
					'completed_at'  => $completed_at,
					'last_activity' => $completed_at,
				)
			);

			++$report['progress_rows'];
			$touched = true;
		}

		if ( $touched ) {
			$this->progress_service->recalculate( $user_id, $edition_id, true );
		}

		$course_completed_at = $this->tutor->completed_course_at( $user_id, $tutor_course_id );

		if ( '' === $course_completed_at ) {
			return;
		}

		$enrollment = $this->enrollment_rows->find_for_student( $user_id, $edition_id );

		if ( null === $enrollment || EnrollmentStatus::COMPLETED === $enrollment['status'] ) {
			return;
		}

		$this->enrollment_rows->update(
			(int) $enrollment['id'],
			array(
				'status'       => EnrollmentStatus::COMPLETED,
				'completed_at' => $course_completed_at,
				'updated_at'   => current_time( 'mysql', true ),
			)
		);

		++$report['completions'];
	}

	/**
	 * Copies Tutor categories to the plugin taxonomy, creating missing terms.
	 *
	 * @param int $tutor_id  Tutor course id.
	 * @param int $course_id New course id.
	 * @return void
	 */
	private function copy_categories( int $tutor_id, int $course_id ): void {
		$names = $this->tutor->category_names( $tutor_id );

		if ( array() === $names ) {
			return;
		}

		$term_ids = array();

		foreach ( $names as $name ) {
			$term = term_exists( $name, CoursePostType::TAX_CATEGORY );

			if ( ! is_array( $term ) ) {
				$term = wp_insert_term( $name, CoursePostType::TAX_CATEGORY );
			}

			if ( is_array( $term ) && isset( $term['term_id'] ) ) {
				$term_ids[] = (int) $term['term_id'];
			}
		}

		if ( array() !== $term_ids ) {
			wp_set_object_terms( $course_id, $term_ids, CoursePostType::TAX_CATEGORY );
		}
	}
}
