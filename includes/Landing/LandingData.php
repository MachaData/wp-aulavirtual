<?php
/**
 * Landing page data model.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Landing;

use SIQA\AulaVirtual\Courses\CourseMeta;
use SIQA\AulaVirtual\Security\Sanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The eight sections of a course landing, stored as one JSON meta.
 *
 * Every section has an `enabled` switch and fixed fields; there is no free
 * layout. That is what makes the landing administrable by a coordinator and
 * duplicable with the course.
 */
final class LandingData {

	public const META_KEY = '_av_landing';

	public const BUTTON_BUY      = 'buy';
	public const BUTTON_REGISTER = 'register';
	public const BUTTON_CUSTOM   = 'custom';
	public const BUTTON_WHATSAPP = 'whatsapp';
	public const BUTTON_NONE     = 'none';

	/**
	 * Section keys in display order.
	 *
	 * @return array<int, string>
	 */
	public static function sections(): array {
		return array( 'hero', 'benefits', 'content', 'instructor', 'info', 'price', 'faq', 'cta' );
	}

	/**
	 * Labels of the sections for the editor.
	 *
	 * @return array<string, string>
	 */
	public static function labels(): array {
		return array(
			'hero'       => __( 'Presentacion', 'aula-virtual' ),
			'benefits'   => __( 'Beneficios', 'aula-virtual' ),
			'content'    => __( 'Contenido', 'aula-virtual' ),
			'instructor' => __( 'Instructor', 'aula-virtual' ),
			'info'       => __( 'Informacion del curso', 'aula-virtual' ),
			'price'      => __( 'Precio', 'aula-virtual' ),
			'faq'        => __( 'Preguntas frecuentes', 'aula-virtual' ),
			'cta'        => __( 'Inscripcion / Compra', 'aula-virtual' ),
		);
	}

	/**
	 * Button behaviours available in the editor.
	 *
	 * @return array<string, string>
	 */
	public static function button_types(): array {
		return array(
			self::BUTTON_BUY      => __( 'Comprar (checkout de la edicion)', 'aula-virtual' ),
			self::BUTTON_REGISTER => __( 'Inscribirse (enlace de inscripcion de la edicion)', 'aula-virtual' ),
			self::BUTTON_WHATSAPP => __( 'WhatsApp', 'aula-virtual' ),
			self::BUTTON_CUSTOM   => __( 'Enlace personalizado', 'aula-virtual' ),
			self::BUTTON_NONE     => __( 'Sin boton', 'aula-virtual' ),
		);
	}

