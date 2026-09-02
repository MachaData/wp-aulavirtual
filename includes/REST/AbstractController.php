<?php
/**
 * Base REST controller.
 *
 * @package SEV\LMS
 */

declare( strict_types = 1 );

namespace SEV\LMS\REST;

use WP_Error;
use WP_REST_Controller;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared behaviour for every SEV LMS REST controller.
 *
 * Controllers must declare a `permission_callback` on every route; the helpers
 * here keep those callbacks one-liners and make the error payloads consistent
 * across endpoints.
 */
abstract class AbstractController extends WP_REST_Controller {

	/**
	 * REST namespace shared by the plugin.
	 */
	public const NAMESPACE_V1 = 'sev-lms/v1';

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
			'sev_lms_forbidden',
			'' === $message ? __( 'No tienes permisos para realizar esta accion.', 'sev-lms' ) : $message,
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
			'sev_lms_not_found',
			'' === $message ? __( 'El recurso solicitado no existe.', 'sev-lms' ) : $message,
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
			'sev_lms_invalid_request',
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
	 * Common collection parameters shared by list endpoints.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_collection_params() {
		return array(
			'page'     => array(
				'description'       => __( 'Pagina solicitada.', 'sev-lms' ),
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
			),
			'per_page' => array(
				'description'       => __( 'Elementos por pagina.', 'sev-lms' ),
				'type'              => 'integer',
				'default'           => 20,
				'minimum'           => 1,
				'maximum'           => 100,
				'sanitize_callback' => 'absint',
			),
			'search'   => array(
				'description'       => __( 'Texto de busqueda.', 'sev-lms' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}
}
