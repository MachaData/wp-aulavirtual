<?php
/**
 * Announcements admin screen.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Admin;

use SIQA\AulaVirtual\Announcements\AnnouncementRepository;
use SIQA\AulaVirtual\Announcements\AnnouncementService;
use SIQA\AulaVirtual\Editions\EditionRepository;
use SIQA\AulaVirtual\Permissions\AccessControl;
use SIQA\AulaVirtual\Permissions\Capabilities;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Writes an announcement to a course or an edition, optionally by email.
 * This is the "manual email to the students of an edition".
 */
final class AnnouncementsScreen {

	public const SLUG          = 'aula-virtual-anuncios';
	public const ACTION_CREATE = 'av_create_announcement';
	public const ACTION_DELETE = 'av_delete_announcement';

	/**
	 * Announcement persistence.
	 *
	 * @var AnnouncementRepository
	 */
	private AnnouncementRepository $announcements;

	/**
	 * Announcement rules.
	 *
	 * @var AnnouncementService
	 */
	private AnnouncementService $service;

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
	 * @param AnnouncementRepository $announcements Announcement persistence.
	 * @param AnnouncementService    $service       Announcement rules.
	 * @param EditionRepository      $editions      Edition persistence.
	 * @param AccessControl          $access        Permission checks.
	 */
	public function __construct(
		AnnouncementRepository $announcements,
		AnnouncementService $service,
		EditionRepository $editions,
		AccessControl $access
	) {
		$this->announcements = $announcements;
		$this->service       = $service;
		$this->editions      = $editions;
		$this->access        = $access;
	}

	/**
	 * Renders the list and the form.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( Capabilities::MANAGE_ANNOUNCE ) ) {
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

		$editions = $this->editions->all(
			array(
				'order_by' => 'start_date',
				'order'    => 'DESC',
				'limit'    => 200,
			)
		);

		$data = array(
			'items'    => $this->announcements->paginate( array( 'order_by' => 'created_at', 'order' => 'DESC' ), 1, 50 )['items'],
			'editions' => $editions,
			'notice'   => $notice,
		);

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- controlled template data.
		extract( $data, EXTR_SKIP );

		require AV_PATH . 'admin/views/announcements.php';
	}

	/**
	 * Creates an announcement.
	 *
	 * @return void
	 */
	public function handle_create(): void {
		$this->guard( self::ACTION_CREATE );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		$input = wp_unslash( $_POST );

		// "target" is "edition:ID" or "course:ID".
		$target = isset( $input['target'] ) ? sanitize_text_field( (string) $input['target'] ) : '';

		if ( preg_match( '/^(edition|course):(\d+)$/', $target, $m ) ) {
			$input[ 'edition' === $m[1] ? 'edition_id' : 'course_id' ] = (int) $m[2];
		}

		$course_id = isset( $input['edition_id'] )
			? (int) ( $this->editions->find( (int) $input['edition_id'] )['course_id'] ?? 0 )
			: (int) ( $input['course_id'] ?? 0 );

		if ( ! $this->access->can_manage_editions( $course_id ) ) {
			wp_die( esc_html__( 'No tienes permisos sobre ese curso.', 'aula-virtual' ), '', array( 'response' => 403 ) );
		}

		$result = $this->service->create( $input );

		if ( $result instanceof WP_Error ) {
			$this->finish( 'error', $result->get_error_message() );
		}

		$this->finish(
			'success',
			$result['recipients'] > 0
				/* translators: %d: number of students emailed. */
				? sprintf( __( 'Anuncio publicado y enviado por correo a %d alumnos.', 'aula-virtual' ), $result['recipients'] )
				: __( 'Anuncio publicado.', 'aula-virtual' )
		);
	}

	/**
	 * Deletes an announcement.
	 *
	 * @return void
	 */
	public function handle_delete(): void {
		$this->guard( self::ACTION_DELETE );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		$id   = isset( $_POST['announcement_id'] ) ? absint( wp_unslash( $_POST['announcement_id'] ) ) : 0;
		$item = $this->announcements->find( $id );

		if ( null === $item || ! $this->access->can_manage_editions( (int) $item['course_id'] ) ) {
			wp_die( esc_html__( 'No tienes permisos sobre ese anuncio.', 'aula-virtual' ), '', array( 'response' => 403 ) );
		}

		$this->service->delete( $id );
		$this->finish( 'success', __( 'Anuncio eliminado.', 'aula-virtual' ) );
	}

	/**
	 * Verifies nonce and capability.
	 *
	 * @param string $action Nonce action.
	 * @return void
	 */
	private function guard( string $action ): void {
		if ( ! current_user_can( Capabilities::MANAGE_ANNOUNCE ) ) {
			wp_die( esc_html__( 'No tienes permisos para realizar esta accion.', 'aula-virtual' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( $action );
	}

	/**
	 * Redirects back with a notice.
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