	/**
	 * Default values, some of them taken from the course meta so a landing
	 * looks complete before anyone edits it.
	 *
	 * @param int $course_id Course id, 0 for bare defaults.
	 * @return array<string, array<string, mixed>>
	 */
	public static function defaults( int $course_id = 0 ): array {
		$meta = static fn( string $key ) => $course_id > 0 ? get_post_meta( $course_id, $key, true ) : '';

		$instructor_id = (int) $meta( CourseMeta::INSTRUCTOR_ID );
		$instructor    = $instructor_id > 0 ? get_userdata( $instructor_id ) : false;

		return array(
			'hero'       => array(
				'enabled'       => true,
				'kicker'        => '',
				'title'         => $course_id > 0 ? get_the_title( $course_id ) : '',
				'subtitle'      => $course_id > 0 ? (string) $meta( CourseMeta::SHORT_DESCRIPTION ) : '',
				'text'          => '',
				'video_url'     => $course_id > 0 ? (string) $meta( CourseMeta::INTRO_URL ) : '',
				'image_id'      => $course_id > 0 ? (int) get_post_thumbnail_id( $course_id ) : 0,
				'background_id' => 0,
				'bg_color'      => '',
				'button_text'   => __( 'Quiero inscribirme', 'aula-virtual' ),
				'button_type'   => self::BUTTON_BUY,
				'button_url'    => '',
			),
			'benefits'   => array(
				'enabled'  => true,
				'title'    => __( 'Que vas a lograr', 'aula-virtual' ),
				'items'    => $course_id > 0 ? self::list_meta( $meta( CourseMeta::LEARNING_OUTCOMES ), $meta( CourseMeta::BENEFITS ) ) : array(),
				'image_id' => 0,
			),
			'content'    => array(
				'enabled'         => true,
				'title'           => __( 'Contenido del curso', 'aula-virtual' ),
				'text'            => '',
				'video_url'       => '',
				'from_curriculum' => true,
				'items'           => array(),
			),
			'instructor' => array(
				'enabled'  => true,
				'title'    => __( 'Tu instructor', 'aula-virtual' ),
				'name'     => false === $instructor ? '' : $instructor->display_name,
				'bio'      => false === $instructor ? '' : (string) get_user_meta( $instructor_id, 'description', true ),
				'image_id' => 0,
				'links'    => array(),
			),
			'info'       => array(
				'enabled'       => true,
				'title'         => __( 'Informacion del curso', 'aula-virtual' ),
				'from_editions' => true,
				'items'         => array(),
			),
			'price'      => array(
				'enabled'     => true,
				'title'       => __( 'Inversion', 'aula-virtual' ),
				'price'       => $course_id > 0 ? (string) $meta( CourseMeta::PRICE_DISPLAY ) : '',
				'old_price'   => '',
				'includes'    => $course_id > 0 ? self::list_meta( $meta( CourseMeta::INCLUDED_MATERIAL ) ) : array(),
				'note'        => '',
				'button_text' => __( 'Comprar ahora', 'aula-virtual' ),
				'button_type' => self::BUTTON_BUY,
				'button_url'  => '',
			),
			'faq'        => array(
				'enabled' => true,
				'title'   => __( 'Preguntas frecuentes', 'aula-virtual' ),
				'items'   => $course_id > 0 ? Sanitizer::faq_list( $meta( CourseMeta::FAQ ) ) : array(),
			),
			'cta'        => array(
				'enabled'       => true,
				'title'         => __( 'Reserva tu lugar', 'aula-virtual' ),
				'text'          => '',
				'button_text'   => __( 'Inscribirme', 'aula-virtual' ),
				'button_type'   => self::BUTTON_REGISTER,
				'button_url'    => '',
				'whatsapp'      => '',
				'background_id' => 0,
				'bg_color'      => '',
			),
		);
	}

	/**
	 * Loads the landing of a course, merged over the defaults.
	 *
	 * @param int $course_id Course id.
	 * @return array<string, array<string, mixed>>
	 */
	public static function load( int $course_id ): array {
		$stored = get_post_meta( $course_id, self::META_KEY, true );
		$stored = is_array( $stored ) ? $stored : array();

		return self::merge( self::defaults( $course_id ), $stored );
	}

	/**
	 * Saves a landing.
	 *
	 * @param int                                $course_id Course id.
	 * @param array<string, array<string, mixed>> $data     Sanitised landing data.
	 * @return void
	 */
	public static function save( int $course_id, array $data ): void {
		update_post_meta( $course_id, self::META_KEY, $data );
	}

