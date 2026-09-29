<?php
/**
 * Edition duplication.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Editions;

use SIQA\AulaVirtual\Core\AuditLog;
use SIQA\AulaVirtual\Curriculum\LessonRepository;
use SIQA\AulaVirtual\Curriculum\ModuleRepository;
use SIQA\AulaVirtual\LiveClasses\LiveClassRepository;
use SIQA\AulaVirtual\Materials\MaterialRepository;
use SIQA\AulaVirtual\Security\Sanitizer;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates a new cohort from an existing one: same curriculum, no students.
 *
 * Copies modules, lessons, materials and (optionally) live classes. When a
 * new start date is given, every live class is shifted by the same number of
 * days, so "Julio 2027" becomes "Noviembre 2027" with the calendar moved.
 * Never copies enrollments, progress, requests, links or announcements.
 */
final class EditionDuplicator {

	/**
	 * Edition persistence.
	 *
	 * @var EditionRepository
	 */
	private EditionRepository $editions;

	/**
	 * Edition rules.
	 *
	 * @var EditionService
	 */
	private EditionService $service;

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
	 * Material persistence.
	 *
	 * @var MaterialRepository
	 */
	private MaterialRepository $materials;

	/**
	 * Live class persistence.
	 *
	 * @var LiveClassRepository
	 */
	private LiveClassRepository $live_classes;

	/**
	 * Audit trail.
	 *
	 * @var AuditLog
	 */
	private AuditLog $audit;

	/**
	 * Constructor.
	 *
	 * @param EditionRepository   $editions     Edition persistence.
	 * @param EditionService      $service      Edition rules.
	 * @param ModuleRepository    $modules      Module persistence.
	 * @param LessonRepository    $lessons      Lesson persistence.
	 * @param MaterialRepository  $materials    Material persistence.
	 * @param LiveClassRepository $live_classes Live class persistence.
	 * @param AuditLog            $audit        Audit trail.
	 */
	public function __construct(
		EditionRepository $editions,
		EditionService $service,
		ModuleRepository $modules,
		LessonRepository $lessons,
		MaterialRepository $materials,
		LiveClassRepository $live_classes,
		AuditLog $audit
	) {
		$this->editions     = $editions;
		$this->service      = $service;
		$this->modules      = $modules;
		$this->lessons      = $lessons;
		$this->materials    = $materials;
		$this->live_classes = $live_classes;
		$this->audit        = $audit;
	}

	/**
	 * Duplicates an edition.
	 *
	 * @param int                  $source_id Edition to copy.
	 * @param array<string, mixed> $options   name, start_date, end_date, copy_live (bool), copy_materials (bool), course_id (target course, defaults to the same).
	 * @return array{edition_id: int, modules: int, lessons: int, materials: int, live_classes: int}|WP_Error
	 */
	public function duplicate( int $source_id, array $options = array() ) {
		$source = $this->editions->find( $source_id );

		if ( null === $source ) {
			return new WP_Error( 'av_edition_not_found', __( 'La edición no existe.', 'aula-virtual' ), array( 'status' => 404 ) );
		}

		$course_id = Sanitizer::int( $options['course_id'] ?? $source['course_id'] );
		$name      = Sanitizer::text( $options['name'] ?? '' );
		$name      = '' === $name ? sprintf( /* translators: %s: source edition name. */ __( 'Copia de %s', 'aula-virtual' ), (string) $source['name'] ) : $name;

		$new_start = Sanitizer::datetime( $options['start_date'] ?? '' );
		$new_end   = Sanitizer::datetime( $options['end_date'] ?? '' );
		$shift     = self::day_shift( $source['start_date'] ?? null, $new_start );

		if ( null !== $new_start && null === $new_end && ! empty( $source['end_date'] ) ) {
			$new_end = self::shift_date( (string) $source['end_date'], $shift );
		}

		$edition_id = $this->service->create(
			array(
				'course_id'     => $course_id,
				'name'          => $name,
				'status'        => EditionStatus::DRAFT,
				'modality'      => $source['modality'],
				'start_date'    => $new_start,
				'end_date'      => $new_end,
				'access_start'  => null === $new_start ? null : self::shift_date( (string) ( $source['access_start'] ?? '' ), $shift ),
				'access_end'    => null === $new_start ? null : self::shift_date( (string) ( $source['access_end'] ?? '' ), $shift ),
				'timezone'      => $source['timezone'],
				'schedule_days' => $source['schedule_days'] ?? '',
				'schedule_time' => $source['schedule_time'] ?? '',
				'price_display' => $source['price_display'] ?? '',
				'capacity'      => $source['capacity'],
				'product_id'    => 0,
			)
		);

		if ( $edition_id instanceof WP_Error ) {
			return $edition_id;
		}

		$report = $this->copy_curriculum(
			$source_id,
			(int) $edition_id,
			$course_id,
			! isset( $options['copy_live'] ) || Sanitizer::bool( $options['copy_live'] ),
			! isset( $options['copy_materials'] ) || Sanitizer::bool( $options['copy_materials'] ),
			$shift
		);

		$report['edition_id'] = (int) $edition_id;

		$this->audit->record( AuditLog::EDITION_UPDATED, 'edition', (int) $edition_id, array( 'action' => 'duplicate', 'from' => $source_id ) );

		return $report;
	}

