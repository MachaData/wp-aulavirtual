<?php
/**
 * Edition business rules.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Editions;

use SIQA\AulaVirtual\Core\AuditLog;
use SIQA\AulaVirtual\Courses\CoursePostType;
use SIQA\AulaVirtual\Security\Sanitizer;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates and updates cohorts, validating everything the database cannot.
 *
 * The repository stores rows; this service decides whether a row is allowed to
 * exist: that the course is real, that the code is unique, that the end date is
 * not before the start date.
 */
final class EditionService {

	public const MODALITY_RECORDED = 'recorded';
	public const MODALITY_LIVE     = 'live';
	public const MODALITY_HYBRID   = 'hybrid';

	/**
	 * Edition persistence.
	 *
	 * @var EditionRepository
	 */
	private EditionRepository $editions;

	/**
	 * Administrative audit trail.
	 *
	 * @var AuditLog
	 */
	private AuditLog $audit;

	/**
	 * Constructor.
	 *
	 * @param EditionRepository $editions Edition persistence.
	 * @param AuditLog          $audit    Audit trail.
	 */
	public function __construct( EditionRepository $editions, AuditLog $audit ) {
		$this->editions = $editions;
		$this->audit    = $audit;
	}

	/**
	 * Available modalities with their labels.
	 *
	 * @return array<string, string>
	 */
	public static function modalities(): array {
		return array(
			self::MODALITY_RECORDED => __( 'Grabado', 'aula-virtual' ),
			self::MODALITY_LIVE     => __( 'En vivo', 'aula-virtual' ),
			self::MODALITY_HYBRID   => __( 'Híbrido', 'aula-virtual' ),
		);
	}

	/**
	 * Creates an edition.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return int|WP_Error Edition id, or the reason it was rejected.
	 */
	public function create( array $input ) {
		$data = $this->prepare( $input );

		if ( $data instanceof WP_Error ) {
			return $data;
		}

		$now = current_time( 'mysql', true );

		$data['created_by'] = get_current_user_id();
		$data['created_at'] = $now;
		$data['updated_at'] = $now;

		$edition_id = $this->editions->insert( $data );

		if ( 0 === $edition_id ) {
			return new WP_Error(
				'av_edition_not_created',
				__( 'No se pudo guardar la edición.', 'aula-virtual' )
			);
		}

		$this->audit->record( AuditLog::EDITION_UPDATED, 'edition', $edition_id, array( 'action' => 'create' ) );

		/**
		 * Fires after an edition is created.
		 *
		 * @param int                  $edition_id Edition id.
		 * @param array<string, mixed> $data       Stored values.
		 */
		do_action( 'aula_virtual/edition_created', $edition_id, $data );

		return $edition_id;
	}

	/**
	 * Updates an edition.
	 *
	 * @param int                  $edition_id Edition id.
	 * @param array<string, mixed> $input      Raw input.
	 * @return true|WP_Error
	 */
	public function update( int $edition_id, array $input ) {
		$existing = $this->editions->find( $edition_id );

		if ( null === $existing ) {
			return new WP_Error(
				'av_edition_not_found',
				__( 'La edición no existe.', 'aula-virtual' ),
				array( 'status' => 404 )
			);
		}

		$data = $this->prepare( $input, $edition_id );

		if ( $data instanceof WP_Error ) {
			return $data;
		}

		$data['updated_at'] = current_time( 'mysql', true );

		$this->editions->update( $edition_id, $data );

		$this->audit->record( AuditLog::EDITION_UPDATED, 'edition', $edition_id, array( 'action' => 'update' ) );

		return true;
	}

	/**
	 * Validates and normalises the input for a write.
	 *
	 * @param array<string, mixed> $input   Raw input.
	 * @param int                  $exclude Edition id to ignore in the code uniqueness check.
	 * @return array<string, mixed>|WP_Error
	 */
	private function prepare( array $input, int $exclude = 0 ) {
		$course_id = Sanitizer::int( $input['course_id'] ?? 0 );
		$course    = get_post( $course_id );

		if ( ! $course instanceof \WP_Post || CoursePostType::POST_TYPE !== $course->post_type ) {
			return new WP_Error(
				'av_invalid_course',
				__( 'Selecciona un curso válido para la edición.', 'aula-virtual' ),
				array( 'status' => 400 )
			);
		}

		$name = Sanitizer::text( $input['name'] ?? '' );

		if ( '' === $name ) {
			return new WP_Error(
				'av_missing_name',
				__( 'La edición necesita un nombre, por ejemplo "Julio 2027".', 'aula-virtual' ),
				array( 'status' => 400 )
			);
		}

		$code = Sanitizer::text( $input['code'] ?? '' );
		$code = '' === $code ? $this->generate_code( $course_id, $name ) : sanitize_title( $code );

		if ( $this->editions->code_exists( $code, $exclude ) ) {
			return new WP_Error(
				'av_duplicate_code',
				sprintf(
					/* translators: %s: edition code. */
					__( 'Ya existe una edición con el código "%s".', 'aula-virtual' ),
					$code
				),
				array( 'status' => 409 )
			);
		}

		$start = Sanitizer::datetime( $input['start_date'] ?? '' );
		$end   = Sanitizer::datetime( $input['end_date'] ?? '' );

		if ( null !== $start && null !== $end && $end < $start ) {
			return new WP_Error(
				'av_invalid_dates',
				__( 'La fecha de fin no puede ser anterior a la de inicio.', 'aula-virtual' ),
				array( 'status' => 400 )
			);
		}

		$access_start = Sanitizer::datetime( $input['access_start'] ?? '' );
		$access_end   = Sanitizer::datetime( $input['access_end'] ?? '' );

		if ( null !== $access_start && null !== $access_end && $access_end < $access_start ) {
			return new WP_Error(
				'av_invalid_access_window',
				__( 'La ventana de acceso termina antes de empezar.', 'aula-virtual' ),
				array( 'status' => 400 )
			);
		}

		return array(
			'course_id'    => $course_id,
			'name'         => $name,
			'code'         => $code,
			'status'       => Sanitizer::enum( $input['status'] ?? '', EditionStatus::all(), EditionStatus::DRAFT ),
			'modality'     => Sanitizer::enum( $input['modality'] ?? '', array_keys( self::modalities() ), self::MODALITY_RECORDED ),
			'start_date'   => $start,
			'end_date'     => $end,
			'access_start' => $access_start,
			'access_end'   => $access_end,
			'timezone'     => Sanitizer::text( $input['timezone'] ?? wp_timezone_string() ),
			'schedule_days' => Sanitizer::text( $input['schedule_days'] ?? '' ),
			'schedule_time' => Sanitizer::text( $input['schedule_time'] ?? '' ),
			'price_display' => Sanitizer::text( $input['price_display'] ?? '' ),
			'capacity'     => max( 0, Sanitizer::int( $input['capacity'] ?? 0 ) ),
			'product_id'   => Sanitizer::int( $input['product_id'] ?? 0 ),
		);
	}

	/**
	 * Builds a unique, readable code from the course and the edition name.
	 *
	 * @param int    $course_id Course post id.
	 * @param string $name      Edition name.
	 * @return string
	 */
	private function generate_code( int $course_id, string $name ): string {
		$base      = sanitize_title( get_the_title( $course_id ) . '-' . $name );
		$base      = '' === $base ? 'edicion' : $base;
		$candidate = substr( $base, 0, 56 );
		$suffix    = 2;

		while ( $this->editions->code_exists( $candidate ) ) {
			$candidate = substr( $base, 0, 52 ) . '-' . $suffix;
			++$suffix;
		}

		return $candidate;
	}
}
