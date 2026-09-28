<?php
/**
 * Registrations REST controller.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\REST;

use SIQA\AulaVirtual\Editions\EditionRepository;
use SIQA\AulaVirtual\Editions\EditionStatus;
use SIQA\AulaVirtual\Enrollments\EnrollmentLinkRepository;
use SIQA\AulaVirtual\Enrollments\RegistrationRequestRepository;
use SIQA\AulaVirtual\Enrollments\RegistrationService;
use SIQA\AulaVirtual\Permissions\Capabilities;
use SIQA\AulaVirtual\Security\Sanitizer;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Receives registration requests pushed by an external site.
 *
 * The request goes through the very same service the public form uses, so
 * duplicates, capacity, edition state, approval and emails behave exactly
 * as if the student had filled the campus form. The route is reachable
 * with the shared integration key (header `X-AV-Key`) or by a logged-in
 * user allowed to enroll students, and it is rate limited per address.
 */
final class RegistrationsController extends AbstractController {

	/**
	 * Label of the link created on demand for API registrations.
	 */
	public const LINK_LABEL = 'api';

	/**
	 * Registrations accepted per address and hour.
	 */
	public const RATE_LIMIT = 20;

	/**
	 * Transient prefix of the rate limit counters.
	 */
	private const RATE_PREFIX = 'av_rest_reg_';

	/**
	 * Registration flow.
	 *
	 * @var RegistrationService
	 */
	private RegistrationService $registration;

	/**
	 * Link persistence.
	 *
	 * @var EnrollmentLinkRepository
	 */
	private EnrollmentLinkRepository $links;

	/**
	 * Request persistence.
	 *
	 * @var RegistrationRequestRepository
	 */
	private RegistrationRequestRepository $requests;

	/**
	 * Edition persistence.
	 *
	 * @var EditionRepository
	 */
	private EditionRepository $editions;

	/**
	 * Constructor.
	 *
	 * @param RegistrationService           $registration Registration flow.
	 * @param EnrollmentLinkRepository      $links        Link persistence.
	 * @param RegistrationRequestRepository $requests     Request persistence.
	 * @param EditionRepository             $editions     Edition persistence.
	 */
	public function __construct(
		RegistrationService $registration,
		EnrollmentLinkRepository $links,
		RegistrationRequestRepository $requests,
		EditionRepository $editions
	) {
		parent::__construct();

		$this->rest_base    = 'registrations';
		$this->registration = $registration;
		$this->links        = $links;
		$this->requests     = $requests;
		$this->editions     = $editions;
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
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args'                => $this->create_args(),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Permission callback: integration key or a user who can enroll.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return true|WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		if ( $this->integration_key_valid( $request ) ) {
			return true;
		}

		if ( is_user_logged_in() && current_user_can( Capabilities::ENROLL_STUDENTS ) ) {
			return true;
		}

