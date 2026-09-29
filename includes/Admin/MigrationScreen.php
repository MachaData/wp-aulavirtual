<?php
/**
 * Tutor LMS migration screen.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Admin;

use SIQA\AulaVirtual\Courses\CourseRepository;
use SIQA\AulaVirtual\Migration\TutorMigrator;
use SIQA\AulaVirtual\Migration\TutorReader;
use SIQA\AulaVirtual\Permissions\Capabilities;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shows the Tutor courses and runs the migration one course at a time.
 */
final class MigrationScreen {

	public const SLUG           = 'aula-virtual-migracion';
	public const ACTION_MIGRATE = 'av_migrate_tutor_course';

	/**
	 * Tutor reader.
	 *
	 * @var TutorReader
	 */
	private TutorReader $tutor;

	/**
	 * Migrator.
	 *
	 * @var TutorMigrator
	 */
	private TutorMigrator $migrator;

	/**
	 * Course queries.
	 *
	 * @var CourseRepository
	 */
	private CourseRepository $courses;

	/**
	 * Constructor.
	 *
	 * @param TutorReader      $tutor    Tutor reader.
	 * @param TutorMigrator    $migrator Migrator.
	 * @param CourseRepository $courses  Course queries.
	 */
	public function __construct( TutorReader $tutor, TutorMigrator $migrator, CourseRepository $courses ) {
		$this->tutor    = $tutor;
		$this->migrator = $migrator;
		$this->courses  = $courses;
	}

	/**
	 * Renders the screen.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( Capabilities::MANAGE_LMS ) ) {
			wp_die( esc_html__( 'No tienes permisos para ver esta página.', 'aula-virtual' ) );
		}

		$notice = null;
		$report = null;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only, produced by our own redirect.
		if ( isset( $_GET['av_notice'], $_GET['av_message'] ) ) {
			$notice = array(
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only.
				'type'    => 'success' === sanitize_key( wp_unslash( $_GET['av_notice'] ) ) ? 'success' : 'error',
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only.
				'message' => sanitize_text_field( wp_unslash( $_GET['av_message'] ) ),
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only report stored by our own handler.
		if ( isset( $_GET['av_report'] ) ) {
			$stored = get_transient( 'av_tutor_report_' . get_current_user_id() );
			$report = is_array( $stored ) ? $stored : null;
		}

		$data = array(
			'available' => TutorReader::is_available(),
			'courses'   => TutorReader::is_available() ? $this->tutor->courses() : array(),
			'map'       => $this->migrator->map(),
			'targets'   => $this->courses->paginate( array( 'status' => array( 'publish', 'draft', 'private' ), 'per_page' => 100, 'orderby' => 'title', 'order' => 'ASC' ) )['items'],
			'notice'    => $notice,
			'report'    => $report,
		);

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- controlled template data.
		extract( $data, EXTR_SKIP );

		require AV_PATH . 'admin/views/migration.php';
	}

	/**
	 * Runs the migration of one course.
	 *
	 * @return void
	 */
	public function handle_migrate(): void {
		if ( ! current_user_can( Capabilities::MANAGE_LMS ) ) {
			wp_die( esc_html__( 'No tienes permisos para realizar esta acción.', 'aula-virtual' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( self::ACTION_MIGRATE );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$tutor_course_id = isset( $_POST['tutor_course_id'] ) ? absint( wp_unslash( $_POST['tutor_course_id'] ) ) : 0;

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$target_course_id = isset( $_POST['target_course_id'] ) ? absint( wp_unslash( $_POST['target_course_id'] ) ) : 0;

		$result = $this->migrator->migrate_course( $tutor_course_id, $target_course_id );
		$base   = add_query_arg( 'page', self::SLUG, admin_url( 'admin.php' ) );

		if ( $result instanceof WP_Error ) {
			wp_safe_redirect( add_query_arg( array( 'av_notice' => 'error', 'av_message' => rawurlencode( $result->get_error_message() ) ), $base ) );
			exit;
		}

		set_transient( 'av_tutor_report_' . get_current_user_id(), $result, MINUTE_IN_SECONDS * 10 );

		$message = (int) $result['remaining'] > 0
			? __( 'Lote migrado. Quedan alumnos por procesar: vuelve a pulsar el botón del curso.', 'aula-virtual' )
			: __( 'Curso migrado por completo.', 'aula-virtual' );

		wp_safe_redirect( add_query_arg( array( 'av_notice' => 'success', 'av_message' => rawurlencode( $message ), 'av_report' => 1 ), $base ) );
		exit;
	}
}
