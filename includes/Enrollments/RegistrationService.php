<?php
/**
 * Registration links and requests.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Enrollments;

use SIQA\AulaVirtual\Core\AuditLog;
use SIQA\AulaVirtual\Core\Events\EventBus;
use SIQA\AulaVirtual\Core\Events\Events;
use SIQA\AulaVirtual\Editions\EditionRepository;
use SIQA\AulaVirtual\Editions\EditionStatus;
use SIQA\AulaVirtual\Permissions\Roles;
use SIQA\AulaVirtual\Security\Sanitizer;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The public registration flow: link, request, approval, enrollment.
 *
 * Approval and enrollment are two different moments on purpose. A paid
 * edition approves the request and waits for the payment to enroll; a free
 * edition enrolls at approval. In both cases the WordPress account is created
 * without a password: the student sets their own through a secure link.
 */
final class RegistrationService {

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
	 * Enrollment rules.
	 *
	 * @var EnrollmentService
	 */
	private EnrollmentService $enrollments;

	/**
	 * Domain events.
	 *
	 * @var EventBus
	 */
	private EventBus $events;

	/**
	 * Audit trail.
	 *
	 * @var AuditLog
	 */
	private AuditLog $audit;

	/**
	 * Constructor.
	 *
	 * @param EnrollmentLinkRepository      $links       Link persistence.
	 * @param RegistrationRequestRepository $requests    Request persistence.
	 * @param EditionRepository             $editions    Edition persistence.
	 * @param EnrollmentService             $enrollments Enrollment rules.
	 * @param EventBus                      $events      Domain events.
	 * @param AuditLog                      $audit       Audit trail.
	 */
	public function __construct(
		EnrollmentLinkRepository $links,
		RegistrationRequestRepository $requests,
		EditionRepository $editions,
		EnrollmentService $enrollments,
		EventBus $events,
		AuditLog $audit
	) {
		$this->links       = $links;
		$this->requests    = $requests;
		$this->editions    = $editions;
		$this->enrollments = $enrollments;
		$this->events      = $events;
		$this->audit       = $audit;
	}

	/**
	 * Creates a registration link for an edition.
	 *
	 * @param int                  $edition_id Edition id.
	 * @param array<string, mixed> $args       Optional label, max_uses, expires_at, requires_approval.
	 * @return int|WP_Error Link id.
	 */
	public function create_link( int $edition_id, array $args = array() ) {
		$edition = $this->editions->find( $edition_id );

		if ( null === $edition ) {
			return new WP_Error( 'av_edition_not_found', __( 'La edicion no existe.', 'aula-virtual' ), array( 'status' => 404 ) );
		}

		$token = $this->unique_token();

		$link_id = $this->links->insert(
			array(
				'course_id'         => (int) $edition['course_id'],
				'edition_id'        => $edition_id,
				'token'             => $token,
				'label'             => Sanitizer::text( $args['label'] ?? __( 'Enlace de inscripcion', 'aula-virtual' ) ),
				'status'            => EnrollmentLinkRepository::STATUS_ACTIVE,
				'requires_approval' => isset( $args['requires_approval'] ) && ! Sanitizer::bool( $args['requires_approval'] ) ? 0 : 1,
				'max_uses'          => max( 0, Sanitizer::int( $args['max_uses'] ?? 0 ) ),
				'uses'              => 0,
				'expires_at'        => Sanitizer::datetime( $args['expires_at'] ?? '' ),
				'created_by'        => get_current_user_id(),
				'created_at'        => current_time( 'mysql', true ),
			)
		);

		if ( 0 === $link_id ) {
			return new WP_Error( 'av_link_not_created', __( 'No se pudo crear el enlace.', 'aula-virtual' ) );
		}

		return $link_id;
	}

	/**
	 * Public URL of a link.
	 *
	 * @param string $token Link token.
	 * @return string
	 */
	public static function link_url( string $token ): string {
		return home_url( '/inscripcion/' . rawurlencode( $token ) . '/' );
	}

