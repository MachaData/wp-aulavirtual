<?php
/**
 * Editions admin screen.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Admin;

use SIQA\AulaVirtual\Courses\CourseDuplicator;
use SIQA\AulaVirtual\Courses\CourseRepository;
use SIQA\AulaVirtual\Curriculum\LessonRepository;
use SIQA\AulaVirtual\Curriculum\LessonService;
use SIQA\AulaVirtual\Editions\EditionDuplicator;
use SIQA\AulaVirtual\Editions\EditionRepository;
use SIQA\AulaVirtual\Editions\EditionService;
use SIQA\AulaVirtual\Enrollments\EnrollmentLinkRepository;
use SIQA\AulaVirtual\Enrollments\EnrollmentRepository;
use SIQA\AulaVirtual\Enrollments\EnrollmentService;
use SIQA\AulaVirtual\Enrollments\EnrollmentStatus;
use SIQA\AulaVirtual\Enrollments\RegistrationRequestRepository;
use SIQA\AulaVirtual\Enrollments\RegistrationService;
use SIQA\AulaVirtual\Permissions\AccessControl;
use SIQA\AulaVirtual\Permissions\Capabilities;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the editions list, the edition form and the edition detail.
 *
 * Every write arrives through `admin-post.php` with a nonce and a capability
 * check; the screen itself only reads and renders.
 */
final class EditionsScreen {

	public const ACTION_SAVE_EDITION = 'av_save_edition';
	public const ACTION_ADD_LESSON   = 'av_add_lesson';
	public const ACTION_ENROLL       = 'av_enroll_student';
	public const ACTION_CREATE_LINK  = 'av_create_registration_link';
	public const ACTION_DUPLICATE    = 'av_duplicate_edition';
	public const ACTION_MOVE_LESSON  = 'av_move_lesson';
	public const ACTION_DUPLICATE_COURSE = 'av_duplicate_course';
	public const ACTION_TOGGLE_LINK      = 'av_toggle_registration_link';

	/**
	 * Edition persistence.
	 *
	 * @var EditionRepository
	 */
	private EditionRepository $editions;

	/**
	 * Edition rules.
	 *
	 * @var EditionService
	 */
	private EditionService $edition_service;

	/**
	 * Lesson persistence.
	 *
	 * @var LessonRepository
	 */
	private LessonRepository $lessons;

	/**
	 * Lesson rules.
	 *
	 * @var LessonService
	 */
	private LessonService $lesson_service;

	/**
	 * Enrollment persistence.
	 *
	 * @var EnrollmentRepository
	 */
	private EnrollmentRepository $enrollments;

	/**
	 * Enrollment rules.
	 *
	 * @var EnrollmentService
	 */
	private EnrollmentService $enrollment_service;

	/**
	 * Course queries.
	 *
	 * @var CourseRepository
	 */
	private CourseRepository $courses;

	/**
	 * Permission checks.
	 *
	 * @var AccessControl
	 */
	private AccessControl $access;

	/**
	 * Registration link persistence.
	 *
	 * @var EnrollmentLinkRepository
	 */
	private EnrollmentLinkRepository $links;

	/**
	 * Registration rules.
	 *
	 * @var RegistrationService
	 */
	private RegistrationService $registration;

	/**
	 * Edition copies.
	 *
	 * @var EditionDuplicator
	 */
	private EditionDuplicator $duplicator;

	/**
	 * Course copies.
	 *
	 * @var CourseDuplicator
	 */
	private CourseDuplicator $course_duplicator;

	/**
	 * Registration request persistence (pending counter).
	 *
	 * @var RegistrationRequestRepository
	 */
	private RegistrationRequestRepository $requests;

	/**
	 * Tabs of the edition detail.
	 */
	public const TABS = array( 'sesiones', 'alumnos', 'inscripcion', 'ajustes' );

