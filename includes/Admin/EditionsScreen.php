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
		CourseDuplicator $course_duplicator
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
	}

	/**
	 * Renders the screen matching the current request.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( Capabilities::MANAGE_EDITIONS ) ) {
			wp_die( esc_html__( 'No tienes permisos para ver esta pagina.', 'aula-virtual' ) );
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

		$this->view(
			'editions-list',
			array(
				'editions' => $editions,
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
			wp_die( esc_html__( 'La edicion no existe.', 'aula-virtual' ) );
		}

		if ( ! $this->access->can_manage_editions( (int) $edition['course_id'] ) ) {
			wp_die( esc_html__( 'No tienes permisos sobre esta edicion.', 'aula-virtual' ) );
		}

		$students   = $this->enrollments->for_edition( $edition_id );
		$user_cache = array();

		foreach ( $students as $student ) {
			$user_id = (int) $student['user_id'];
			$user    = get_userdata( $user_id );

			$user_cache[ $user_id ] = false === $user
				? __( 'Usuario eliminado', 'aula-virtual' )
				: $user->display_name . ' (' . $user->user_email . ')';
		}

		$this->view(
			'edition-detail',
			array(
				'edition'  => $edition,
				'course'   => get_post( (int) $edition['course_id'] ),
				'lessons'  => $this->lessons->for_edition( $edition_id, false ),
				'students' => $students,
				'names'    => $user_cache,
				'links'    => $this->links->for_edition( $edition_id ),
				'notice'   => $notice,
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
		$input     = wp_unslash( $_POST );
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
			AdminMenu::editions_url( array( 'edition' => (int) $result ) ),
			'success',
			__( 'Edicion creada.', 'aula-virtual' )
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
		$url    = AdminMenu::editions_url( array( 'edition' => $edition_id ) );

		if ( $result instanceof WP_Error ) {
			$this->redirect_with_notice( $url, 'error', $result->get_error_message() );
		}

		$this->redirect_with_notice( $url, 'success', __( 'Sesion anadida.', 'aula-virtual' ) );
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
		$url    = AdminMenu::editions_url( array( 'edition' => $edition_id ) );

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
		$url    = AdminMenu::editions_url( array( 'edition' => $edition_id ) );

		if ( $result instanceof WP_Error ) {
			$this->redirect_with_notice( $url, 'error', $result->get_error_message() );
		}

		$this->redirect_with_notice( $url, 'success', __( 'Enlace de inscripcion creado.', 'aula-virtual' ) );
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
			$this->redirect_with_notice( AdminMenu::editions_url( array( 'edition' => $edition_id ) ), 'error', $result->get_error_message() );
		}

		$this->redirect_with_notice(
			AdminMenu::editions_url( array( 'edition' => (int) $result['edition_id'] ) ),
			'success',
			sprintf(
				/* translators: 1: lessons copied, 2: materials copied, 3: live classes copied. */
				__( 'Edicion duplicada en borrador: %1$d sesiones, %2$d materiales y %3$d clases en vivo copiadas. Revisa fechas y estado antes de abrirla.', 'aula-virtual' ),
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
		$url   = AdminMenu::editions_url( array( 'edition' => $edition_id ) );

		if ( false === $index || ! isset( $ids[ $index + $direction ] ) ) {
			$this->redirect_with_notice( $url, 'error', __( 'No se puede mover esa sesion.', 'aula-virtual' ) );
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
	 * Verifies nonce and capability, or stops the request.
	 *
	 * @param string $action     Action name, used as the nonce action.
	 * @param string $capability Capability required.
	 * @return void
	 */
	private function guard( string $action, string $capability ): void {
		if ( ! current_user_can( $capability ) ) {
			wp_die( esc_html__( 'No tienes permisos para realizar esta accion.', 'aula-virtual' ), '', array( 'response' => 403 ) );
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
			wp_die( esc_html__( 'No tienes permisos sobre esta edicion.', 'aula-virtual' ), '', array( 'response' => 403 ) );
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
