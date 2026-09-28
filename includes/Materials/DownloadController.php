<?php
/**
 * Serves materials after checking the student's enrollment.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Materials;

use SIQA\AulaVirtual\Enrollments\EnrollmentService;
use SIQA\AulaVirtual\Permissions\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * `admin-post.php?action=av_download&material={id}`.
 *
 * Files stored in the protected folder (see MaterialService::protect_attachment)
 * are only reachable through this endpoint; the web server denies the direct
 * URL. Files left in the public media library still get the enrollment check
 * on this URL, but their direct URL stays public.
 */
final class DownloadController {

	public const ACTION = 'av_download';

	/**
	 * Material persistence.
	 *
	 * @var MaterialRepository
	 */
	private MaterialRepository $materials;

	/**
	 * Access rules.
	 *
	 * @var EnrollmentService
	 */
	private EnrollmentService $enrollments;

	/**
	 * Constructor.
	 *
	 * @param MaterialRepository $materials   Material persistence.
	 * @param EnrollmentService  $enrollments Access rules.
	 */
	public function __construct( MaterialRepository $materials, EnrollmentService $enrollments ) {
		$this->materials   = $materials;
		$this->enrollments = $enrollments;
	}

	/**
	 * Returns the protected URL of a material.
	 *
	 * @param int $material_id Material id.
	 * @return string
	 */
	public static function url( int $material_id ): string {
		return add_query_arg(
			array(
				'action'   => self::ACTION,
				'material' => $material_id,
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * Sends the visitor to log in and come back.
	 *
	 * @return void
	 */
	public function handle_anonymous(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only id.
		$material_id = isset( $_GET['material'] ) ? absint( wp_unslash( $_GET['material'] ) ) : 0;

		wp_safe_redirect( wp_login_url( self::url( $material_id ) ) );
		exit;
	}

	/**
	 * Streams the file or redirects to the external link.
	 *
	 * @return void
	 */
	public function handle(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- access is decided by the enrollment, not by a nonce.
		$material_id = isset( $_GET['material'] ) ? absint( wp_unslash( $_GET['material'] ) ) : 0;
		$material    = $this->materials->find( $material_id );

		if ( null === $material ) {
			wp_die( esc_html__( 'El material no existe.', 'aula-virtual' ), '', array( 'response' => 404 ) );
		}

		if ( ! $this->can_open( $material ) ) {
			wp_die( esc_html__( 'No tienes acceso a este material.', 'aula-virtual' ), '', array( 'response' => 403 ) );
		}

		if ( (int) $material['attachment_id'] <= 0 ) {
			$url = (string) $material['external_url'];

			if ( '' === $url ) {
				wp_die( esc_html__( 'El material no tiene archivo ni enlace.', 'aula-virtual' ), '', array( 'response' => 404 ) );
			}

			if ( ! in_array( wp_parse_url( $url, PHP_URL_SCHEME ), array( 'http', 'https' ), true ) ) {
				wp_die( esc_html__( 'Enlace no valido.', 'aula-virtual' ), '', array( 'response' => 400 ) );
			}

			wp_redirect( esc_url_raw( $url ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- external link chosen by the instructor.
			exit;
		}

		$path = get_attached_file( (int) $material['attachment_id'] );

		if ( ! is_string( $path ) || ! is_readable( $path ) ) {
			wp_die( esc_html__( 'El archivo ya no esta disponible.', 'aula-virtual' ), '', array( 'response' => 404 ) );
		}

		$this->stream( $path, (bool) $material['downloadable'], (string) $material['title'] );
	}

	/**
	 * Whether the current user may open a material.
	 *
	 * @param array<string, mixed> $material Material row.
	 * @return bool
	 */
	private function can_open( array $material ): bool {
		if ( current_user_can( Capabilities::MANAGE_MATERIALS ) ) {
			return true;
		}

		return $this->enrollments->has_access( get_current_user_id(), (int) $material['edition_id'] );
	}

	/**
	 * Sends the file to the browser.
	 *
	 * @param string $path         Absolute path.
	 * @param bool   $downloadable Attachment (download) or inline (view).
	 * @param string $title        Material title, used as the file name.
	 * @return void
	 */
	private function stream( string $path, bool $downloadable, string $title = '' ): void {
		$check = wp_check_filetype( $path );
		$mime  = is_string( $check['type'] ?? null ) && '' !== $check['type'] ? $check['type'] : 'application/octet-stream';
		$ext   = strtolower( (string) pathinfo( $path, PATHINFO_EXTENSION ) );
		$base  = sanitize_file_name( '' === trim( $title ) ? pathinfo( $path, PATHINFO_FILENAME ) : $title );
		$name  = ( '' === $base ? 'material' : $base ) . ( '' === $ext || str_ends_with( strtolower( $base ), '.' . $ext ) ? '' : '.' . $ext );
		$size  = filesize( $path );

		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		nocache_headers();
		header( 'Content-Type: ' . $mime );
		header( 'Content-Disposition: ' . ( $downloadable ? 'attachment' : 'inline' ) . '; filename="' . $name . '"' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		header( 'X-Content-Type-Options: nosniff' );

		if ( false !== $size ) {
			header( 'Content-Length: ' . $size );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- streaming a local file.
		readfile( $path );
		exit;
	}
}
