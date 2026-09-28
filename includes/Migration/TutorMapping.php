<?php
/**
 * Pure value mappings from Tutor LMS to Aula Virtual.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Migration;

use SIQA\AulaVirtual\Enrollments\EnrollmentStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Translates Tutor's meta values into the plugin's vocabulary.
 *
 * Kept free of database access so every rule here is covered by the smoke
 * test before it touches real student data.
 */
final class TutorMapping {

	/**
	 * Maps Tutor's `_video` meta to a provider and a URL.
	 *
	 * @param mixed $video Tutor meta value (array with `source` and `source_*` keys).
	 * @return array{provider: string, url: string, minutes: int}
	 */
	public static function video( mixed $video ): array {
		$empty = array(
			'provider' => '',
			'url'      => '',
			'minutes'  => 0,
		);

		if ( ! is_array( $video ) ) {
			return $empty;
		}

		$source = isset( $video['source'] ) ? strtolower( (string) $video['source'] ) : '';

		$map = array(
			'youtube'      => array( 'youtube', 'source_youtube' ),
			'vimeo'        => array( 'vimeo', 'source_vimeo' ),
			'external_url' => array( 'url', 'source_external_url' ),
			'embedded'     => array( 'embed', 'source_embedded' ),
			'html5'        => array( 'html5', 'source_html5' ),
			'shortcode'    => array( 'shortcode', 'source_shortcode' ),
		);

		if ( ! isset( $map[ $source ] ) ) {
			return $empty;
		}

		[ $provider, $key ] = $map[ $source ];

		$url = isset( $video[ $key ] ) ? trim( (string) $video[ $key ] ) : '';

		if ( 'html5' === $provider && ctype_digit( $url ) ) {
			$attachment = wp_get_attachment_url( (int) $url );
			$url        = is_string( $attachment ) ? $attachment : '';
		}

		$runtime = isset( $video['runtime'] ) && is_array( $video['runtime'] ) ? $video['runtime'] : array();
		$minutes = (int) ( $runtime['hours'] ?? 0 ) * 60 + (int) ( $runtime['minutes'] ?? 0 );

		if ( 0 === $minutes && (int) ( $runtime['seconds'] ?? 0 ) > 0 ) {
			$minutes = 1;
		}

		return array(
			'provider' => '' === $url ? '' : $provider,
			'url'      => $url,
			'minutes'  => $minutes,
		);
	}

	/**
	 * Maps Tutor's enrollment post status to the plugin's status.
	 *
	 * In Tutor, a `tutor_enrolled` post with status "completed" means the
	 * student is enrolled (the *enrollment* completed, not the course).
	 *
	 * @param string $post_status Tutor enrollment post status.
	 * @return string
	 */
	public static function enrollment_status( string $post_status ): string {
		return match ( strtolower( $post_status ) ) {
			'completed', 'complete', 'publish' => EnrollmentStatus::ACTIVE,
			'cancel', 'cancelled', 'canceled' => EnrollmentStatus::CANCELLED,
			'pending', 'on-hold'              => EnrollmentStatus::PENDING,
			default                           => EnrollmentStatus::CANCELLED,
		};
	}

	/**
	 * Maps Tutor's course level to the plugin's level.
	 *
	 * @param string $level Tutor level.
	 * @return string
	 */
	public static function level( string $level ): string {
		return match ( strtolower( $level ) ) {
			'beginner'     => 'beginner',
			'intermediate' => 'intermediate',
			'expert', 'advanced' => 'advanced',
			default        => 'all',
		};
	}

	/**
	 * Splits a Tutor multi-line text meta (benefits, requirements...) into a list.
	 *
	 * @param mixed $text Meta value.
	 * @return array<int, string>
	 */
	public static function lines( mixed $text ): array {
		if ( ! is_string( $text ) ) {
			return array();
		}

		$lines = preg_split( '/\r\n|\r|\n/', $text );

		if ( false === $lines ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map( static fn( string $line ): string => sanitize_text_field( $line ), $lines ),
				static fn( string $line ): bool => '' !== $line
			)
		);
	}

	/**
	 * Formats Tutor's `_course_duration` meta as a readable string.
	 *
	 * @param mixed $duration Meta value with hours and minutes.
	 * @return string
	 */
	public static function duration( mixed $duration ): string {
		if ( ! is_array( $duration ) ) {
			return '';
		}

		$hours   = (int) ( $duration['hours'] ?? 0 );
		$minutes = (int) ( $duration['minutes'] ?? 0 );

		if ( 0 === $hours && 0 === $minutes ) {
			return '';
		}

		$parts = array();

		if ( $hours > 0 ) {
			$parts[] = $hours . ' h';
		}

		if ( $minutes > 0 ) {
			$parts[] = $minutes . ' min';
		}

		return implode( ' ', $parts );
	}
}
