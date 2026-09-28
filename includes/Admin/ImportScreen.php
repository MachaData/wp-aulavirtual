<?php
/**
 * Student import screen.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Admin;

use SIQA\AulaVirtual\Editions\EditionRepository;
use SIQA\AulaVirtual\Imports\ImportJobRepository;
use SIQA\AulaVirtual\Imports\ImportParser;
use SIQA\AulaVirtual\Imports\ImportService;
use SIQA\AulaVirtual\Permissions\AccessControl;
use SIQA\AulaVirtual\Permissions\Capabilities;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Four steps: upload, map columns, review, import in batches.
 *
 * The parsed file lives in a transient keyed by a token until the job is
 * created; nothing is written and no email is sent before the last step.
 */
final class ImportScreen {

	public const SLUG           = 'aula-virtual-importar';
	public const ACTION_UPLOAD  = 'av_import_upload';
	public const ACTION_MAP     = 'av_import_map';
	public const ACTION_CONFIRM = 'av_import_confirm';
	public const ACTION_RUN     = 'av_import_run';

	/**
	 * Edition persistence.
	 *
	 * @var EditionRepository
	 */
	private EditionRepository $editions;

	/**
	 * Import rules.
	 *
	 * @var ImportService
	 */
	private ImportService $service;

	/**
	 * Job persistence.
	 *
	 * @var ImportJobRepository
	 */
	private ImportJobRepository $jobs;

	/**
	 * Permission checks.
	 *
	 * @var AccessControl
	 */
	private AccessControl $access;

	/**
	 * Constructor.
	 *
	 * @param EditionRepository   $editions Edition persistence.
	 * @param ImportService       $service  Import rules.
	 * @param ImportJobRepository $jobs     Job persistence.
	 * @param AccessControl       $access   Permission checks.
	 */
	public function __construct( EditionRepository $editions, ImportService $service, ImportJobRepository $jobs, AccessControl $access ) {
		$this->editions = $editions;
		$this->service  = $service;
		$this->jobs     = $jobs;
		$this->access   = $access;
	}

