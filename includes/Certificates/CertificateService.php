<?php
/**
 * Certificate rules.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Certificates;

use SIQA\AulaVirtual\Core\AuditLog;
use SIQA\AulaVirtual\Core\Events\EventBus;
use SIQA\AulaVirtual\Core\Events\Events;
use SIQA\AulaVirtual\Editions\EditionRepository;
use SIQA\AulaVirtual\Enrollments\EnrollmentRepository;
use SIQA\AulaVirtual\Enrollments\EnrollmentStatus;
use SIQA\AulaVirtual\Security\Sanitizer;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Issues, revokes and verifies certificates.
 *
 * A certificate is a row with a public code: the printable page is rendered
 * on demand from that row, so nothing is stored on disk and a revoked
 * certificate stops validating immediately.
 */
final class CertificateService {

	public const OPTION_AUTO_ISSUE      = 'av_auto_certificate';
	public const OPTION_SIGNATURE_NAME  = 'av_certificate_signature_name';
	public const OPTION_SIGNATURE_TITLE = 'av_certificate_signature_title';
	public const OPTION_LOGO            = 'av_certificate_logo';

	public const CODE_PREFIX = 'AV';
	public const CODE_REGEX  = '/^AV-\d{4}-[A-Z0-9]{6}$/';

	public const AUDIT_REVOKED = 'certificate.revoked';

	/**
	 * Characters used in the random part of a code.
	 */
	private const CODE_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

	/**
	 * Certificate persistence.
	 *
	 * @var CertificateRepository
	 */
	private CertificateRepository $certificates;

	/**
	 * Enrollment persistence.
	 *
	 * @var EnrollmentRepository
	 */
	private EnrollmentRepository $enrollments;

	/**
	 * Edition persistence.
	 *
	 * @var EditionRepository
	 */
	private EditionRepository $editions;

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
	 * @param CertificateRepository $certificates Certificate persistence.
	 * @param EnrollmentRepository  $enrollments  Enrollment persistence.
	 * @param EditionRepository     $editions     Edition persistence.
	 * @param EventBus              $events       Domain events.
	 * @param AuditLog              $audit        Audit trail.
	 */
	public function __construct(
		CertificateRepository $certificates,
		EnrollmentRepository $enrollments,
		EditionRepository $editions,
		EventBus $events,
		AuditLog $audit
	) {
		$this->certificates = $certificates;
		$this->enrollments  = $enrollments;
		$this->editions     = $editions;
		$this->events       = $events;
		$this->audit        = $audit;
	}

	/**
	 * Whether certificates are issued automatically when a student finishes.
	 *
	 * @return bool
	 */
	public static function auto_issue_enabled(): bool {
		return Sanitizer::bool( get_option( self::OPTION_AUTO_ISSUE, true ) );
	}

