<?php
/**
 * Course duplication.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Courses;

use SIQA\AulaVirtual\Core\AuditLog;
use SIQA\AulaVirtual\Editions\EditionDuplicator;
use SIQA\AulaVirtual\Editions\EditionRepository;
use SIQA\AulaVirtual\Editions\EditionService;
use SIQA\AulaVirtual\Editions\EditionStatus;
use SIQA\AulaVirtual\Landing\LandingData;
use SIQA\AulaVirtual\Security\Sanitizer;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Copies a course as a draft: content, image, meta, categories and landing.
 * Optionally copies the curriculum of one of its editions into a first
 * edition of the new course. Never copies students, enrollments, progress,
 * requests or certificates.
 */
final class CourseDuplicator {

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
	private EditionService $edition_service;

	/**
	 * Edition duplicator, for the curriculum copy.
	 *
	 * @var EditionDuplicator
	 */
	private EditionDuplicator $duplicator;

	/**
	 * Audit trail.
	 *
	 * @var AuditLog
	 */
	private AuditLog $audit;

	/**
	 * Constructor.
	 *
	 * @param EditionRepository $editions        Edition persistence.
	 * @param EditionService    $edition_service Edition rules.
	 * @param EditionDuplicator $duplicator      Edition duplicator.
	 * @param AuditLog          $audit           Audit trail.
	 */
	public function __construct( EditionRepository $editions, EditionService $edition_service, EditionDuplicator $duplicator, AuditLog $audit ) {
		$this->editions        = $editions;
		$this->edition_service = $edition_service;
		$this->duplicator      = $duplicator;
		$this->audit           = $audit;
	}

	/**
	 * Duplicates a course.
	 *
	 * @param int                  $course_id Source course.
	 * @param array<string, mixed> $options   title, copy_landing (bool), copy_edition_id (int: copy that edition's curriculum), edition_name.
	 * @return array{course_id: int, edition_id: int, lessons: int}|WP_Error
	 */
	public function duplicate( int $course_id, array $options = array() ) {
		$source = get_post( $course_id );

		if ( ! $source instanceof \WP_Post || CoursePostType::POST_TYPE !== $source->post_type ) {
			return new WP_Error( 'av_course_not_found', __( 'El curso no existe.', 'aula-virtual' ), array( 'status' => 404 ) );
		}

		$title = Sanitizer::text( $options['title'] ?? '' );
		$title = '' === $title ? sprintf( /* translators: %s: source course title. */ __( 'Copia de %s', 'aula-virtual' ), $source->post_title ) : $title;

		$new_id = wp_insert_post(
			array(
				'post_type'    => CoursePostType::POST_TYPE,
				'post_title'   => $title,
				'post_content' => $source->post_content,
				'post_excerpt' => $source->post_excerpt,
				'post_status'  => 'draft',
				'post_author'  => get_current_user_id(),
			),
			true
		);

		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}

		$new_id = (int) $new_id;

		$thumbnail = get_post_thumbnail_id( $course_id );

		if ( $thumbnail > 0 ) {
			set_post_thumbnail( $new_id, $thumbnail );
		}

		$skip = array( LandingData::META_KEY, '_av_migrated_from_tutor', CourseMeta::PRODUCT_ID, '_edit_lock', '_edit_last', '_thumbnail_id' );

		foreach ( (array) get_post_meta( $course_id ) as $key => $values ) {
			if ( in_array( $key, $skip, true ) || ! str_starts_with( (string) $key, '_av_' ) ) {
				continue;
			}

			foreach ( (array) $values as $value ) {
				add_post_meta( $new_id, $key, maybe_unserialize( $value ) );
			}
		}

		if ( ! isset( $options['copy_landing'] ) || Sanitizer::bool( $options['copy_landing'] ) ) {
			$landing = get_post_meta( $course_id, LandingData::META_KEY, true );

			if ( is_array( $landing ) ) {
				if ( isset( $landing['hero']['title'] ) && $landing['hero']['title'] === $source->post_title ) {
					$landing['hero']['title'] = $title;
				}

				LandingData::save( $new_id, $landing );
			}
		}

		foreach ( array( CoursePostType::TAX_CATEGORY, CoursePostType::TAX_TAG ) as $taxonomy ) {
			$terms = wp_get_object_terms( $course_id, $taxonomy, array( 'fields' => 'ids' ) );

			if ( is_array( $terms ) && array() !== $terms ) {
				wp_set_object_terms( $new_id, array_map( 'intval', $terms ), $taxonomy );
			}
		}

		$report = array(
			'course_id'  => $new_id,
			'edition_id' => 0,
			'lessons'    => 0,
		);

		$copy_edition = Sanitizer::int( $options['copy_edition_id'] ?? 0 );

		if ( $copy_edition > 0 ) {
			$edition = $this->editions->find( $copy_edition );

			if ( null !== $edition && (int) $edition['course_id'] === $course_id ) {
				$result = $this->duplicator->duplicate(
					$copy_edition,
					array(
						'course_id'      => $new_id,
						'name'           => Sanitizer::text( $options['edition_name'] ?? '' ) ?: (string) $edition['name'],
						'copy_live'      => false,
						'copy_materials' => true,
					)
				);

				if ( ! $result instanceof WP_Error ) {
					$report['edition_id'] = (int) $result['edition_id'];
					$report['lessons']    = (int) $result['lessons'];
				}
			}
		}

		$this->audit->record( AuditLog::EDITION_UPDATED, 'course', $new_id, array( 'action' => 'duplicate_course', 'from' => $course_id ) );

		return $report;
	}
}
