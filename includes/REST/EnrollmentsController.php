<?php
/**
 * Enrollments REST controller.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\REST;

use SIQA\AulaVirtual\Editions\EditionRepository;
use SIQA\AulaVirtual\Editions\EditionStatus;
use SIQA\AulaVirtual\Enrollments\EnrollmentRepository;
use SIQA\AulaVirtual\Enrollments\EnrollmentService;
use SIQA\AulaVirtual\Enrollments\EnrollmentStatus;
use SIQA\AulaVirtual\Permissions\AccessControl;
use SIQA\AulaVirtual\Permissions\Capabilities;
use SIQA\AulaVirtual\Permissions\Roles;
use SIQA\AulaVirtual\Security\Sanitizer;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lets an authorised script manage enrollments.
 *
 * Every route requires the enroll-students capability, normally exercised
 * through an Application Password over HTTPS. Accounts created here get a
 * random password that is never returned: the welcome email carries the
 * link to set one.
 */
final class EnrollmentsController extends AbstractController {

	/**
	 * Enrollment persistence.
	 *
	 * @var EnrollmentRepository
	 */
	private EnrollmentRepository $enrollments;

	/**
	 * Enrollment rules.
	 *
	 * @var EnrollmentService
	 */
	private EnrollmentService $service;

	/**
	 * Edition persistence.
	 *
	 * @var EditionRepository
	 */
	private EditionRepository $editions;

	/**
	 * Permission checks.
	 *
	 * @var AccessControl
	 */
	private AccessControl $access;