	/**
	 * Issues a certificate to a student for an edition.
	 *
	 * Idempotent: when the student already holds a valid certificate for the
	 * edition, its id is returned and nothing else happens. An automatic issue
	 * ($issued_by = 0) requires a completed enrollment; a manual issue by staff
	 * accepts any enrollment that grants access.
	 *
	 * @param int  $user_id    Student id.
	 * @param int  $edition_id Edition id.
	 * @param int  $issued_by  Staff user id for manual issues, 0 when automatic.
	 * @param bool $silent     Whether the notification emails should be skipped.
	 * @return int|WP_Error Certificate id.
	 */
	public function issue( int $user_id, int $edition_id, int $issued_by = 0, bool $silent = false ) {
		if ( $user_id <= 0 || $edition_id <= 0 ) {
			return new WP_Error( 'av_certificate_invalid_input', __( 'Selecciona un alumno y una edicion.', 'aula-virtual' ), array( 'status' => 400 ) );
		}

		$edition = $this->editions->find( $edition_id );

		if ( null === $edition ) {
			return new WP_Error( 'av_edition_not_found', __( 'La edicion no existe.', 'aula-virtual' ), array( 'status' => 404 ) );
		}

		if ( false === get_userdata( $user_id ) ) {
			return new WP_Error( 'av_user_not_found', __( 'El alumno no existe.', 'aula-virtual' ), array( 'status' => 404 ) );
		}

		$enrollment = $this->enrollments->find_for_student( $user_id, $edition_id );

		if ( null === $enrollment ) {
			return new WP_Error( 'av_enrollment_not_found', __( 'El alumno no esta matriculado en esa edicion.', 'aula-virtual' ), array( 'status' => 404 ) );
		}

		$status = (string) $enrollment['status'];

		if ( $issued_by > 0 ) {
			if ( ! EnrollmentStatus::grants_access( $status ) ) {
				return new WP_Error( 'av_enrollment_without_access', __( 'La matricula no esta activa; no se puede emitir el certificado.', 'aula-virtual' ), array( 'status' => 409 ) );
			}
		} elseif ( EnrollmentStatus::COMPLETED !== $status ) {
			return new WP_Error( 'av_enrollment_not_completed', __( 'El alumno todavia no ha completado el curso.', 'aula-virtual' ), array( 'status' => 409 ) );
		}

		$existing = $this->certificates->find_issued_for( $user_id, $edition_id );

		if ( null !== $existing ) {
			return (int) $existing['id'];
		}

		$code = $this->generate_code();

		if ( '' === $code ) {
			return new WP_Error( 'av_certificate_code_failed', __( 'No se pudo generar un codigo de certificado unico.', 'aula-virtual' ) );
		}

		$course_id = (int) $edition['course_id'];

		$certificate_id = $this->certificates->insert(
			array(
				'user_id'           => $user_id,
				'course_id'         => $course_id,
				'edition_id'        => $edition_id,
				'certificate_code'  => $code,
				'verification_hash' => self::hash( $code, $user_id, $edition_id ),
				'file_url'          => '',
				'status'            => CertificateRepository::STATUS_ISSUED,
				'issued_at'         => current_time( 'mysql', true ),
				'issued_by'         => max( 0, $issued_by ),
				'revoked_at'        => null,
			)
		);

		if ( 0 === $certificate_id ) {
			return new WP_Error( 'av_certificate_not_saved', __( 'No se pudo guardar el certificado.', 'aula-virtual' ) );
		}

		$certificate = $this->certificates->find( $certificate_id );
		$url         = null !== $certificate ? $this->url( $certificate ) : '';

		$this->audit->record(
			AuditLog::CERTIFICATE_ISSUED,
			'certificate',
			$certificate_id,
			array(
				'edition_id' => $edition_id,
				'course_id'  => $course_id,
				'code'       => $code,
				'automatic'  => 0 === $issued_by,
			),
			$user_id
		);

		$this->events->dispatch(
			Events::CERTIFICATE_ISSUED,
			array(
				'user_id'          => $user_id,
				'edition_id'       => $edition_id,
				'course_id'        => $course_id,
				'enrollment_id'    => (int) $enrollment['id'],
				'certificate_id'   => $certificate_id,
				'certificate_code' => $code,
				'certificate_url'  => $url,
				'silent'           => $silent,
			)
		);

		return $certificate_id;
	}

	/**
	 * Revokes a certificate so its public page stops validating.
	 *
	 * @param int $certificate_id Certificate id.
	 * @return true|WP_Error
	 */
	public function revoke( int $certificate_id ) {
		$certificate = $this->certificates->find( $certificate_id );

		if ( null === $certificate ) {
			return new WP_Error( 'av_certificate_not_found', __( 'El certificado no existe.', 'aula-virtual' ), array( 'status' => 404 ) );
		}

		if ( CertificateRepository::STATUS_REVOKED === (string) $certificate['status'] ) {
			return true;
		}

		$updated = $this->certificates->update(
			$certificate_id,
			array(
				'status'     => CertificateRepository::STATUS_REVOKED,
				'revoked_at' => current_time( 'mysql', true ),
			)
		);

		if ( ! $updated ) {
			return new WP_Error( 'av_certificate_not_revoked', __( 'No se pudo anular el certificado.', 'aula-virtual' ) );
		}

		$this->audit->record(
			self::AUDIT_REVOKED,
			'certificate',
			$certificate_id,
			array(
				'edition_id' => (int) $certificate['edition_id'],
				'course_id'  => (int) $certificate['course_id'],
				'code'       => (string) $certificate['certificate_code'],
			),
			(int) $certificate['user_id']
		);

		return true;
	}