		return $this->forbidden( __( 'Se requiere la clave de integracion o un usuario autorizado.', 'aula-virtual' ) );
	}

	/**
	 * Records a registration request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$edition_id = (int) $request->get_param( 'edition_id' );
		$edition    = $this->editions->find( $edition_id );

		if ( null === $edition ) {
			return $this->not_found( __( 'La edicion no existe.', 'aula-virtual' ) );
		}

		if ( ! EditionStatus::accepts_enrollments( (string) $edition['status'] ) ) {
			return new WP_Error(
				'av_edition_closed',
				__( 'Esta edicion ya no admite inscripciones.', 'aula-virtual' ),
				array( 'status' => 410 )
			);
		}

		$email = Sanitizer::email( $request->get_param( 'email' ) );

		if ( '' === $email ) {
			return $this->invalid( __( 'Indica un correo electronico valido.', 'aula-virtual' ) );
		}

		$exempt = is_user_logged_in() && current_user_can( Capabilities::ENROLL_STUDENTS );

		if ( ! $exempt && ! $this->within_rate_limit() ) {
			return $this->too_many_requests();
		}

		$link = $this->usable_link_for( $edition_id );

		if ( $link instanceof WP_Error ) {
			return $link;
		}

		$input = array(
			'first_name' => Sanitizer::text( $request->get_param( 'first_name' ) ),
			'last_name'  => Sanitizer::text( $request->get_param( 'last_name' ) ),
			'email'      => $email,
			'phone'      => Sanitizer::text( $request->get_param( 'phone' ) ),
			'document'   => Sanitizer::text( $request->get_param( 'document' ) ),
		);

		$notes = Sanitizer::textarea( $request->get_param( 'notes' ) );

		if ( '' !== $notes ) {
			// Stored with the request as a custom field, like any extra form input.
			$input['notes'] = $notes;
		}

		$input['origin'] = 'api';

		$result = $this->registration->submit( (string) $link['token'], $input );

		if ( $result instanceof WP_Error ) {
			return $result;
		}

		$stored = $this->requests->find( (int) $result );
		$status = null === $stored ? RegistrationRequestRepository::STATUS_PENDING : (string) $stored['status'];

		$response = rest_ensure_response(
			array(
				'request_id' => (int) $result,
				'edition_id' => $edition_id,
				'status'     => $status,
				'message'    => $this->message_for( $status ),
			)
		);

		$response->set_status( 201 );

		return $response;
	}

	/**
	 * Finds an active link of the edition, creating one when none is usable.
	 *
	 * @param int $edition_id Edition id.
	 * @return array<string, mixed>|WP_Error Usable link row.
	 */
	private function usable_link_for( int $edition_id ) {
		$preferred = null;

		foreach ( $this->links->for_edition( $edition_id ) as $candidate ) {
			$link = $this->registration->usable_link( (string) $candidate['token'] );

			if ( $link instanceof WP_Error ) {
				continue;
			}

			if ( self::LINK_LABEL === (string) $link['label'] ) {
				return $link;
			}

			if ( null === $preferred ) {
				$preferred = $link;
			}
		}

		if ( null !== $preferred ) {
			return $preferred;
		}

		$args = array( 'label' => self::LINK_LABEL );

		/**
		 * Filters the arguments of the link created for API registrations.
		 *
		 * By default the link requires approval, like a link created from the
		 * admin. Return `requires_approval => false` to enroll on the spot.
		 *
		 * @param array<string, mixed> $args       Link arguments.
		 * @param int                  $edition_id Edition id.
		 */
		$args = (array) apply_filters( 'aula_virtual/rest_registration_link_args', $args, $edition_id );

		$link_id = $this->registration->create_link( $edition_id, $args );

		if ( $link_id instanceof WP_Error ) {
			return $link_id;
		}

		$created = $this->links->find( (int) $link_id );

		if ( null === $created ) {
			return new WP_Error(
				'av_link_not_created',
				__( 'No se pudo preparar el enlace de inscripcion.', 'aula-virtual' ),
				array( 'status' => 500 )
			);
		}

		$link = $this->registration->usable_link( (string) $created['token'] );

		return $link;
	}

	/**
	 * Counts the current request against the hourly limit of its address.
	 *
	 * @return bool True while the address is under the limit.
	 */
	private function within_rate_limit(): bool {
		$key   = self::RATE_PREFIX . md5( $this->client_ip() );
		$count = (int) get_transient( $key );

		if ( $count >= self::RATE_LIMIT ) {
			return false;
		}

		set_transient( $key, $count + 1, HOUR_IN_SECONDS );

		return true;
	}

	/**
	 * Human message for the resulting request state.
	 *
	 * @param string $status Request state.
	 * @return string
	 */
	private function message_for( string $status ): string {
		switch ( $status ) {
			case RegistrationRequestRepository::STATUS_ENROLLED:
				return __( 'Inscripcion completada. El alumno recibira la bienvenida por correo.', 'aula-virtual' );
			case RegistrationRequestRepository::STATUS_APPROVED:
				return __( 'Solicitud aprobada. El alumno recibira por correo el enlace de pago.', 'aula-virtual' );
			case RegistrationRequestRepository::STATUS_REJECTED:
				return __( 'La solicitud fue rechazada.', 'aula-virtual' );
			default:
				return __( 'Solicitud recibida. Queda pendiente de revision.', 'aula-virtual' );
		}
	}

	/**
	 * Argument definitions of the create route.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function create_args(): array {
		return array(
			'edition_id' => array(
				'description'       => __( 'Identificador de la edicion.', 'aula-virtual' ),
				'type'              => 'integer',
				'required'          => true,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
			),
			'first_name' => array(
				'description'       => __( 'Nombre del alumno.', 'aula-virtual' ),
				'type'              => 'string',
				'default'           => '',
				'maxLength'         => 100,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'last_name'  => array(
				'description'       => __( 'Apellido del alumno.', 'aula-virtual' ),
				'type'              => 'string',
				'default'           => '',
				'maxLength'         => 100,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'email'      => array(
				'description'       => __( 'Correo electronico del alumno.', 'aula-virtual' ),
				'type'              => 'string',
				'format'            => 'email',
				'required'          => true,
				'sanitize_callback' => 'sanitize_email',
				'validate_callback' => static fn( $value ): bool => is_string( $value ) && false !== is_email( $value ),
			),
			'phone'      => array(
				'description'       => __( 'Telefono de contacto.', 'aula-virtual' ),
				'type'              => 'string',
				'default'           => '',
				'maxLength'         => 40,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'document'   => array(
				'description'       => __( 'Documento de identidad.', 'aula-virtual' ),
				'type'              => 'string',
				'default'           => '',
				'maxLength'         => 40,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'notes'      => array(
				'description'       => __( 'Comentario libre del alumno.', 'aula-virtual' ),
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
			'title'      => 'av_registration',
			'type'       => 'object',
			'properties' => array(
				'request_id' => array(
					'type'     => 'integer',
					'readonly' => true,
				),
				'edition_id' => array( 'type' => 'integer' ),
				'status'     => array(
					'type' => 'string',
					'enum' => RegistrationRequestRepository::statuses(),
				),
				'message'    => array( 'type' => 'string' ),
			),
		);

		return $this->add_additional_fields_schema( $this->schema );
	}
}