	/**
	 * Returns a link when it can still be used.
	 *
	 * @param string $token Token from the URL.
	 * @return array<string, mixed>|WP_Error
	 */
	public function usable_link( string $token ) {
		$token = preg_replace( '/[^A-Za-z0-9]/', '', $token );
		$link  = '' === $token ? null : $this->links->find_by_token( (string) $token );

		if ( null === $link || EnrollmentLinkRepository::STATUS_ACTIVE !== $link['status'] ) {
			return new WP_Error( 'av_link_invalid', __( 'Este enlace de inscripcion no es valido.', 'aula-virtual' ), array( 'status' => 404 ) );
		}

		if ( ! empty( $link['expires_at'] ) && current_time( 'mysql', true ) > $link['expires_at'] ) {
			return new WP_Error( 'av_link_expired', __( 'Este enlace de inscripcion ya vencio.', 'aula-virtual' ), array( 'status' => 410 ) );
		}

		if ( (int) $link['max_uses'] > 0 && (int) $link['uses'] >= (int) $link['max_uses'] ) {
			return new WP_Error( 'av_link_exhausted', __( 'Este enlace de inscripcion alcanzo su limite de usos.', 'aula-virtual' ), array( 'status' => 410 ) );
		}

		$edition = $this->editions->find( (int) $link['edition_id'] );

		if ( null === $edition || ! EditionStatus::accepts_enrollments( (string) $edition['status'] ) ) {
			return new WP_Error( 'av_edition_closed', __( 'Esta edicion ya no admite inscripciones.', 'aula-virtual' ), array( 'status' => 410 ) );
		}

		$link['edition'] = $edition;

		return $link;
	}

	/**
	 * Records a request submitted through a link.
	 *
	 * @param string               $token Link token.
	 * @param array<string, mixed> $input Form input.
	 * @return int|WP_Error Request id.
	 */
	public function submit( string $token, array $input ) {
		return $this->do_submit( $token, $input );
	}

	/**
	 * Public message for an error code of the registration flow. Only these
	 * texts reach the form, never text taken from the URL.
	 *
	 * @param string $code Error code.
	 * @return string
	 */
	public static function error_message( string $code ): string {
		$messages = array(
			'av_missing_name'        => __( 'Indica tu nombre y apellido.', 'aula-virtual' ),
			'av_invalid_email'       => __( 'Indica un correo electronico valido.', 'aula-virtual' ),
			'av_link_invalid'        => __( 'Este enlace de inscripcion no es valido.', 'aula-virtual' ),
			'av_link_expired'        => __( 'Este enlace de inscripcion ya vencio.', 'aula-virtual' ),
			'av_link_exhausted'      => __( 'Este enlace de inscripcion alcanzo su limite de usos.', 'aula-virtual' ),
			'av_edition_closed'      => __( 'Esta edicion ya no admite inscripciones.', 'aula-virtual' ),
			'av_edition_full'        => __( 'La edicion alcanzo su cupo maximo.', 'aula-virtual' ),
			'av_rate_limited'        => __( 'Recibimos demasiadas solicitudes. Intentalo de nuevo en una hora.', 'aula-virtual' ),
		);

		return $messages[ $code ] ?? __( 'No se pudo registrar la solicitud. Revisa los datos e intentalo de nuevo.', 'aula-virtual' );
	}

	/**
	 * Validates and stores a submission.
	 *
	 * @param string               $token Link token.
	 * @param array<string, mixed> $input Form input.
	 * @return int|WP_Error Request id (0 when nothing had to be created).
	 */
	private function do_submit( string $token, array $input ) {
		$link = $this->usable_link( $token );

		if ( $link instanceof WP_Error ) {
			return $link;
		}

		$first_name = Sanitizer::text( $input['first_name'] ?? '' );
		$last_name  = Sanitizer::text( $input['last_name'] ?? '' );
		$email      = Sanitizer::email( $input['email'] ?? '' );

		if ( '' === $first_name || '' === $last_name ) {
			return new WP_Error( 'av_missing_name', __( 'Indica tu nombre y apellido.', 'aula-virtual' ), array( 'status' => 400 ) );
		}

		if ( '' === $email ) {
			return new WP_Error( 'av_invalid_email', __( 'Indica un correo electronico valido.', 'aula-virtual' ), array( 'status' => 400 ) );
		}

		$edition_id = (int) $link['edition_id'];
		$existing   = $this->requests->find_open( $email, $edition_id );

		if ( null !== $existing ) {
			return (int) $existing['id'];
		}

		$user = get_user_by( 'email', $email );

		// Ya matriculado: se responde igual que a una inscripcion nueva para no
		// revelar a un tercero quien estudia en la edicion. No se crea nada.
		if ( false !== $user && null !== $this->enrollments_for( $user->ID, $edition_id ) ) {
			return 0;
		}

		$request_id = $this->requests->insert(
			array(
				'course_id'   => (int) $link['course_id'],
				'edition_id'  => $edition_id,
				'link_id'     => (int) $link['id'],
				'first_name'  => $first_name,
				'last_name'   => $last_name,
				'email'       => $email,
				'phone'       => Sanitizer::text( $input['phone'] ?? '' ),
				'document'    => Sanitizer::text( $input['document'] ?? '' ),
				'custom_data' => wp_json_encode( $this->custom_fields( $input ) ),
				'status'      => RegistrationRequestRepository::STATUS_PENDING,
				'user_id'     => false === $user ? 0 : (int) $user->ID,
				'created_at'  => current_time( 'mysql', true ),
			)
		);

		if ( 0 === $request_id ) {
			return new WP_Error( 'av_request_not_created', __( 'No se pudo registrar la solicitud.', 'aula-virtual' ) );
		}

		$this->links->increment_uses( (int) $link['id'] );

		$this->events->dispatch(
			Events::REGISTRATION_REQUEST_CREATED,
			array(
				'request_id' => $request_id,
				'edition_id' => $edition_id,
				'course_id'  => (int) $link['course_id'],
				'email'      => $email,
			)
		);

		if ( 0 === (int) $link['requires_approval'] ) {
			$approved = $this->approve( $request_id );

			if ( $approved instanceof WP_Error ) {
				return $approved;
			}
		}

		return $request_id;
	}

