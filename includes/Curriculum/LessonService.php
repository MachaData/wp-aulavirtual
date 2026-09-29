<?php
/**
 * Lesson business rules.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Curriculum;

use SIQA\AulaVirtual\Editions\EditionRepository;
use SIQA\AulaVirtual\Security\Sanitizer;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates and reorders the lessons of an edition.
 *
 * A lesson always belongs to an edition, never to a course directly: that is
 * what lets the same course run twice with different sessions and different
 * students without the two cohorts interfering.
 */
final class LessonService {

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
	 * Constructor.
	 *
	 * @param LessonRepository  $lessons  Lesson persistence.
	 * @param EditionRepository $editions Edition persistence.
	 */
	public function __construct( LessonRepository $lessons, EditionRepository $editions ) {
		$this->lessons  = $lessons;
		$this->editions = $editions;
	}

	/**
	 * Creates a lesson at the end of the edition.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return int|WP_Error Lesson id, or the reason it was rejected.
	 */
	public function create( array $input ) {
		$edition_id = Sanitizer::int( $input['edition_id'] ?? 0 );
		$edition    = $this->editions->find( $edition_id );

		if ( null === $edition ) {
			return new WP_Error(
				'av_edition_not_found',
				__( 'La edición no existe.', 'aula-virtual' ),
				array( 'status' => 404 )
			);
		}

		$title = Sanitizer::text( $input['title'] ?? '' );

		if ( '' === $title ) {
			return new WP_Error(
				'av_missing_title',
				__( 'La sesión necesita un título.', 'aula-virtual' ),
				array( 'status' => 400 )
			);
		}

		$now = current_time( 'mysql', true );

		$lesson_id = $this->lessons->insert(
			array(
				'course_id'      => (int) $edition['course_id'],
				'edition_id'     => $edition_id,
				'module_id'      => Sanitizer::int( $input['module_id'] ?? 0 ),
				'title'          => $title,
				'description'    => Sanitizer::textarea( $input['description'] ?? '' ),
				'content'        => Sanitizer::html( $input['content'] ?? '' ),
				'lesson_type'    => Sanitizer::enum( $input['lesson_type'] ?? '', LessonType::all(), LessonType::VIDEO ),
				'video_provider' => Sanitizer::key( $input['video_provider'] ?? '' ),
				'video_url'      => self::video_source( $input['video_provider'] ?? '', $input['video_url'] ?? '' ),
				'duration'       => max( 0, Sanitizer::int( $input['duration'] ?? 0 ) ),
				'is_preview'     => Sanitizer::bool( $input['is_preview'] ?? false ) ? 1 : 0,
				'position'       => $this->lessons->next_position( $edition_id ),
				'status'         => Sanitizer::enum( $input['status'] ?? '', LessonType::statuses(), LessonType::STATUS_PUBLISH ),
				'created_at'     => $now,
				'updated_at'     => $now,
			)
		);

		if ( 0 === $lesson_id ) {
			return new WP_Error(
				'av_lesson_not_created',
				__( 'No se pudo guardar la sesión.', 'aula-virtual' )
			);
		}

		/**
		 * Fires after a lesson is created.
		 *
		 * @param int $lesson_id  Lesson id.
		 * @param int $edition_id Edition the lesson belongs to.
		 */
		do_action( 'aula_virtual/lesson_created', $lesson_id, $edition_id );

		return $lesson_id;
	}

	/**
	 * Updates an existing lesson.
	 *
	 * @param int                  $lesson_id Lesson id.
	 * @param array<string, mixed> $input     Raw input.
	 * @return true|WP_Error
	 */
	public function update( int $lesson_id, array $input ) {
		$lesson = $this->lessons->find( $lesson_id );

		if ( null === $lesson ) {
			return new WP_Error( 'av_lesson_not_found', __( 'La sesión no existe.', 'aula-virtual' ), array( 'status' => 404 ) );
		}

		$title = Sanitizer::text( $input['title'] ?? $lesson['title'] );

		if ( '' === $title ) {
			return new WP_Error( 'av_missing_title', __( 'La sesión necesita un título.', 'aula-virtual' ), array( 'status' => 400 ) );
		}

		$data = array(
			'title'          => $title,
			'description'    => Sanitizer::textarea( $input['description'] ?? $lesson['description'] ),
			'content'        => Sanitizer::html( $input['content'] ?? $lesson['content'] ),
			'lesson_type'    => Sanitizer::enum( $input['lesson_type'] ?? $lesson['lesson_type'], LessonType::all(), LessonType::VIDEO ),
			'video_provider' => Sanitizer::key( $input['video_provider'] ?? $lesson['video_provider'] ),
			'video_url'      => self::video_source( $input['video_provider'] ?? $lesson['video_provider'], $input['video_url'] ?? $lesson['video_url'] ),
			'duration'       => max( 0, Sanitizer::int( $input['duration'] ?? $lesson['duration'] ) ),
			'featured_image' => max( 0, Sanitizer::int( $input['featured_image'] ?? $lesson['featured_image'] ) ),
			'is_preview'     => Sanitizer::bool( $input['is_preview'] ?? $lesson['is_preview'] ) ? 1 : 0,
			'release_type'   => Sanitizer::enum( $input['release_type'] ?? $lesson['release_type'], array( 'immediate', 'date', 'offset' ), 'immediate' ),
			'release_date'   => Sanitizer::datetime( $input['release_date'] ?? ( $lesson['release_date'] ?? '' ) ),
			'release_offset' => max( 0, Sanitizer::int( $input['release_offset'] ?? $lesson['release_offset'] ) ),
			'status'         => Sanitizer::enum( $input['status'] ?? $lesson['status'], LessonType::statuses(), LessonType::STATUS_PUBLISH ),
			'updated_at'     => current_time( 'mysql', true ),
		);

		$this->lessons->update( $lesson_id, $data );

		return true;
	}

