<?php
/**
 * Public landing integration: template, SEO tags, shortcodes.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Landing;

use SIQA\AulaVirtual\Courses\CourseMeta;
use SIQA\AulaVirtual\Courses\CoursePostType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serves the course landing on the course permalink.
 *
 * The plugin template is used only when the theme does not ship its own
 * `single-av_course.php`, and it can be turned off with a filter so a page
 * builder can take over while keeping the shortcodes.
 */
final class LandingTemplate {

	/**
	 * Renderer.
	 *
	 * @var LandingRenderer
	 */
	private LandingRenderer $renderer;

	/**
	 * Constructor.
	 *
	 * @param LandingRenderer $renderer Renderer.
	 */
	public function __construct( LandingRenderer $renderer ) {
		$this->renderer = $renderer;
	}

	/**
	 * Swaps the theme template for the plugin landing on single courses.
	 *
	 * @param string $template Template chosen by WordPress.
	 * @return string
	 */
	public function template_include( string $template ): string {
		if ( ! is_singular( CoursePostType::POST_TYPE ) ) {
			return $template;
		}

		if ( '' !== locate_template( array( 'single-' . CoursePostType::POST_TYPE . '.php' ) ) ) {
			return $template;
		}

		/**
		 * Filters whether the plugin renders the course landing.
		 *
		 * Return false to let the theme or a page builder render the course.
		 *
		 * @param bool $use       Whether to use the plugin landing.
		 * @param int  $course_id Course id.
		 */
		if ( ! apply_filters( 'aula_virtual/use_landing_template', true, get_queried_object_id() ) ) {
			return $template;
		}

		return AV_PATH . 'templates/landing/single-course.php';
	}

	/**
	 * Loads the landing stylesheet on course pages.
	 *
	 * @return void
	 */
	public function enqueue(): void {
		if ( ! is_singular( CoursePostType::POST_TYPE ) ) {
			return;
		}

		// El color de acento va en el propio contenedor (LandingRenderer::accent()).
		wp_enqueue_style( 'av-landing', AV_URL . 'assets/css/landing.css', array(), AV_VERSION );
		wp_enqueue_script( 'av-landing', AV_URL . 'assets/js/landing.js', array(), AV_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	}

	/**
	 * Prints social and structured data tags for the course.
	 *
	 * @return void
	 */
	public function head(): void {
		if ( ! is_singular( CoursePostType::POST_TYPE ) ) {
			return;
		}

		$course_id   = get_queried_object_id();
		$landing     = LandingData::load( $course_id );
		$title       = '' !== $landing['hero']['title'] ? $landing['hero']['title'] : get_the_title( $course_id );
		$description = '' !== $landing['hero']['subtitle'] ? $landing['hero']['subtitle'] : wp_strip_all_tags( get_the_excerpt( $course_id ) );
		$image       = (int) $landing['hero']['image_id'] > 0 ? wp_get_attachment_image_url( (int) $landing['hero']['image_id'], 'large' ) : get_the_post_thumbnail_url( $course_id, 'large' );

		echo '<meta property="og:type" content="website">' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
		echo '<meta property="og:description" content="' . esc_attr( $description ) . '">' . "\n";

		if ( is_string( $image ) && '' !== $image ) {
			echo '<meta property="og:image" content="' . esc_url( $image ) . '">' . "\n";
		}

		$instructor_id = (int) get_post_meta( $course_id, CourseMeta::INSTRUCTOR_ID, true );
		$instructor    = $instructor_id > 0 ? get_userdata( $instructor_id ) : false;

		$schema = array(
			'@context'    => 'https://schema.org',
			'@type'       => 'Course',
			'name'        => $title,
			'description' => $description,
			'url'         => get_permalink( $course_id ),
			'provider'    => array(
				'@type' => 'Organization',
				'name'  => wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES ),
				'url'   => home_url( '/' ),
			),
		);

		if ( false !== $instructor ) {
			$schema['instructor'] = array(
				'@type' => 'Person',
				'name'  => $instructor->display_name,
			);
		}

		echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
	}

	/**
	 * `[av_course_landing id=""]`: the full landing, for page builders.
	 *
	 * @param array<string, string>|string $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode_landing( $atts ): string {
		$course_id = $this->course_from_atts( $atts );

		return $course_id > 0 ? $this->renderer->render( $course_id ) : '';
	}

	/**
	 * `[av_course_editions id=""]`: only the editions block with buttons.
	 *
	 * @param array<string, string>|string $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode_editions( $atts ): string {
		$course_id = $this->course_from_atts( $atts );

		return $course_id > 0 ? $this->renderer->render_editions( $course_id ) : '';
	}

	/**
	 * Resolves the course id from shortcode attributes or the current post.
	 *
	 * @param array<string, string>|string $atts Shortcode attributes.
	 * @return int
	 */
	private function course_from_atts( $atts ): int {
		$atts      = shortcode_atts( array( 'id' => 0 ), is_array( $atts ) ? $atts : array() );
		$course_id = absint( $atts['id'] );

		if ( 0 === $course_id ) {
			$course_id = (int) get_the_ID();
		}

		$post = get_post( $course_id );

		if ( ! $post instanceof \WP_Post || CoursePostType::POST_TYPE !== $post->post_type || ( 'publish' !== $post->post_status && ! current_user_can( 'edit_post', $course_id ) ) ) {
			return 0;
		}

		wp_enqueue_style( 'av-landing', AV_URL . 'assets/css/landing.css', array(), AV_VERSION );

		return $course_id;
	}
}
