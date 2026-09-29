<?php
/**
 * Students admin screen: search, student profile and account actions.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Admin;

use SIQA\AulaVirtual\Core\AuditLog;
use SIQA\AulaVirtual\Editions\EditionRepository;
use SIQA\AulaVirtual\Enrollments\EnrollmentRepository;
use SIQA\AulaVirtual\Enrollments\EnrollmentService;
use SIQA\AulaVirtual\Enrollments\EnrollmentStatus;
use SIQA\AulaVirtual\Permissions\AccessControl;
use SIQA\AulaVirtual\Permissions\Capabilities;
use SIQA\AulaVirtual\Students\AccountHelper;
use SIQA\AulaVirtual\Students\StudentRepository;
use SIQA\AulaVirtual\Students\StudentService;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every write goes through admin-post with nonce, capability and course
 * ownership. Instructors only see and act on enrollments of their own
 * courses; account data and passwords need the WordPress `edit_user`
 * capability (administrators).
 */
final class StudentsScreen {

	public const SLUG               = 'aula-virtual-alumnos';
	public const TABS               = array( 'matriculas', 'acceso', 'datos', 'historial' );
	public const ACTION_ACCESS_LINK = 'av_student_access_link';
	public const ACTION_PASSWORD    = 'av_student_password';
	public const ACTION_LOGOUT      = 'av_student_logout';
	public const ACTION_PROFILE     = 'av_student_profile';
	public const ACTION_ENROLL      = 'av_student_enroll';
	public const ACTION_ENROLLMENT  = 'av_student_enrollment';
	public const ACTION_BULK        = 'av_students_bulk';

	/**
	 * Student search.
	 *
	 * @var StudentRepository
	 */
	private StudentRepository $students;

	/**
	 * Student operations.
	 *
	 * @var StudentService
	 */
	private StudentService $service;

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
	 * Edition persistence.
	 *
	 * @var EditionRepository
	 */
	private EditionRepository $editions;

	/**
	 * Permission checks.
	 *
	 * @var AccessControl
	 */
	private AccessControl $access;

	/**
	 * Audit trail.
	 *
	 * @var AuditLog
	 */
	private AuditLog $audit;

	/**
	 * Constructor.
	 *
	 * @param StudentRepository    $students           Student search.
	 * @param StudentService       $service            Student operations.
	 * @param EnrollmentRepository $enrollments        Enrollment persistence.
	 * @param EnrollmentService    $enrollment_service Enrollment rules.
	 * @param EditionRepository    $editions           Edition persistence.
	 * @param AccessControl        $access             Permission checks.
	 * @param AuditLog             $audit              Audit trail.
	 */
	public function __construct(
		StudentRepository $students,
		StudentService $service,
		EnrollmentRepository $enrollments,
		EnrollmentService $enrollment_service,
		EditionRepository $editions,
		AccessControl $access,
		AuditLog $audit
	) {
		$this->students           = $students;
		$this->service            = $service;
		$this->enrollments        = $enrollments;
		$this->enrollment_service = $enrollment_service;
		$this->editions           = $editions;
		$this->access             = $access;
		$this->audit              = $audit;
	}

