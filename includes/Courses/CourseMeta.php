<?php
/**
 * Course meta fields.
 *
 * @package SEV\LMS
 */

declare( strict_types = 1 );

namespace SEV\LMS\Courses;

use SEV\LMS\Permissions\Capabilities;
use SEV\LMS\Security\Sanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Declares the commercial and pedagogical fields stored on a course.
 *
 * Registering them with `register_post_meta()` gives the fields REST support,
 * a sanitisation callback and an authorisation callback for free, so the admin
 * screens and the REST controllers never have to repeat those rules.
 */
final class CourseMeta {

	public const SHORT_DESCRIPTION = '_sev_lms_short_description';
	public const LEVEL             = '_sev_lms_level';
	public const DURATION          = '_sev_lms_duration';
	public const INSTRUCTOR_ID     = '_sev_lms_instructor_id';
	public const INTRO_PROVIDER    = '_sev_lms_intro_provider';
	public const INTRO_URL         = '_sev_lms_intro_url';
	public const LEARNING_OUTCOMES = '_sev_lms_learning_outcomes';
	public const BENEFITS          = '_sev_lms_benefits';
	public const REQUIREMENTS      = '_sev_lms_requirements';
	public const TARGET_AUDIENCE   = '_sev_lms_target_audience';
	public const INCLUDED_MATERIAL = '_sev_lms_included_material';
	public const FAQ               = '_sev_lms_faq';
	public const PRICE_DISPLAY     = '_sev_lms_price_display';
	public const PRODUCT_ID        = '_sev_lms_product_id';
	public const DISPLAY_OVERRIDES = '_sev_lms_display_overrides';

	/**
	 * Levels accepted by the level field.
	 *
	 * @var array<int, string>
	 */
	public const LEVELS = array( 'beginner', 'intermediate', 'advanced', 'all' );

	/**
	 * Registers every course meta field.
	 *
	 * @return void
	 */
	public static function register(): void {
		foreach ( self::definitions() as $key => $definition ) {
			register_post_meta(
				CoursePostType::POST_TYPE,
				$key,
				array(
					'type'              => $definition['type'],
					'description'       => $definition['description'],
					'single'            => true,
					'default'           => $definition['default'],
					'sanitize_callback' => $definition['sanitize'],
					'auth_callback'     => static fn( bool $allowed, string $meta_key, int $post_id ): bool =>
						current_user_can( Capabilities::course_capabilities()['edit_post'], $post_id ),
					'show_in_rest'      => $definition['show_in_rest'],
				)
			);
		}
	}

	/**
	 * Returns the meta field definitions.
	 *
	 * @return array<string, array{type: string, description: string, default: mixed, sanitize: callable, show_in_rest: mixed}>
	 */
	public static function definitions(): array {
		$string_list_schema = array(
			'schema' => array(
				'type'  => 'array',
				'items' => array( 'type' => 'string' ),
			),
		);

		return array(
			self::SHORT_DESCRIPTION => array(
				'type'         => 'string',
				'description'  => 'Descripcion corta usada en el catalogo.',
				'default'      => '',
				'sanitize'     => array( Sanitizer::class, 'textarea' ),
				'show_in_rest' => true,
			),
			self::LEVEL             => array(
				'type'         => 'string',
				'description'  => 'Nivel del curso.',
				'default'      => 'all',
				'sanitize'     => static fn( $value ): string => Sanitizer::enum( $value, self::LEVELS, 'all' ),
				'show_in_rest' => true,
			),
			self::DURATION          => array(
				'type'         => 'string',
				'description'  => 'Duracion estimada mostrada al alumno.',
				'default'      => '',
				'sanitize'     => array( Sanitizer::class, 'text' ),
				'show_in_rest' => true,
			),
			self::INSTRUCTOR_ID     => array(
				'type'         => 'integer',
				'description'  => 'Usuario instructor responsable del curso.',
				'default'      => 0,
				'sanitize'     => array( Sanitizer::class, 'int' ),
				'show_in_rest' => true,
			),
			self::INTRO_PROVIDER    => array(
				'type'         => 'string',
				'description'  => 'Proveedor del video de presentacion.',
				'default'      => '',
				'sanitize'     => array( Sanitizer::class, 'key' ),
				'show_in_rest' => true,
			),
			self::INTRO_URL         => array(
				'type'         => 'string',
				'description'  => 'URL del video de presentacion.',
				'default'      => '',
				'sanitize'     => array( Sanitizer::class, 'url' ),
				'show_in_rest' => true,
			),
			self::LEARNING_OUTCOMES => array(
				'type'         => 'array',
				'description'  => 'Que aprendera el alumno.',
				'default'      => array(),
				'sanitize'     => array( Sanitizer::class, 'text_list' ),
				'show_in_rest' => $string_list_schema,
			),
			self::BENEFITS          => array(
				'type'         => 'array',
				'description'  => 'Beneficios del curso.',
				'default'      => array(),
				'sanitize'     => array( Sanitizer::class, 'text_list' ),
				'show_in_rest' => $string_list_schema,
			),
			self::REQUIREMENTS      => array(
				'type'         => 'array',
				'description'  => 'Requisitos previos.',
				'default'      => array(),
				'sanitize'     => array( Sanitizer::class, 'text_list' ),
				'show_in_rest' => $string_list_schema,
			),
			self::TARGET_AUDIENCE   => array(
				'type'         => 'array',
				'description'  => 'Publico objetivo.',
				'default'      => array(),
				'sanitize'     => array( Sanitizer::class, 'text_list' ),
				'show_in_rest' => $string_list_schema,
			),
			self::INCLUDED_MATERIAL => array(
				'type'         => 'array',
				'description'  => 'Material incluido.',
				'default'      => array(),
				'sanitize'     => array( Sanitizer::class, 'text_list' ),
				'show_in_rest' => $string_list_schema,
			),
			self::FAQ               => array(
				'type'         => 'array',
				'description'  => 'Preguntas frecuentes.',
				'default'      => array(),
				'sanitize'     => array( Sanitizer::class, 'faq_list' ),
				'show_in_rest' => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'question' => array( 'type' => 'string' ),
								'answer'   => array( 'type' => 'string' ),
							),
						),
					),
				),
			),
			self::PRICE_DISPLAY     => array(
				'type'         => 'string',
				'description'  => 'Precio informativo mostrado en la landing.',
				'default'      => '',
				'sanitize'     => array( Sanitizer::class, 'text' ),
				'show_in_rest' => true,
			),
			self::PRODUCT_ID        => array(
				'type'         => 'integer',
				'description'  => 'Producto WooCommerce por defecto del curso.',
				'default'      => 0,
				'sanitize'     => array( Sanitizer::class, 'int' ),
				'show_in_rest' => true,
			),
			self::DISPLAY_OVERRIDES => array(
				'type'         => 'object',
				'description'  => 'Sobrescrituras de visibilidad para este curso.',
				'default'      => array(),
				'sanitize'     => static fn( $value ): array => is_array( $value )
					? array_map( array( Sanitizer::class, 'bool' ), $value )
					: array(),
				'show_in_rest' => array(
					'schema' => array(
						'type'                 => 'object',
						'additionalProperties' => array( 'type' => 'boolean' ),
					),
				),
			),
		);
	}
}
