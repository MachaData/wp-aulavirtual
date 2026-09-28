<?php
/**
 * Live class rules.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\LiveClasses;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use SIQA\AulaVirtual\Core\Events\EventBus;
use SIQA\AulaVirtual\Core\Events\Events;
use SIQA\AulaVirtual\Curriculum\LessonRepository;
use SIQA\AulaVirtual\Editions\EditionRepository;
use SIQA\AulaVirtual\Security\Sanitizer;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schedules the live session of a lesson and decides when its link is shown.
 *
 * Dates are typed by the instructor in the edition's time zone and stored in
 * UTC. The join button appears `open_before` minutes before the start and
 * disappears `close_after` minutes after the end; outside that window the
 * meeting link is never printed.
 */
final class LiveClassService {

	public const WINDOW_BEFORE = 'before';
	public const WINDOW_OPEN   = 'open';
	public const WINDOW_AFTER  = 'after';

	/**
	 * Providers offered in the editor.
	 *
	 * @return array<string, string>
	 */
	public static function providers(): array {
		return array(
			'zoom'  => 'Zoom',
			'meet'  => 'Google Meet',
			'teams' => 'Microsoft Teams',
			'other' => __( 'Otra plataforma', 'aula-virtual' ),
		);
	}

	/**
	 * Live class persistence.
	 *
	 * @var LiveClassRepository
	 */
	private LiveClassRepository $classes;

	/**
	 * Lesson persistence.
	 *
	 * @var LessonRepository
	 */
	private LessonRepository $lessons;

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
	 * Constructor.
	 *
	 * @param LiveClassRepository $classes  Live class persistence.
	 * @param LessonRepository    $lessons  Lesson persistence.
	 * @param EditionRepository   $editions Edition persistence.
	 * @param EventBus            $events   Domain events.
	 */
	public function __construct( LiveClassRepository $classes, LessonRepository $lessons, EditionRepository $editions, EventBus $events ) {
		$this->classes  = $classes;
		$this->lessons  = $lessons;
		$this->editions = $editions;
		$this->events   = $events;
	}

	/**
	 * Creates or updates the live class of a lesson.
	 *
	 * @param int                  $lesson_id Lesson id.
	 * @param array<string, mixed> $input     Raw input (local datetimes).
	 * @return int|WP_Error Live class id.
	 */
	public function save( int $lesson_id, array $input ) {
		$lesson = $this->lessons->find( $lesson_id );

		if ( null === $lesson ) {
			return new WP_Error( 'av_lesson_not_found', __( 'La sesion no existe.', 'aula-virtual' ), array( 'status' => 404 ) );
		}

		$edition  = $this->editions->find( (int) $lesson['edition_id'] );
		$timezone = Sanitizer::text( $input['timezone'] ?? ( $edition['timezone'] ?? '' ) );
		$timezone = '' === $timezone ? wp_timezone_string() : $timezone;

		$start = self::to_utc( (string) ( $input['start_local'] ?? '' ), $timezone );
		$end   = self::to_utc( (string) ( $input['end_local'] ?? '' ), $timezone );

		if ( null === $start ) {
			return new WP_Error( 'av_missing_start', __( 'Indica fecha y hora de inicio de la clase.', 'aula-virtual' ), array( 'status' => 400 ) );
		}

		if ( null === $end ) {
			$duration = max( 15, Sanitizer::int( $input['duration'] ?? 60 ) );
			$end      = gmdate( 'Y-m-d H:i:s', (int) strtotime( $start . ' UTC' ) + $duration * 60 );
		}

		if ( $end <= $start ) {
			return new WP_Error( 'av_invalid_range', __( 'La clase termina antes de empezar.', 'aula-virtual' ), array( 'status' => 400 ) );
		}

		$existing = $this->classes->for_lesson( $lesson_id );
		$now      = current_time( 'mysql', true );

		$data = array(
			'lesson_id'      => $lesson_id,
			'edition_id'     => (int) $lesson['edition_id'],
			'provider'       => Sanitizer::enum( $input['provider'] ?? '', array_keys( self::providers() ), 'other' ),
			'meeting_url'    => Sanitizer::url( $input['meeting_url'] ?? '' ),
			'meeting_id'     => Sanitizer::text( $input['meeting_id'] ?? '' ),
			'access_code'    => Sanitizer::text( $input['access_code'] ?? '' ),
			'start_datetime' => $start,
			'end_datetime'   => $end,
			'timezone'       => $timezone,
			'open_before'    => max( 0, Sanitizer::int( $input['open_before'] ?? 15 ) ),
			'close_after'    => max( 0, Sanitizer::int( $input['close_after'] ?? 30 ) ),
			'message'        => Sanitizer::textarea( $input['message'] ?? '' ),
			'recording_url'  => Sanitizer::url( $input['recording_url'] ?? '' ),
			'status'         => Sanitizer::enum( $input['status'] ?? '', array( LiveClassRepository::STATUS_SCHEDULED, LiveClassRepository::STATUS_DONE, LiveClassRepository::STATUS_CANCELLED ), LiveClassRepository::STATUS_SCHEDULED ),
			'updated_at'     => $now,
		);

		if ( null === $existing ) {
			$data['created_at'] = $now;
			$id                 = $this->classes->insert( $data );
		} else {
			$id = (int) $existing['id'];
			$this->classes->update( $id, $data );
		}

		if ( 0 === $id ) {
			return new WP_Error( 'av_live_not_saved', __( 'No se pudo guardar la clase en vivo.', 'aula-virtual' ) );
		}

		$had_recording = null !== $existing && '' !== (string) ( $existing['recording_url'] ?? '' );

		if ( '' !== $data['recording_url'] && ! $had_recording ) {
			$this->events->dispatch(
				Events::LIVE_CLASS_RECORDED,
				array(
					'lesson_id'  => $lesson_id,
					'edition_id' => (int) $lesson['edition_id'],
					'course_id'  => (int) $lesson['course_id'],
				)
			);
		}

		return $id;
	}

