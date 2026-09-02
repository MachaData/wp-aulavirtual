<?php
/**
 * Campus front end.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Campus;

use SIQA\AulaVirtual\Curriculum\LessonRepository;
use SIQA\AulaVirtual\Editions\EditionRepository;
use SIQA\AulaVirtual\Enrollments\EnrollmentRepository;
use SIQA\AulaVirtual\Enrollments\EnrollmentService;
use SIQA\AulaVirtual\Progress\ProgressRepository;
use SIQA\AulaVirtual\Progress\ProgressService;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the student campus and handles the actions it offers.
 *
 * The campus is one page with three views selected by query arguments, which
 * keeps it working on any theme and any permalink structure. Pretty URLs
 * (/campus/curso/{code}/) come with the rewrite rules of the next milestone.
 */
final class CampusController {

	public const ACTION_COMPLETE = 'av_complete_lesson';
	public const QUERY_EDITION   = 'av_edicion';
	public const QUERY_LESSON    = 'av_leccion';

	/**
	 * Enrollment persistence.
	 *
	 * @var EnrollmentRepository
	 */
	private EnrollmentRepository $enrollments;

	/**
	 * Access rules.
	 *
	 * @var EnrollmentService
	 */
	private EnrollmentService $enrollment_service;

	/**
	 * Edition persistence.
	 *
	 * @var EditionRepository
	 */
	private EditionRepository $editions;

	/**
	 * Lesson persistence.
	 *
	 * @var LessonRepository
	 */
	private LessonRepository $lessons;

	/**
	 * Progress persistence.
	 *
	 * @var ProgressRepository
	 */
	private ProgressRepository $progress;

	/**
	 * Progress rules.
	 *
	 * @var ProgressService
	 */
	private ProgressService $progress_service;

	/**
	 * Constructor.
	 *
	 * @param EnrollmentRepository $enrollments        Enrollment persistence.
	 * @param EnrollmentService    $enrollment_service Access rules.
	 * @param EditionRepository    $editions           Edition persistence.
	 * @param LessonRepository     $lessons            Lesson persistence.
	 * @param ProgressRepository   $progress           Progress persistence.
	 * @param ProgressService      $progress_service   Progress rules.
	 */
	public function __construct(
		EnrollmentRepository $enrollments,
		EnrollmentService $enrollment_service,
		EditionRepository $editions,
		LessonRepository $lessons,
		ProgressRepository $progress,
		ProgressService $progress_service
	) {
		$this->enrollments        = $enrollments;
		$this->enrollment_service = $enrollment_service;
		$this->editions           = $editions;
		$this->lessons            = $lessons;
		$this->progress           = $progress;
		$this->progress_service   = $progress_service;
	}

