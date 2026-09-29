<?php
/**
 * Base REST controller.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\REST;

use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared behaviour for every Aula Virtual REST controller.
 *
 * Controllers must declare a `permission_callback` on every route; the helpers
 * here keep those callbacks one-liners and make the error payloads consistent
 * across endpoints.
 */
abstract class AbstractController extends WP_REST_Controller {

	/**
	 * REST namespace shared by the plugin.
	 */
	public const NAMESPACE_V1 = 'aula-virtual/v1';

	/**
	 * Option holding the shared integration key of external sites.
	 */
	public const OPTION_INTEGRATION_KEY = 'av_integration_key';

	/**
	 * Header that carries the integration key.
	 */
	public const HEADER_INTEGRATION_KEY = 'X-AV-Key';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = self::NAMESPACE_V1;
	}

	/**
	 * Builds a forbidden error response.
	 *
	 * @param string $message Message shown to the client.
	 * @return WP_Error
	 */
	protected function forbidden( string $message = '' ): WP_Error {
		return new WP_Error(
			'av_forbidden',
			'' === $message ? __( 'No tienes permisos para realizar esta acción.', 'aula-virtual' ) : $message,
			array( 'status' => is_user_logged_in() ? 403 : 401 )
		);
	}

	/**
	 * Builds a not found error response.
	 *
	 * @param string $message Message shown to the client.
	 * @return WP_Error
	 */
	protected function not_found( string $message = '' ): WP_Error {
		return new WP_Error(
			'av_not_found',
			'' === $message ? __( 'El recurso solicitado no existe.', 'aula-virtual' ) : $message,
			array( 'status' => 404 )
		);
	}

	/**
	 * Builds an invalid input error response.
	 *
	 * @param string               $message Message shown to the client.
	 * @param array<string, mixed> $data    Extra error data.
	 * @return WP_Error
	 */
	protected function invalid( string $message, array $data = array() ): WP_Error {
		return new WP_Error(
			'av_invalid_request',
			$message,
			array_merge( array( 'status' => 400 ), $data )
		);
	}

	/**
	 * Checks a capability and returns the error to hand back to REST.
	 *
	 * @param string $capability Capability required.
	 * @return true|WP_Error
	 */
	protected function require_capability( string $capability ) {
		return current_user_can( $capability ) ? true : $this->forbidden();
	}

	/**
	 * Wraps a paginated repository result into a REST response.
	 *
	 * @param array<int, mixed>                                      $items  Prepared items.
	 * @param array{total: int, pages: int, page: int, per_page: int} $paging Paging data.
	 * @return WP_REST_Response
	 */
	protected function paginated_response( array $items, array $paging ): WP_REST_Response {
		$response = rest_ensure_response( $items );

		$response->header( 'X-WP-Total', (string) $paging['total'] );
		$response->header( 'X-WP-TotalPages', (string) $paging['pages'] );

		return $response;
	}

	/**
	 * Whether the request carries the shared integration key.
	 *
	 * The key lives in the `av_integration_key` option and travels in the
	 * `X-AV-Key` header. It is compared in constant time and is never logged
	 * nor echoed back. An empty option disables key access altogether.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	protected function integration_key_valid( WP_REST_Request $request ): bool {
		$stored = get_option( self::OPTION_INTEGRATION_KEY, '' );
		$stored = is_string( $stored ) ? trim( $stored ) : '';

		if ( '' === $stored ) {
			return false;
		}

		$provided = $request->get_header( self::HEADER_INTEGRATION_KEY );

		if ( ! is_string( $provided ) || '' === $provided ) {
			return false;
		}

		return hash_equals( $stored, trim( $provided ) );
	}

	/**
	 * Best-effort client address used for rate limiting.
	 *
	 * Only `REMOTE_ADDR` is trusted; a site behind a proxy can resolve the
	 * real address through the filter.
	 *
	 * @return string
	 */
	protected function client_ip(): string {
		return \SIQA\AulaVirtual\Security\RateLimiter::client_ip();
	}

	/**
	 * Builds a "too many requests" error response.
	 *
	 * @param string $message Message shown to the client.
	 * @return WP_Error
	 */
	protected function too_many_requests( string $message = '' ): WP_Error {
		return new WP_Error(
			'av_rate_limited',
			'' === $message ? __( 'Demasiadas solicitudes. Intenta de nuevo más tarde.', 'aula-virtual' ) : $message,
			array( 'status' => 429 )
		);
	}

	/**
	 * Common collection parameters shared by list endpoints.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_collection_params() {
		return array(
			'page'     => array(
				'description'       => __( 'Página solicitada.', 'aula-virtual' ),
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
			),
			'per_page' => array(
				'description'       => __( 'Elementos por página.', 'aula-virtual' ),
				'type'              => 'integer',
				'default'           => 20,
				'minimum'           => 1,
				'maximum'           => 100,
				'sanitize_callback' => 'absint',
			),
			'search'   => array(
				'description'       => __( 'Texto de búsqueda.', 'aula-virtual' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}
}