	/**
	 * URL of the list, or of one student (and tab).
	 *
	 * @param int    $user_id Student, 0 for the list.
	 * @param string $tab     Tab of the profile.
	 * @return string
	 */
	public static function url( int $user_id = 0, string $tab = '' ): string {
		$args = array( 'page' => self::SLUG );

		if ( $user_id > 0 ) {
			$args['student'] = $user_id;
		}

		if ( '' !== $tab ) {
			$args['tab'] = $tab;
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Renders the list or a profile.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( Capabilities::VIEW_STUDENTS ) ) {
			wp_die( esc_html__( 'No tienes permisos para ver esta página.', 'aula-virtual' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
		$user_id = isset( $_GET['student'] ) ? absint( wp_unslash( $_GET['student'] ) ) : 0;

		if ( $user_id > 0 ) {
			$this->render_profile( $user_id );

			return;
		}

		$this->render_list();
	}

	/**
	 * Renders the student list.
	 *
	 * @return void
	 */
	private function render_list(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
		$filters = array(
			'search'     => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			'edition_id' => isset( $_GET['edition'] ) ? absint( wp_unslash( $_GET['edition'] ) ) : 0,
			'status'     => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
			'course_ids' => $this->access->can_manage() ? null : $this->access->own_course_ids(),
		);
		$page    = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$result = $this->students->search( $filters, $page, 30 );

		$this->view(
			'students-list',
			array(
				'rows'     => $result['items'],
				'total'    => $result['total'],
				'page'     => $page,
				'filters'  => $filters,
				'editions' => $this->manageable_editions(),
				'notice'   => $this->notice(),
			)
		);
	}

	/**
	 * Renders a student profile.
	 *
	 * @param int $user_id Student.
	 * @return void
	 */
	private function render_profile( int $user_id ): void {
		$user = get_userdata( $user_id );

		if ( false === $user ) {
			wp_die( esc_html__( 'La persona no existe.', 'aula-virtual' ) );
		}

		$enrollments = $this->visible_enrollments( $user_id );

		if ( array() === $enrollments && ! $this->access->can_manage() ) {
			wp_die( esc_html__( 'No tienes permisos sobre esta persona.', 'aula-virtual' ), '', array( 'response' => 403 ) );
		}

		$editions = array();

		foreach ( $enrollments as $row ) {
			$editions[ (int) $row['edition_id'] ] = $this->editions->find( (int) $row['edition_id'] );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		$tab = in_array( $tab, self::TABS, true ) ? $tab : 'matriculas';

		$enrolled_ids = array_map( static fn( array $e ): int => (int) $e['edition_id'], $this->enrollments->all( array( 'where' => array( 'user_id' => $user_id ), 'limit' => 500 ) ) );

		$this->view(
			'student-detail',
			array(
				'user'            => $user,
				'enrollments'     => $enrollments,
				'editions'        => $editions,
				'targets'         => array_values( array_filter( $this->manageable_editions(), static fn( array $e ): bool => ! in_array( (int) $e['id'], $enrolled_ids, true ) ) ),
				'all_editions'    => $this->manageable_editions(),
				'tab'             => $tab,
				'can_edit'        => current_user_can( 'edit_user', $user_id ) && get_current_user_id() !== $user_id,
				'needs_password'  => AccountHelper::needs_password( $user_id ),
				'last_login'      => AccountHelper::last_login( $user_id ),
				'link_expiry'     => AccountHelper::lifetime_label( AccountHelper::link_hours() ),
				'history'         => 'historial' === $tab ? $this->audit->for_user( $user_id, 100 ) : array(),
				'notice'          => $this->notice(),
			)
		);
	}

	/**
	 * Emails a new access link.
	 *
	 * @return void
	 */
	public function handle_access_link(): void {
		$user_id = $this->guard_student( self::ACTION_ACCESS_LINK, Capabilities::ENROLL_STUDENTS );
		$result  = $this->service->send_access_link( $user_id );

		$this->finish(
			$this->return_url( self::url( $user_id, 'acceso' ) ),
			$result,
			__( 'Enlace de acceso enviado por correo.', 'aula-virtual' )
		);
	}

	/**
	 * Sets a password typed by the administrator.
	 *
	 * @return void
	 */
	public function handle_password(): void {
		$user_id = $this->guard_account( self::ACTION_PASSWORD );

		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- verified in guard; passwords are not sanitised on purpose.
		$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
		$confirm  = isset( $_POST['password_confirm'] ) ? (string) wp_unslash( $_POST['password_confirm'] ) : '';
		// phpcs:enable

		$this->finish(
			self::url( $user_id, 'acceso' ),
			$this->service->set_password( $user_id, $password, $confirm ),
			__( 'Contraseña actualizada. Se cerraron sus sesiones abiertas. Compártela por un canal seguro; no se envió por correo.', 'aula-virtual' )
		);
	}

	/**
	 * Closes every open session of the student.
	 *
	 * @return void
	 */
	public function handle_logout(): void {
		$user_id = $this->guard_account( self::ACTION_LOGOUT );

		if ( class_exists( '\WP_Session_Tokens' ) ) {
			\WP_Session_Tokens::get_instance( $user_id )->destroy_all();
		}

		$this->finish( self::url( $user_id, 'acceso' ), true, __( 'Se cerraron todas sus sesiones abiertas.', 'aula-virtual' ) );
	}

	/**
	 * Updates personal data.
	 *
	 * @return void
	 */
	public function handle_profile(): void {
		$user_id = $this->guard_account( self::ACTION_PROFILE );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard.
		$input = wp_unslash( $_POST );

		$this->finish(
			self::url( $user_id, 'datos' ),
			$this->service->update_profile(
				$user_id,
				array(
					'first_name' => $input['first_name'] ?? '',
					'last_name'  => $input['last_name'] ?? '',
					'email'      => $input['email'] ?? '',
					'phone'      => $input['phone'] ?? '',
					'document'   => $input['document'] ?? '',
				)
			),
			__( 'Datos guardados.', 'aula-virtual' )
		);
	}

	/**
	 * Enrolls the student in another edition.
	 *
	 * @return void
	 */
	public function handle_enroll(): void {
		$user_id = $this->guard_student( self::ACTION_ENROLL, Capabilities::ENROLL_STUDENTS );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in guard.
		$edition_id = isset( $_POST['edition_id'] ) ? absint( wp_unslash( $_POST['edition_id'] ) ) : 0;
		$notify     = ! empty( $_POST['notify'] );
		// phpcs:enable

		$this->assert_edition( $edition_id );

		$this->finish(
			self::url( $user_id ),
			$this->service->enroll( $user_id, $edition_id, $notify ),
			$notify ? __( 'Matriculado. Se envió el correo de bienvenida.', 'aula-virtual' ) : __( 'Matriculado sin enviar correo.', 'aula-virtual' )
		);
	}

	/**
	 * One action on one enrollment: status, access date, reset, move.
	 *
	 * @return void
	 */
	public function handle_enrollment(): void {
		if ( ! current_user_can( Capabilities::ENROLL_STUDENTS ) ) {
			wp_die( esc_html__( 'No tienes permisos para realizar esta acción.', 'aula-virtual' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( self::ACTION_ENROLLMENT );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified above.
		$enrollment_id = isset( $_POST['enrollment_id'] ) ? absint( wp_unslash( $_POST['enrollment_id'] ) ) : 0;
		$operation     = isset( $_POST['operation'] ) ? sanitize_key( wp_unslash( $_POST['operation'] ) ) : '';
		$input         = wp_unslash( $_POST );
		// phpcs:enable

		$enrollment = $this->enrollments->find( $enrollment_id );

		if ( null === $enrollment || ! $this->access->can_manage_editions( (int) $enrollment['course_id'] ) ) {
			wp_die( esc_html__( 'No tienes permisos sobre esta matrícula.', 'aula-virtual' ), '', array( 'response' => 403 ) );
		}

		$back = $this->return_url( self::url( (int) $enrollment['user_id'] ) );

		switch ( $operation ) {
			case 'status':
				$result  = $this->enrollment_service->change_status( $enrollment_id, sanitize_key( (string) ( $input['status'] ?? '' ) ) );
				$message = __( 'Estado de la matrícula actualizado.', 'aula-virtual' );
				break;
			case 'access':
				$result  = $this->service->set_access_until( $enrollment_id, empty( $input['clear'] ) ? sanitize_text_field( (string) ( $input['access_until'] ?? '' ) ) : '' );
				$message = empty( $input['clear'] ) ? __( 'Fecha de acceso actualizada.', 'aula-virtual' ) : __( 'El acceso vuelve a seguir las fechas de la edición.', 'aula-virtual' );
				break;
			case 'reset':
				$result  = $this->service->reset_progress( $enrollment_id );
				$message = __( 'Avance reiniciado: empieza de nuevo desde la primera sesión.', 'aula-virtual' );
				break;
			case 'move':
				$target = absint( $input['edition_id'] ?? 0 );
				$this->assert_edition( $target );
				$result  = $this->service->move( $enrollment_id, $target );
				$message = __( 'Matrícula movida a la otra edición. El avance empieza de cero.', 'aula-virtual' );
				break;
			default:
				$result  = new WP_Error( 'av_unknown_operation', __( 'Acción no reconocida.', 'aula-virtual' ) );
				$message = '';
		}

		$this->finish( $back, $result, $message );
	}

	/**
	 * Bulk actions from the Students tab of an edition.
	 *
	 * @return void
	 */
	public function handle_bulk(): void {
		if ( ! current_user_can( Capabilities::ENROLL_STUDENTS ) ) {
			wp_die( esc_html__( 'No tienes permisos para realizar esta acción.', 'aula-virtual' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( self::ACTION_BULK );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified above.
		$edition_id = isset( $_POST['edition_id'] ) ? absint( wp_unslash( $_POST['edition_id'] ) ) : 0;
		$action     = isset( $_POST['bulk_action'] ) ? sanitize_key( wp_unslash( $_POST['bulk_action'] ) ) : '';
		$target     = isset( $_POST['target_edition'] ) ? absint( wp_unslash( $_POST['target_edition'] ) ) : 0;
		$notify     = ! empty( $_POST['notify'] );
		$ids        = isset( $_POST['enrollment_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['enrollment_ids'] ) ) : array();
		// phpcs:enable

		$edition = $this->editions->find( $edition_id );

		if ( null === $edition || ! $this->access->can_manage_editions( (int) $edition['course_id'] ) ) {
			wp_die( esc_html__( 'No tienes permisos sobre esta edición.', 'aula-virtual' ), '', array( 'response' => 403 ) );
		}

		$back = AdminMenu::editions_url( array( 'edition' => $edition_id, 'tab' => 'alumnos' ) );

		if ( array() === $ids || '' === $action ) {
			$this->finish( $back, new WP_Error( 'av_bulk_empty', __( 'Marca al menos un alumno y elige una acción.', 'aula-virtual' ) ), '' );
		}

		if ( 'enroll' === $action ) {
			$this->assert_edition( $target );
		}

		$ok     = 0;
		$failed = 0;

		foreach ( array_slice( array_unique( $ids ), 0, 500 ) as $enrollment_id ) {
			$enrollment = $this->enrollments->find( $enrollment_id );

			// Solo matriculas de esta edicion: el formulario no puede colar otras.
			if ( null === $enrollment || (int) $enrollment['edition_id'] !== $edition_id ) {
				++$failed;
				continue;
			}

			$user_id = (int) $enrollment['user_id'];

			if ( 'access_link' === $action ) {
				$result = $this->service->send_access_link( $user_id );
			} elseif ( 'enroll' === $action ) {
				$result = $this->service->enroll( $user_id, $target, $notify );
			} elseif ( str_starts_with( $action, 'status_' ) ) {
				$result = $this->enrollment_service->change_status( $enrollment_id, substr( $action, 7 ) );
			} else {
				$result = new WP_Error( 'av_unknown_operation', '' );
			}

			$result instanceof WP_Error ? ++$failed : ++$ok;
		}

		$message = sprintf(
			/* translators: 1: done, 2: skipped. */
			__( 'Acción aplicada a %1$d alumnos. Omitidos: %2$d (por ejemplo, un cambio de estado no permitido o una matrícula que ya existía).', 'aula-virtual' ),
			$ok,
			$failed
		);

		$this->finish( $back, 0 === $ok && $failed > 0 ? new WP_Error( 'av_bulk_failed', $message ) : true, $message );
	}

	/**
	 * Enrollments of a student the current user may see.
	 *
	 * @param int $user_id Student.
	 * @return array<int, array<string, mixed>>
	 */
	private function visible_enrollments( int $user_id ): array {
		$rows = $this->enrollments->all(
			array(
				'where'    => array( 'user_id' => $user_id ),
				'order_by' => 'enrolled_at',
				'order'    => 'DESC',
				'limit'    => 500,
			)
		);

		return array_values( array_filter( $rows, fn( array $e ): bool => $this->access->can_manage_editions( (int) $e['course_id'] ) ) );
	}

	/**
	 * Editions the current user may manage, newest first.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function manageable_editions(): array {
		$all = $this->editions->all(
			array(
				'order_by' => 'start_date',
				'order'    => 'DESC',
				'limit'    => 300,
			)
		);

		return array_values( array_filter( $all, fn( array $e ): bool => $this->access->can_manage_editions( (int) $e['course_id'] ) ) );
	}

	/**
	 * Nonce + capability + the student must be visible to the user.
	 *
	 * @param string $action     Nonce action.
	 * @param string $capability Capability.
	 * @return int Student id.
	 */
	private function guard_student( string $action, string $capability ): int {
		if ( ! current_user_can( $capability ) ) {
			wp_die( esc_html__( 'No tienes permisos para realizar esta acción.', 'aula-virtual' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( $action );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$user_id = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;

		if ( false === get_userdata( $user_id ) || ( ! $this->access->can_manage() && array() === $this->visible_enrollments( $user_id ) ) ) {
			wp_die( esc_html__( 'No tienes permisos sobre esta persona.', 'aula-virtual' ), '', array( 'response' => 403 ) );
		}

		return $user_id;
	}

	/**
	 * Nonce + WordPress edit_user on the target (never on oneself here).
	 *
	 * @param string $action Nonce action.
	 * @return int Student id.
	 */
	private function guard_account( string $action ): int {
		check_admin_referer( $action );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$user_id = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;

		if ( $user_id <= 0 || get_current_user_id() === $user_id || ! current_user_can( 'edit_user', $user_id ) ) {
			wp_die( esc_html__( 'No tienes permisos para cambiar esta cuenta.', 'aula-virtual' ), '', array( 'response' => 403 ) );
		}

		return $user_id;
	}

	/**
	 * Stops unless the user manages the edition's course.
	 *
	 * @param int $edition_id Edition.
	 * @return void
	 */
	private function assert_edition( int $edition_id ): void {
		$edition = $this->editions->find( $edition_id );

		if ( null === $edition || ! $this->access->can_manage_editions( (int) $edition['course_id'] ) ) {
			wp_die( esc_html__( 'No tienes permisos sobre esa edición.', 'aula-virtual' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Uses the posted return URL when it is one of our admin pages.
	 *
	 * @param string $fallback Default URL.
	 * @return string
	 */
	private function return_url( string $fallback ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- the caller verified the nonce.
		$return = isset( $_POST['return'] ) ? esc_url_raw( wp_unslash( (string) $_POST['return'] ) ) : '';

		return '' !== $return && str_starts_with( $return, admin_url( 'admin.php?page=aula-virtual' ) ) ? $return : $fallback;
	}

	/**
	 * Redirects with the outcome.
	 *
	 * @param string          $url     Target.
	 * @param mixed           $result  true, an id, or WP_Error.
	 * @param string          $success Message on success.
	 * @return void
	 */
	private function finish( string $url, $result, string $success ): void {
		$error = $result instanceof WP_Error;

		wp_safe_redirect(
			add_query_arg(
				array(
					'av_notice'  => $error ? 'error' : 'success',
					'av_message' => rawurlencode( $error ? $result->get_error_message() : $success ),
				),
				$url
			)
		);
		exit;
	}

	/**
	 * Notice carried by our own redirect.
	 *
	 * @return array{type: string, message: string}|null
	 */
	private function notice(): ?array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
		if ( ! isset( $_GET['av_notice'], $_GET['av_message'] ) ) {
			return null;
		}

		return array(
			'type'    => 'success' === sanitize_key( wp_unslash( $_GET['av_notice'] ) ) ? 'success' : 'error',
			'message' => sanitize_text_field( wp_unslash( $_GET['av_message'] ) ),
		);
		// phpcs:enable
	}

	/**
	 * Loads a view.
	 *
	 * @param string               $view View name.
	 * @param array<string, mixed> $data Variables.
	 * @return void
	 */
	private function view( string $view, array $data ): void {
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- controlled data.
		extract( $data, EXTR_SKIP );

		require AV_PATH . 'admin/views/' . $view . '.php';
	}
}