	/**
	 * Constructor.
	 *
	 * @param EditionRepository    $editions           Edition persistence.
	 * @param EditionService       $edition_service    Edition rules.
	 * @param LessonRepository     $lessons            Lesson persistence.
	 * @param LessonService        $lesson_service     Lesson rules.
	 * @param EnrollmentRepository $enrollments        Enrollment persistence.
	 * @param EnrollmentService    $enrollment_service Enrollment rules.
	 * @param CourseRepository     $courses            Course queries.
	 * @param AccessControl        $access             Permission checks.
	 * @param EnrollmentLinkRepository $links          Registration link persistence.
	 * @param RegistrationService  $registration       Registration rules.
	 * @param EditionDuplicator    $duplicator         Edition copies.
	 * @param CourseDuplicator     $course_duplicator  Course copies.
	 * @param RegistrationRequestRepository $requests  Registration requests.
	 */
	public function __construct(
		EditionRepository $editions,
		EditionService $edition_service,
		LessonRepository $lessons,
		LessonService $lesson_service,
		EnrollmentRepository $enrollments,
		EnrollmentService $enrollment_service,
		CourseRepository $courses,
		AccessControl $access,
		EnrollmentLinkRepository $links,
		RegistrationService $registration,
		EditionDuplicator $duplicator,
		CourseDuplicator $course_duplicator,
		RegistrationRequestRepository $requests
	) {
		$this->editions           = $editions;
		$this->edition_service    = $edition_service;
		$this->lessons            = $lessons;
		$this->lesson_service     = $lesson_service;
		$this->enrollments        = $enrollments;
		$this->enrollment_service = $enrollment_service;
		$this->courses            = $courses;
		$this->access             = $access;
		$this->links              = $links;
		$this->registration       = $registration;
		$this->duplicator         = $duplicator;
		$this->course_duplicator  = $course_duplicator;
		$this->requests           = $requests;
	}

	/**
	 * Renders the screen matching the current request.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( Capabilities::MANAGE_EDITIONS ) ) {
			wp_die( esc_html__( 'No tienes permisos para ver esta página.', 'aula-virtual' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation between views.
		$edition_id = isset( $_GET['edition'] ) ? absint( wp_unslash( $_GET['edition'] ) ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation between views.
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

		$notice = $this->current_notice();

		if ( $edition_id > 0 ) {
			$this->render_detail( $edition_id, $notice );

			return;
		}

		if ( 'new' === $action ) {
			$this->render_form( $notice );

			return;
		}

		$this->render_list( $notice );
	}

	/**
	 * Renders the list of editions.
	 *
	 * @param array{type: string, message: string}|null $notice Notice to show.
	 * @return void
	 */
	private function render_list( ?array $notice ): void {
		$editions = $this->editions->paginate(
			array(
				'order_by' => 'start_date',
				'order'    => 'DESC',
			),
			1,
			50
		);

		$seats = array();

		foreach ( $editions['items'] as $row ) {
			$seats[ (int) $row['id'] ] = $this->enrollments->count_seats_taken( (int) $row['id'] );
		}

		$this->view(
			'editions-list',
			array(
				'editions' => $editions,
				'seats'    => $seats,
				'notice'   => $notice,
				'screen'   => $this,
			)
		);
	}

	/**
	 * Renders the new edition form.
	 *
	 * @param array{type: string, message: string}|null $notice Notice to show.
	 * @return void
	 */
	private function render_form( ?array $notice ): void {
		$courses = $this->courses->paginate(
			array(
				'status'   => array( 'publish', 'draft', 'private' ),
				'per_page' => 100,
				'orderby'  => 'title',
				'order'    => 'ASC',
			)
		);

		$this->view(
			'edition-form',
			array(
				'courses' => $courses['items'],
				'notice'  => $notice,
			)
		);
	}

