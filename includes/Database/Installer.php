<?php
/**
 * Activation and deactivation routines.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Database;

use SIQA\AulaVirtual\Courses\CoursePostType;
use SIQA\AulaVirtual\Emails\EmailDefaults;
use SIQA\AulaVirtual\Emails\EmailTemplateRepository;
use SIQA\AulaVirtual\Enrollments\RegistrationController;
use SIQA\AulaVirtual\Permissions\Roles;

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
	public const VERSION_OPTION = 'av_version';

	/**
	 * Option storing the ids of the pages created on activation.
	 */
	public const PAGES_OPTION = 'av_pages';

	/**
	 * Option controlling data removal on uninstall.
	 */
	public const DELETE_DATA_OPTION = 'av_delete_data_on_uninstall';

	/**
	 * Runs on plugin activation.
	 *
	 * @return void
	 */
	public static function activate(): void {
		$schema   = new Schema();
		$migrator = new Migrator( $schema );

		$migrator->migrate();

		// Al activar, los proveedores todavia no han arrancado, asi que las
		// plantillas se siembran aqui y no desde el hook de migracion.
		EmailDefaults::seed( new EmailTemplateRepository( $schema ) );

		Roles::install();
		self::install_default_options();
		self::create_pages();

		CoursePostType::register();
		RegistrationController::register_rewrite();
		flush_rewrite_rules();

		update_option( self::VERSION_OPTION, AV_VERSION, false );

		/**
		 * Fires once the plugin finished its activation routine.
		 */
		do_action( 'aula_virtual/activated' );
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
		do_action( 'aula_virtual/deactivated' );
	}

	/**
	 * Adds the default options without overwriting existing values.
	 *
	 * @return void
	 */
	private static function install_default_options(): void {
		$defaults = array(
			self::DELETE_DATA_OPTION          => false,
			'av_log_level'               => 'info',
			'av_block_wp_admin'          => true,
			CoursePostType::SLUG_OPTION       => CoursePostType::DEFAULT_SLUG,
			'av_enrollment_auto_approve' => false,
			'av_send_welcome_email'      => true,
			'av_progress_mode'           => 'flexible',
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
		// Solo se crean las paginas cuyo shortcode existe ya. Login, recuperacion
		// y catalogo se anaden con su modulo, para no publicar paginas que
		// mostrarian el shortcode en crudo.
		$pages = array(
			'campus' => array(
				'title'     => __( 'Campus', 'aula-virtual' ),
				'slug'      => 'campus',
				'shortcode' => '[av_campus]',
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
