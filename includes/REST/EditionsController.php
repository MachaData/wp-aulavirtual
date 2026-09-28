<?php
/**
 * Editions REST controller.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\REST;

use SIQA\AulaVirtual\Courses\CourseRepository;
use SIQA\AulaVirtual\Editions\EditionRepository;
use SIQA\AulaVirtual\Editions\EditionService;
use SIQA\AulaVirtual\Editions\EditionStatus;
use SIQA\AulaVirtual\Enrollments\EnrollmentRepository;
use SIQA\AulaVirtual\Enrollments\EnrollmentStatus;
use SIQA\AulaVirtual\Permissions\AccessControl;
use SIQA\AulaVirtual\Permissions\Capabilities;
use SIQA\AulaVirtual\WooCommerce\ProductLink;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exposes the cohorts (editions) of the catalogue over REST.
 *
 * The list and the single item are public so the shop site can render
 * dates, seats and the buy button. Draft editions and editions of
 * unpublished courses stay hidden from anonymous clients. The student list
 * of an edition is personal data and requires the view-students capability.
 */
final class EditionsController extends AbstractController {

	/**
	 * Edition persistence.
	 *
	 * @var EditionRepository
	 */
	private EditionRepository $editions;

	/**
	 * Course queries.
	 *
	 * @var CourseRepository
	 */
	private CourseRepository $courses;

	/**
	 * Enrollment persistence.
	 *
	 * @var EnrollmentRepository
	 */
	private EnrollmentRepository $enrollments;

	/**
	 * Permission checks.
	 *
	 * @var AccessControl
	 */
	private AccessControl $access;