	/**
	 * Copies modules, lessons, materials and live classes into another edition.
	 *
	 * @param int  $source_id      Source edition.
	 * @param int  $target_id      Target edition.
	 * @param int  $course_id      Target course.
	 * @param bool $copy_live      Whether to copy live classes.
	 * @param bool $copy_materials Whether to copy materials.
	 * @param int  $shift_days     Days to shift live class dates.
	 * @return array{modules: int, lessons: int, materials: int, live_classes: int}
	 */
	public function copy_curriculum( int $source_id, int $target_id, int $course_id, bool $copy_live, bool $copy_materials, int $shift_days ): array {
		$now    = current_time( 'mysql', true );
		$report = array( 'modules' => 0, 'lessons' => 0, 'materials' => 0, 'live_classes' => 0 );
		$module_map = array();

		foreach ( $this->modules->for_edition( $source_id ) as $module ) {
			$new_id = $this->modules->insert(
				array(
					'course_id'   => $course_id,
					'edition_id'  => $target_id,
					'title'       => $module['title'],
					'description' => $module['description'],
					'position'    => (int) $module['position'],
					'status'      => $module['status'],
					'created_at'  => $now,
					'updated_at'  => $now,
				)
			);

			if ( $new_id > 0 ) {
				$module_map[ (int) $module['id'] ] = $new_id;
				++$report['modules'];
			}
		}

		foreach ( $this->lessons->for_edition( $source_id, false ) as $lesson ) {
			$row = $lesson;
			unset( $row['id'] );

			$row['course_id']  = $course_id;
			$row['edition_id'] = $target_id;
			$row['module_id']  = $module_map[ (int) $lesson['module_id'] ] ?? 0;
			$row['created_at'] = $now;
			$row['updated_at'] = $now;

			if ( 'date' === $row['release_type'] && ! empty( $row['release_date'] ) ) {
				$row['release_date'] = self::shift_date( (string) $row['release_date'], $shift_days );
			}

			$new_lesson_id = $this->lessons->insert( $row );

			if ( $new_lesson_id <= 0 ) {
				continue;
			}

			++$report['lessons'];

			if ( $copy_materials ) {
				foreach ( $this->materials->for_lesson( (int) $lesson['id'] ) as $material ) {
					$m = $material;
					unset( $m['id'] );
					$m['course_id']  = $course_id;
					$m['edition_id'] = $target_id;
					$m['lesson_id']  = $new_lesson_id;
					$m['created_at'] = $now;

					if ( $this->materials->insert( $m ) > 0 ) {
						++$report['materials'];
					}
				}
			}

			if ( $copy_live ) {
				$live = $this->live_classes->for_lesson( (int) $lesson['id'] );

				if ( null !== $live ) {
					$l = $live;
					unset( $l['id'] );
					$l['lesson_id']      = $new_lesson_id;
					$l['edition_id']     = $target_id;
					$l['start_datetime'] = self::shift_date( (string) $live['start_datetime'], $shift_days );
					$l['end_datetime']   = self::shift_date( (string) $live['end_datetime'], $shift_days );
					$l['recording_url']  = '';
					$l['status']         = LiveClassRepository::STATUS_SCHEDULED;
					$l['created_at']     = $now;
					$l['updated_at']     = $now;

					if ( $this->live_classes->insert( $l ) > 0 ) {
						++$report['live_classes'];
					}
				}
			}
		}

		if ( $copy_materials ) {
			foreach ( $this->materials->for_edition( $source_id ) as $material ) {
				$m = $material;
				unset( $m['id'] );
				$m['course_id']  = $course_id;
				$m['edition_id'] = $target_id;
				$m['created_at'] = $now;

				if ( $this->materials->insert( $m ) > 0 ) {
					++$report['materials'];
				}
			}
		}

		return $report;
	}

	/**
	 * Whole days between two dates, 0 when either is missing.
	 *
	 * @param string|null $from Source date.
	 * @param string|null $to   Target date.
	 * @return int
	 */
	public static function day_shift( ?string $from, ?string $to ): int {
		if ( null === $from || '' === $from || null === $to || '' === $to ) {
			return 0;
		}

		$a = strtotime( substr( $from, 0, 10 ) . ' UTC' );
		$b = strtotime( substr( $to, 0, 10 ) . ' UTC' );

		if ( false === $a || false === $b ) {
			return 0;
		}

		return (int) round( ( $b - $a ) / DAY_IN_SECONDS );
	}

	/**
	 * Shifts a MySQL datetime by whole days, keeping the time of day.
	 *
	 * @param string $date  MySQL datetime.
	 * @param int    $days  Days to add (negative to subtract).
	 * @return string|null Null when the input is empty or invalid.
	 */
	public static function shift_date( string $date, int $days ): ?string {
		if ( '' === $date ) {
			return null;
		}

		$ts = strtotime( $date . ' UTC' );

		if ( false === $ts ) {
			return null;
		}

		return gmdate( 'Y-m-d H:i:s', $ts + $days * DAY_IN_SECONDS );
	}
}
