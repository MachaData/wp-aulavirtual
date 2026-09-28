<?php
/**
 * Reports admin screen.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Admin;

use SIQA\AulaVirtual\Editions\EditionRepository;
use SIQA\AulaVirtual\Permissions\AccessControl;
use SIQA\AulaVirtual\Permissions\Capabilities;
use SIQA\AulaVirtual\Reports\ReportRepository;
use SIQA\AulaVirtual\Reports\ReportService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shows the site overview or the report of one edition, and exports the
 * student list of an edition as CSV.
 */
final class ReportsScreen {

	public const SLUG          = 'aula-virtual-reportes';
	public const ACTION_EXPORT = 'av_export_students';

	/**
	 * Report queries.
	 *
	 * @var ReportRepository
	 */
	private ReportRepository $reports;

	/**
	 * Report exports.
	 *
	 * @var ReportService
	 */
	private ReportService $service;

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
	 * @param ReportRepository  $reports  Report queries.
	 * @param ReportService     $service  Report exports.
	 * @param EditionRepository $editions Edition persistence.
	 * @param AccessControl     $access   Permission checks.
	 */
	public function __construct(
		ReportRepository $reports,
		ReportService $service,
		EditionRepository $editions,
		AccessControl $access
	) {
		$this->reports  = $reports;
		$this->service  = $service;
		$this->editions = $editions;
		$this->access   = $access;
	}

	/**
	 * Renders the overview or the edition report.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( Capabilities::VIEW_REPORTS ) ) {
			wp_die( esc_html__( 'No tienes permisos para ver esta pagina.', 'aula-virtual' ) );
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

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation parameter.
		$edition_id = isset( $_GET['edition'] ) ? absint( wp_unslash( $_GET['edition'] ) ) : 0;

		if ( $edition_id > 0 ) {
			$this->render_edition( $edition_id, $notice );
			return;
		}

		$data = array(
			'mode'     => 'overview',
			'overview' => $this->reports->overview(),
			'notice'   => $notice,
		);

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- controlled template data.
		extract( $data, EXTR_SKIP );

		require AV_PATH . 'admin/views/reports.php';
	}

	/**
	 * Streams the students of an edition as CSV.
	 *
	 * @return void
	 */
	public function handle_export(): void {
		if ( ! current_user_can( Capabilities::VIEW_REPORTS ) ) {
			wp_die( esc_html__( 'No tienes permisos para realizar esta accion.', 'aula-virtual' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( self::ACTION_EXPORT );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified above.
		$edition_id = isset( $_GET['edition'] ) ? absint( wp_unslash( $_GET['edition'] ) ) : 0;
		$edition    = $this->editions->find( $edition_id );

		if ( null === $edition ) {
			$this->finish( 'error', __( 'La edicion no existe.', 'aula-virtual' ) );
		}

		if ( ! $this->access->can_manage_editions( (int) $edition['course_id'] ) ) {
			wp_die( esc_html__( 'No tienes permisos sobre ese curso.', 'aula-virtual' ), '', array( 'response' => 403 ) );
		}

		$csv      = $this->service->csv_students( $edition_id );
		$filename = $this->service->csv_filename( $edition );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . (string) strlen( $csv ) );

		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV body, not HTML.
		exit;
	}

	/**
	 * URL of the report of one edition.
	 *
	 * @param int $edition_id Edition id.
	 * @return string
	 */
	public static function edition_url( int $edition_id ): string {
		return add_query_arg(
			array(
				'page'    => self::SLUG,
				'edition' => $edition_id,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Nonce-protected URL of the students export of one edition.
	 *
	 * @param int $edition_id Edition id.
	 * @return string
	 */
	public static function export_url( int $edition_id ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'  => self::ACTION_EXPORT,
					'edition' => $edition_id,
				),
				admin_url( 'admin-post.php' )
			),
			self::ACTION_EXPORT
		);
	}

	/**
	 * Renders the report of one edition.
	 *
	 * @param int                                       $edition_id Edition id.
	 * @param array{type: string, message: string}|null $notice     Notice to show.
	 * @return void
	 */
	private function render_edition( int $edition_id, ?array $notice ): void {
		$edition = $this->editions->find( $edition_id );

		if ( null === $edition ) {
			$this->finish( 'error', __( 'La edicion no existe.', 'aula-virtual' ) );
		}

		if ( ! $this->access->can_manage_editions( (int) $edition['course_id'] ) ) {
			wp_die( esc_html__( 'No tienes permisos sobre ese curso.', 'aula-virtual' ), '', array( 'response' => 403 ) );
		}

		$data = array(
			'mode'    => 'edition',
			'edition' => $edition,
			'course'  => get_post( (int) $edition['course_id'] ),
			'summary' => $this->reports->edition_summary( $edition_id ),
			'lessons' => $this->reports->lesson_completion( $edition_id ),
			'notice'  => $notice,
		);

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- controlled template data.
		extract( $data, EXTR_SKIP );

		require AV_PATH . 'admin/views/reports.php';
	}

	/**
	 * Redirects back to the overview with a notice.
	 *
	 * @param string $type    success or error.
	 * @param string $message Message.
	 * @return void
	 */
	private function finish( string $type, string $message ): void {
		wp_safe_redirect( add_query_arg( array( 'page' => self::SLUG, 'av_notice' => $type, 'av_message' => rawurlencode( $message ) ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