	/**
	 * Removes the live class of a lesson.
	 *
	 * @param int $lesson_id Lesson id.
	 * @return bool
	 */
	public function remove( int $lesson_id ): bool {
		$existing = $this->classes->for_lesson( $lesson_id );

		return null !== $existing && $this->classes->delete( (int) $existing['id'] );
	}

	/**
	 * Where "now" falls relative to the join window.
	 *
	 * @param string $start_utc   Start, MySQL UTC.
	 * @param string $end_utc     End, MySQL UTC.
	 * @param int    $open_before Minutes before start the button appears.
	 * @param int    $close_after Minutes after end the button disappears.
	 * @param string $now_utc     Moment to evaluate, MySQL UTC.
	 * @return string One of the WINDOW_* constants.
	 */
	public static function window_state( string $start_utc, string $end_utc, int $open_before, int $close_after, string $now_utc ): string {
		$start = (int) strtotime( $start_utc . ' UTC' );
		$end   = (int) strtotime( $end_utc . ' UTC' );
		$now   = (int) strtotime( $now_utc . ' UTC' );

		if ( $now < $start - $open_before * 60 ) {
			return self::WINDOW_BEFORE;
		}

		if ( $now > $end + $close_after * 60 ) {
			return self::WINDOW_AFTER;
		}

		return self::WINDOW_OPEN;
	}

	/**
	 * Converts a local datetime in a time zone to MySQL UTC.
	 *
	 * @param string $local    Local datetime, e.g. "2027-07-01 19:00".
	 * @param string $timezone IANA time zone, e.g. "America/Lima".
	 * @return string|null
	 */
	public static function to_utc( string $local, string $timezone ): ?string {
		$local = trim( $local );

		if ( '' === $local ) {
			return null;
		}

		try {
			$date = new DateTimeImmutable( $local, new DateTimeZone( $timezone ) );
		} catch ( Exception $e ) {
			return null;
		}

		return $date->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Converts MySQL UTC to a local datetime string for display.
	 *
	 * @param string $utc      MySQL UTC datetime.
	 * @param string $timezone IANA time zone.
	 * @param string $format   Output format.
	 * @return string
	 */
	public static function to_local( string $utc, string $timezone, string $format = 'Y-m-d H:i' ): string {
		if ( '' === $utc ) {
			return '';
		}

		try {
			$date = new DateTimeImmutable( $utc, new DateTimeZone( 'UTC' ) );

			return $date->setTimezone( new DateTimeZone( $timezone ) )->format( $format );
		} catch ( Exception $e ) {
			return $utc;
		}
	}
}
