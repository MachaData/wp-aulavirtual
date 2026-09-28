<?php
/**
 * Campus front end.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Campus;

use SIQA\AulaVirtual\Announcements\AnnouncementRepository;
use SIQA\AulaVirtual\Curriculum\LessonRepository;
use SIQA\AulaVirtual\Editions\EditionRepository;
use SIQA\AulaVirtual\Enrollments\EnrollmentRepository;
use SIQA\AulaVirtual\Enrollments\EnrollmentService;
use SIQA\AulaVirtual\LiveClasses\LiveClassRepository;
use SIQA\AulaVirtual\LiveClasses\LiveClassService;
use SIQA\AulaVirtual\Materials\MaterialRepository;
use SIQA\AulaVirtual\Materials\MaterialService;
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
	public const ACTION_PROFILE  = 'av_update_profile';
	public const QUERY_EDITION   = 'av_edicion';
	public const QUERY_LESSON    = 'av_leccion';
	public const QUERY_PROFILE   = 'av_perfil';

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
	 * Live class persistence.
	 *
	 * @var LiveClassRepository
	 */
	private LiveClassRepository $live_classes;

	/**
	 * Material persistence.
	 *
	 * @var MaterialRepository
	 */
	private MaterialRepository $materials;

	/**
	 * Announcement persistence.
	 *
	 * @var AnnouncementRepository
	 */
	private AnnouncementRepository $announcements;

	/**
	 * Constructor.
	 *
	 * @param EnrollmentRepository $enrollments        Enrollment persistence.
	 * @param EnrollmentService    $enrollment_service Access rules.
	 * @param EditionRepository    $editions           Edition persistence.
	 * @param LessonRepository     $lessons            Lesson persistence.
	 * @param ProgressRepository   $progress           Progress persistence.
	 * @param ProgressService      $progress_service   Progress rules.
	 * @param LiveClassRepository  $live_classes       Live class persistence.
	 * @param MaterialRepository   $materials          Material persistence.
	 * @param AnnouncementRepository $announcements    Announcement persistence.
	 */
	public function __construct(
		EnrollmentRepository $enrollments,
		EnrollmentService $enrollment_service,
		EditionRepository $editions,
		LessonRepository $lessons,
		ProgressRepository $progress,
		ProgressService $progress_service,
		LiveClassRepository $live_classes,
		MaterialRepository $materials,
		AnnouncementRepository $announcements
	) {
		$this->enrollments        = $enrollments;
		$this->enrollment_service = $enrollment_service;
		$this->editions           = $editions;
		$this->lessons            = $lessons;
		$this->progress           = $progress;
		$this->progress_service   = $progress_service;
		$this->live_classes       = $live_classes;
		$this->materials          = $materials;
		$this->announcements      = $announcements;
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

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
		if ( isset( $_GET[ self::QUERY_PROFILE ] ) ) {
			return $this->render_profile( $user_id );
		}

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

		$news = array();

		foreach ( $cards as $card ) {
			foreach ( $this->announcements->for_student( (int) $card['enrollment']['course_id'], (int) $card['edition']['id'], 3 ) as $item ) {
				$item['course_title'] = $card['course'] instanceof \WP_Post ? get_the_title( $card['course'] ) : '';
				$news[]               = $item;
			}
		}

		usort( $news, static fn( array $a, array $b ): int => strcmp( (string) $b['created_at'], (string) $a['created_at'] ) );

		return $this->template(
			'dashboard',
			array(
				'cards'         => $cards,
				'user'          => wp_get_current_user(),
				'announcements' => array_slice( $news, 0, 5 ),
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
				'announcements' => $this->announcements->for_student( (int) $edition['course_id'], $edition_id, 10 ),
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
		$edition  = $this->editions->find( (int) $lesson['edition_id'] );
		$live     = $this->live_classes->for_lesson( $lesson_id );
		$now      = current_time( 'mysql', true );
		$timezone = (string) ( $edition['timezone'] ?? '' );
		$timezone = '' === $timezone ? wp_timezone_string() : $timezone;

		$live_view = null;

		if ( null !== $live ) {
			$state = LiveClassService::window_state(
				(string) $live['start_datetime'],
				(string) $live['end_datetime'],
				(int) $live['open_before'],
				(int) $live['close_after'],
				$now
			);

			$live_view = array(
				'provider'    => LiveClassService::providers()[ $live['provider'] ] ?? (string) $live['provider'],
				'start_local' => LiveClassService::to_local( (string) $live['start_datetime'], $timezone, (string) get_option( 'date_format' ) . ' H:i' ),
				'end_local'   => LiveClassService::to_local( (string) $live['end_datetime'], $timezone, 'H:i' ),
				'timezone'    => $timezone,
				'state'       => $state,
				'status'      => (string) $live['status'],
				// The meeting link only leaves the server while the window is open.
				'url'         => LiveClassService::WINDOW_OPEN === $state && 'cancelled' !== $live['status'] ? (string) $live['meeting_url'] : '',
				'meeting_id'  => LiveClassService::WINDOW_OPEN === $state ? (string) $live['meeting_id'] : '',
				'access_code' => LiveClassService::WINDOW_OPEN === $state ? (string) $live['access_code'] : '',
				'message'     => (string) $live['message'],
				'recording'   => (string) $live['recording_url'],
			);
		}

		$materials = array();

		foreach ( $this->materials->for_lesson( $lesson_id ) as $material ) {
			$url = MaterialService::url( $material );

			if ( '' === $url ) {
				continue;
			}

			$materials[] = array(
				'title'        => (string) $material['title'],
				'description'  => (string) $material['description'],
				'url'          => $url,
				'type'         => (string) $material['file_type'],
				'downloadable' => (bool) $material['downloadable'],
			);
		}

		return $this->template(
			'lesson',
			array(
				'lesson'    => $lesson,
				'live'      => $live_view,
				'materials' => $materials,
				'completed' => null !== $progress && ProgressRepository::STATUS_COMPLETED === $progress['status'],
				'back_url'  => $this->campus_url( array( self::QUERY_EDITION => (int) $lesson['edition_id'] ) ),
			)
		);
	}

	/**
	 * Renders the student profile.
	 *
	 * @param int $user_id Student id.
	 * @return string
	 */
	private function render_profile( int $user_id ): string {
		$user = get_userdata( $user_id );

		if ( false === $user ) {
			return $this->denied();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag set by our own redirect.
		$result = isset( $_GET['av_resultado'] ) ? sanitize_key( wp_unslash( $_GET['av_resultado'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only message set by our own redirect.
		$message = isset( $_GET['av_mensaje'] ) ? sanitize_text_field( wp_unslash( $_GET['av_mensaje'] ) ) : '';

		return $this->template(
			'profile',
			array(
				'user'     => $user,
				'phone'    => (string) get_user_meta( $user_id, 'av_phone', true ),
				'document' => (string) get_user_meta( $user_id, 'av_document', true ),
				'result'   => $result,
				'message'  => $message,
				'back_url' => $this->campus_url(),
			)
		);
	}

	/**
	 * Handles the profile form.
	 *
	 * The email is read-only on purpose: it is the key that ties enrollments
	 * and orders to the student, so only an administrator changes it. A new
	 * password requires the current one, so an open session cannot be hijacked
	 * into a permanent takeover.
	 *
	 * @return void
	 */
	public function handle_profile(): void {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Necesitas iniciar sesion.', 'aula-virtual' ), '', array( 'response' => 401 ) );
		}

		check_admin_referer( self::ACTION_PROFILE );

		$user_id = get_current_user_id();
		$user    = get_userdata( $user_id );

		if ( false === $user ) {
			wp_die( esc_html__( 'Usuario no valido.', 'aula-virtual' ), '', array( 'response' => 403 ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$input = wp_unslash( $_POST );

		$first = sanitize_text_field( (string) ( $input['first_name'] ?? '' ) );
		$last  = sanitize_text_field( (string) ( $input['last_name'] ?? '' ) );

		if ( '' === $first ) {
			$this->redirect_profile( 'error', __( 'Indica tu nombre.', 'aula-virtual' ) );
		}

		$updated = wp_update_user(
			array(
				'ID'           => $user_id,
				'first_name'   => $first,
				'last_name'    => $last,
				'display_name' => trim( $first . ' ' . $last ),
			)
		);

		if ( is_wp_error( $updated ) ) {
			$this->redirect_profile( 'error', $updated->get_error_message() );
		}

		update_user_meta( $user_id, 'av_phone', sanitize_text_field( (string) ( $input['phone'] ?? '' ) ) );
		update_user_meta( $user_id, 'av_document', sanitize_text_field( (string) ( $input['document'] ?? '' ) ) );

		$new_password = (string) ( $input['new_password'] ?? '' );

		if ( '' !== $new_password ) {
			$current = (string) ( $input['current_password'] ?? '' );

			if ( ! wp_check_password( $current, $user->user_pass, $user_id ) ) {
				$this->redirect_profile( 'error', __( 'La contrasena actual no es correcta.', 'aula-virtual' ) );
			}

			if ( strlen( $new_password ) < 8 ) {
				$this->redirect_profile( 'error', __( 'La nueva contrasena debe tener al menos 8 caracteres.', 'aula-virtual' ) );
			}

			if ( $new_password !== (string) ( $input['confirm_password'] ?? '' ) ) {
				$this->redirect_profile( 'error', __( 'Las contrasenas no coinciden.', 'aula-virtual' ) );
			}

			wp_set_password( $new_password, $user_id );
			// wp_set_password destroys the session; log the student back in.
			wp_set_auth_cookie( $user_id, true );
		}

		$this->redirect_profile( 'ok', __( 'Datos guardados.', 'aula-virtual' ) );
	}

	/**
	 * Sends the student back to the profile with a result.
	 *
	 * @param string $result  ok or error.
	 * @param string $message Message.
	 * @return void
	 */
	private function redirect_profile( string $result, string $message ): void {
		wp_safe_redirect(
			$this->campus_url(
				array(
					self::QUERY_PROFILE => 1,
					'av_resultado'      => $result,
					'av_mensaje'        => rawurlencode( $message ),
				)
			)
		);
		exit;
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
	 * Swaps the theme template for a clean page when a lesson is viewed in
	 * focus mode.
	 *
	 * @param string $template Template chosen by WordPress.
	 * @return string
	 */
	public function focus_template( string $template ): string {
		if ( ! get_option( 'av_campus_focus_mode', false ) || ! is_user_logged_in() ) {
			return $template;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
		if ( empty( $_GET[ self::QUERY_LESSON ] ) ) {
			return $template;
		}

		$pages   = get_option( 'av_pages', array() );
		$page_id = is_array( $pages ) && isset( $pages['campus'] ) ? (int) $pages['campus'] : 0;

		if ( $page_id <= 0 || ! is_page( $page_id ) ) {
			return $template;
		}

		return AV_PATH . 'templates/campus/focus.php';
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
