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

	public const OPTION_PROTECT  = 'av_protect_materials';
	public const PRIVATE_SUBDIR  = 'aula-virtual/private';

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
			$allowed = self::can_use_attachment( $attachment_id, $course_id, $this->materials->all( array( 'where' => array( 'attachment_id' => $attachment_id ), 'limit' => 50 ) ) );

			if ( $allowed instanceof WP_Error ) {
				return $allowed;
			}

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

		if ( $attachment_id > 0 && get_option( self::OPTION_PROTECT, true ) ) {
			// Si el traslado falla, el material queda igualmente registrado; solo
			// pierde la proteccion del servidor web (el endpoint sigue comprobando).
			self::protect_attachment( $attachment_id );
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
	 * Whether the current user may attach a media file to a course.
	 *
	 * The file is moved to the protected folder when it becomes a material,
	 * so it must be the user's own upload (or the user must be able to edit
	 * others' media) and must not already belong to another course.
	 *
	 * @param int                              $attachment_id Attachment id.
	 * @param int                              $course_id     Target course.
	 * @param array<int, array<string, mixed>> $existing      Materials already using that attachment.
	 * @return true|WP_Error
	 */
	public static function can_use_attachment( int $attachment_id, int $course_id, array $existing ) {
		if ( 'attachment' !== get_post_type( $attachment_id ) || ! current_user_can( 'edit_post', $attachment_id ) ) {
			return new WP_Error( 'av_attachment_forbidden', __( 'No puedes usar ese archivo como material.', 'aula-virtual' ), array( 'status' => 403 ) );
		}

		foreach ( $existing as $material ) {
			if ( (int) $material['course_id'] !== $course_id ) {
				return new WP_Error( 'av_attachment_in_use', __( 'Ese archivo ya es material de otro curso. Subelo de nuevo para este curso.', 'aula-virtual' ), array( 'status' => 409 ) );
			}
		}

		return true;
	}

	/**
	 * Moves an attachment's file into the protected folder.
	 *
	 * The folder is `uploads/aula-virtual/private/` with an `.htaccess` that
	 * denies direct requests (Apache/LiteSpeed). On Nginx, add the location
	 * rule from the technical guide. WordPress keeps the attachment: only the
	 * path and the GUID change. Idempotent.
	 *
	 * @param int $attachment_id Attachment id.
	 * @return true|WP_Error
	 */
	public static function protect_attachment( int $attachment_id ) {
		$current = get_attached_file( $attachment_id, true );

		if ( ! is_string( $current ) || '' === $current || ! file_exists( $current ) ) {
			return new WP_Error( 'av_attachment_not_found', __( 'El archivo no existe en la biblioteca de medios.', 'aula-virtual' ), array( 'status' => 404 ) );
		}

		$dir = self::private_dir();

		if ( $dir instanceof WP_Error ) {
			return $dir;
		}

		if ( str_starts_with( wp_normalize_path( $current ), wp_normalize_path( $dir ) ) ) {
			return true;
		}

		$target = trailingslashit( $dir ) . wp_unique_filename( $dir, basename( $current ) );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- moving inside uploads.
		if ( ! rename( $current, $target ) ) {
			return new WP_Error( 'av_protect_failed', __( 'No se pudo mover el archivo a la carpeta protegida.', 'aula-virtual' ) );
		}

		// Miniaturas generadas para imagenes: se eliminan, ya no son alcanzables.
		$meta = wp_get_attachment_metadata( $attachment_id );

		if ( is_array( $meta ) && ! empty( $meta['sizes'] ) ) {
			foreach ( (array) $meta['sizes'] as $size ) {
				$thumb = dirname( $current ) . '/' . ( $size['file'] ?? '' );

				if ( '' !== ( $size['file'] ?? '' ) && file_exists( $thumb ) ) {
					wp_delete_file( $thumb );
				}
			}

			$meta['sizes'] = array();
		}

		$uploads  = wp_get_upload_dir();
		$relative = ltrim( str_replace( wp_normalize_path( (string) $uploads['basedir'] ), '', wp_normalize_path( $target ) ), '/' );

		update_attached_file( $attachment_id, $target );

		if ( is_array( $meta ) ) {
			$meta['file'] = $relative;
			wp_update_attachment_metadata( $attachment_id, $meta );
		}

		wp_update_post(
			array(
				'ID'   => $attachment_id,
				'guid' => trailingslashit( (string) $uploads['baseurl'] ) . $relative,
			)
		);

		update_post_meta( $attachment_id, '_av_protected', 1 );

		return true;
	}

	/**
	 * Moves every material file into the protected folder.
	 *
	 * @return array{moved: int, skipped: int, failed: int}
	 */
	public function protect_all(): array {
		$report = array( 'moved' => 0, 'skipped' => 0, 'failed' => 0 );

		foreach ( $this->materials->all( array( 'limit' => 5000 ) ) as $material ) {
			$attachment_id = (int) $material['attachment_id'];

			if ( $attachment_id <= 0 ) {
				continue;
			}

			if ( get_post_meta( $attachment_id, '_av_protected', true ) ) {
				++$report['skipped'];
				continue;
			}

			$result = self::protect_attachment( $attachment_id );

			if ( $result instanceof WP_Error ) {
				++$report['failed'];
			} else {
				++$report['moved'];
			}
		}

		return $report;
	}

	/**
	 * Returns the protected folder, creating it with its guard files.
	 *
	 * @return string|WP_Error Absolute path without trailing slash.
	 */
	public static function private_dir() {
		$uploads = wp_get_upload_dir();

		if ( ! empty( $uploads['error'] ) ) {
			return new WP_Error( 'av_uploads_unavailable', (string) $uploads['error'] );
		}

		$dir = trailingslashit( (string) $uploads['basedir'] ) . self::PRIVATE_SUBDIR;

		if ( ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'av_private_dir', __( 'No se pudo crear la carpeta protegida de materiales.', 'aula-virtual' ) );
		}

		$htaccess = $dir . '/.htaccess';

		if ( ! file_exists( $htaccess ) ) {
			$rules = "# Aula Virtual: los materiales se sirven solo a alumnos matriculados.\n"
				. "<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n"
				. "<IfModule !mod_authz_core.c>\n\tOrder deny,allow\n\tDeny from all\n</IfModule>\n";
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- guard file inside uploads.
			file_put_contents( $htaccess, $rules );
		}

		if ( ! file_exists( $dir . '/index.html' ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- guard file inside uploads.
			file_put_contents( $dir . '/index.html', '' );
		}

		return $dir;
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