	/**
	 * Sanitises the video reference according to its provider.
	 *
	 * Embedded code and shortcodes are not URLs: they are kept as text and
	 * sanitised at render time by VideoEmbed, which only lets through iframes
	 * from allow-listed hosts.
	 *
	 * @param mixed $provider Provider key.
	 * @param mixed $source   URL or code.
	 * @return string
	 */
	public static function video_source( mixed $provider, mixed $source ): string {
		$source   = is_scalar( $source ) ? trim( (string) $source ) : '';
		$provider = Sanitizer::key( $provider );

		if ( '' === $provider ) {
			$provider = \SIQA\AulaVirtual\Videos\VideoEmbed::detect( $source );
		}

		if ( 'shortcode' === $provider ) {
			return self::allowed_shortcode( (string) $source );
		}

		if ( in_array( $provider, array( 'embed', 'shortcode' ), true ) ) {
			return wp_kses( $source, array( 'iframe' => array( 'src' => true, 'width' => true, 'height' => true, 'allow' => true, 'allowfullscreen' => true, 'frameborder' => true, 'title' => true ) ) );
		}

		return Sanitizer::url( $source );
	}

	/**
	 * Deletes a lesson.
	 *
	 * @param int $lesson_id Lesson id.
	 * @return bool
	 */
	public function delete( int $lesson_id ): bool {
		return $this->lessons->delete( $lesson_id );
	}

	/**
	 * Applies a new order to the lessons of an edition.
	 *
	 * Ids that do not belong to the edition are ignored, so a tampered request
	 * cannot move somebody else's lessons.
	 *
	 * @param int              $edition_id Edition id.
	 * @param array<int, int>  $lesson_ids Lesson ids in the desired order.
	 * @return int Number of lessons repositioned.
	 */
	public function reorder( int $edition_id, array $lesson_ids ): int {
		$owned = wp_list_pluck( $this->lessons->for_edition( $edition_id, false ), 'id' );
		$owned = array_map( 'intval', $owned );
		$moved = 0;

		foreach ( array_values( $lesson_ids ) as $index => $lesson_id ) {
			$lesson_id = (int) $lesson_id;

			if ( ! in_array( $lesson_id, $owned, true ) ) {
				continue;
			}

			$this->lessons->update( $lesson_id, array( 'position' => $index + 1 ) );
			++$moved;
		}

		return $moved;
	}
	/**
	 * Keeps a video shortcode only when its tag is on the allow-list.
	 *
	 * Instructors do not have unfiltered_html; running any shortcode of the
	 * site (checkout, forms, third-party plugins) inside a lesson would give
	 * them more power than their role. Only video/audio players are allowed.
	 *
	 * @param string $source Raw shortcode text.
	 * @return string The shortcode, or an empty string when not allowed.
	 */
	public static function allowed_shortcode( string $source ): string {
		$source = trim( wp_strip_all_tags( $source ) );

		/**
		 * Filters the shortcode tags accepted as lesson video.
		 *
		 * @param array<int, string> $tags Shortcode tags.
		 */
		$tags = (array) apply_filters( 'aula_virtual/allowed_video_shortcodes', array( 'video', 'audio', 'playlist', 'embed', 'presto_player', 'bunny_video' ) );

		if ( ! preg_match( '/^\[([a-z0-9_-]+)(?:\s[^\[\]]*)?\](?:[^\[]*\[\/\1\])?$/i', $source, $match ) ) {
			return '';
		}

		return in_array( strtolower( $match[1] ), array_map( 'strtolower', $tags ), true ) ? $source : '';
	}
}
