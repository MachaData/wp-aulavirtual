<?php
/**
 * Course post type and taxonomies.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Courses;

use SIQA\AulaVirtual\Permissions\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the course post type.
 *
 * Courses are the only entity modelled as posts: they need permalinks, SEO,
 * featured images, Gutenberg and page builders for their public landing page.
 * Everything transactional (editions, enrollments, progress) stays in custom
 * tables.
 */
final class CoursePostType {

	public const POST_TYPE     = 'av_course';
	public const TAX_CATEGORY  = 'av_course_cat';
	public const TAX_TAG       = 'av_course_tag';
	public const SLUG_OPTION   = 'av_course_slug';
	public const DEFAULT_SLUG  = 'curso';

	/**
	 * Registers the post type and its taxonomies.
	 *
	 * @return void
	 */
	public static function register(): void {
		self::register_post_type();
		self::register_taxonomies();
	}

	/**
	 * Returns the public slug used for course permalinks.
	 *
	 * @return string
	 */
	public static function slug(): string {
		$slug = get_option( self::SLUG_OPTION, self::DEFAULT_SLUG );
		$slug = is_string( $slug ) ? sanitize_title( $slug ) : '';

		return '' === $slug ? self::DEFAULT_SLUG : $slug;
	}

	/**
	 * Registers the course post type.
	 *
	 * @return void
	 */
	private static function register_post_type(): void {
		$labels = array(
			'name'               => __( 'Cursos', 'aula-virtual' ),
			'singular_name'      => __( 'Curso', 'aula-virtual' ),
			'add_new'            => __( 'Añadir curso', 'aula-virtual' ),
			'add_new_item'       => __( 'Añadir nuevo curso', 'aula-virtual' ),
			'edit_item'          => __( 'Editar curso', 'aula-virtual' ),
			'new_item'           => __( 'Nuevo curso', 'aula-virtual' ),
			'view_item'          => __( 'Ver curso', 'aula-virtual' ),
			'search_items'       => __( 'Buscar cursos', 'aula-virtual' ),
			'not_found'          => __( 'No se encontraron cursos', 'aula-virtual' ),
			'not_found_in_trash' => __( 'No hay cursos en la papelera', 'aula-virtual' ),
			'all_items'          => __( 'Todos los cursos', 'aula-virtual' ),
			'menu_name'          => __( 'Cursos', 'aula-virtual' ),
		);

		$args = array(
			'labels'              => $labels,
			'public'              => true,
			'show_ui'             => true,
			// El curso se muestra dentro del menu Aula Virtual, no como menu propio.
			'show_in_menu'        => false,
			'show_in_rest'        => true,
			'menu_icon'           => 'dashicons-welcome-learn-more',
			'menu_position'       => 26,
			'has_archive'         => false,
			'hierarchical'        => false,
			'exclude_from_search' => false,
			'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author', 'revisions', 'custom-fields' ),
			'rewrite'             => array(
				'slug'       => self::slug(),
				'with_front' => false,
			),
			'capability_type'     => array( Capabilities::COURSE_SINGULAR, Capabilities::COURSE_PLURAL ),
			'capabilities'        => Capabilities::course_capabilities(),
			'map_meta_cap'        => true,
			'delete_with_user'    => false,
		);

		/**
		 * Filters the arguments used to register the course post type.
		 *
		 * @param array<string, mixed> $args Registration arguments.
		 */
		register_post_type( self::POST_TYPE, apply_filters( 'aula_virtual/course_post_type_args', $args ) );
	}

	/**
	 * Registers the course taxonomies.
	 *
	 * @return void
	 */
	private static function register_taxonomies(): void {
		register_taxonomy(
			self::TAX_CATEGORY,
			self::POST_TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Categorías de curso', 'aula-virtual' ),
					'singular_name' => __( 'Categoría de curso', 'aula-virtual' ),
				),
				'public'            => true,
				'hierarchical'      => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array(
					'slug'       => self::slug() . '-categoria',
					'with_front' => false,
				),
			)
		);

		register_taxonomy(
			self::TAX_TAG,
			self::POST_TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Etiquetas de curso', 'aula-virtual' ),
					'singular_name' => __( 'Etiqueta de curso', 'aula-virtual' ),
				),
				'public'            => true,
				'hierarchical'      => false,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array(
					'slug'       => self::slug() . '-etiqueta',
					'with_front' => false,
				),
			)
		);
	}
}