	/**
	 * Constructor.
	 *
	 * @param EditionRepository    $editions    Edition persistence.
	 * @param CourseRepository     $courses     Course queries.
	 * @param EnrollmentRepository $enrollments Enrollment persistence.
	 * @param AccessControl        $access      Permission checks.
	 */
	public function __construct(
		EditionRepository $editions,
		CourseRepository $courses,
		EnrollmentRepository $enrollments,
		AccessControl $access
	) {
		parent::__construct();

		$this->rest_base   = 'editions';
		$this->editions    = $editions;
		$this->courses     = $courses;
		$this->enrollments = $enrollments;
		$this->access      = $access;
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
						'id' => $this->id_arg(),
					),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/students',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_students' ),
					'permission_callback' => array( $this, 'students_permissions_check' ),
					'args'                => array_merge(
						array( 'id' => $this->id_arg() ),
						$this->students_params()
					),
				),
				'schema' => array( $this, 'get_public_student_schema' ),
			)
		);
	}

	/**
	 * Permission callback of the student list.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return true|WP_Error
	 */
	public function students_permissions_check( $request ) {
		$check = $this->require_capability( Capabilities::VIEW_STUDENTS );

		if ( true !== $check ) {
			return $check;
		}

		// Un instructor solo ve los alumnos de ediciones de sus propios cursos.
		$edition = $this->editions->find( (int) $request->get_param( 'id' ) );

		if ( null === $edition ) {
			return $this->not_found( __( 'La edicion no existe.', 'aula-virtual' ) );
		}

		return $this->access->can_manage_editions( (int) $edition['course_id'] ) ? true : $this->forbidden();
	}

	/**
	 * Returns a page of editions.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$where     = array();
		$course_id = (int) $request->get_param( 'course' );
		$status    = (string) $request->get_param( 'status' );
		$open_only = (bool) $request->get_param( 'open_only' );

		if ( $course_id > 0 ) {
			$where['course_id'] = $course_id;
		}

		if ( $open_only ) {
			$where['status'] = EditionStatus::enrollable();
		} elseif ( '' !== $status ) {
			if ( ! $this->can_see_hidden() && ! $this->is_public_status( $status ) ) {
				return $this->forbidden();
			}

			$where['status'] = $status;
		} elseif ( ! $this->can_see_hidden() ) {
			$where['status'] = $this->public_statuses();
		}

		// Sin permisos, solo cursos publicados; se filtra antes de paginar para
		// que el total no delate cursos en borrador.
		if ( ! $this->can_see_hidden() ) {
			$visible = array_map(
				'intval',
				(array) get_posts(
					array(
						'post_type'      => \SIQA\AulaVirtual\Courses\CoursePostType::POST_TYPE,
						'post_status'    => 'publish',
						'fields'         => 'ids',
						'posts_per_page' => -1,
						'no_found_rows'  => true,
					)
				)
			);

			$where['course_id'] = $course_id > 0 ? ( in_array( $course_id, $visible, true ) ? $course_id : array() ) : $visible;
		}

		$result = $this->editions->paginate(
			array(
				'where'    => $where,
				'order_by' => 'start_date',
				'order'    => 'ASC',
			),
			(int) $request->get_param( 'page' ),
			(int) $request->get_param( 'per_page' )
		);

		$items = array();

		foreach ( $result['items'] as $edition ) {
			if ( ! $this->course_visible( (int) $edition['course_id'] ) ) {
				continue;
			}

			$items[] = $this->prepare_edition( $edition );
		}

		return $this->paginated_response( $items, $result );
	}

	/**
	 * Returns a single edition.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$edition = $this->editions->find( (int) $request->get_param( 'id' ) );

		if ( null === $edition || ! $this->edition_visible( $edition ) ) {
			return $this->not_found( __( 'La edicion no existe.', 'aula-virtual' ) );
		}

		return rest_ensure_response( $this->prepare_edition( $edition ) );
	}

	/**
	 * Returns the students enrolled in an edition.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_students( $request ) {
		$edition_id = (int) $request->get_param( 'id' );
		$edition    = $this->editions->find( $edition_id );

		if ( null === $edition ) {
			return $this->not_found( __( 'La edicion no existe.', 'aula-virtual' ) );
		}

		$where  = array( 'edition_id' => $edition_id );
		$status = (string) $request->get_param( 'status' );

		if ( '' !== $status ) {
			$where['status'] = $status;
		}

		$result = $this->enrollments->paginate(
			array(
				'where'    => $where,
				'order_by' => 'enrolled_at',
				'order'    => 'DESC',
			),
			(int) $request->get_param( 'page' ),
			(int) $request->get_param( 'per_page' )
		);

		$items = array_map(
			fn( array $enrollment ): array => $this->prepare_student( $enrollment ),
			$result['items']
		);

		return $this->paginated_response( $items, $result );
	}

	/**
	 * Builds the public representation of an edition.
	 *
	 * @param array<string, mixed> $edition Edition row.
	 * @return array<string, mixed>
	 */
	private function prepare_edition( array $edition ): array {
		$edition_id  = (int) $edition['id'];
		$course_id   = (int) $edition['course_id'];
		$capacity    = max( 0, (int) $edition['capacity'] );
		$seats_taken = $this->enrollments->count_seats_taken( $edition_id );
		$product_id  = (int) ( $edition['product_id'] ?? 0 );
		$checkout    = ProductLink::buy_url( $edition );
		$status      = (string) $edition['status'];
		$modality    = (string) $edition['modality'];

		return array(
			'id'                  => $edition_id,
			'code'                => (string) $edition['code'],
			'name'                => (string) $edition['name'],
			'course_id'           => $course_id,
			'course_title'        => (string) get_the_title( $course_id ),
			'status'              => $status,
			'status_label'        => EditionStatus::label( $status ),
			'accepts_enrollments' => EditionStatus::accepts_enrollments( $status ),
			'modality'            => $modality,
			'modality_label'      => EditionService::modalities()[ $modality ] ?? $modality,
			'start_date'          => $this->nullable_string( $edition['start_date'] ?? null ),
			'end_date'            => $this->nullable_string( $edition['end_date'] ?? null ),
			'timezone'            => (string) ( $edition['timezone'] ?? '' ),
			'schedule_days'       => (string) ( $edition['schedule_days'] ?? '' ),
			'schedule_time'       => (string) ( $edition['schedule_time'] ?? '' ),
			'price_display'       => (string) ( $edition['price_display'] ?? '' ),
			'capacity'            => $capacity,
			'seats_taken'         => $seats_taken,
			'seats_available'     => 0 === $capacity ? null : max( 0, $capacity - $seats_taken ),
			'product_id'          => $product_id,
			'checkout_url'        => '' === $checkout ? null : $checkout,
			'landing_url'         => (string) get_permalink( $course_id ),
		);
	}

	/**
	 * Builds the representation of an enrolled student.
	 *
	 * @param array<string, mixed> $enrollment Enrollment row.
	 * @return array<string, mixed>
	 */
	private function prepare_student( array $enrollment ): array {
		$user_id = (int) $enrollment['user_id'];
		$user    = get_userdata( $user_id );
		$status  = (string) $enrollment['status'];

		return array(
			'enrollment_id'       => (int) $enrollment['id'],
			'user_id'             => $user_id,
			'display_name'        => $user ? (string) $user->display_name : '',
			'email'               => $user ? (string) $user->user_email : '',
			'status'              => $status,
			'status_label'        => EnrollmentStatus::label( $status ),
			'source'              => (string) $enrollment['source'],
			'progress_percentage' => (float) ( $enrollment['progress_percentage'] ?? 0 ),
			'enrolled_at'         => $this->nullable_string( $enrollment['enrolled_at'] ?? null ),
		);
	}

	/**
	 * Whether the current client may see drafts and archived editions.
	 *
	 * @return bool
	 */
	private function can_see_hidden(): bool {
		return $this->access->can( Capabilities::MANAGE_EDITIONS ) || $this->access->can( Capabilities::VIEW_STUDENTS );
	}

	/**
	 * States shown to anonymous clients.
	 *
	 * @return array<int, string>
	 */
	private function public_statuses(): array {
		return array( EditionStatus::UPCOMING, EditionStatus::OPEN, EditionStatus::RUNNING, EditionStatus::FINISHED );
	}

	/**
	 * Whether a state is visible to anonymous clients.
	 *
	 * @param string $status Edition state.
	 * @return bool
	 */
	private function is_public_status( string $status ): bool {
		return in_array( $status, $this->public_statuses(), true );
	}

	/**
	 * Whether the course of an edition is reachable by the current client.
	 *
	 * @param int $course_id Course post id.
	 * @return bool
	 */
	private function course_visible( int $course_id ): bool {
		$course = $this->courses->find( $course_id );

		if ( null === $course ) {
			return false;
		}

		return 'publish' === $course->post_status || $this->access->can_edit_course( $course_id );
	}

	/**
	 * Whether an edition may be shown to the current client.
	 *
	 * @param array<string, mixed> $edition Edition row.
	 * @return bool
	 */
	private function edition_visible( array $edition ): bool {
		if ( ! $this->course_visible( (int) $edition['course_id'] ) ) {
			return false;
		}

		return $this->can_see_hidden() || $this->is_public_status( (string) $edition['status'] );
	}

	/**
	 * Normalises an optional database string.
	 *
	 * @param mixed $value Raw value.
	 * @return string|null
	 */
	private function nullable_string( mixed $value ): ?string {
		if ( null === $value || '' === $value || '0000-00-00 00:00:00' === $value ) {
			return null;
		}

		return (string) $value;
	}

	/**
	 * Argument definition of the edition id.
	 *
	 * @return array<string, mixed>
	 */
	private function id_arg(): array {
		return array(
			'description'       => __( 'Identificador de la edicion.', 'aula-virtual' ),
			'type'              => 'integer',
			'required'          => true,
			'minimum'           => 1,
			'sanitize_callback' => 'absint',
		);
	}

	/**
	 * Parameters of the student list.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function students_params(): array {
		$params = parent::get_collection_params();

		unset( $params['search'] );

		$params['status'] = array(
			'description'       => __( 'Filtra por estado de matricula.', 'aula-virtual' ),
			'type'              => 'string',
			'default'           => '',
			'enum'              => array_merge( array( '' ), EnrollmentStatus::all() ),
			'sanitize_callback' => 'sanitize_key',
		);

		return $params;
	}

	/**
	 * Collection parameters of the edition list.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_collection_params() {
		$params = parent::get_collection_params();

		unset( $params['search'] );

		$params['course'] = array(
			'description'       => __( 'Filtra por identificador de curso.', 'aula-virtual' ),
			'type'              => 'integer',
			'default'           => 0,
			'minimum'           => 0,
			'sanitize_callback' => 'absint',
		);

		$params['status'] = array(
			'description'       => __( 'Filtra por estado de la edicion.', 'aula-virtual' ),
			'type'              => 'string',
			'default'           => '',
			'enum'              => array_merge( array( '' ), EditionStatus::all() ),
			'sanitize_callback' => 'sanitize_key',
		);

		$params['open_only'] = array(
			'description'       => __( 'Solo ediciones que admiten matricula (proxima, abierta o en curso).', 'aula-virtual' ),
			'type'              => 'boolean',
			'default'           => false,
			'sanitize_callback' => 'rest_sanitize_boolean',
		);

		return $params;
	}

	/**
	 * Returns the edition schema.
	 *
	 * @return array<string, mixed>
	 */
	public function get_item_schema() {
		if ( null !== $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'av_edition',
			'type'       => 'object',
			'properties' => array(
				'id'                  => array(
					'type'     => 'integer',
					'readonly' => true,
				),
				'code'                => array( 'type' => 'string' ),
				'name'                => array( 'type' => 'string' ),
				'course_id'           => array( 'type' => 'integer' ),
				'course_title'        => array( 'type' => 'string' ),
				'status'              => array(
					'type' => 'string',
					'enum' => EditionStatus::all(),
				),
				'status_label'        => array( 'type' => 'string' ),
				'accepts_enrollments' => array( 'type' => 'boolean' ),
				'modality'            => array(
					'type' => 'string',
					'enum' => array( EditionService::MODALITY_RECORDED, EditionService::MODALITY_LIVE, EditionService::MODALITY_HYBRID ),
				),
				'modality_label'      => array( 'type' => 'string' ),
				'start_date'          => array( 'type' => array( 'string', 'null' ) ),
				'end_date'            => array( 'type' => array( 'string', 'null' ) ),
				'timezone'            => array( 'type' => 'string' ),
				'schedule_days'       => array( 'type' => 'string' ),
				'schedule_time'       => array( 'type' => 'string' ),
				'price_display'       => array( 'type' => 'string' ),
				'capacity'            => array( 'type' => 'integer' ),
				'seats_taken'         => array( 'type' => 'integer' ),
				'seats_available'     => array( 'type' => array( 'integer', 'null' ) ),
				'product_id'          => array( 'type' => 'integer' ),
				'checkout_url'        => array( 'type' => array( 'string', 'null' ) ),
				'landing_url'         => array( 'type' => 'string' ),
			),
		);

		return $this->add_additional_fields_schema( $this->schema );
	}

	/**
	 * Returns the public schema of a student row.
	 *
	 * @return array<string, mixed>
	 */
	public function get_public_student_schema(): array {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'av_edition_student',
			'type'       => 'object',
			'properties' => array(
				'enrollment_id'       => array( 'type' => 'integer' ),
				'user_id'             => array( 'type' => 'integer' ),
				'display_name'        => array( 'type' => 'string' ),
				'email'               => array( 'type' => 'string' ),
				'status'              => array(
					'type' => 'string',
					'enum' => EnrollmentStatus::all(),
				),
				'status_label'        => array( 'type' => 'string' ),
				'source'              => array(
					'type' => 'string',
					'enum' => EnrollmentStatus::sources(),
				),
				'progress_percentage' => array( 'type' => 'number' ),
				'enrolled_at'         => array( 'type' => array( 'string', 'null' ) ),
			),
		);
	}
}
