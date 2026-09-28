<?php
/**
 * Minimal WordPress stubs so the core classes can be exercised without a
 * WordPress installation. Only the functions touched by the smoke test are
 * defined; anything else would hide a real integration problem.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'AV_VERSION', '0.1.0' );

$GLOBALS['av_test_options'] = array();

/**
 * Stub $wpdb exposing only the API the core relies on.
 */
class AV_Test_WPDB {

	public string $prefix = 'wp_';

	public string $options = 'wp_options';

	public int $insert_id = 0;

	/** @var array<int, string> */
	public array $queries = array();

	public function get_charset_collate(): string {
		return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci';
	}

	public function esc_like( string $text ): string {
		return addcslashes( $text, '_%\\' );
	}

	/**
	 * Very small prepare() emulation: enough to assert placeholder counts.
	 *
	 * @param string            $query  Query with placeholders.
	 * @param array<int, mixed> ...$args Values.
	 * @return string
	 */
	public function prepare( string $query, ...$args ): string {
		$values = ( 1 === count( $args ) && is_array( $args[0] ) ) ? $args[0] : $args;

		$this->queries[] = $query;

		return preg_replace_callback(
			'/%[dfs]/',
			static function ( array $match ) use ( &$values ): string {
				$value = array_shift( $values );

				if ( '%d' === $match[0] ) {
					return (string) (int) $value;
				}

				if ( '%f' === $match[0] ) {
					return (string) (float) $value;
				}

				return "'" . addslashes( (string) $value ) . "'";
			},
			$query
		);
	}
}

$GLOBALS['wpdb'] = new AV_Test_WPDB();

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}

function esc_url_raw( $url ) {
	$url = trim( (string) $url );

	if ( '' === $url ) {
		return '';
	}

	// WordPress solo admite una lista de protocolos; javascript: y data: no estan.
	if ( preg_match( '/^([a-z][a-z0-9+.-]*):/i', $url, $m ) && ! in_array( strtolower( $m[1] ), array( 'http', 'https', 'mailto', 'tel' ), true ) ) {
		return '';
	}

	return filter_var( $url, FILTER_SANITIZE_URL );
}

function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}

function sanitize_title( $title ) {
	return trim( preg_replace( '/[^a-z0-9\-]+/', '-', strtolower( (string) $title ) ), '-' );
}

function sanitize_text_field( $text ) {
	return trim( preg_replace( '/[\r\n\t]+/', ' ', wp_strip_all_tags( (string) $text ) ) );
}

function sanitize_textarea_field( $text ) {
	return trim( wp_strip_all_tags( (string) $text ) );
}

function sanitize_email( $email ) {
	return trim( (string) $email );
}

function is_email( $email ) {
	return false !== filter_var( (string) $email, FILTER_VALIDATE_EMAIL );
}

function wp_strip_all_tags( $text ) {
	return strip_tags( (string) $text );
}

function wp_kses_post( $text ) {
	return strip_tags( (string) $text, '<p><a><strong><em><ul><ol><li><br>' );
}

function rest_sanitize_boolean( $value ) {
	if ( is_string( $value ) ) {
		return ! in_array( strtolower( $value ), array( '', '0', 'false', 'off', 'no' ), true );
	}

	return (bool) $value;
}

function wp_json_encode( $data ) {
	return json_encode( $data );
}

function current_time( $type, $gmt = 0 ) {
	return gmdate( 'mysql' === $type ? 'Y-m-d H:i:s' : 'U' );
}

function get_current_user_id() {
	return 1;
}

function get_option( $name, $default = false ) {
	return $GLOBALS['av_test_options'][ $name ] ?? $default;
}

function update_option( $name, $value, $autoload = null ) {
	$GLOBALS['av_test_options'][ $name ] = $value;

	return true;
}

function add_option( $name, $value, $deprecated = '', $autoload = null ) {
	if ( array_key_exists( $name, $GLOBALS['av_test_options'] ) ) {
		return false;
	}

	return update_option( $name, $value );
}

function delete_option( $name ) {
	unset( $GLOBALS['av_test_options'][ $name ] );

	return true;
}

function apply_filters( $hook, $value, ...$args ) {
	return $value;
}

function do_action( $hook, ...$args ) {
}

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
}

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
}

function __( $text, $domain = 'default' ) {
	return $text;
}

function esc_html__( $text, $domain = 'default' ) {
	return $text;
}

function wp_get_attachment_url( $id ) {
	return 'https://example.test/uploads/' . (int) $id . '.mp4';
}

define( 'HOUR_IN_SECONDS', 3600 );

function esc_url( $url ) {
	return esc_url_raw( $url );
}

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( (string) $url, $component );
}

function wp_oembed_get( $url ) {
	// Sin red en las pruebas: solo YouTube y Vimeo "responden".
	$host = (string) parse_url( (string) $url, PHP_URL_HOST );

	// Como WordPress: YouTube responde con youtube.com/embed y Vimeo con player.vimeo.com.
	if ( str_contains( $host, 'youtu' ) ) {
		return '<iframe width="200" height="113" src="https://www.youtube.com/embed/abc?feature=oembed" frameborder="0" allowfullscreen></iframe>';
	}

	if ( str_contains( $host, 'vimeo' ) ) {
		return '<iframe src="https://player.vimeo.com/video/1" frameborder="0"></iframe>';
	}

	return false;
}

function do_shortcode( $content ) {
	return (string) $content;
}

function wp_kses( $content, $allowed ) {
	return strip_tags( (string) $content, '<iframe>' );
}

function add_query_arg( ...$args ) {
	if ( is_array( $args[0] ) ) {
		$params = $args[0];
		$url    = (string) ( $args[1] ?? '' );
	} else {
		$params = array( (string) $args[0] => $args[1] );
		$url    = (string) ( $args[2] ?? '' );
	}

	$parts = parse_url( $url );
	$query = array();

	if ( ! empty( $parts['query'] ) ) {
		parse_str( $parts['query'], $query );
	}

	foreach ( $params as $k => $v ) {
		if ( false === $v ) {
			unset( $query[ $k ] );
		} else {
			$query[ $k ] = $v;
		}
	}

	$base = ( isset( $parts['scheme'] ) ? $parts['scheme'] . '://' : '' ) . ( $parts['host'] ?? '' ) . ( $parts['path'] ?? '' );

	return $base . ( array() === $query ? '' : '?' . http_build_query( $query ) );
}


function admin_url( $path = '' ) {
	return 'https://example.test/wp-admin/' . ltrim( (string) $path, '/' );
}

// Clases REST de WordPress que los controladores extienden o reciben.
class WP_REST_Response {

	public function __construct( public mixed $data = null ) {
	}

	public function header( string $name, string $value ): void {
	}
}

class WP_REST_Request {

	public function get_param( string $key ): mixed {
		return null;
	}
}

abstract class WP_REST_Controller {

	protected string $namespace = '';

	protected string $rest_base = '';

	protected ?array $schema = null;

	public function add_additional_fields_schema( $schema ) {
		return $schema;
	}

	public function get_public_item_schema() {
		return array();
	}
}
