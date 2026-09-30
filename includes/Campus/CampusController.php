<?php
/**
 * Campus front end.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Campus;

use SIQA\AulaVirtual\Announcements\AnnouncementRepository;
use SIQA\AulaVirtual\Certificates\CertificateRepository;
use SIQA\AulaVirtual\Certificates\CertificateService;
use SIQA\AulaVirtual\Comments\CommentService;
use SIQA\AulaVirtual\Curriculum\LessonType;
use SIQA\AulaVirtual\Landing\LandingRenderer;
use SIQA\AulaVirtual\Curriculum\ReleaseSchedule;
use SIQA\AulaVirtual\Materials\DownloadController;
use SIQA\AulaVirtual\Permissions\Capabilities;
use SIQA\AulaVirtual\Curriculum\LessonRepository;
use SIQA\AulaVirtual\Curriculum\ModuleRepository;
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
	public const ACTION_RETAKE   = 'av_retake_edition';
	public const ACTION_COMMENT  = 'av_post_comment';
	public const ACTION_DELETE_COMMENT = 'av_delete_comment';
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
	 * Lesson comments.
	 *
	 * @var CommentService
	 */
	private CommentService $comments;

	/**
	 * Certificate persistence.
	 *
	 * @var CertificateRepository
	 */
	private CertificateRepository $certificates;

	/**
	 * Certificate rules.
	 *
	 * @var CertificateService
	 */
	private CertificateService $certificate_service;

	/**
	 * Module rows (sections of the curriculum).
	 *
	 * @var ModuleRepository|null
	 */
	private ?ModuleRepository $modules;

	/**
	 * Edition codes already loaded in this request, for pretty URLs.
	 *
	 * @var array<int, string>
	 */
	private array $codes = array();

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
	 * @param CommentService       $comments           Lesson comments.
	 * @param CertificateRepository $certificates      Certificate persistence.
	 * @param CertificateService   $certificate_service Certificate rules.
	 * @param ModuleRepository|null $modules            Curriculum sections.
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
		AnnouncementRepository $announcements,
		CommentService $comments,
		CertificateRepository $certificates,
		CertificateService $certificate_service,
		?ModuleRepository $modules = null
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
		$this->comments           = $comments;
		$this->certificates       = $certificates;
		$this->certificate_service = $certificate_service;
		$this->modules             = $modules;
	}

	/**
	 * Renders the campus shortcode.
	 *
	 * @return string
	 */
	public function render(): string {
		self::enqueue_assets( true );

		if ( ! is_user_logged_in() ) {
			return $this->template( 'login', array( 'redirect' => $this->campus_url() ) );
		}

		$user_id = get_current_user_id();

		$lesson_id  = absint( $this->query( self::QUERY_LESSON ) );
		$edition_id = $this->resolve_edition_id( $this->query( self::QUERY_EDITION ) );

		if ( '' !== $this->query( self::QUERY_PROFILE ) ) {
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
		$cert_urls   = $this->certificate_urls( $user_id );

		foreach ( $enrollments as $enrollment ) {
			$edition = $this->editions->find( (int) $enrollment['edition_id'] );

			if ( null === $edition ) {
				continue;
			}

			$this->remember_code( $edition );

			$cards[] = array(
				'edition'    => $edition,
				'enrollment' => $enrollment,
				'course'     => get_post( (int) $enrollment['course_id'] ),
				'url'        => $this->campus_url( array( self::QUERY_EDITION => (int) $edition['id'] ) ),
				'certificate_url' => $cert_urls[ (int) $edition['id'] ] ?? '',
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

		$edition    = $this->editions->find( $edition_id );
		$enrollment = $this->enrollments->find_for_student( $user_id, $edition_id );
		$completed  = $this->progress->completed_lesson_ids( $user_id, $edition_id );

		$this->remember_code( $edition );

		$items = $this->edition_items( $user_id, $edition_id, $edition, $enrollment );

		return $this->template(
			'edition',
			array(
				'edition'    => $edition,
				'course'     => get_post( (int) $edition['course_id'] ),
				'items'      => $items,
				'sections'   => self::build_outline( $this->edition_modules( $edition_id ), $items ),
				'percentage' => null === $enrollment ? 0.0 : (float) $enrollment['progress_percentage'],
				'announcements' => $this->announcements->for_student( (int) $edition['course_id'], $edition_id, 10 ),
				'back_url'   => $this->campus_url(),
				'can_retake' => (bool) get_option( 'av_allow_retake', true ) && ! empty( $completed ),
				'certificate_url' => $this->certificate_urls( $user_id )[ $edition_id ] ?? '',
			)
		);
	}

	/**
	 * Published sessions of an edition with the student's state.
	 *
	 * @param int                       $user_id    Student id.
	 * @param int                       $edition_id Edition id.
	 * @param array<string, mixed>|null $edition    Edition row.
	 * @param array<string, mixed>|null $enrollment Enrollment row.
	 * @return array<int, array<string, mixed>>
	 */
	private function edition_items( int $user_id, int $edition_id, ?array $edition, ?array $enrollment ): array {
		$completed = $this->progress->completed_lesson_ids( $user_id, $edition_id );
		$now       = current_time( 'mysql', true );
		$timezone  = (string) ( $edition['timezone'] ?? '' );
		$timezone  = '' === $timezone ? wp_timezone_string() : $timezone;
		$items     = array();

		foreach ( $this->lessons->for_edition( $edition_id ) as $lesson ) {
			$available_at = ReleaseSchedule::available_at( $lesson, $enrollment );
			$available    = null === $available_at || strcmp( $available_at, $now ) <= 0;

			$items[] = array(
				'lesson'       => $lesson,
				'completed'    => in_array( (int) $lesson['id'], $completed, true ),
				'url'          => $available ? $this->campus_url( array( self::QUERY_LESSON => (int) $lesson['id'] ) ) : '',
				'available'    => $available,
				'available_at' => $available ? '' : LiveClassService::to_local( (string) $available_at, $timezone, (string) get_option( 'date_format' ) ),
			);
		}

		return $items;
	}

	/**
	 * Modules of an edition, or none when the repository is not wired.
	 *
	 * @param int $edition_id Edition id.
	 * @return array<int, array<string, mixed>>
	 */
	private function edition_modules( int $edition_id ): array {
		return null === $this->modules ? array() : $this->modules->for_edition( $edition_id );
	}

	/**
	 * What the campus shows of a material, or null when it has no file.
	 *
	 * @param array<string, mixed> $material Material row.
	 * @return array{title: string, description: string, url: string, type: string, downloadable: bool}|null
	 */
	private static function material_view( array $material ): ?array {
		if ( '' === MaterialService::url( $material ) ) {
			return null;
		}

		return array(
			'title'        => (string) $material['title'],
			'description'  => (string) $material['description'],
			// Siempre por el endpoint protegido; la URL directa solo la ve el servidor.
			'url'          => DownloadController::url( (int) $material['id'] ),
			'type'         => (string) $material['file_type'],
			'downloadable' => (bool) $material['downloadable'],
		);
	}

	/**
	 * Groups the sessions by module for the course outline.
	 *
	 * Sessions without a module (or whose module is gone) go first in a
	 * section without title. Numbers run across the whole course, as the
	 * student sees them in the lesson header ("Sesión 3 de 12").
	 *
	 * @param array<int, array<string, mixed>>             $modules   Module rows in order.
	 * @param array<int, array<string, mixed>>             $items     Session items in order.
	 * @param array<int, array<int, array<string, mixed>>> $materials Materials by lesson id.
	 * @return array<int, array{title: string, items: array<int, array<string, mixed>>, done: int, total: int, minutes: int}>
	 */
	public static function build_outline( array $modules, array $items, array $materials = array() ): array {
		$sections = array(
			0 => array(
				'title' => '',
				'items' => array(),
			),
		);

		foreach ( $modules as $module ) {
			$sections[ (int) $module['id'] ] = array(
				'title' => (string) $module['title'],
				'items' => array(),
			);
		}

		foreach ( array_values( $items ) as $index => $item ) {
			$lesson_id = (int) $item['lesson']['id'];
			$module_id = (int) ( $item['lesson']['module_id'] ?? 0 );
			$key       = isset( $sections[ $module_id ] ) ? $module_id : 0;

			$item['number']    = $index + 1;
			$item['materials'] = $materials[ $lesson_id ] ?? array();

			$sections[ $key ]['items'][] = $item;
		}

		$outline = array();

		foreach ( $sections as $section ) {
			if ( array() === $section['items'] ) {
				continue;
			}

			$section['total']   = count( $section['items'] );
			$section['done']    = count( array_filter( $section['items'], static fn( array $i ): bool => ! empty( $i['completed'] ) ) );
			$section['minutes'] = (int) array_sum( array_map( static fn( array $i ): int => (int) ( $i['lesson']['duration'] ?? 0 ), $section['items'] ) );
			$outline[]          = $section;
		}

		return $outline;
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

		if ( null === $lesson || LessonType::STATUS_PUBLISH !== $lesson['status'] || ! $this->enrollment_service->has_access( $user_id, (int) $lesson['edition_id'] ) ) {
			return $this->denied();
		}

		$edition  = $this->editions->find( (int) $lesson['edition_id'] );
		$now      = current_time( 'mysql', true );
		$timezone = (string) ( $edition['timezone'] ?? '' );
		$timezone = '' === $timezone ? wp_timezone_string() : $timezone;

		$this->remember_code( $edition );

		$enrollment   = $this->enrollments->find_for_student( $user_id, (int) $lesson['edition_id'] );
		$available_at = ReleaseSchedule::available_at( $lesson, $enrollment );

		if ( null !== $available_at && strcmp( $available_at, $now ) > 0 ) {
			return $this->template(
				'locked',
				array(
					'lesson'       => $lesson,
					'available_at' => LiveClassService::to_local( $available_at, $timezone, (string) get_option( 'date_format' ) . ' H:i' ),
					'timezone'     => $timezone,
					'back_url'     => $this->campus_url( array( self::QUERY_EDITION => (int) $lesson['edition_id'] ) ),
				)
			);
		}

		$this->progress_service->start( $user_id, $lesson_id );

		$progress = $this->progress->find_for_lesson( $user_id, $lesson_id );
		$live     = $this->live_classes->for_lesson( $lesson_id );

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

		$edition_id = (int) $lesson['edition_id'];
		$items      = $this->edition_items( $user_id, $edition_id, $edition, $enrollment );
		$by_lesson  = array();

		foreach ( $this->materials->all_for_edition( $edition_id ) as $material ) {
			$view = self::material_view( $material );

			if ( null !== $view && (int) $material['lesson_id'] > 0 ) {
				$by_lesson[ (int) $material['lesson_id'] ][] = $view;
			}
		}

		// Los materiales de una sesión cerrada no se anuncian en el panel.
		foreach ( $items as $item ) {
			if ( empty( $item['available'] ) ) {
				unset( $by_lesson[ (int) $item['lesson']['id'] ] );
			}
		}

		$materials = $by_lesson[ $lesson_id ] ?? array();
		$position  = 0;
		$prev      = null;
		$next      = null;

		foreach ( $items as $index => $item ) {
			if ( (int) $item['lesson']['id'] === $lesson_id ) {
				$position = $index + 1;
				$prev     = $items[ $index - 1 ] ?? null;
				$next     = $items[ $index + 1 ] ?? null;
			}
		}

		$course     = null === $edition ? null : get_post( (int) $edition['course_id'] );
		$link       = fn( ?array $item ): ?array => null === $item ? null : array(
			'title'     => (string) $item['lesson']['title'],
			'url'       => $this->campus_url( array( self::QUERY_LESSON => (int) $item['lesson']['id'] ) ),
			'available' => ! empty( $item['available'] ),
		);

		return $this->template(
			'lesson',
			array(
				'position'     => $position,
				'total'        => count( $items ),
				'prev'         => $link( $prev ),
				'next'         => $link( $next ),
				'sections'     => self::build_outline( $this->edition_modules( $edition_id ), $items, $by_lesson ),
				'done_count'   => count( array_filter( $items, static fn( array $i ): bool => ! empty( $i['completed'] ) ) ),
				'percentage'   => null === $enrollment ? 0.0 : (float) $enrollment['progress_percentage'],
				'course_title' => $course instanceof \WP_Post ? get_the_title( $course ) : (string) ( $edition['name'] ?? '' ),
				'lesson'    => $lesson,
				'live'      => $live_view,
				'materials' => $materials,
				'completed' => null !== $progress && ProgressRepository::STATUS_COMPLETED === $progress['status'],
				'back_url'  => $this->campus_url( array( self::QUERY_EDITION => (int) $lesson['edition_id'] ) ),
				'comments_enabled' => CommentService::enabled(),
				'comments'  => CommentService::enabled() ? $this->comments->thread( $lesson_id ) : array(),
				'can_moderate' => current_user_can( Capabilities::MANAGE_CURRICULUM ),
				'current_user_id' => $user_id,
			)
		);
	}

	/**
	 * Posts a comment on a lesson.
	 *
	 * @return void
	 */
	public function handle_comment(): void {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Necesitas iniciar sesión.', 'aula-virtual' ), '', array( 'response' => 401 ) );
		}

		check_admin_referer( self::ACTION_COMMENT );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified right above.
		$lesson_id = isset( $_POST['lesson_id'] ) ? absint( wp_unslash( $_POST['lesson_id'] ) ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified right above.
		$parent_id = isset( $_POST['parent_id'] ) ? absint( wp_unslash( $_POST['parent_id'] ) ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- sanitised by the service.
		$content = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '';

		$result = $this->comments->post( get_current_user_id(), $lesson_id, $content, $parent_id );

		if ( $result instanceof WP_Error ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => (int) ( $result->get_error_data()['status'] ?? 400 ) ) );
		}

		wp_safe_redirect( $this->campus_url( array( self::QUERY_LESSON => $lesson_id ) ) . '#av-comment-' . (int) $result );
		exit;
	}

	/**
	 * Hides a comment (author or course staff).
	 *
	 * @return void
	 */
	public function handle_delete_comment(): void {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Necesitas iniciar sesión.', 'aula-virtual' ), '', array( 'response' => 401 ) );
		}

		check_admin_referer( self::ACTION_DELETE_COMMENT );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified right above.
		$comment_id = isset( $_POST['comment_id'] ) ? absint( wp_unslash( $_POST['comment_id'] ) ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified right above.
		$lesson_id = isset( $_POST['lesson_id'] ) ? absint( wp_unslash( $_POST['lesson_id'] ) ) : 0;

		$result = $this->comments->remove( $comment_id, get_current_user_id() );

		if ( $result instanceof WP_Error ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 403 ) );
		}

		wp_safe_redirect( $this->campus_url( array( self::QUERY_LESSON => $lesson_id ) ) . '#av-comments' );
		exit;
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
			wp_die( esc_html__( 'Necesitas iniciar sesión.', 'aula-virtual' ), '', array( 'response' => 401 ) );
		}

		check_admin_referer( self::ACTION_PROFILE );

		$user_id = get_current_user_id();
		$user    = get_userdata( $user_id );

		if ( false === $user ) {
			wp_die( esc_html__( 'Usuario no válido.', 'aula-virtual' ), '', array( 'response' => 403 ) );
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
				$this->redirect_profile( 'error', __( 'La contraseña actual no es correcta.', 'aula-virtual' ) );
			}

			if ( strlen( $new_password ) < 8 ) {
				$this->redirect_profile( 'error', __( 'La nueva contraseña debe tener al menos 8 caracteres.', 'aula-virtual' ) );
			}

			if ( $new_password !== (string) ( $input['confirm_password'] ?? '' ) ) {
				$this->redirect_profile( 'error', __( 'Las contraseñas no coinciden.', 'aula-virtual' ) );
			}

			wp_set_password( $new_password, $user_id );
			// Cierra las demas sesiones: quien tuviera la cookie antigua queda fuera.
			wp_destroy_all_sessions();
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
			wp_die( esc_html__( 'Necesitas iniciar sesión.', 'aula-virtual' ), '', array( 'response' => 401 ) );
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
	 * Verification URLs of the student's valid certificates, by edition id.
	 *
	 * @param int $user_id Student id.
	 * @return array<int, string>
	 */
	private function certificate_urls( int $user_id ): array {
		$urls = array();

		foreach ( $this->certificates->for_student( $user_id ) as $certificate ) {
			if ( CertificateRepository::STATUS_ISSUED === $certificate['status'] ) {
				$urls[ (int) $certificate['edition_id'] ] = $this->certificate_service->url( $certificate );
			}
		}

		return $urls;
	}

	/**
	 * Restarts an edition for the current student.
	 *
	 * @return void
	 */
	public function handle_retake(): void {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Necesitas iniciar sesión.', 'aula-virtual' ), '', array( 'response' => 401 ) );
		}

		check_admin_referer( self::ACTION_RETAKE );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified right above.
		$edition_id = isset( $_POST['edition_id'] ) ? absint( wp_unslash( $_POST['edition_id'] ) ) : 0;

		$result = $this->progress_service->reset( get_current_user_id(), $edition_id );

		if ( $result instanceof WP_Error ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 403 ) );
		}

		wp_safe_redirect( $this->campus_url( array( self::QUERY_EDITION => $edition_id ) ) );
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

		if ( '' === $this->query( self::QUERY_LESSON ) ) {
			return $template;
		}

		$page_id = self::page_id();

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
		$page_id = self::page_id();
		$base    = $page_id > 0 ? (string) get_permalink( $page_id ) : home_url( '/' );

		if ( array() === $args ) {
			return $base;
		}

		if ( $page_id > 0 && '' !== (string) get_option( 'permalink_structure', '' ) ) {
			$pretty = self::pretty_path( $args, $this->codes );

			if ( null !== $pretty ) {
				return trailingslashit( $base ) . $pretty;
			}
		}

		return add_query_arg( $args, $base );
	}

	/**
	 * Builds the clean path for the campus arguments, or null when the
	 * arguments have no clean form.
	 *
	 * @param array<string, int|string> $args  Query arguments.
	 * @param array<int, string>        $codes Known edition codes by id.
	 * @return string|null
	 */
	public static function pretty_path( array $args, array $codes = array() ): ?string {
		if ( isset( $args[ self::QUERY_LESSON ] ) && 1 === count( $args ) ) {
			return 'sesion/' . (int) $args[ self::QUERY_LESSON ] . '/';
		}

		if ( isset( $args[ self::QUERY_EDITION ] ) && 1 === count( $args ) ) {
			$id = (int) $args[ self::QUERY_EDITION ];

			return 'curso/' . ( $codes[ $id ] ?? (string) $id ) . '/';
		}

		if ( isset( $args[ self::QUERY_PROFILE ] ) && 1 === count( $args ) ) {
			return 'perfil/';
		}

		return null;
	}

	/**
	 * Rewrite rules for a campus page: {uri}/curso/{code|id}/, {uri}/sesion/{id}/, {uri}/perfil/.
	 *
	 * @param string $page_uri Page path relative to the site root, without slashes.
	 * @param int    $page_id  Campus page id.
	 * @return array<string, string> Regex => query.
	 */
	public static function rewrite_rules( string $page_uri, int $page_id ): array {
		$uri = preg_quote( trim( $page_uri, '/' ), '#' );

		if ( '' === $uri || $page_id <= 0 ) {
			return array();
		}

		return array(
			'^' . $uri . '/curso/([a-z0-9_-]+)/?$' => 'index.php?page_id=' . $page_id . '&' . self::QUERY_EDITION . '=$matches[1]',
			'^' . $uri . '/sesion/([0-9]+)/?$'     => 'index.php?page_id=' . $page_id . '&' . self::QUERY_LESSON . '=$matches[1]',
			'^' . $uri . '/perfil/?$'              => 'index.php?page_id=' . $page_id . '&' . self::QUERY_PROFILE . '=1',
		);
	}

	/**
	 * Registers the query vars and rewrite rules of the campus page.
	 *
	 * @return void
	 */
	public static function register_rewrite(): void {
		$page_id = self::page_id();

		if ( $page_id <= 0 ) {
			return;
		}

		$uri = get_page_uri( $page_id );

		foreach ( self::rewrite_rules( is_string( $uri ) ? $uri : '', $page_id ) as $regex => $query ) {
			add_rewrite_rule( $regex, $query, 'top' );
		}
	}

	/**
	 * Loads the campus stylesheet on the campus page.
	 *
	 * @param bool $force Load it even outside the campus page (shortcode elsewhere).
	 * @return void
	 */
	public static function enqueue_assets( bool $force = false ): void {
		$page_id = self::page_id();
		$post    = get_post();
		$here    = ( $page_id > 0 && is_page( $page_id ) ) || ( $post instanceof \WP_Post && has_shortcode( (string) $post->post_content, 'av_campus' ) );

		if ( ! $force && ! $here ) {
			return;
		}

		if ( ! wp_style_is( 'av-campus', 'enqueued' ) ) {
			wp_enqueue_style( 'av-campus', AV_URL . 'assets/css/campus.css', array(), AV_VERSION );
			wp_add_inline_style( 'av-campus', self::inline_css( LandingRenderer::accent() ) );
		}

		if ( ! wp_script_is( 'av-campus', 'enqueued' ) ) {
			wp_enqueue_script( 'av-campus', AV_URL . 'assets/js/campus.js', array(), AV_VERSION, true );
		}
	}

	/**
	 * Brand variables of the campus.
	 *
	 * @param string $accent Brand colour.
	 * @return string
	 */
	public static function inline_css( string $accent ): string {
		$accent = 1 === preg_match( '/^#[0-9a-fA-F]{6}$/', $accent ) ? strtolower( $accent ) : '#1d4ed8';

		return '.av-campus,.av-focus{--av-accent:' . $accent . ';}';
	}

	/**
	 * Whether any campus rule is absent from the rules WordPress has stored.
	 *
	 * @param mixed                 $stored Value of the rewrite_rules option.
	 * @param array<string, string> $rules  Campus rules (regex => query).
	 * @return bool
	 */
	public static function rules_missing( $stored, array $rules ): bool {
		if ( array() === $rules ) {
			return false;
		}

		if ( ! is_array( $stored ) ) {
			return true;
		}

		foreach ( $rules as $regex => $query ) {
			if ( ! isset( $stored[ $regex ] ) || $stored[ $regex ] !== $query ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Regenerates the permalinks when the campus rules are not stored yet.
	 *
	 * Happens after installing or updating the plugin, or after renaming the
	 * campus page (e.g. /campus/ to /aula-virtual/): without it, the clean
	 * URLs /{campus}/curso/{codigo}/ answer 404 until someone saves
	 * Settings > Permalinks. Guarded so it runs at most once an hour.
	 *
	 * @return void
	 */
	public static function ensure_rewrite(): void {
		if ( '' === (string) get_option( 'permalink_structure', '' ) ) {
			return;
		}

		$page_id = self::page_id();
		$uri     = $page_id > 0 ? get_page_uri( $page_id ) : '';
		$rules   = self::rewrite_rules( is_string( $uri ) ? $uri : '', $page_id );

		if ( ! self::rules_missing( get_option( 'rewrite_rules' ), $rules ) || false !== get_transient( 'av_campus_rewrite_flush' ) ) {
			return;
		}

		set_transient( 'av_campus_rewrite_flush', 1, HOUR_IN_SECONDS );
		flush_rewrite_rules( false );
	}

	/**
	 * Adds the campus query vars so WordPress keeps them.
	 *
	 * @param array<int, string> $vars Public query vars.
	 * @return array<int, string>
	 */
	public static function query_vars( array $vars ): array {
		return array_merge( $vars, array( self::QUERY_EDITION, self::QUERY_LESSON, self::QUERY_PROFILE ) );
	}

	/**
	 * Id of the campus page.
	 *
	 * @return int
	 */
	public static function page_id(): int {
		$pages = get_option( 'av_pages', array() );

		return is_array( $pages ) && isset( $pages['campus'] ) ? (int) $pages['campus'] : 0;
	}

	/**
	 * Reads a campus argument from the rewrite (query var) or the query string.
	 *
	 * @param string $name Argument name.
	 * @return string
	 */
	private function query( string $name ): string {
		$value = get_query_var( $name, '' );

		if ( '' === $value || null === $value ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
			$value = isset( $_GET[ $name ] ) ? wp_unslash( $_GET[ $name ] ) : '';
		}

		return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
	}

	/**
	 * Turns the edition argument (id or code) into an id.
	 *
	 * @param string $value Raw argument.
	 * @return int
	 */
	private function resolve_edition_id( string $value ): int {
		if ( '' === $value ) {
			return 0;
		}

		if ( ctype_digit( $value ) ) {
			return (int) $value;
		}

		$edition = $this->editions->find_by_code( sanitize_title( $value ) );

		return null === $edition ? 0 : (int) $edition['id'];
	}

	/**
	 * Keeps the code of a loaded edition for pretty URLs.
	 *
	 * @param array<string, mixed>|null $edition Edition row.
	 * @return void
	 */
	private function remember_code( ?array $edition ): void {
		if ( null !== $edition && '' !== (string) ( $edition['code'] ?? '' ) ) {
			$this->codes[ (int) $edition['id'] ] = (string) $edition['code'];
		}
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
