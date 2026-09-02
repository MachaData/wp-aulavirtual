<?php
/**
 * Activation and deactivation routines.
 *
 * @package SEV\LMS
 */

declare( strict_types = 1 );

namespace SEV\LMS\Database;

use SEV\LMS\Courses\CoursePostType;
use SEV\LMS\Permissions\Roles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prepares the site the first time the plugin is activated.
 *
 * Activation is deliberately additive: it creates tables, roles and the campus
 * pages, and never deletes anything. Removing data is only possible through the
 * uninstall routine, and only when the site owner opted in.
 */
final class Installer {

	/**
	 * Option storing the plugin version that was last activated.
	 */
	public const VERSION_OPTION = 'sev_lms_version';

	/**
	 * Option storing the ids of the pages created on activation.
	 */
	public const PAGES_OPTION = 'sev_lms_pages';

	/**
	 * Option controlling data removal on uninstall.
	 */
	public const DELETE_DATA_OPTION = 'sev_lms_delete_data_on_uninstall';

	/**
	 * Runs on plugin activation.
	 *
	 * @return void
	 */
	public static function activate(): void {
		$schema   = new Schema();
		$migrator = new Migrator( $schema );

		$migrator->migrate();

		Roles::install();
		self::install_default_options();
		self::create_pages();

		CoursePostType::register();
		flush_rewrite_rules();

		update_option( self::VERSION_OPTION, SEV_LMS_VERSION, false );

		/**
		 * Fires once the plugin finished its activation routine.
		 */
		do_action( 'sev_lms/activated' );
	}

	/**
	 * Runs on plugin deactivation.
	 *
	 * No data is removed here: deactivating a plugin must never cost an
	 * institution its enrollments.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();

		/**
		 * Fires once the plugin finished its deactivation routine.
		 */
		do_action( 'sev_lms/deactivated' );
	}

	/**
	 * Adds the default options without overwriting existing values.
	 *
	 * @return void
	 */
	private static function install_default_options(): void {
		$defaults = array(
			self::DELETE_DATA_OPTION          => false,
			'sev_lms_log_level'               => 'info',
			'sev_lms_block_wp_admin'          => true,
			CoursePostType::SLUG_OPTION       => CoursePostType::DEFAULT_SLUG,
			'sev_lms_enrollment_auto_approve' => false,
			'sev_lms_send_welcome_email'      => true,
			'sev_lms_progress_mode'           => 'flexible',
		);

		foreach ( $defaults as $option => $value ) {
			add_option( $option, $value, '', false );
		}
	}

	/**
	 * Creates the campus pages when they do not exist yet.
	 *
	 * @return void
	 */
	private static function create_pages(): void {
		$pages = array(
			'campus'   => array(
				'title'     => __( 'Campus', 'sev-lms' ),
				'slug'      => 'campus',
				'shortcode' => '[sev_lms_campus]',
			),
			'login'    => array(
				'title'     => __( 'Ingresar al campus', 'sev-lms' ),
				'slug'      => 'campus-login',
				'shortcode' => '[sev_lms_login]',
			),
			'recover'  => array(
				'title'     => __( 'Recuperar contrasena', 'sev-lms' ),
				'slug'      => 'campus-recuperar-contrasena',
				'shortcode' => '[sev_lms_lost_password]',
			),
			'catalog'  => array(
				'title'     => __( 'Cursos', 'sev-lms' ),
				'slug'      => 'cursos',
				'shortcode' => '[sev_lms_catalog]',
			),
		);

		$stored = get_option( self::PAGES_OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();

		foreach ( $pages as $key => $page ) {
			if ( isset( $stored[ $key ] ) && get_post( (int) $stored[ $key ] ) instanceof \WP_Post ) {
				continue;
			}

			$existing = get_page_by_path( $page['slug'] );

			if ( $existing instanceof \WP_Post ) {
				$stored[ $key ] = $existing->ID;
				continue;
			}

			$page_id = wp_insert_post(
				array(
					'post_title'     => $page['title'],
					'post_name'      => $page['slug'],
					'post_content'   => $page['shortcode'],
					'post_status'    => 'publish',
					'post_type'      => 'page',
					'comment_status' => 'closed',
					'ping_status'    => 'closed',
				)
			);

			if ( ! is_wp_error( $page_id ) && $page_id > 0 ) {
				$stored[ $key ] = (int) $page_id;
			}
		}

		update_option( self::PAGES_OPTION, $stored, false );
	}
}
