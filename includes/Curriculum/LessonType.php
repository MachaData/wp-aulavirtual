<?php
/**
 * Lesson types and states.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Curriculum;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The kinds of session an edition can contain.
 *
 * Quiz and assignment are declared but not built yet: they belong to phase 2
 * and are listed here so the type column never needs a migration to accept
 * them.
 */
final class LessonType {

	public const VIDEO      = 'video';
	public const TEXT       = 'text';
	public const LIVE       = 'live';
	public const MATERIAL   = 'material';
	public const QUIZ       = 'quiz';
	public const ASSIGNMENT = 'assignment';

	public const STATUS_PUBLISH = 'publish';
	public const STATUS_DRAFT   = 'draft';

	/**
	 * Types available in the current version.
	 *
	 * @return array<string, string>
	 */
	public static function available(): array {
		return array(
			self::VIDEO    => __( 'Video', 'aula-virtual' ),
			self::TEXT     => __( 'Texto', 'aula-virtual' ),
			self::LIVE     => __( 'Clase en vivo', 'aula-virtual' ),
			self::MATERIAL => __( 'Material', 'aula-virtual' ),
		);
	}

	/**
	 * Every type the column accepts, including the ones not built yet.
	 *
	 * @return array<int, string>
	 */
	public static function all(): array {
		return array( self::VIDEO, self::TEXT, self::LIVE, self::MATERIAL, self::QUIZ, self::ASSIGNMENT );
	}

	/**
	 * Valid lesson states.
	 *
	 * @return array<int, string>
	 */
	public static function statuses(): array {
		return array( self::STATUS_PUBLISH, self::STATUS_DRAFT );
	}

	/**
	 * Returns the label of a type.
	 *
	 * @param string $type Lesson type.
	 * @return string
	 */
	public static function label( string $type ): string {
		return self::available()[ $type ] ?? $type;
	}
}
