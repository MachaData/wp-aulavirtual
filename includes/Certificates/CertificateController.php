<?php
/**
 * Public certificate page.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Certificates;

use SIQA\AulaVirtual\Editions\EditionRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serves /certificado/{codigo}/: a standalone, printable page that doubles
 * as the public verification of the certificate.
 *
 * There is no PDF library on purpose: the page is laid out for A4 landscape
 * and the visitor prints it or saves it as PDF from the browser.
 */
final class CertificateController {

	public const QUERY_VAR = 'av_certificado';

	/**
	 * Certificate rules.
	 *
	 * @var CertificateService
	 */
	private CertificateService $certificates;

	/**
	 * Edition persistence.
	 *
	 * @var EditionRepository
	 */
	private EditionRepository $editions;

	/**
	 * Constructor.
	 *
	 * @param CertificateService $certificates Certificate rules.
	 * @param EditionRepository  $editions     Edition persistence.
	 */
	public function __construct( CertificateService $certificates, EditionRepository $editions ) {
		$this->certificates = $certificates;
		$this->editions     = $editions;
	}

	/**
	 * Registers the pretty URL of the certificate page.
	 *
	 * @return void
	 */
	public static function register_rewrite(): void {
		add_rewrite_tag( '%' . self::QUERY_VAR . '%', '([A-Za-z0-9-]+)' );
		add_rewrite_rule( '^certificado/([A-Za-z0-9-]+)/?$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top' );
	}

	/**
	 * Exposes the query var so WordPress keeps it after parsing the URL.
	 *
	 * @param array<int, string> $vars Public query vars.
	 * @return array<int, string>
	 */
	public static function query_vars( array $vars ): array {
		if ( ! in_array( self::QUERY_VAR, $vars, true ) ) {
			$vars[] = self::QUERY_VAR;
		}

		return $vars;
	}

	/**
	 * Renders the certificate when the URL carries a code.
	 *
	 * @return void
	 */
	public function maybe_render(): void {
		$raw = get_query_var( self::QUERY_VAR, '' );

		if ( ! is_string( $raw ) || '' === $raw ) {
			return;
		}

		nocache_headers();

		$code = CertificateService::normalize_code( $raw );

		// Solo cuentan los intentos fallidos: frena que alguien recorra codigos
		// para sacar nombres de alumnos, sin molestar a quien verifica uno real.
		$bucket = 'cert_miss_' . \SIQA\AulaVirtual\Security\RateLimiter::client_ip();

		if ( \SIQA\AulaVirtual\Security\RateLimiter::exceeded( $bucket, 30 ) ) {
			status_header( 429 );
			wp_die( esc_html__( 'Demasiadas consultas. Intentalo de nuevo en una hora.', 'aula-virtual' ), '', array( 'response' => 429 ) );
		}

		$certificate = $this->certificates->verify( $code );

		if ( null === $certificate ) {
			\SIQA\AulaVirtual\Security\RateLimiter::hit( $bucket, 1000 );

			status_header( 404 );
			$this->template( $this->invalid_data( $code ) );
			exit;
		}

		status_header( 200 );
		$this->template( $this->valid_data( $certificate ) );
		exit;
	}

	/**
	 * Data for a valid certificate.
	 *
	 * @param array<string, mixed> $certificate Certificate row.
	 * @return array<string, mixed>
	 */
	private function valid_data( array $certificate ): array {
		$user      = get_userdata( (int) $certificate['user_id'] );
		$edition   = $this->editions->find( (int) $certificate['edition_id'] );
		$course_id = (int) $certificate['course_id'];
		$issued_at = (string) ( $certificate['issued_at'] ?? '' );

		$student_name = '';

		if ( false !== $user ) {
			$full         = trim( (string) $user->first_name . ' ' . (string) $user->last_name );
			$student_name = '' !== $full ? $full : (string) $user->display_name;
		}

		return array_merge(
			$this->branding(),
			array(
				'valid'            => true,
				'certificate'      => $certificate,
				'code'             => (string) $certificate['certificate_code'],
				'student_name'     => $student_name,
				'course_title'     => $course_id > 0 ? (string) get_the_title( $course_id ) : '',
				'edition_name'     => null !== $edition ? (string) $edition['name'] : '',
				'issued_date'      => '' !== $issued_at ? (string) date_i18n( (string) get_option( 'date_format' ), (int) strtotime( $issued_at . ' UTC' ) ) : '',
				'verification_url' => $this->certificates->url( $certificate ),
				'message'          => '',
			)
		);
	}

	/**
	 * Data for an unknown or revoked code.
	 *
	 * @param string $code Normalised code.
	 * @return array<string, mixed>
	 */
	private function invalid_data( string $code ): array {
		return array_merge(
			$this->branding(),
			array(
				'valid'            => false,
				'certificate'      => null,
				'code'             => $code,
				'student_name'     => '',
				'course_title'     => '',
				'edition_name'     => '',
				'issued_date'      => '',
				'verification_url' => home_url( '/certificado/' . rawurlencode( $code ) . '/' ),
				'message'          => __( 'Este certificado no es valido. El codigo no existe o fue anulado por la institucion.', 'aula-virtual' ),
			)
		);
	}

	/**
	 * Site branding shared by both states of the page.
	 *
	 * @return array<string, mixed>
	 */
	private function branding(): array {
		$color = (string) get_option( 'av_brand_color', '#1d4ed8' );

		if ( ! preg_match( '/^#[0-9a-fA-F]{6}$/', $color ) ) {
			$color = '#1d4ed8';
		}

		$logo_id  = (int) get_option( CertificateService::OPTION_LOGO, 0 );
		$logo_url = $logo_id > 0 ? (string) wp_get_attachment_image_url( $logo_id, 'medium' ) : '';

		return array(
			'site_name'       => wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES ),
			'brand_color'     => $color,
			'signature_name'  => (string) get_option( CertificateService::OPTION_SIGNATURE_NAME, '' ),
			'signature_title' => (string) get_option( CertificateService::OPTION_SIGNATURE_TITLE, '' ),
			'logo_url'        => $logo_url,
		);
	}

	/**
	 * Loads the certificate template, letting the theme override it.
	 *
	 * @param array<string, mixed> $data Variables exposed to the template.
	 * @return void
	 */
	private function template( array $data ): void {
		$override = locate_template( array( 'aula-virtual/certificates/certificate.php' ) );
		$path     = '' !== $override ? $override : AV_PATH . 'templates/certificates/certificate.php';

		if ( ! is_readable( $path ) ) {
			return;
		}

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- controlled template data.
		extract( $data, EXTR_SKIP );

		require $path;
	}
}