	/**
	 * Constructor.
	 *
	 * @param EnrollmentRepository $enrollments Enrollment persistence.
	 * @param EnrollmentService    $service     Enrollment rules.
	 * @param EditionRepository    $editions    Edition persistence.
	 * @param AccessControl        $access      Permission checks.
	 */
	public function __construct(
		EnrollmentRepository $enrollments,
		EnrollmentService $service,
		EditionRepository $editions,
		AccessControl $access
	) {
		parent::__construct();

		$this->rest_base   = 'enrollments';
		$this->enrollments = $enrollments;
		$this->service     = $service;
		$this->editions    = $editions;
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
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'email' => $this->email_arg( __( 'Correo del alumno cuyas matrículas se consultan.', 'aula-virtual' ) ),
					),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => $this->create_args(),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => 'PATCH',
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'id'     => array(
							'description'       => __( 'Identificador de la matrícula.', 'aula-virtual' ),
							'type'              => 'integer',
							'required'          => true,
							'minimum'           => 1,
							'sanitize_callback' => 'absint',
						),
						'status' => array(
							'description'       => __( 'Nuevo estado de la matrícula.', 'aula-virtual' ),
							'type'              => 'string',
							'required'          => true,
							'enum'              => EnrollmentStatus::all(),
							'sanitize_callback' => 'sanitize_key',
						),
					),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Permission callback shared by every route.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return true|WP_Error
	 */
	public function permissions_check( $request ) {
		return $this->require_capability( Capabilities::ENROLL_STUDENTS );
	}

	/**
	 * Returns the enrollments of the student with the given email.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$email = Sanitizer::email( $request->get_param( 'email' ) );

		if ( '' === $email ) {
			return $this->invalid( __( 'Indica un correo electrónico válido.', 'aula-virtual' ) );
		}

		$user = get_user_by( 'email', $email );
		$rows = false === $user ? array() : $this->enrollments->all(
			array(
				'where'    => array( 'user_id' => (int) $user->ID ),
				'order_by' => 'enrolled_at',
				'order'    => 'DESC',
				'limit'    => 200,
			)
		);

		// Solo matriculas de cursos propios; la misma respuesta 404 exista o no
		// la cuenta, para no revelar quien tiene usuario en el sitio.
		$rows = array_values( array_filter( $rows, fn( array $e ): bool => $this->access->can_manage_editions( (int) $e['course_id'] ) ) );

		if ( false === $user || ( array() === $rows && ! $this->access->can_manage() ) ) {
			return $this->not_found( __( 'No hay matrículas visibles para ese correo.', 'aula-virtual' ) );
		}

		return rest_ensure_response(
			array(
				'user_id'      => (int) $user->ID,
				'email'        => (string) $user->user_email,
				'display_name' => (string) $user->display_name,
				'enrollments'  => array_map(
					fn( array $enrollment ): array => $this->prepare_enrollment( $enrollment ),
					$rows
				),
			)
		);
	}

	/**
	 * Enrolls a student, creating the account when needed.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$edition_id = (int) $request->get_param( 'edition_id' );
		$edition    = $this->editions->find( $edition_id );

		if ( null === $edition ) {
			return $this->not_found( __( 'La edición no existe.', 'aula-virtual' ) );
		}

		if ( ! $this->access->can_manage_editions( (int) $edition['course_id'] ) ) {
			return $this->forbidden();
		}

		$email = Sanitizer::email( $request->get_param( 'email' ) );

		if ( '' === $email ) {
			return $this->invalid( __( 'Indica un correo electrónico válido.', 'aula-virtual' ) );
		}

		$status = Sanitizer::enum( $request->get_param( 'status' ), EnrollmentStatus::all(), EnrollmentStatus::ACTIVE );

		$account = $this->find_or_create_user(
			array(
				'email'      => $email,
				'first_name' => Sanitizer::text( $request->get_param( 'first_name' ) ),
				'last_name'  => Sanitizer::text( $request->get_param( 'last_name' ) ),
				'phone'      => Sanitizer::text( $request->get_param( 'phone' ) ),
				'document'   => Sanitizer::text( $request->get_param( 'document' ) ),
			)
		);

		if ( $account instanceof WP_Error ) {
			return $account;
		}

		$send_welcome  = Sanitizer::bool( $request->get_param( 'send_welcome' ) );
		$enrollment_id = $this->service->enroll(
			$account['id'],
			$edition_id,
			EnrollmentStatus::SOURCE_MANUAL,
			array(
				'status' => $status,
				'notes'  => Sanitizer::textarea( $request->get_param( 'notes' ) ),
				'silent' => ! $send_welcome,
			)
		);

		if ( $enrollment_id instanceof WP_Error ) {
			$enrollment_id->add_data(
				array_merge( (array) $enrollment_id->get_error_data(), array( 'user_id' => $account['id'] ) )
			);

			return $enrollment_id;
		}

		$stored = $this->enrollments->find( (int) $enrollment_id );

		$response = rest_ensure_response(
			array(
				'enrollment_id' => (int) $enrollment_id,
				'user_id'       => $account['id'],
				'created_user'  => $account['created'],
				'status'        => null === $stored ? $status : (string) $stored['status'],
				'edition_id'    => $edition_id,
			)
		);

		$response->set_status( 201 );

		return $response;
	}

	/**
	 * Changes the state of an enrollment.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$enrollment_id = (int) $request->get_param( 'id' );
		$status        = Sanitizer::key( $request->get_param( 'status' ) );
		$current       = $this->enrollments->find( $enrollment_id );

		if ( null === $current ) {
			return $this->not_found( __( 'La matrícula no existe.', 'aula-virtual' ) );
		}

		if ( ! $this->access->can_manage_editions( (int) $current['course_id'] ) ) {
			return $this->forbidden();
		}

		$result = $this->service->change_status( $enrollment_id, $status );

		if ( $result instanceof WP_Error ) {
			return $result;
		}

		$stored = $this->enrollments->find( $enrollment_id );

		return rest_ensure_response(
			null === $stored
				? array(
					'enrollment_id' => $enrollment_id,
					'status'        => $status,
				)
				: $this->prepare_enrollment( $stored )
		);
	}

	/**
	 * Builds the representation of an enrollment.
	 *
	 * @param array<string, mixed> $enrollment Enrollment row.
	 * @return array<string, mixed>
	 */
	private function prepare_enrollment( array $enrollment ): array {
		$edition = $this->editions->find( (int) $enrollment['edition_id'] );
		$status  = (string) $enrollment['status'];

		return array(
			'enrollment_id'        => (int) $enrollment['id'],
			'user_id'              => (int) $enrollment['user_id'],
			'course_id'            => (int) $enrollment['course_id'],
			'edition_id'           => (int) $enrollment['edition_id'],
			'edition_code'         => null === $edition ? '' : (string) $edition['code'],
			'edition_name'         => null === $edition ? '' : (string) $edition['name'],
			'edition_status'       => null === $edition ? '' : (string) $edition['status'],
			'edition_status_label' => null === $edition ? '' : EditionStatus::label( (string) $edition['status'] ),
			'status'               => $status,
			'status_label'         => EnrollmentStatus::label( $status ),
			'source'               => (string) $enrollment['source'],
			'progress_percentage'  => (float) ( $enrollment['progress_percentage'] ?? 0 ),
			'enrolled_at'          => $this->nullable_string( $enrollment['enrolled_at'] ?? null ),
			'expires_at'           => $this->nullable_string( $enrollment['expires_at'] ?? null ),
		);
	}

	/**
	 * Finds or creates the account of a student.
	 *
	 * Mirrors the Excel import: the login derives from the email, the password
	 * is random and never leaves the server, and the role is student.
	 *
	 * @param array{email: string, first_name: string, last_name: string, phone: string, document: string} $row Student data.
	 * @return array{id: int, created: bool}|WP_Error
	 */
	private function find_or_create_user( array $row ) {
		$user = get_user_by( 'email', $row['email'] );

		if ( false !== $user ) {
			return array(
				'id'      => (int) $user->ID,
				'created' => false,
			);
		}

		$local = strstr( $row['email'], '@', true );
		$local = false === $local || '' === $local ? $row['email'] : $local;
		$base  = sanitize_user( $local, true );
		$base  = '' === $base ? 'alumno' : $base;
		$login = $base;
		$i     = 2;

		while ( username_exists( $login ) ) {
			$login = $base . $i;
			++$i;
		}

		$full_name = trim( $row['first_name'] . ' ' . $row['last_name'] );

		$user_id = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_email'   => $row['email'],
				'user_pass'    => wp_generate_password( 32, true, true ),
				'first_name'   => $row['first_name'],
				'last_name'    => $row['last_name'],
				'display_name' => '' === $full_name ? $local : $full_name,
				'role'         => Roles::STUDENT,
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		if ( '' !== $row['phone'] ) {
			update_user_meta( (int) $user_id, 'av_phone', $row['phone'] );
		}

		if ( '' !== $row['document'] ) {
			update_user_meta( (int) $user_id, 'av_document', $row['document'] );
		}

		return array(
			'id'      => (int) $user_id,
			'created' => true,
		);
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
	 * Argument definition of an email parameter.
	 *
	 * @param string $description Parameter description.
	 * @return array<string, mixed>
	 */
	private function email_arg( string $description ): array {
		return array(
			'description'       => $description,
			'type'              => 'string',
			'format'            => 'email',
			'required'          => true,
			'sanitize_callback' => 'sanitize_email',
			'validate_callback' => static fn( $value ): bool => is_string( $value ) && false !== is_email( $value ),
		);
	}

	/**
	 * Argument definitions of the create route.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function create_args(): array {
		return array(
			'edition_id'   => array(
				'description'       => __( 'Identificador de la edición.', 'aula-virtual' ),
				'type'              => 'integer',
				'required'          => true,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
			),
			'email'        => $this->email_arg( __( 'Correo electrónico del alumno.', 'aula-virtual' ) ),
			'first_name'   => array(
				'description'       => __( 'Nombre del alumno.', 'aula-virtual' ),
				'type'              => 'string',
				'default'           => '',
				'maxLength'         => 100,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'last_name'    => array(
				'description'       => __( 'Apellido del alumno.', 'aula-virtual' ),
				'type'              => 'string',
				'default'           => '',
				'maxLength'         => 100,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'phone'        => array(
				'description'       => __( 'Teléfono de contacto.', 'aula-virtual' ),
				'type'              => 'string',
				'default'           => '',
				'maxLength'         => 40,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'document'     => array(
				'description'       => __( 'Documento de identidad.', 'aula-virtual' ),
				'type'              => 'string',
				'default'           => '',
				'maxLength'         => 40,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'status'       => array(
				'description'       => __( 'Estado inicial de la matrícula.', 'aula-virtual' ),
				'type'              => 'string',
				'default'           => EnrollmentStatus::ACTIVE,
				'enum'              => EnrollmentStatus::all(),
				'sanitize_callback' => 'sanitize_key',
			),
			'send_welcome' => array(
				'description'       => __( 'Enviar el correo de bienvenida con el enlace para crear la contraseña.', 'aula-virtual' ),
				'type'              => 'boolean',
				'default'           => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
			),
			'notes'        => array(
				'description'       => __( 'Notas internas de la matrícula.', 'aula-virtual' ),
				'type'              => 'string',
				'default'           => '',
				'maxLength'         => 1000,
				'sanitize_callback' => 'sanitize_textarea_field',
			),
		);
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
			'title'      => 'av_enrollment',
			'type'       => 'object',
			'properties' => array(
				'enrollment_id'        => array(
					'type'     => 'integer',
					'readonly' => true,
				),
				'user_id'              => array( 'type' => 'integer' ),
				'course_id'            => array( 'type' => 'integer' ),
				'edition_id'           => array( 'type' => 'integer' ),
				'edition_code'         => array( 'type' => 'string' ),
				'edition_name'         => array( 'type' => 'string' ),
				'edition_status'       => array( 'type' => 'string' ),
				'edition_status_label' => array( 'type' => 'string' ),
				'status'               => array(
					'type' => 'string',
					'enum' => EnrollmentStatus::all(),
				),
				'status_label'         => array( 'type' => 'string' ),
				'source'               => array(
					'type' => 'string',
					'enum' => EnrollmentStatus::sources(),
				),
				'progress_percentage'  => array( 'type' => 'number' ),
				'enrolled_at'          => array( 'type' => array( 'string', 'null' ) ),
				'expires_at'           => array( 'type' => array( 'string', 'null' ) ),
			),
		);

		return $this->add_additional_fields_schema( $this->schema );
	}
}