	/**
	 * Sanitises raw editor input into a landing structure.
	 *
	 * Unknown sections and keys are dropped; every known key is normalised to
	 * the type of its default.
	 *
	 * @param mixed $input     Raw input, usually $_POST['av_landing'].
	 * @param int   $course_id Course id, for defaults.
	 * @return array<string, array<string, mixed>>
	 */
	public static function sanitize( mixed $input, int $course_id = 0 ): array {
		$defaults = self::defaults( $course_id );
		$input    = is_array( $input ) ? $input : array();
		$clean    = array();

		foreach ( $defaults as $section => $fields ) {
			$raw = isset( $input[ $section ] ) && is_array( $input[ $section ] ) ? $input[ $section ] : array();

			$clean[ $section ] = array();

			foreach ( $fields as $key => $default ) {
				// Una clave que no viene en el envio conserva su valor por defecto;
				// una clave enviada vacia se guarda vacia. Asi un guardado parcial
				// (API, importacion) no borra lo que no menciona.
				if ( ! array_key_exists( $key, $raw ) ) {
					$clean[ $section ][ $key ] = $default;
					continue;
				}

				$value = $raw[ $key ];

				$clean[ $section ][ $key ] = match ( true ) {
					'enabled' === $key || 'from_curriculum' === $key || 'from_editions' === $key => Sanitizer::bool( $value ?? false ),
					'button_type' === $key => Sanitizer::enum( $value ?? '', array_keys( self::button_types() ), (string) $default ),
					str_ends_with( $key, '_id' ) => max( 0, Sanitizer::int( $value ?? 0 ) ),
					str_ends_with( $key, '_url' ) => Sanitizer::url( $value ?? '' ),
					'bg_color' === $key => self::color( $value ),
					'whatsapp' === $key => preg_replace( '/[^0-9+]/', '', (string) ( is_scalar( $value ) ? $value : '' ) ),
					'bio' === $key || 'text' === $key || 'note' === $key => Sanitizer::html( $value ?? '' ),
					'items' === $key && 'faq' === $section => Sanitizer::faq_list( $value ),
					'items' === $key && 'info' === $section => self::pairs( $value, 'label', 'value' ),
					'links' === $key => self::pairs( $value, 'label', 'url', true ),
					'items' === $key || 'includes' === $key => Sanitizer::text_list( self::lines_or_list( $value ) ),
					default => Sanitizer::text( $value ?? '' ),
				};
			}
		}

		return $clean;
	}

	/**
	 * Merges stored data over defaults, section by section.
	 *
	 * @param array<string, array<string, mixed>> $defaults Defaults.
	 * @param array<string, mixed>                $stored   Stored data.
	 * @return array<string, array<string, mixed>>
	 */
	private static function merge( array $defaults, array $stored ): array {
		foreach ( $defaults as $section => $fields ) {
			if ( ! isset( $stored[ $section ] ) || ! is_array( $stored[ $section ] ) ) {
				continue;
			}

			foreach ( $fields as $key => $default ) {
				if ( array_key_exists( $key, $stored[ $section ] ) ) {
					$defaults[ $section ][ $key ] = $stored[ $section ][ $key ];
				}
			}
		}

		return $defaults;
	}

	/**
	 * Merges one or more list metas into a single list.
	 *
	 * @param mixed ...$lists Meta values.
	 * @return array<int, string>
	 */
	private static function list_meta( mixed ...$lists ): array {
		$merged = array();

		foreach ( $lists as $list ) {
			if ( is_array( $list ) ) {
				$merged = array_merge( $merged, $list );
			}
		}

		return Sanitizer::text_list( $merged );
	}

	/**
	 * Accepts either an array of strings or a textarea with one item per line.
	 *
	 * @param mixed $value Raw value.
	 * @return array<int, string>
	 */
	private static function lines_or_list( mixed $value ): array {
		if ( is_array( $value ) ) {
			return array_map( static fn( $item ): string => is_scalar( $item ) ? (string) $item : '', $value );
		}

		if ( ! is_string( $value ) ) {
			return array();
		}

		$lines = preg_split( '/\r\n|\r|\n/', $value );

		return false === $lines ? array() : $lines;
	}

	/**
	 * Sanitises a list of two-field rows.
	 *
	 * @param mixed  $value      Raw rows.
	 * @param string $first      First key.
	 * @param string $second     Second key.
	 * @param bool   $second_url Whether the second value is a URL.
	 * @return array<int, array<string, string>>
	 */
	private static function pairs( mixed $value, string $first, string $second, bool $second_url = false ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$rows = array();

		foreach ( $value as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$a = Sanitizer::text( $row[ $first ] ?? '' );
			$b = $second_url ? Sanitizer::url( $row[ $second ] ?? '' ) : Sanitizer::text( $row[ $second ] ?? '' );

			if ( '' === $a && '' === $b ) {
				continue;
			}

			$rows[] = array(
				$first  => $a,
				$second => $b,
			);
		}

		return $rows;
	}

	/**
	 * Sanitises a hex colour.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private static function color( mixed $value ): string {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';

		return preg_match( '/^#[0-9a-fA-F]{6}$/', $value ) ? strtolower( $value ) : '';
	}
}
