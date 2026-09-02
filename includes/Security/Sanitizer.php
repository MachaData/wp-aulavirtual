<?php
/**
 * Sanitisation helpers.
 *
 * @package SEV\LMS
 */

declare( strict_types = 1 );

namespace SEV\LMS\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalises untrusted input before it reaches the database.
 *
 * Every value that arrives from a form, a REST request or an imported file
 * passes through one of these helpers. Escaping for output is a separate
 * concern handled at render time.
 */
final class Sanitizer {

	/**
	 * Sanitises a single line of text.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function text( mixed $value ): string {
		return sanitize_text_field( (string) ( is_scalar( $value ) ? $value : '' ) );
	}

	/**
	 * Sanitises a multi-line plain text value.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function textarea( mixed $value ): string {
		return sanitize_textarea_field( (string) ( is_scalar( $value ) ? $value : '' ) );
	}

	/**
	 * Sanitises rich content, keeping the markup allowed in posts.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function html( mixed $value ): string {
		return wp_kses_post( (string) ( is_scalar( $value ) ? $value : '' ) );
	}

	/**
	 * Sanitises a URL.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function url( mixed $value ): string {
		return esc_url_raw( trim( (string) ( is_scalar( $value ) ? $value : '' ) ) );
	}

	/**
	 * Sanitises an email address, returning an empty string when invalid.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function email( mixed $value ): string {
		$email = sanitize_email( (string) ( is_scalar( $value ) ? $value : '' ) );

		return is_email( $email ) ? $email : '';
	}

	/**
	 * Sanitises an integer.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public static function int( mixed $value ): int {
		return (int) ( is_scalar( $value ) ? $value : 0 );
	}

	/**
	 * Sanitises a boolean flag.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	public static function bool( mixed $value ): bool {
		return (bool) rest_sanitize_boolean( $value );
	}

	/**
	 * Sanitises a slug-like key.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function key( mixed $value ): string {
		return sanitize_key( (string) ( is_scalar( $value ) ? $value : '' ) );
	}

	/**
	 * Sanitises a value against a closed list of options.
	 *
	 * @param mixed              $value    Raw value.
	 * @param array<int, string> $allowed  Allowed values.
	 * @param string             $fallback Value returned when the input is not allowed.
	 * @return string
	 */
	public static function enum( mixed $value, array $allowed, string $fallback ): string {
		$value = self::key( $value );

		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	/**
	 * Sanitises a MySQL datetime string, returning null when invalid.
	 *
	 * @param mixed $value Raw value.
	 * @return string|null
	 */
	public static function datetime( mixed $value ): ?string {
		$value = trim( (string) ( is_scalar( $value ) ? $value : '' ) );

		if ( '' === $value ) {
			return null;
		}

		$timestamp = strtotime( $value );

		return false === $timestamp ? null : gmdate( 'Y-m-d H:i:s', $timestamp );
	}

	/**
	 * Sanitises a flat list of text values.
	 *
	 * @param mixed $value Raw value.
	 * @return array<int, string>
	 */
	public static function text_list( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$items = array_map( array( self::class, 'text' ), $value );

		return array_values( array_filter( $items, static fn( string $item ): bool => '' !== $item ) );
	}

	/**
	 * Sanitises a list of question and answer pairs.
	 *
	 * @param mixed $value Raw value.
	 * @return array<int, array{question: string, answer: string}>
	 */
	public static function faq_list( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$faq = array();

		foreach ( $value as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$question = self::text( $item['question'] ?? '' );
			$answer   = self::html( $item['answer'] ?? '' );

			if ( '' === $question ) {
				continue;
			}

			$faq[] = array(
				'question' => $question,
				'answer'   => $answer,
			);
		}

		return $faq;
	}
}