	/**
	 * Renders the campus shortcode.
	 *
	 * @return string
	 */
	public function render(): string {
		if ( ! is_user_logged_in() ) {
			return $this->template( 'login', array( 'redirect' => $this->campus_url() ) );
		}

		$user_id = get_current_user_id();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
		$lesson_id = isset( $_GET[ self::QUERY_LESSON ] ) ? absint( wp_unslash( $_GET[ self::QUERY_LESSON ] ) ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
		$edition_id = isset( $_GET[ self::QUERY_EDITION ] ) ? absint( wp_unslash( $_GET[ self::QUERY_EDITION ] ) ) : 0;

		if ( $lesson_id > 0 ) {
			return $this->render_lesson( $user_id, $lesson_id );
		}

		if ( $edition_id > 0 ) {
			return $this->render_edition( $user_id, $edition_id );
		}

		return $this->render_dashboard( $user_id );
	}

	/**
	 * Renders the list of courses the student is enrolled in.
	 *
	 * @param int $user_id Student id.
	 * @return string
	 */
	private function render_dashboard( int $user_id ): string {
		$enrollments = $this->enrollments->active_for_student( $user_id );
		$cards       = array();

		foreach ( $enrollments as $enrollment ) {
			$edition = $this->editions->find( (int) $enrollment['edition_id'] );

			if ( null === $edition ) {
				continue;
			}

			$cards[] = array(
				'edition'    => $edition,
				'enrollment' => $enrollment,
				'course'     => get_post( (int) $enrollment['course_id'] ),
				'url'        => $this->campus_url( array( self::QUERY_EDITION => (int) $edition['id'] ) ),
			);
		}

		return $this->template(
			'dashboard',
			array(
				'cards' => $cards,
				'user'  => wp_get_current_user(),
			)
		);
	}

	/**
	 * Renders the lesson list of an edition.
	 *
	 * @param int $user_id    Student id.
	 * @param int $edition_id Edition id.
	 * @return string
	 */
	private function render_edition( int $user_id, int $edition_id ): string {
		if ( ! $this->enrollment_service->has_access( $user_id, $edition_id ) ) {
			return $this->denied();
		}

		$edition   = $this->editions->find( $edition_id );
		$lessons   = $this->lessons->for_edition( $edition_id );
		$completed = $this->progress->completed_lesson_ids( $user_id, $edition_id );

		$items = array();

		foreach ( $lessons as $lesson ) {
			$items[] = array(
				'lesson'    => $lesson,
				'completed' => in_array( (int) $lesson['id'], $completed, true ),
				'url'       => $this->campus_url( array( self::QUERY_LESSON => (int) $lesson['id'] ) ),
			);
		}

		$enrollment = $this->enrollments->find_for_student( $user_id, $edition_id );

		return $this->template(
			'edition',
			array(
				'edition'    => $edition,
				'course'     => get_post( (int) $edition['course_id'] ),
				'items'      => $items,
				'percentage' => null === $enrollment ? 0.0 : (float) $enrollment['progress_percentage'],
				'back_url'   => $this->campus_url(),
			)
		);
	}

	/**
	 * Renders a single lesson.
	 *
	 * @param int $user_id   Student id.
	 * @param int $lesson_id Lesson id.
	 * @return string
	 */
	private function render_lesson( int $user_id, int $lesson_id ): string {
		$lesson = $this->lessons->find( $lesson_id );

		if ( null === $lesson || ! $this->enrollment_service->has_access( $user_id, (int) $lesson['edition_id'] ) ) {
			return $this->denied();
		}

		$this->progress_service->start( $user_id, $lesson_id );

		$progress = $this->progress->find_for_lesson( $user_id, $lesson_id );

		return $this->template(
			'lesson',
			array(
				'lesson'    => $lesson,
				'completed' => null !== $progress && ProgressRepository::STATUS_COMPLETED === $progress['status'],
				'back_url'  => $this->campus_url( array( self::QUERY_EDITION => (int) $lesson['edition_id'] ) ),
			)
		);
	}

	/**
	 * Handles the "mark as completed" submission.
	 *
	 * @return void
	 */
	public function handle_complete(): void {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Necesitas iniciar sesion.', 'aula-virtual' ), '', array( 'response' => 401 ) );
		}

		check_admin_referer( self::ACTION_COMPLETE );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified right above.
		$lesson_id = isset( $_POST['lesson_id'] ) ? absint( wp_unslash( $_POST['lesson_id'] ) ) : 0;

		$result = $this->progress_service->complete( get_current_user_id(), $lesson_id );

		if ( $result instanceof WP_Error ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 403 ) );
		}

		$lesson   = $this->lessons->find( $lesson_id );
		$redirect = null === $lesson
			? $this->campus_url()
			: $this->campus_url( array( self::QUERY_EDITION => (int) $lesson['edition_id'] ) );

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Returns the URL of the campus page with optional query arguments.
	 *
	 * @param array<string, int|string> $args Query arguments.
	 * @return string
	 */
	public function campus_url( array $args = array() ): string {
		$pages   = get_option( 'av_pages', array() );
		$page_id = is_array( $pages ) && isset( $pages['campus'] ) ? (int) $pages['campus'] : 0;
		$base    = $page_id > 0 ? (string) get_permalink( $page_id ) : home_url( '/' );

		return array() === $args ? $base : add_query_arg( $args, $base );
	}

	/**
	 * Renders the "no access" message.
	 *
	 * @return string
	 */
	private function denied(): string {
		return $this->template( 'denied', array( 'back_url' => $this->campus_url() ) );
	}

	/**
	 * Renders a campus template, allowing the theme to override it.
	 *
	 * A theme can replace any template by placing it under
	 * `aula-virtual/campus/{name}.php` in its own directory.
	 *
	 * @param string               $name Template name.
	 * @param array<string, mixed> $data Variables exposed to the template.
	 * @return string
	 */
	private function template( string $name, array $data ): string {
		$name     = sanitize_file_name( $name );
		$override = locate_template( array( 'aula-virtual/campus/' . $name . '.php' ) );
		$path     = '' !== $override ? $override : AV_PATH . 'templates/campus/' . $name . '.php';

		if ( ! is_readable( $path ) ) {
			return '';
		}

		$data['controller'] = $this;

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- controlled template data.
		extract( $data, EXTR_SKIP );

		ob_start();
		require $path;

		return (string) ob_get_clean();
	}
}
