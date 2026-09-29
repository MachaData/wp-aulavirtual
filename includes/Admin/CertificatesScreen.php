<?php
/**
 * Certificates admin screen.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Admin;

use SIQA\AulaVirtual\Certificates\CertificateRepository;
use SIQA\AulaVirtual\Certificates\CertificateService;
use SIQA\AulaVirtual\Editions\EditionRepository;
use SIQA\AulaVirtual\Permissions\AccessControl;
use SIQA\AulaVirtual\Permissions\Capabilities;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lists the issued certificates, issues one by hand and revokes it.
 */
final class CertificatesScreen {

	public const SLUG          = 'aula-virtual-certificados';
	public const ACTION_ISSUE  = 'av_issue_certificate';
	public const ACTION_REVOKE = 'av_revoke_certificate';

	/**
	 * Certificate persistence.
	 *
	 * @var CertificateRepository
	 */
	private CertificateRepository $certificates;

	/**
	 * Certificate rules.
	 *
	 * @var CertificateService
	 */
	private CertificateService $service;

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
	 * @param CertificateRepository $certificates Certificate persistence.
	 * @param CertificateService    $service      Certificate rules.
	 * @param EditionRepository     $editions     Edition persistence.
	 * @param AccessControl         $access       Permission checks.
	 */
	public function __construct(
		CertificateRepository $certificates,
		CertificateService $service,
		EditionRepository $editions,
		AccessControl $access
	) {
		$this->certificates = $certificates;
		$this->service      = $service;
		$this->editions     = $editions;
		$this->access       = $access;
	}

	/**
	 * Renders the list and the manual issue form.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( Capabilities::ISSUE_CERTIFICATES ) ) {
			wp_die( esc_html__( 'No tienes permisos para ver esta página.', 'aula-virtual' ) );
		}

		$notice = null;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only, produced by our own redirect.
		if ( isset( $_GET['av_notice'], $_GET['av_message'] ) ) {
			$notice = array(
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only.
				'type'    => 'success' === sanitize_key( wp_unslash( $_GET['av_notice'] ) ) ? 'success' : 'error',
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only.
				'message' => sanitize_text_field( wp_unslash( $_GET['av_message'] ) ),
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only filter.
		$edition_filter = isset( $_GET['edition'] ) ? absint( wp_unslash( $_GET['edition'] ) ) : 0;

		$editions = $this->editions->all(
			array(
				'order_by' => 'start_date',
				'order'    => 'DESC',
				'limit'    => 200,
			)
		);

		$items = $edition_filter > 0
			? $this->certificates->for_edition( $edition_filter )
			: $this->certificates->paginate( array( 'order_by' => 'issued_at', 'order' => 'DESC' ), 1, 100 )['items'];

		$urls = array();

		foreach ( $items as $item ) {
			$urls[ (int) $item['id'] ] = $this->service->url( $item );
		}

		$data = array(
			'items'          => $items,
			'urls'           => $urls,
			'editions'       => $editions,
			'edition_filter' => $edition_filter,
			'auto_enabled'   => CertificateService::auto_issue_enabled(),
			'notice'         => $notice,
		);

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- controlled template data.
		extract( $data, EXTR_SKIP );

		require AV_PATH . 'admin/views/certificates.php';
	}

	/**
	 * Issues a certificate by hand.
	 *
	 * @return void
	 */
	public function handle_issue(): void {
		$this->guard( self::ACTION_ISSUE );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		$user_id = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		$edition_id = isset( $_POST['edition_id'] ) ? absint( wp_unslash( $_POST['edition_id'] ) ) : 0;

		$edition = $edition_id > 0 ? $this->editions->find( $edition_id ) : null;

		if ( null === $edition ) {
			$this->finish( 'error', __( 'Selecciona una edición.', 'aula-virtual' ) );
		}

		if ( ! $this->access->can_manage_editions( (int) $edition['course_id'] ) ) {
			wp_die( esc_html__( 'No tienes permisos sobre ese curso.', 'aula-virtual' ), '', array( 'response' => 403 ) );
		}

		$result = $this->service->issue( $user_id, $edition_id, get_current_user_id() );

		if ( $result instanceof WP_Error ) {
			$this->finish( 'error', $result->get_error_message(), $edition_id );
		}

		$certificate = $this->certificates->find( (int) $result );

		$this->finish(
			'success',
			null !== $certificate
				/* translators: %s: certificate code. */
				? sprintf( __( 'Certificado emitido con el código %s.', 'aula-virtual' ), (string) $certificate['certificate_code'] )
				: __( 'Certificado emitido.', 'aula-virtual' ),
			$edition_id
		);
	}

	/**
	 * Revokes a certificate.
	 *
	 * @return void
	 */
	public function handle_revoke(): void {
		$this->guard( self::ACTION_REVOKE );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		$id   = isset( $_POST['certificate_id'] ) ? absint( wp_unslash( $_POST['certificate_id'] ) ) : 0;
		$item = $this->certificates->find( $id );

		if ( null === $item || ! $this->access->can_manage_editions( (int) $item['course_id'] ) ) {
			wp_die( esc_html__( 'No tienes permisos sobre ese certificado.', 'aula-virtual' ), '', array( 'response' => 403 ) );
		}

		$result = $this->service->revoke( $id );

		if ( $result instanceof WP_Error ) {
			$this->finish( 'error', $result->get_error_message(), (int) $item['edition_id'] );
		}

		$this->finish( 'success', __( 'Certificado anulado. Su página pública ya no valida.', 'aula-virtual' ), (int) $item['edition_id'] );
	}

	/**
	 * Verifies nonce and capability.
	 *
	 * @param string $action Nonce action.
	 * @return void
	 */
	private function guard( string $action ): void {
		if ( ! current_user_can( Capabilities::ISSUE_CERTIFICATES ) ) {
			wp_die( esc_html__( 'No tienes permisos para realizar esta acción.', 'aula-virtual' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( $action );
	}

	/**
	 * Redirects back with a notice, keeping the edition filter.
	 *
	 * @param string $type       success or error.
	 * @param string $message    Message.
	 * @param int    $edition_id Edition filter to keep, 0 for none.
	 * @return void
	 */
	private function finish( string $type, string $message, int $edition_id = 0 ): void {
		$args = array(
			'page'       => self::SLUG,
			'av_notice'  => $type,
			'av_message' => rawurlencode( $message ),
		);

		if ( $edition_id > 0 ) {
			$args['edition'] = $edition_id;
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}
}
