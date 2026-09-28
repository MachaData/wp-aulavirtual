<?php
/**
 * Registration requests admin screen.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Admin;

use SIQA\AulaVirtual\Editions\EditionRepository;
use SIQA\AulaVirtual\Enrollments\EnrollmentStatus;
use SIQA\AulaVirtual\Enrollments\RegistrationRequestRepository;
use SIQA\AulaVirtual\Enrollments\RegistrationService;
use SIQA\AulaVirtual\Permissions\AccessControl;
use SIQA\AulaVirtual\Permissions\Capabilities;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lists requests and lets the administrator approve, reject or mark them paid.
 */
final class RequestsScreen {

	public const SLUG            = 'aula-virtual-solicitudes';
	public const ACTION_APPROVE  = 'av_approve_request';
	public const ACTION_REJECT   = 'av_reject_request';
	public const ACTION_MARK_PAID = 'av_mark_request_paid';

	/**
	 * Request persistence.
	 *
	 * @var RegistrationRequestRepository
	 */
	private RegistrationRequestRepository $requests;

	/**
	 * Registration rules.
	 *
	 * @var RegistrationService
	 */
	private RegistrationService $registration;

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
	 * @param RegistrationRequestRepository $requests     Request persistence.
	 * @param RegistrationService           $registration Registration rules.
	 * @param EditionRepository             $editions     Edition persistence.
	 * @param AccessControl                 $access       Permission checks.
	 */
	public function __construct(
		RegistrationRequestRepository $requests,
		RegistrationService $registration,
		EditionRepository $editions,
		AccessControl $access
	) {
		$this->requests     = $requests;
		$this->registration = $registration;
		$this->editions     = $editions;
		$this->access       = $access;
	}

	/**
	 * Renders the list.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( Capabilities::APPROVE_REQUESTS ) ) {
			wp_die( esc_html__( 'No tienes permisos para ver esta pagina.', 'aula-virtual' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
		$status = isset( $_GET['estado'] ) ? sanitize_key( wp_unslash( $_GET['estado'] ) ) : RegistrationRequestRepository::STATUS_PENDING;
		$status = in_array( $status, RegistrationRequestRepository::statuses(), true ) ? $status : 'all';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination.
		$page = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;

		$args = array(
			'order_by' => 'created_at',
			'order'    => 'DESC',
		);

		if ( 'all' !== $status ) {
			$args['where'] = array( 'status' => $status );
		}

		$result   = $this->requests->paginate( $args, $page, 30 );
		$editions = array();

		foreach ( $result['items'] as $request ) {
			$edition_id = (int) $request['edition_id'];

			if ( ! isset( $editions[ $edition_id ] ) ) {
				$editions[ $edition_id ] = $this->editions->find( $edition_id );
			}
		}

		$notice = null;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only message produced by our own redirect.
		if ( isset( $_GET['av_notice'], $_GET['av_message'] ) ) {
			$notice = array(
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only.
				'type'    => 'success' === sanitize_key( wp_unslash( $_GET['av_notice'] ) ) ? 'success' : 'error',
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only.
				'message' => sanitize_text_field( wp_unslash( $_GET['av_message'] ) ),
			);
		}

		$data = array(
			'result'   => $result,
			'editions' => $editions,
			'status'   => $status,
			'counts'   => array( 'pending' => $this->requests->count_pending() ),
			'notice'   => $notice,
		);

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- controlled template data.
		extract( $data, EXTR_SKIP );

		require AV_PATH . 'admin/views/requests-list.php';
	}

	/**
	 * Approves a request.
	 *
	 * @return void
	 */
	public function handle_approve(): void {
		$request_id = $this->guard( self::ACTION_APPROVE );

		$this->finish( $this->registration->approve( $request_id ), __( 'Solicitud aprobada.', 'aula-virtual' ) );
	}

	/**
	 * Rejects a request.
	 *
	 * @return void
	 */
	public function handle_reject(): void {
		$request_id = $this->guard( self::ACTION_REJECT );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		$reason = isset( $_POST['reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ) : '';

		$this->finish( $this->registration->reject( $request_id, $reason ), __( 'Solicitud rechazada.', 'aula-virtual' ) );
	}

	/**
	 * Marks a paid edition request as paid by hand and enrolls the student.
	 *
	 * @return void
	 */
	public function handle_mark_paid(): void {
		$request_id = $this->guard( self::ACTION_MARK_PAID, Capabilities::ENROLL_STUDENTS );

		$this->finish(
			$this->registration->enroll_request( $request_id, EnrollmentStatus::SOURCE_MANUAL ),
			__( 'Pago registrado y alumno matriculado.', 'aula-virtual' )
		);
	}

	/**
	 * Verifies nonce, capability and ownership, returning the request id.
	 *
	 * @param string $action     Nonce action.
	 * @param string $capability Capability required.
	 * @return int
	 */
	private function guard( string $action, string $capability = Capabilities::APPROVE_REQUESTS ): int {
		if ( ! current_user_can( $capability ) ) {
			wp_die( esc_html__( 'No tienes permisos para realizar esta accion.', 'aula-virtual' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( $action );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$request_id = isset( $_POST['request_id'] ) ? absint( wp_unslash( $_POST['request_id'] ) ) : 0;
		$request    = $this->requests->find( $request_id );

		if ( null === $request || ! $this->access->can_manage_editions( (int) $request['course_id'] ) ) {
			wp_die( esc_html__( 'No tienes permisos sobre esta solicitud.', 'aula-virtual' ), '', array( 'response' => 403 ) );
		}

		return $request_id;
	}

	/**
	 * Redirects back to the list with the outcome.
	 *
	 * @param true|WP_Error $result  Service result.
	 * @param string        $success Message on success.
	 * @return void
	 */
	private function finish( $result, string $success ): void {
		$args = $result instanceof WP_Error
			? array( 'av_notice' => 'error', 'av_message' => rawurlencode( $result->get_error_message() ) )
			: array( 'av_notice' => 'success', 'av_message' => rawurlencode( $success ) );

		wp_safe_redirect( add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
