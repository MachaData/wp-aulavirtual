<?php
/**
 * Material rules.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Materials;

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
 * Attaches files from the Media Library, or external links, to lessons.
 *
 * Files live in the Media Library on purpose: WordPress already validates
 * uploads, generates thumbnails and handles storage. The plugin adds a
 * whitelist of extensions so an executable can never be listed as material.
 */
final class MaterialService {

	/**
	 * Extensions accepted as material.
	 *
	 * @return array<int, string>
	 */
	public static function allowed_extensions(): array {
		/**
		 * Filters the file extensions accepted as course material.
		 *
		 * @param array<int, string> $extensions Lower-case extensions.
		 */
		return (array) apply_filters(
			'aula_virtual/material_extensions',
			array( 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'mp3', 'mp4', 'txt', 'csv', 'odt', 'ods', 'odp' )
		);
	}

	/**
	 * Whether an extension is accepted.
	 *
	 * @param string $extension Extension, any case.
	 * @return bool
	 */
	public static function is_allowed_extension( string $extension ): bool {
		return in_array( strtolower( ltrim( $extension, '.' ) ), self::allowed_extensions(), true );
	}

	/**
	 * Material persistence.
	 *
	 * @var MaterialRepository
	 */
	private MaterialRepository $materials;

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
	 * @param MaterialRepository $materials Material persistence.
	 * @param LessonRepository   $lessons   Lesson persistence.
	 * @param EditionRepository  $editions  Edition persistence.
	 * @param EventBus           $events    Domain events.
	 */
	public function __construct( MaterialRepository $materials, LessonRepository $lessons, EditionRepository $editions, EventBus $events ) {
		$this->materials = $materials;
		$this->lessons   = $lessons;
		$this->editions  = $editions;
		$this->events    = $events;
	}

	/**
	 * Adds a material to a lesson (or to the edition when lesson_id is 0).
	 *
	 * @param array<string, mixed> $input Raw input: edition_id, lesson_id, title, description, attachment_id or external_url, downloadable.
	 * @return int|WP_Error Material id.
	 */
	public function add( array $input ) {
		$lesson_id  = Sanitizer::int( $input['lesson_id'] ?? 0 );
		$edition_id = Sanitizer::int( $input['edition_id'] ?? 0 );
		$course_id  = 0;

		if ( $lesson_id > 0 ) {
			$lesson = $this->lessons->find( $lesson_id );

			if ( null === $lesson ) {
				return new WP_Error( 'av_lesson_not_found', __( 'La sesion no existe.', 'aula-virtual' ), array( 'status' => 404 ) );
			}

			$edition_id = (int) $lesson['edition_id'];
			$course_id  = (int) $lesson['course_id'];
		} else {
			$edition = $this->editions->find( $edition_id );

			if ( null === $edition ) {
				return new WP_Error( 'av_edition_not_found', __( 'La edicion no existe.', 'aula-virtual' ), array( 'status' => 404 ) );
			}

			$course_id = (int) $edition['course_id'];
		}

		$attachment_id = Sanitizer::int( $input['attachment_id'] ?? 0 );
		$external_url  = Sanitizer::url( $input['external_url'] ?? '' );
		$file_type     = '';
		$title         = Sanitizer::text( $input['title'] ?? '' );

		if ( $attachment_id > 0 ) {
			$file = get_attached_file( $attachment_id );

			if ( ! is_string( $file ) || '' === $file ) {
				return new WP_Error( 'av_attachment_not_found', __( 'El archivo no existe en la biblioteca de medios.', 'aula-virtual' ), array( 'status' => 404 ) );
			}

			$check     = wp_check_filetype( $file );
			$file_type = strtolower( (string) ( $check['ext'] ?? '' ) );

			if ( '' === $file_type || ! self::is_allowed_extension( $file_type ) ) {
				return new WP_Error( 'av_file_type_not_allowed', __( 'Ese tipo de archivo no se admite como material.', 'aula-virtual' ), array( 'status' => 400 ) );
			}

			if ( '' === $title ) {
				$title = get_the_title( $attachment_id );
			}
		} elseif ( '' !== $external_url ) {
			$file_type = 'link';

			if ( '' === $title ) {
				$title = $external_url;
			}
		} else {
			return new WP_Error( 'av_material_empty', __( 'Elige un archivo o indica un enlace.', 'aula-virtual' ), array( 'status' => 400 ) );
		}

		$existing = $lesson_id > 0 ? $this->materials->for_lesson( $lesson_id ) : $this->materials->for_edition( $edition_id );

		$material_id = $this->materials->insert(
			array(
				'course_id'     => $course_id,
				'edition_id'    => $edition_id,
				'lesson_id'     => $lesson_id,
				'title'         => $title,
				'description'   => Sanitizer::textarea( $input['description'] ?? '' ),
				'attachment_id' => $attachment_id,
				'external_url'  => $external_url,
				'file_type'     => $file_type,
				'downloadable'  => isset( $input['downloadable'] ) && ! Sanitizer::bool( $input['downloadable'] ) ? 0 : 1,
				'position'      => count( $existing ) + 1,
				'created_by'    => get_current_user_id(),
				'created_at'    => current_time( 'mysql', true ),
			)
		);

		if ( 0 === $material_id ) {
			return new WP_Error( 'av_material_not_saved', __( 'No se pudo guardar el material.', 'aula-virtual' ) );
		}

		$this->events->dispatch(
			Events::MATERIAL_ADDED,
			array(
				'material_id' => $material_id,
				'lesson_id'   => $lesson_id,
				'edition_id'  => $edition_id,
				'course_id'   => $course_id,
			)
		);

		return $material_id;
	}

	/**
	 * Deletes a material row. The file stays in the Media Library.
	 *
	 * @param int $material_id Material id.
	 * @return bool
	 */
	public function delete( int $material_id ): bool {
		return $this->materials->delete( $material_id );
	}

	/**
	 * Public URL of a material.
	 *
	 * @param array<string, mixed> $material Material row.
	 * @return string
	 */
	public static function url( array $material ): string {
		if ( (int) $material['attachment_id'] > 0 ) {
			$url = wp_get_attachment_url( (int) $material['attachment_id'] );

			return is_string( $url ) ? $url : '';
		}

		return (string) ( $material['external_url'] ?? '' );
	}
}
