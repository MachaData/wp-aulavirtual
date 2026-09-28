<?php
/**
 * Public registration form.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Enrollments;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serves /inscripcion/{token}/ and handles its submission.
 *
 * The page is rendered inside the active theme (header and footer) so the
 * link an institution shares looks like the rest of its site.
 */
final class RegistrationController {

	public const QUERY_VAR      = 'av_registration_token';
	public const ACTION_SUBMIT  = 'av_register';
	public const QUERY_RESULT   = 'av_resultado';

	/**
	 * Registration rules.
	 *
	 * @var RegistrationService
	 */
	private RegistrationService $registration;

	/**
	 * Constructor.
	 *
	 * @param RegistrationService $registration Registration rules.
	 */
	public function __construct( RegistrationService $registration ) {
		$this->registration = $registration;
	}

	/**
	 * Registers the pretty URL of the form.
	 *
	 * @return void
	 */
	public static function register_rewrite(): void {
		add_rewrite_tag( '%' . self::QUERY_VAR . '%', '([A-Za-z0-9]+)' );
		add_rewrite_rule( '^inscripcion/([A-Za-z0-9]+)/?$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top' );
	}

	/**
	 * Renders the form when the URL carries a token.
	 *
	 * @return void
	 */
	public function maybe_render(): void {
		$token = get_query_var( self::QUERY_VAR, '' );

		if ( ! is_string( $token ) || '' === $token ) {
			return;
		}

		nocache_headers();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag set by our own redirect.
		$result = isset( $_GET[ self::QUERY_RESULT ] ) ? sanitize_key( wp_unslash( $_GET[ self::QUERY_RESULT ] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only message set by our own redirect.
		$message = isset( $_GET['av_mensaje'] ) ? sanitize_text_field( wp_unslash( $_GET['av_mensaje'] ) ) : '';

		$link = $this->registration->usable_link( $token );

		status_header( 200 );
		get_header();

		if ( 'ok' === $result ) {
			$this->template( 'success', array( 'link' => $link instanceof WP_Error ? null : $link ) );
		} elseif ( $link instanceof WP_Error ) {
			$this->template( 'closed', array( 'message' => $link->get_error_message() ) );
		} else {
			$this->template(
				'form',
				array(
					'link'    => $link,
					'edition' => $link['edition'],
					'course'  => get_post( (int) $link['course_id'] ),
					'token'   => $token,
					'error'   => 'error' === $result ? $message : '',
					'action'  => admin_url( 'admin-post.php' ),
				)
			);
		}

		get_footer();
		exit;
	}

	/**
	 * Handles the form submission (logged in or not).
	 *
	 * @return void
	 */
	public function handle_submit(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified right below.
		$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		$token = preg_replace( '/[^A-Za-z0-9]/', '', $token );

		check_admin_referer( self::ACTION_SUBMIT . '_' . $token );

		// Honeypot: bots fill every field, people never see this one.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		if ( ! empty( $_POST['av_website'] ) ) {
			$this->redirect( (string) $token, 'ok' );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$result = $this->registration->submit( (string) $token, wp_unslash( $_POST ) );

		if ( $result instanceof WP_Error ) {
			$this->redirect( (string) $token, 'error', $result->get_error_message() );
		}

		$this->redirect( (string) $token, 'ok' );
	}

	/**
	 * Sends the visitor back to the form with a result flag.
	 *
	 * @param string $token   Link token.
	 * @param string $result  ok or error.
	 * @param string $message Message for errors.
	 * @return void
	 */
	private function redirect( string $token, string $result, string $message = '' ): void {
		$args = array( self::QUERY_RESULT => $result );

		if ( '' !== $message ) {
			$args['av_mensaje'] = rawurlencode( $message );
		}

		wp_safe_redirect( add_query_arg( $args, RegistrationService::link_url( $token ) ) );
		exit;
	}

	/**
	 * Loads a registration template, letting the theme override it.
	 *
	 * @param string               $name Template name.
	 * @param array<string, mixed> $data Variables exposed to the template.
	 * @return void
	 */
	private function template( string $name, array $data ): void {
		$override = locate_template( array( 'aula-virtual/registration/' . $name . '.php' ) );
		$path     = '' !== $override ? $override : AV_PATH . 'templates/registration/' . $name . '.php';

		if ( ! is_readable( $path ) ) {
			return;
		}

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- controlled template data.
		extract( $data, EXTR_SKIP );

		require $path;
	}
}