	/**
	 * Renders the current step.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( Capabilities::IMPORT_STUDENTS ) ) {
			wp_die( esc_html__( 'No tienes permisos para ver esta pagina.', 'aula-virtual' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
		$token = isset( $_GET['token'] ) ? preg_replace( '/[^a-z0-9]/', '', (string) wp_unslash( $_GET['token'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
		$job_id = isset( $_GET['job'] ) ? absint( wp_unslash( $_GET['job'] ) ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
		$step = isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : 'upload';

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

		$data = array(
			'step'     => 'upload',
			'notice'   => $notice,
			'editions' => $this->editions->all( array( 'order_by' => 'start_date', 'order' => 'DESC', 'limit' => 200 ) ),
		);

		if ( $job_id > 0 ) {
			$job = $this->jobs->find( $job_id );

			if ( null !== $job ) {
				$data['step']   = 'run';
				$data['job']    = $job;
				$data['errors'] = json_decode( (string) $job['errors'], true ) ?: array();
			}
		} elseif ( '' !== $token ) {
			$stash = get_transient( 'av_import_stash_' . $token );

			if ( is_array( $stash ) ) {
				$data['token'] = $token;
				$data['stash'] = $stash;
				$data['step']  = 'map' === $step ? 'map' : 'review';

				if ( 'review' === $data['step'] ) {
					$mapped         = ImportService::map_rows( $stash['rows'], $stash['mapping'] );
					$data['review'] = $this->service->validate( $mapped, (int) $stash['edition_id'] );
				}
			}
		}

		$data['edition'] = isset( $data['stash'] ) ? $this->editions->find( (int) $data['stash']['edition_id'] ) : ( isset( $data['job'] ) ? $this->editions->find( (int) $data['job']['edition_id'] ) : null );

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- controlled template data.
		extract( $data, EXTR_SKIP );

		require AV_PATH . 'admin/views/import.php';
	}

	/**
	 * Step 1: receives the file and parses it.
	 *
	 * @return void
	 */
	public function handle_upload(): void {
		$this->guard( self::ACTION_UPLOAD );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		$edition_id = isset( $_POST['edition_id'] ) ? absint( wp_unslash( $_POST['edition_id'] ) ) : 0;
		$this->assert_edition( $edition_id );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- file array handled below.
		$file = $_FILES['import_file'] ?? null;

		if ( ! is_array( $file ) || empty( $file['tmp_name'] ) || UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
			$this->finish( 'error', __( 'Sube un archivo .csv o .xlsx.', 'aula-virtual' ) );
		}

		$filename = sanitize_file_name( (string) $file['name'] );
		$parsed   = ImportParser::parse( (string) $file['tmp_name'], $filename );

		if ( $parsed instanceof WP_Error ) {
			$this->finish( 'error', $parsed->get_error_message() );
		}

		$token = wp_generate_password( 16, false, false );

		set_transient(
			'av_import_stash_' . strtolower( $token ),
			array(
				'edition_id' => $edition_id,
				'filename'   => $filename,
				'headers'    => $parsed['headers'],
				'rows'       => $parsed['rows'],
				'mapping'    => ImportParser::guess_mapping( $parsed['headers'] ),
			),
			HOUR_IN_SECONDS * 2
		);

		wp_safe_redirect( add_query_arg( array( 'page' => self::SLUG, 'token' => strtolower( $token ), 'step' => 'map' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Step 2: saves the column mapping and moves to review.
	 *
	 * @return void
	 */
	public function handle_map(): void {
		$this->guard( self::ACTION_MAP );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		$token = isset( $_POST['token'] ) ? preg_replace( '/[^a-z0-9]/', '', (string) wp_unslash( $_POST['token'] ) ) : '';
		$stash = get_transient( 'av_import_stash_' . $token );

		if ( ! is_array( $stash ) ) {
			$this->finish( 'error', __( 'La sesion de importacion caduco. Vuelve a subir el archivo.', 'aula-virtual' ) );
		}

		$this->assert_edition( (int) $stash['edition_id'] );

		$mapping = array();

		foreach ( array( 'email', 'first_name', 'last_name', 'phone', 'document' ) as $field ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
			$value = isset( $_POST['map'][ $field ] ) ? (string) wp_unslash( $_POST['map'][ $field ] ) : '';

			if ( '' !== $value && is_numeric( $value ) ) {
				$mapping[ $field ] = (int) $value;
			}
		}

		if ( ! isset( $mapping['email'] ) ) {
			$this->finish( 'error', __( 'Indica que columna tiene el correo.', 'aula-virtual' ), array( 'token' => $token, 'step' => 'map' ) );
		}

		$stash['mapping'] = $mapping;
		set_transient( 'av_import_stash_' . $token, $stash, HOUR_IN_SECONDS * 2 );

		wp_safe_redirect( add_query_arg( array( 'page' => self::SLUG, 'token' => $token, 'step' => 'review' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Step 3: creates the job from the valid rows.
	 *
	 * @return void
	 */
	public function handle_confirm(): void {
		$this->guard( self::ACTION_CONFIRM );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		$token = isset( $_POST['token'] ) ? preg_replace( '/[^a-z0-9]/', '', (string) wp_unslash( $_POST['token'] ) ) : '';
		$stash = get_transient( 'av_import_stash_' . $token );

		if ( ! is_array( $stash ) ) {
			$this->finish( 'error', __( 'La sesion de importacion caduco. Vuelve a subir el archivo.', 'aula-virtual' ) );
		}

		$this->assert_edition( (int) $stash['edition_id'] );

		$mapped = ImportService::map_rows( $stash['rows'], $stash['mapping'] );
		$review = $this->service->validate( $mapped, (int) $stash['edition_id'] );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		$welcome = ! empty( $_POST['send_welcome'] );

		$job_id = $this->service->create_job( (int) $stash['edition_id'], (string) $stash['filename'], $review['valid'], $stash['mapping'], $welcome );

		if ( $job_id instanceof WP_Error ) {
			$this->finish( 'error', $job_id->get_error_message() );
		}

		delete_transient( 'av_import_stash_' . $token );

		wp_safe_redirect( add_query_arg( array( 'page' => self::SLUG, 'job' => (int) $job_id ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Step 4: runs one batch.
	 *
	 * @return void
	 */
	public function handle_run(): void {
		$this->guard( self::ACTION_RUN );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		$job_id = isset( $_POST['job_id'] ) ? absint( wp_unslash( $_POST['job_id'] ) ) : 0;
		$job    = $this->jobs->find( $job_id );

		if ( null === $job ) {
			$this->finish( 'error', __( 'La importacion no existe.', 'aula-virtual' ) );
		}

		$this->assert_edition( (int) $job['edition_id'] );

		$result = $this->service->run_batch( $job_id );

		if ( $result instanceof WP_Error ) {
			$this->finish( 'error', $result->get_error_message(), array( 'job' => $job_id ) );
		}

		wp_safe_redirect( add_query_arg( array( 'page' => self::SLUG, 'job' => $job_id ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Verifies nonce and capability.
	 *
	 * @param string $action Nonce action.
	 * @return void
	 */
	private function guard( string $action ): void {
		if ( ! current_user_can( Capabilities::IMPORT_STUDENTS ) ) {
			wp_die( esc_html__( 'No tienes permisos para realizar esta accion.', 'aula-virtual' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( $action );
	}

	/**
	 * Stops when the edition does not exist or is not the user's.
	 *
	 * @param int $edition_id Edition id.
	 * @return void
	 */
	private function assert_edition( int $edition_id ): void {
		$edition = $this->editions->find( $edition_id );

		if ( null === $edition || ! $this->access->can_manage_editions( (int) $edition['course_id'] ) ) {
			wp_die( esc_html__( 'No tienes permisos sobre esta edicion.', 'aula-virtual' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Redirects to the screen with a notice.
	 *
	 * @param string                    $type    success or error.
	 * @param string                    $message Message.
	 * @param array<string, string|int> $args    Extra query arguments.
	 * @return void
	 */
	private function finish( string $type, string $message, array $args = array() ): void {
		wp_safe_redirect( add_query_arg( array_merge( array( 'page' => self::SLUG, 'av_notice' => $type, 'av_message' => rawurlencode( $message ) ), $args ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
