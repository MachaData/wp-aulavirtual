<?php
/**
 * Admin menu.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Admin;

use SIQA\AulaVirtual\Migration\TutorReader;
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
	 * Requests screen.
	 *
	 * @var RequestsScreen
	 */
	private RequestsScreen $requests;

	/**
	 * Emails screen.
	 *
	 * @var EmailsScreen
	 */
	private EmailsScreen $emails;

	/**
	 * Migration screen.
	 *
	 * @var MigrationScreen
	 */
	private MigrationScreen $migration;

	/**
	 * Announcements screen.
	 *
	 * @var AnnouncementsScreen
	 */
	private AnnouncementsScreen $announcements;

	/**
	 * Settings screen.
	 *
	 * @var SettingsScreen
	 */
	private SettingsScreen $settings;

	/**
	 * Import screen.
	 *
	 * @var ImportScreen
	 */
	private ImportScreen $import;

	/**
	 * Reports screen.
	 *
	 * @var ReportsScreen
	 */
	private ReportsScreen $reports;

	/**
	 * Certificates screen.
	 *
	 * @var CertificatesScreen
	 */
	private CertificatesScreen $certificates;

	/**
	 * Constructor.
	 *
	 * @param EditionsScreen $editions Editions screen.
	 * @param RequestsScreen $requests Requests screen.
	 * @param EmailsScreen    $emails    Emails screen.
	 * @param MigrationScreen     $migration     Migration screen.
	 * @param AnnouncementsScreen $announcements Announcements screen.
	 * @param SettingsScreen      $settings      Settings screen.
	 * @param ImportScreen        $import        Import screen.
	 * @param ReportsScreen       $reports       Reports screen.
	 * @param CertificatesScreen  $certificates  Certificates screen.
	 */
	public function __construct(
		EditionsScreen $editions,
		RequestsScreen $requests,
		EmailsScreen $emails,
		MigrationScreen $migration,
		AnnouncementsScreen $announcements,
		SettingsScreen $settings,
		ImportScreen $import,
		ReportsScreen $reports,
		CertificatesScreen $certificates
	) {
		$this->editions      = $editions;
		$this->requests      = $requests;
		$this->emails        = $emails;
		$this->migration     = $migration;
		$this->announcements = $announcements;
		$this->settings      = $settings;
		$this->import        = $import;
		$this->reports       = $reports;
		$this->certificates  = $certificates;
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
			__( 'Solicitudes', 'aula-virtual' ),
			__( 'Solicitudes', 'aula-virtual' ),
			Capabilities::APPROVE_REQUESTS,
			RequestsScreen::SLUG,
			array( $this->requests, 'render' )
		);

		add_submenu_page(
			self::EDITIONS_SLUG,
			__( 'Importar alumnos', 'aula-virtual' ),
			__( 'Importar alumnos', 'aula-virtual' ),
			Capabilities::IMPORT_STUDENTS,
			ImportScreen::SLUG,
			array( $this->import, 'render' )
		);

		add_submenu_page(
			self::EDITIONS_SLUG,
			__( 'Anuncios', 'aula-virtual' ),
			__( 'Anuncios', 'aula-virtual' ),
			Capabilities::MANAGE_ANNOUNCE,
			AnnouncementsScreen::SLUG,
			array( $this->announcements, 'render' )
		);

		add_submenu_page(
			self::EDITIONS_SLUG,
			__( 'Certificados', 'aula-virtual' ),
			__( 'Certificados', 'aula-virtual' ),
			Capabilities::ISSUE_CERTIFICATES,
			CertificatesScreen::SLUG,
			array( $this->certificates, 'render' )
		);

		add_submenu_page(
			self::EDITIONS_SLUG,
			__( 'Reportes', 'aula-virtual' ),
			__( 'Reportes', 'aula-virtual' ),
			Capabilities::VIEW_REPORTS,
			ReportsScreen::SLUG,
			array( $this->reports, 'render' )
		);

		add_submenu_page(
			self::EDITIONS_SLUG,
			__( 'Emails', 'aula-virtual' ),
			__( 'Emails', 'aula-virtual' ),
			Capabilities::MANAGE_LMS,
			EmailsScreen::SLUG,
			array( $this->emails, 'render' )
		);

		if ( TutorReader::is_available() ) {
			add_submenu_page(
				self::EDITIONS_SLUG,
				__( 'Migrar desde Tutor LMS', 'aula-virtual' ),
				__( 'Migrar desde Tutor', 'aula-virtual' ),
				Capabilities::MANAGE_LMS,
				MigrationScreen::SLUG,
				array( $this->migration, 'render' )
			);
		}

		add_submenu_page(
			self::EDITIONS_SLUG,
			__( 'Cursos', 'aula-virtual' ),
			__( 'Cursos', 'aula-virtual' ),
			Capabilities::course_capabilities()['edit_posts'],
			'edit.php?post_type=av_course'
		);

		add_submenu_page(
			self::EDITIONS_SLUG,
			__( 'Configuracion', 'aula-virtual' ),
			__( 'Configuracion', 'aula-virtual' ),
			Capabilities::MANAGE_LMS,
			SettingsScreen::SLUG,
			array( $this->settings, 'render' )
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