	/**
	 * Returns the certificate behind a code when it is valid.
	 *
	 * @param string $code Public code, as typed or taken from the URL.
	 * @return array<string, mixed>|null Null when unknown or revoked.
	 */
	public function verify( string $code ): ?array {
		$code = self::normalize_code( $code );

		if ( '' === $code ) {
			return null;
		}

		$certificate = $this->certificates->find_by_code( $code );

		if ( null === $certificate || CertificateRepository::STATUS_ISSUED !== (string) $certificate['status'] ) {
			return null;
		}

		if ( ! hash_equals( (string) $certificate['verification_hash'], self::hash( $code, (int) $certificate['user_id'], (int) $certificate['edition_id'] ) ) ) {
			return null;
		}

		return $certificate;
	}

	/**
	 * Public verification URL of a certificate.
	 *
	 * @param array<string, mixed> $certificate Certificate row.
	 * @return string
	 */
	public function url( array $certificate ): string {
		$code = self::normalize_code( (string) ( $certificate['certificate_code'] ?? '' ) );

		return home_url( '/certificado/' . rawurlencode( $code ) . '/' );
	}

	/**
	 * Issues the certificate when a student finishes a course, if enabled.
	 *
	 * Listener of Events::COURSE_COMPLETED.
	 *
	 * @param array<string, mixed> $payload Event payload with user_id and edition_id.
	 * @return void
	 */
	public function on_course_completed( array $payload ): void {
		if ( ! self::auto_issue_enabled() ) {
			return;
		}

		$user_id    = Sanitizer::int( $payload['user_id'] ?? 0 );
		$edition_id = Sanitizer::int( $payload['edition_id'] ?? 0 );

		if ( $user_id <= 0 || $edition_id <= 0 ) {
			return;
		}

		$this->issue( $user_id, $edition_id, 0, Sanitizer::bool( $payload['silent'] ?? false ) );
	}

	/**
	 * Cleans a code coming from user input: uppercase, only letters, digits
	 * and dashes.
	 *
	 * @param string $code Raw code.
	 * @return string
	 */
	public static function normalize_code( string $code ): string {
		$code = strtoupper( trim( $code ) );

		return (string) preg_replace( '/[^A-Z0-9-]/', '', $code );
	}

	/**
	 * Whether a string has the shape of a certificate code.
	 *
	 * @param string $code Code.
	 * @return bool
	 */
	public static function is_valid_code( string $code ): bool {
		return 1 === preg_match( self::CODE_REGEX, $code );
	}

	/**
	 * Builds the tamper-evidence hash stored with the certificate.
	 *
	 * @param string $code       Certificate code.
	 * @param int    $user_id    Student id.
	 * @param int    $edition_id Edition id.
	 * @return string
	 */
	public static function hash( string $code, int $user_id, int $edition_id ): string {
		return wp_hash( $code . $user_id . $edition_id );
	}

	/**
	 * Generates a unique code such as AV-2026-K7Q2ZM.
	 *
	 * @return string Empty when no unique code was found after a few tries.
	 */
	private function generate_code(): string {
		$year = gmdate( 'Y' );

		for ( $attempt = 0; $attempt < 10; $attempt++ ) {
			$random = '';

			for ( $i = 0; $i < 6; $i++ ) {
				$random .= self::CODE_ALPHABET[ random_int( 0, strlen( self::CODE_ALPHABET ) - 1 ) ];
			}

			$code = self::CODE_PREFIX . '-' . $year . '-' . $random;

			if ( null === $this->certificates->find_by_code( $code ) ) {
				return $code;
			}
		}

		return '';
	}
}
