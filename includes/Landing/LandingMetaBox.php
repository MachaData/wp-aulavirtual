<?php
/**
 * Landing editor inside the course screen.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Landing;

use SIQA\AulaVirtual\Courses\CoursePostType;
use SIQA\AulaVirtual\Permissions\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A meta box with the eight sections, each collapsible with its own switch.
 *
 * Works in the block editor and the classic editor alike: it is a classic
 * meta box, saved on `save_post` with its own nonce.
 */
final class LandingMetaBox {

	public const ID    = 'av_landing';
	public const NONCE = 'av_landing_nonce';
	public const FIELD = 'av_landing';

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'add_meta_boxes_' . CoursePostType::POST_TYPE, array( $this, 'add' ) );
		add_action( 'save_post_' . CoursePostType::POST_TYPE, array( $this, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Adds the meta box.
	 *
	 * @return void
	 */
	public function add(): void {
		add_meta_box(
			self::ID,
			__( 'Landing del curso', 'aula-virtual' ),
			array( $this, 'render' ),
			CoursePostType::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Loads the editor assets on the course screen only.
	 *
	 * @param string $hook Current admin page.
	 * @return void
	 */
	public function enqueue( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( null === $screen || CoursePostType::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'av-landing-admin', AV_URL . 'assets/css/landing-admin.css', array(), AV_VERSION );
		wp_enqueue_script( 'av-landing-admin', AV_URL . 'assets/js/landing-admin.js', array( 'jquery' ), AV_VERSION, true );
		wp_localize_script(
			'av-landing-admin',
			'avLandingAdmin',
			array(
				'chooseImage' => __( 'Elegir imagen', 'aula-virtual' ),
				'useImage'    => __( 'Usar esta imagen', 'aula-virtual' ),
				'remove'      => __( 'Quitar', 'aula-virtual' ),
			)
		);
	}

	/**
	 * Renders the editor.
	 *
	 * @param \WP_Post $post Course post.
	 * @return void
	 */
	public function render( \WP_Post $post ): void {
		$landing = LandingData::load( (int) $post->ID );

		wp_nonce_field( self::NONCE, self::NONCE );

		$data = array(
			'landing'      => $landing,
			'labels'       => LandingData::labels(),
			'button_types' => LandingData::button_types(),
			'field'        => self::FIELD,
			'course_id'    => (int) $post->ID,
		);

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- controlled template data.
		extract( $data, EXTR_SKIP );

		require AV_PATH . 'admin/views/landing-metabox.php';
	}

	/**
	 * Saves the landing.
	 *
	 * @param int      $post_id Course id.
	 * @param \WP_Post $post    Course post.
	 * @return void
	 */
	public function save( int $post_id, \WP_Post $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || 'auto-draft' === $post->post_status ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified right below.
		if ( ! isset( $_POST[ self::NONCE ] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) ) {
			return;
		}

		if ( ! current_user_can( Capabilities::course_capabilities()['edit_post'], $post_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above; sanitised by LandingData.
		$raw = isset( $_POST[ self::FIELD ] ) ? wp_unslash( $_POST[ self::FIELD ] ) : array();

		LandingData::save( $post_id, LandingData::sanitize( $raw, $post_id ) );
	}
}
