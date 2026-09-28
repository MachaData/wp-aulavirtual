<?php
/**
 * Placeholder rendering.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Emails;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Replaces `{{variable}}` placeholders in subjects and bodies.
 *
 * Unknown placeholders are left untouched on purpose, so a typo in a template
 * is visible in the received email instead of silently disappearing.
 */
final class TemplateRenderer {

	/**
	 * Renders a template string.
	 *
	 * @param string                $template  Text with `{{name}}` placeholders.
	 * @param array<string, string> $variables Values keyed by placeholder name.
	 * @return string
	 */
	public static function render( string $template, array $variables ): string {
		return (string) preg_replace_callback(
			'/\{\{\s*([a-z0-9_]+)\s*\}\}/i',
			static function ( array $match ) use ( $variables ): string {
				$key = strtolower( $match[1] );

				return array_key_exists( $key, $variables ) ? $variables[ $key ] : $match[0];
			},
			$template
		);
	}

	/**
	 * Returns the placeholder names used by a template.
	 *
	 * @param string $template Template text.
	 * @return array<int, string>
	 */
	public static function placeholders( string $template ): array {
		preg_match_all( '/\{\{\s*([a-z0-9_]+)\s*\}\}/i', $template, $matches );

		return array_values( array_unique( array_map( 'strtolower', $matches[1] ) ) );
	}
}