	/**
	 * Approves a request.
	 *
	 * Free edition: the account is created and the student is enrolled at
	 * once. Paid edition: the request waits for the payment; the approval
	 * email carries the payment link.
	 *
	 * @param int $request_id Request id.
	 * @return true|WP_Error
	 */
	public function approve( int $request_id ) {
		$request = $this->requests->find( $request_id );

		if ( null === $request ) {
			return new WP_Error( 'av_request_not_found', __( 'La solicitud no existe.', 'aula-virtual' ), array( 'status' => 404 ) );
		}

		if ( RegistrationRequestRepository::STATUS_PENDING !== $request['status'] ) {
			return new WP_Error( 'av_request_not_pending', __( 'La solicitud ya fue procesada.', 'aula-virtual' ), array( 'status' => 409 ) );
		}

		$edition = $this->editions->find( (int) $request['edition_id'] );

		if ( null === $edition ) {
			return new WP_Error( 'av_edition_not_found', __( 'La edicion no existe.', 'aula-virtual' ), array( 'status' => 404 ) );
		}

		$now = current_time( 'mysql', true );

		$this->requests->update(
			$request_id,
			array(
				'status'      => RegistrationRequestRepository::STATUS_APPROVED,
				'approved_at' => $now,
				'approved_by' => get_current_user_id(),
			)
		);

		$this->audit->record( AuditLog::REQUEST_APPROVED, 'registration_request', $request_id, array( 'edition_id' => (int) $edition['id'] ) );

		$is_paid = (int) $edition['product_id'] > 0;

		if ( $is_paid ) {
			$this->events->dispatch(
				Events::REGISTRATION_REQUEST_APPROVED,
				array(
					'request_id' => $request_id,
					'edition_id' => (int) $edition['id'],
					'course_id'  => (int) $edition['course_id'],
					'email'      => (string) $request['email'],
				)
			);

			return true;
		}

		return $this->enroll_request( $request_id, EnrollmentStatus::SOURCE_PRIVATE_LINK );
	}

	/**
	 * Enrolls the student of an approved request.
	 *
	 * Used after the payment is confirmed (by WooCommerce or by the
	 * administrator marking it paid) and for free editions at approval.
	 *
	 * @param int    $request_id Request id.
	 * @param string $source     Enrollment source.
	 * @param int    $order_id   WooCommerce order id when any.
	 * @return true|WP_Error
	 */
	public function enroll_request( int $request_id, string $source = EnrollmentStatus::SOURCE_PRIVATE_LINK, int $order_id = 0 ) {
		$request = $this->requests->find( $request_id );

		if ( null === $request ) {
			return new WP_Error( 'av_request_not_found', __( 'La solicitud no existe.', 'aula-virtual' ), array( 'status' => 404 ) );
		}

		if ( RegistrationRequestRepository::STATUS_ENROLLED === $request['status'] ) {
			return true;
		}

		if ( RegistrationRequestRepository::STATUS_REJECTED === $request['status'] ) {
			return new WP_Error( 'av_request_rejected', __( 'La solicitud fue rechazada.', 'aula-virtual' ), array( 'status' => 409 ) );
		}

		$user_id = $this->find_or_create_user( $request );

		if ( $user_id instanceof WP_Error ) {
			return $user_id;
		}

		$enrollment_id = $this->enrollments->enroll(
			$user_id,
			(int) $request['edition_id'],
			$source,
			array(
				'status'   => EnrollmentStatus::ACTIVE,
				'order_id' => $order_id,
			)
		);

		if ( $enrollment_id instanceof WP_Error ) {
			return $enrollment_id;
		}

		$this->requests->update(
			$request_id,
			array(
				'status'  => RegistrationRequestRepository::STATUS_ENROLLED,
				'user_id' => $user_id,
			)
		);

		return true;
	}

