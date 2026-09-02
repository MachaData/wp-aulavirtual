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
			'' === $message ? __( 'No tienes permisos para realizar esta accion.', 'aula-virtual' ) : $message,
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
	 * Common collection parameters shared by list endpoints.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_collection_params() {
		return array(
			'page'     => array(
				'description'       => __( 'Pagina solicitada.', 'aula-virtual' ),
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
			),
			'per_page' => array(
				'description'       => __( 'Elementos por pagina.', 'aula-virtual' ),
				'type'              => 'integer',
				'default'           => 20,
				'minimum'           => 1,
				'maximum'           => 100,
				'sanitize_callback' => 'absint',
			),
			'search'   => array(
				'description'       => __( 'Texto de busqueda.', 'aula-virtual' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}
}