	/**
	 * Renders one edition with its lessons and students.
	 *
	 * @param int                                       $edition_id Edition id.
	 * @param array{type: string, message: string}|null $notice     Notice to show.
	 * @return void
	 */
	private function render_detail( int $edition_id, ?array $notice ): void {
		$edition = $this->editions->find( $edition_id );

		if ( null === $edition ) {
			wp_die( esc_html__( 'La edición no existe.', 'aula-virtual' ) );
		}

		if ( ! $this->access->can_manage_editions( (int) $edition['course_id'] ) ) {
			wp_die( esc_html__( 'No tienes permisos sobre esta edición.', 'aula-virtual' ) );
		}

		$students = $this->enrollments->for_edition( $edition_id );
		$lessons  = $this->lessons->for_edition( $edition_id, false );
		$links    = $this->links->for_edition( $edition_id );
		$people   = array();

		foreach ( $students as $student ) {
			$user_id = (int) $student['user_id'];
			$user    = get_userdata( $user_id );

			if ( false !== $user ) {
				$people[ $user_id ] = array(
					'name'  => (string) $user->display_name,
					'email' => (string) $user->user_email,
				);
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		$tab = in_array( $tab, self::TABS, true ) ? $tab : 'sesiones';

		$this->view(
			'edition-detail',
			array(
				'edition'        => $edition,
				'course'         => get_post( (int) $edition['course_id'] ),
				'lessons'        => $lessons,
				'students'       => $students,
				'people'         => $people,
				'links'          => $links,
				'stats'          => self::stats( $students, $lessons, $this->requests->count( array( 'edition_id' => $edition_id, 'status' => RegistrationRequestRepository::STATUS_PENDING ) ) ),
				'tab'            => $tab,
				'other_editions' => array_values(
					array_filter(
						$this->editions->all( array( 'order_by' => 'start_date', 'order' => 'DESC', 'limit' => 300 ) ),
						fn( array $e ): bool => (int) $e['id'] !== $edition_id && $this->access->can_manage_editions( (int) $e['course_id'] )
					)
				),
				'suggested_slug' => self::suggest_slug( (string) $edition['code'], array_map( static fn( array $l ): string => (string) $l['token'], $links ) ),
				'notice'         => $notice,
			)
		);
	}

	/**
	 * Handles the edition form submission.
	 *
	 * @return void
	 */
	public function handle_save_edition(): void {
		$this->guard( self::ACTION_SAVE_EDITION, Capabilities::MANAGE_EDITIONS );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		$input      = wp_unslash( $_POST );
		$edition_id = isset( $input['edition_id'] ) ? absint( $input['edition_id'] ) : 0;

		// Editar una edicion existente: el curso no cambia y debe ser del usuario.
		if ( $edition_id > 0 ) {
			$this->assert_edition_ownership( $edition_id );

			$current             = $this->editions->find( $edition_id );
			$input['course_id']  = (int) $current['course_id'];
			$result              = $this->edition_service->update( $edition_id, $input );
			$url                 = self::tab_url( $edition_id, 'ajustes' );

			if ( $result instanceof WP_Error ) {
				$this->redirect_with_notice( $url, 'error', $result->get_error_message() );
			}

			$this->redirect_with_notice( $url, 'success', __( 'Cambios guardados.', 'aula-virtual' ) );
		}

		$course_id = isset( $input['course_id'] ) ? absint( $input['course_id'] ) : 0;

		// The capability alone is not enough: the course has to be theirs.
		if ( ! $this->access->can_manage_editions( $course_id ) ) {
			wp_die( esc_html__( 'No tienes permisos sobre ese curso.', 'aula-virtual' ), '', array( 'response' => 403 ) );
		}

		$result = $this->edition_service->create( $input );

		if ( $result instanceof WP_Error ) {
			$this->redirect_with_notice(
				AdminMenu::editions_url( array( 'action' => 'new' ) ),
				'error',
				$result->get_error_message()
			);
		}

		$this->redirect_with_notice(
			self::tab_url( (int) $result, 'sesiones' ),
			'success',
			__( 'Edición creada. Ahora añade las sesiones del temario.', 'aula-virtual' )
		);
	}

	/**
	 * Handles the quick lesson form.
	 *
	 * @return void
	 */
	public function handle_add_lesson(): void {
		$this->guard( self::ACTION_ADD_LESSON, Capabilities::MANAGE_CURRICULUM );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		$input      = wp_unslash( $_POST );
		$edition_id = isset( $input['edition_id'] ) ? absint( $input['edition_id'] ) : 0;

		$this->assert_edition_ownership( $edition_id );

		$result = $this->lesson_service->create( $input );
		$url    = self::tab_url( $edition_id, 'sesiones' );

		if ( $result instanceof WP_Error ) {
			$this->redirect_with_notice( $url, 'error', $result->get_error_message() );
		}

		$this->redirect_with_notice( $url, 'success', __( 'Sesión añadida.', 'aula-virtual' ) );
	}

	/**
	 * Handles the manual enrollment form.
	 *
	 * @return void
	 */
	public function handle_enroll(): void {
		$this->guard( self::ACTION_ENROLL, Capabilities::ENROLL_STUDENTS );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		$input      = wp_unslash( $_POST );
		$edition_id = isset( $input['edition_id'] ) ? absint( $input['edition_id'] ) : 0;
		$user_id    = isset( $input['user_id'] ) ? absint( $input['user_id'] ) : 0;

		$this->assert_edition_ownership( $edition_id );

		$result = $this->enrollment_service->enroll( $user_id, $edition_id, EnrollmentStatus::SOURCE_MANUAL );
		$url    = self::tab_url( $edition_id, 'alumnos' );

		if ( $result instanceof WP_Error ) {
			$this->redirect_with_notice( $url, 'error', $result->get_error_message() );
		}

		$this->redirect_with_notice( $url, 'success', __( 'Alumno matriculado.', 'aula-virtual' ) );
	}

	/**
	 * Creates a registration link for an edition.
	 *
	 * @return void
	 */
	public function handle_create_link(): void {
		$this->guard( self::ACTION_CREATE_LINK, Capabilities::ENROLL_STUDENTS );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		$input      = wp_unslash( $_POST );
		$edition_id = isset( $input['edition_id'] ) ? absint( $input['edition_id'] ) : 0;

		$this->assert_edition_ownership( $edition_id );

		$result = $this->registration->create_link( $edition_id, $input );
		$url    = self::tab_url( $edition_id, 'inscripcion' );

		if ( $result instanceof WP_Error ) {
			$this->redirect_with_notice( $url, 'error', $result->get_error_message() );
		}

		$this->redirect_with_notice( $url, 'success', __( 'Enlace de inscripción creado. Ya puedes copiarlo y compartirlo.', 'aula-virtual' ) );
	}

	/**
	 * Turns a registration link off (or back on).
	 *
	 * @return void
	 */
	public function handle_toggle_link(): void {
		$this->guard( self::ACTION_TOGGLE_LINK, Capabilities::ENROLL_STUDENTS );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		$link_id = isset( $_POST['link_id'] ) ? absint( wp_unslash( $_POST['link_id'] ) ) : 0;
		$link    = $this->links->find( $link_id );

		if ( null === $link ) {
			wp_die( esc_html__( 'El enlace no existe.', 'aula-virtual' ), '', array( 'response' => 404 ) );
		}

		$this->assert_edition_ownership( (int) $link['edition_id'] );

		$active = \SIQA\AulaVirtual\Enrollments\EnrollmentLinkRepository::STATUS_ACTIVE === $link['status'];

		$this->links->update(
			$link_id,
			array( 'status' => $active ? \SIQA\AulaVirtual\Enrollments\EnrollmentLinkRepository::STATUS_DISABLED : \SIQA\AulaVirtual\Enrollments\EnrollmentLinkRepository::STATUS_ACTIVE )
		);

		$this->redirect_with_notice(
			self::tab_url( (int) $link['edition_id'], 'inscripcion' ),
			'success',
			$active ? __( 'Enlace desactivado: quien lo abra verá que ya no admite inscripciones.', 'aula-virtual' ) : __( 'Enlace activado de nuevo.', 'aula-virtual' )
		);
	}

	/**
	 * Duplicates an edition with its curriculum.
	 *
	 * @return void
	 */
	public function handle_duplicate(): void {
		$this->guard( self::ACTION_DUPLICATE, Capabilities::MANAGE_EDITIONS );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		$input      = wp_unslash( $_POST );
		$edition_id = isset( $input['edition_id'] ) ? absint( $input['edition_id'] ) : 0;

		$this->assert_edition_ownership( $edition_id );

		$result = $this->duplicator->duplicate(
			$edition_id,
			array(
				'name'           => $input['name'] ?? '',
				'start_date'     => $input['start_date'] ?? '',
				'copy_live'      => ! empty( $input['copy_live'] ),
				'copy_materials' => ! empty( $input['copy_materials'] ),
			)
		);

		if ( $result instanceof WP_Error ) {
			$this->redirect_with_notice( self::tab_url( $edition_id, 'ajustes' ), 'error', $result->get_error_message() );
		}

		$this->redirect_with_notice(
			self::tab_url( (int) $result['edition_id'], 'ajustes' ),
			'success',
			sprintf(
				/* translators: 1: lessons copied, 2: materials copied, 3: live classes copied. */
				__( 'Edición duplicada en borrador: %1$d sesiones, %2$d materiales y %3$d clases en vivo copiadas. Revisa las fechas y el estado antes de abrirla.', 'aula-virtual' ),
				(int) $result['lessons'],
				(int) $result['materials'],
				(int) $result['live_classes']
			)
		);
	}

	/**
	 * Moves a lesson one position up or down.
	 *
	 * @return void
	 */
	public function handle_move_lesson(): void {
		$this->guard( self::ACTION_MOVE_LESSON, Capabilities::MANAGE_CURRICULUM );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		$input      = wp_unslash( $_POST );
		$edition_id = isset( $input['edition_id'] ) ? absint( $input['edition_id'] ) : 0;
		$lesson_id  = isset( $input['lesson_id'] ) ? absint( $input['lesson_id'] ) : 0;
		$direction  = 'up' === ( $input['direction'] ?? '' ) ? -1 : 1;

		$this->assert_edition_ownership( $edition_id );

		$ids   = array_map( 'intval', wp_list_pluck( $this->lessons->for_edition( $edition_id, false ), 'id' ) );
		$index = array_search( $lesson_id, $ids, true );
		$url   = self::tab_url( $edition_id, 'sesiones' );

		if ( false === $index || ! isset( $ids[ $index + $direction ] ) ) {
			$this->redirect_with_notice( $url, 'error', __( 'No se puede mover esa sesión.', 'aula-virtual' ) );
		}

		$swap                     = $ids[ $index + $direction ];
		$ids[ $index + $direction ] = $lesson_id;
		$ids[ $index ]            = $swap;

		$this->lesson_service->reorder( $edition_id, $ids );
		$this->redirect_with_notice( $url, 'success', __( 'Orden actualizado.', 'aula-virtual' ) );
	}

	/**
	 * Duplicates a course from the course list (GET link with nonce).
	 *
	 * @return void
	 */
	public function handle_duplicate_course(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified right below.
		$course_id = isset( $_GET['course_id'] ) ? absint( wp_unslash( $_GET['course_id'] ) ) : 0;

		check_admin_referer( self::ACTION_DUPLICATE_COURSE . '_' . $course_id );

		if ( ! current_user_can( 'edit_post', $course_id ) ) {
			wp_die( esc_html__( 'No tienes permisos sobre ese curso.', 'aula-virtual' ), '', array( 'response' => 403 ) );
		}

		// Se copia el temario de la edicion mas reciente, si existe.
		$latest = $this->editions->for_course( $course_id );
		$result = $this->course_duplicator->duplicate(
			$course_id,
			array( 'copy_edition_id' => empty( $latest ) ? 0 : (int) end( $latest )['id'] )
		);

		if ( $result instanceof WP_Error ) {
			$this->redirect_with_notice( admin_url( 'edit.php?post_type=av_course' ), 'error', $result->get_error_message() );
		}

		wp_safe_redirect( get_edit_post_link( (int) $result['course_id'], 'raw' ) ?: admin_url( 'edit.php?post_type=av_course' ) );
		exit;
	}

	/**
	 * Returns the nonce-protected URL that duplicates a course.
	 *
	 * @param int $course_id Course id.
	 * @return string
	 */
	public static function duplicate_course_url( int $course_id ): string {
		return wp_nonce_url(
			add_query_arg(
				array( 'action' => self::ACTION_DUPLICATE_COURSE, 'course_id' => $course_id ),
				admin_url( 'admin-post.php' )
			),
			self::ACTION_DUPLICATE_COURSE . '_' . $course_id
		);
	}

	/**
	 * Summary figures for the edition header.
	 *
	 * @param array<int, array<string, mixed>> $students Enrollment rows.
	 * @param array<int, array<string, mixed>> $lessons  Lesson rows.
	 * @param int                              $pending  Pending registration requests.
	 * @return array{seats_taken: int, published_lessons: int, avg_progress: float, completed: int, pending_requests: int}
	 */
	public static function stats( array $students, array $lessons, int $pending ): array {
		$counted = array_values(
			array_filter(
				$students,
				static fn( array $e ): bool => in_array( (string) $e['status'], EnrollmentStatus::occupying_seat(), true )
			)
		);
		$sum     = array_sum( array_map( static fn( array $e ): float => (float) $e['progress_percentage'], $counted ) );

		return array(
			'seats_taken'       => count( $counted ),
			'published_lessons' => count( array_filter( $lessons, static fn( array $l ): bool => \SIQA\AulaVirtual\Curriculum\LessonType::STATUS_PUBLISH === $l['status'] ) ),
			'avg_progress'      => array() === $counted ? 0.0 : round( $sum / count( $counted ), 1 ),
			'completed'         => count( array_filter( $students, static fn( array $e ): bool => EnrollmentStatus::COMPLETED === $e['status'] ) ),
			'pending_requests'  => $pending,
		);
	}

	/**
	 * Proposes a readable address for a new registration link.
	 *
	 * @param string             $code  Edition code.
	 * @param array<int, string> $taken Tokens already used by this edition.
	 * @return string
	 */
	public static function suggest_slug( string $code, array $taken ): string {
		$base = sanitize_title( $code );

		if ( '' === $base ) {
			return '';
		}

		$slug = $base;

		for ( $n = 2; in_array( $slug, $taken, true ); $n++ ) {
			$slug = $base . '-' . $n;
		}

		return $slug;
	}

	/**
	 * URL of an edition tab.
	 *
	 * @param int    $edition_id Edition id.
	 * @param string $tab        Tab key.
	 * @return string
	 */
	private static function tab_url( int $edition_id, string $tab ): string {
		return AdminMenu::editions_url( array( 'edition' => $edition_id, 'tab' => $tab ) );
	}

	/**
	 * Verifies nonce and capability, or stops the request.
	 *
	 * @param string $action     Action name, used as the nonce action.
	 * @param string $capability Capability required.
	 * @return void
	 */
	private function guard( string $action, string $capability ): void {
		if ( ! current_user_can( $capability ) ) {
			wp_die( esc_html__( 'No tienes permisos para realizar esta acción.', 'aula-virtual' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( $action );
	}

	/**
	 * Stops the request when the user does not own the edition's course.
	 *
	 * @param int $edition_id Edition id.
	 * @return void
	 */
	private function assert_edition_ownership( int $edition_id ): void {
		$edition = $this->editions->find( $edition_id );

		if ( null === $edition || ! $this->access->can_manage_editions( (int) $edition['course_id'] ) ) {
			wp_die( esc_html__( 'No tienes permisos sobre esta edición.', 'aula-virtual' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Redirects back to a screen carrying a notice.
	 *
	 * @param string $url     Target URL.
	 * @param string $type    Notice type: success or error.
	 * @param string $message Message to display.
	 * @return void
	 */
	private function redirect_with_notice( string $url, string $type, string $message ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'av_notice' => $type,
					'av_message' => rawurlencode( $message ),
				),
				$url
			)
		);
		exit;
	}

	/**
	 * Reads the notice carried by the current request.
	 *
	 * @return array{type: string, message: string}|null
	 */
	private function current_notice(): ?array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only message produced by our own redirect.
		if ( ! isset( $_GET['av_notice'], $_GET['av_message'] ) ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only message produced by our own redirect.
		$type = sanitize_key( wp_unslash( $_GET['av_notice'] ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only message produced by our own redirect.
		$message = sanitize_text_field( wp_unslash( $_GET['av_message'] ) );

		return array(
			'type'    => 'success' === $type ? 'success' : 'error',
			'message' => $message,
		);
	}

	/**
	 * Loads an admin view.
	 *
	 * @param string               $view Template name without extension.
	 * @param array<string, mixed> $data Variables exposed to the template.
	 * @return void
	 */
	private function view( string $view, array $data ): void {
		$path = AV_PATH . 'admin/views/' . $view . '.php';

		if ( ! is_readable( $path ) ) {
			return;
		}

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- controlled template data.
		extract( $data, EXTR_SKIP );

		require $path;
	}
}
