<?php
/**
 * Courses REST controller.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\REST;

use SIQA\AulaVirtual\Courses\CourseMeta;
use SIQA\AulaVirtual\Courses\CoursePostType;
use SIQA\AulaVirtual\Courses\CourseRepository;
use SIQA\AulaVirtual\Permissions\AccessControl;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exposes the public course catalogue over REST.
 *
 * Only published courses are returned to anonymous clients; drafts and private
 * courses require the capability that would let the user edit them.
 */
final class CoursesController extends AbstractController {

	/**
	 * Course queries.
	 *
	 * @var CourseRepository
	 */
	private CourseRepository $courses;

	/**
	 * Permission checks.
	 *
	 * @var AccessControl
	 */
	private AccessControl $access;

	/**
	 * Constructor.
	 *
	 * @param CourseRepository $courses Course queries.
	 * @param AccessControl    $access  Permission checks.
	 */
	public function __construct( CourseRepository $courses, AccessControl $access ) {
		parent::__construct();

		$this->rest_base = 'courses';
		$this->courses   = $courses;
		$this->access    = $access;
	}

	/**
	 * Registers the controller routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => '__return_true',
					'args'                => $this->get_collection_params(),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'id' => array(
							'description'       => __( 'Identificador del curso.', 'aula-virtual' ),
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
					),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Returns a page of courses.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$result = $this->courses->paginate(
			array(
				'page'     => (int) $request->get_param( 'page' ),
				'per_page' => (int) $request->get_param( 'per_page' ),
				'search'   => (string) $request->get_param( 'search' ),
				'category' => (string) $request->get_param( 'category' ),
				'tag'      => (string) $request->get_param( 'tag' ),
				'status'   => 'publish',
			)
		);

		$items = array_map(
			fn( WP_Post $course ): array => $this->prepare_course( $course ),
			$result['items']
		);

		return $this->paginated_response( $items, $result );
	}

	/**
	 * Returns a single course.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function get_item( $request ) {
		$course = $this->courses->find( (int) $request->get_param( 'id' ) );

		if ( null === $course ) {
			return $this->not_found();
		}

		if ( 'publish' !== $course->post_status && ! $this->access->can_edit_course( $course->ID ) ) {
			return $this->forbidden();
		}

		return rest_ensure_response( $this->prepare_course( $course ) );
	}

	/**
	 * Builds the public representation of a course.
	 *
	 * @param WP_Post $course Course post.
	 * @return array<string, mixed>
	 */
	private function prepare_course( WP_Post $course ): array {
		$instructor_id = (int) get_post_meta( $course->ID, CourseMeta::INSTRUCTOR_ID, true );
		$instructor    = $instructor_id > 0 ? get_userdata( $instructor_id ) : false;

		return array(
			'id'                => $course->ID,
			'title'             => get_the_title( $course ),
			'slug'              => $course->post_name,
			'link'              => (string) get_permalink( $course ),
			'status'            => $course->post_status,
			'excerpt'           => wp_strip_all_tags( get_the_excerpt( $course ) ),
			'short_description' => (string) get_post_meta( $course->ID, CourseMeta::SHORT_DESCRIPTION, true ),
			'level'             => (string) get_post_meta( $course->ID, CourseMeta::LEVEL, true ),
			'duration'          => (string) get_post_meta( $course->ID, CourseMeta::DURATION, true ),
			'price_display'     => (string) get_post_meta( $course->ID, CourseMeta::PRICE_DISPLAY, true ),
			'featured_image'    => (string) get_the_post_thumbnail_url( $course, 'large' ),
			'instructor'        => array(
				'id'   => $instructor_id,
				'name' => $instructor ? $instructor->display_name : '',
			),
			'categories'        => wp_get_post_terms( $course->ID, CoursePostType::TAX_CATEGORY, array( 'fields' => 'slugs' ) ),
		);
	}

	/**
	 * Adds the catalogue filters to the collection parameters.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_collection_params() {
		$params = parent::get_collection_params();

		$params['category'] = array(
			'description'       => __( 'Filtra por slug de categoria.', 'aula-virtual' ),
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'sanitize_title',
		);

		$params['tag'] = array(
			'description'       => __( 'Filtra por slug de etiqueta.', 'aula-virtual' ),
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'sanitize_title',
		);

		return $params;
	}

	/**
	 * Returns the item schema.
	 *
	 * @return array<string, mixed>
	 */
	public function get_item_schema() {
		if ( null !== $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'av_course',
			'type'       => 'object',
			'properties' => array(
				'id'                => array(
					'type'     => 'integer',
					'readonly' => true,
				),
				'title'             => array( 'type' => 'string' ),
				'slug'              => array( 'type' => 'string' ),
				'link'              => array( 'type' => 'string' ),
				'status'            => array( 'type' => 'string' ),
				'excerpt'           => array( 'type' => 'string' ),
				'short_description' => array( 'type' => 'string' ),
				'level'             => array(
					'type' => 'string',
					'enum' => CourseMeta::LEVELS,
				),
				'duration'          => array( 'type' => 'string' ),
				'price_display'     => array( 'type' => 'string' ),
				'featured_image'    => array( 'type' => 'string' ),
				'instructor'        => array( 'type' => 'object' ),
				'categories'        => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
			),
		);

		return $this->add_additional_fields_schema( $this->schema );
	}
}