	/**
	 * Rejects a request.
	 *
	 * @param int    $request_id Request id.
	 * @param string $reason     Reason shown to the student.
	 * @return true|WP_Error
	 */
	public function reject( int $request_id, string $reason = '' ) {
		$request = $this->requests->find( $request_id );

		if ( null === $request ) {
			return new WP_Error( 'av_request_not_found', __( 'La solicitud no existe.', 'aula-virtual' ), array( 'status' => 404 ) );
		}

		if ( RegistrationRequestRepository::STATUS_ENROLLED === $request['status'] ) {
			return new WP_Error( 'av_request_enrolled', __( 'La solicitud ya se convirtio en matricula.', 'aula-virtual' ), array( 'status' => 409 ) );
		}

		$reason = Sanitizer::textarea( $reason );

		$this->requests->update(
			$request_id,
			array(
				'status'           => RegistrationRequestRepository::STATUS_REJECTED,
				'rejection_reason' => $reason,
				'approved_by'      => get_current_user_id(),
			)
		);

		$this->audit->record( AuditLog::REQUEST_REJECTED, 'registration_request', $request_id );

		$this->events->dispatch(
			Events::REGISTRATION_REQUEST_REJECTED,
			array(
				'request_id' => $request_id,
				'edition_id' => (int) $request['edition_id'],
				'course_id'  => (int) $request['course_id'],
				'email'      => (string) $request['email'],
				'reason'     => $reason,
			)
		);

		return true;
	}

	/**
	 * Finds the WordPress user of a request, creating it when needed.
	 *
	 * The account is created with a random password the student never sees;
	 * the welcome email carries the secure link to set their own.
	 *
	 * @param array<string, mixed> $request Request row.
	 * @return int|WP_Error User id.
	 */
	private function find_or_create_user( array $request ) {
		$email = (string) $request['email'];
		$user  = get_user_by( 'email', $email );

		if ( false !== $user ) {
			return (int) $user->ID;
		}

		$user_id = wp_insert_user(
			array(
				'user_login'   => $this->unique_login( $email ),
				'user_email'   => $email,
				'user_pass'    => wp_generate_password( 32, true, true ),
				'first_name'   => (string) $request['first_name'],
				'last_name'    => (string) $request['last_name'],
				'display_name' => trim( $request['first_name'] . ' ' . $request['last_name'] ),
				'role'         => Roles::STUDENT,
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		if ( '' !== (string) $request['phone'] ) {
			update_user_meta( (int) $user_id, 'av_phone', (string) $request['phone'] );
		}

		if ( '' !== (string) $request['document'] ) {
			update_user_meta( (int) $user_id, 'av_document', (string) $request['document'] );
		}

		return (int) $user_id;
	}

	/**
	 * Returns the enrollment of a user in an edition, if any.
	 *
	 * @param int $user_id    User id.
	 * @param int $edition_id Edition id.
	 * @return array<string, mixed>|null
	 */
	private function enrollments_for( int $user_id, int $edition_id ): ?array {
		return $this->enrollments->find_enrollment( $user_id, $edition_id );
	}

	/**
	 * Keeps only the custom fields of a submission.
	 *
	 * @param array<string, mixed> $input Form input.
	 * @return array<string, string>
	 */
	private function custom_fields( array $input ): array {
		$known  = array( 'first_name', 'last_name', 'email', 'phone', 'document', 'action', '_wpnonce', '_wp_http_referer', 'token' );
		$custom = array();

		foreach ( $input as $key => $value ) {
			$key = sanitize_key( (string) $key );

			if ( '' === $key || in_array( $key, $known, true ) || ! is_scalar( $value ) ) {
				continue;
			}

			$custom[ $key ] = Sanitizer::text( $value );
		}

		return $custom;
	}

	/**
	 * Generates a token no other link uses.
	 *
	 * @return string
	 */
	private function unique_token(): string {
		do {
			$token = wp_generate_password( 20, false, false );
		} while ( null !== $this->links->find_by_token( $token ) );

		return $token;
	}

	/**
	 * Derives a free login from an email.
	 *
	 * @param string $email Email address.
	 * @return string
	 */
	private function unique_login( string $email ): string {
		$base  = sanitize_user( strstr( $email, '@', true ) ?: $email, true );
		$base  = '' === $base ? 'alumno' : $base;
		$login = $base;
		$i     = 2;

		while ( username_exists( $login ) ) {
			$login = $base . $i;
			++$i;
		}

		return $login;
	}
}
