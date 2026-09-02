<?php
/**
 * Admin menu.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Admin;

use SIQA\AulaVirtual\Permissions\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the "Aula Virtual" menu.
 *
 * The course post type keeps its own WordPress screens; everything that lives
 * in custom tables hangs from here.
 */
final class AdminMenu {

	public const SLUG          = 'aula-virtual';
	public const EDITIONS_SLUG = 'aula-virtual-ediciones';

	/**
	 * Editions screen.
	 *
	 * @var EditionsScreen
	 */
	private EditionsScreen $editions;

	/**
	 * Constructor.
	 *
	 * @param EditionsScreen $editions Editions screen.
	 */
	public function __construct( EditionsScreen $editions ) {
		$this->editions = $editions;
	}

	/**
	 * Registers the menu and its submenus.
	 *
	 * @return void
	 */
	public function register(): void {
		add_menu_page(
			__( 'Aula Virtual', 'aula-virtual' ),
			__( 'Aula Virtual', 'aula-virtual' ),
			Capabilities::MANAGE_EDITIONS,
			self::EDITIONS_SLUG,
			array( $this->editions, 'render' ),
			'dashicons-welcome-learn-more',
			26
		);

		add_submenu_page(
			self::EDITIONS_SLUG,
			__( 'Ediciones', 'aula-virtual' ),
			__( 'Ediciones', 'aula-virtual' ),
			Capabilities::MANAGE_EDITIONS,
			self::EDITIONS_SLUG,
			array( $this->editions, 'render' )
		);

		add_submenu_page(
			self::EDITIONS_SLUG,
			__( 'Cursos', 'aula-virtual' ),
			__( 'Cursos', 'aula-virtual' ),
			Capabilities::course_capabilities()['edit_posts'],
			'edit.php?post_type=av_course'
		);
	}

	/**
	 * Returns the URL of the editions list.
	 *
	 * @param array<string, string|int> $args Extra query arguments.
	 * @return string
	 */
	public static function editions_url( array $args = array() ): string {
		return add_query_arg(
			array_merge( array( 'page' => self::EDITIONS_SLUG ), $args ),
			admin_url( 'admin.php' )
		);
	}
}
