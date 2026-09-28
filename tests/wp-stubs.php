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
	return filter_var( (string) $url, FILTER_SANITIZE_URL );
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
