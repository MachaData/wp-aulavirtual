<?php
/**
 * Lesson editor screen.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Admin;

use SIQA\AulaVirtual\Comments\CommentService;
use SIQA\AulaVirtual\Curriculum\LessonRepository;
use SIQA\AulaVirtual\Curriculum\LessonService;
use SIQA\AulaVirtual\Editions\EditionRepository;
use SIQA\AulaVirtual\LiveClasses\LiveClassRepository;
use SIQA\AulaVirtual\LiveClasses\LiveClassService;
use SIQA\AulaVirtual\Materials\MaterialRepository;
use SIQA\AulaVirtual\Materials\MaterialService;
use SIQA\AulaVirtual\Permissions\AccessControl;
use SIQA\AulaVirtual\Permissions\Capabilities;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Edits one lesson: content, video, live class and materials.
 *
 * Reached from the edition detail. Not listed in the menu.
 */
final class LessonScreen {

	public const SLUG                   = 'aula-virtual-sesion';
	public const ACTION_SAVE            = 'av_save_lesson';
	public const ACTION_DELETE          = 'av_delete_lesson';
	public const ACTION_SAVE_LIVE       = 'av_save_live_class';
	public const ACTION_REMOVE_LIVE     = 'av_remove_live_class';
	public const ACTION_ADD_MATERIAL    = 'av_add_material';
	public const ACTION_DELETE_MATERIAL = 'av_delete_material';
	public const ACTION_DELETE_COMMENT  = 'av_admin_delete_comment';

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
	 * Edition persistence.
	 *
	 * @var EditionRepository
	 */
	private EditionRepository $editions;

	/**
	 * Live class persistence.
	 *
	 * @var LiveClassRepository
	 */
	private LiveClassRepository $live_classes;

	/**
	 * Live class rules.
	 *
	 * @var LiveClassService
	 */
	private LiveClassService $live_service;

	/**
	 * Material persistence.
	 *
	 * @var MaterialRepository
	 */
	private MaterialRepository $materials;

	/**
	 * Material rules.
	 *
	 * @var MaterialService
	 */
	private MaterialService $material_service;

	/**
	 * Permission checks.
	 *
	 * @var AccessControl
	 */
	private AccessControl $access;

	/**
	 * Lesson comments.
	 *
	 * @var CommentService
	 */
	private CommentService $comments;

	/**
	 * Constructor.
	 *
	 * @param LessonRepository    $lessons          Lesson persistence.
	 * @param LessonService       $lesson_service   Lesson rules.
	 * @param EditionRepository   $editions         Edition persistence.
	 * @param LiveClassRepository $live_classes     Live class persistence.
	 * @param LiveClassService    $live_service     Live class rules.
	 * @param MaterialRepository  $materials        Material persistence.
	 * @param MaterialService     $material_service Material rules.
	 * @param AccessControl       $access           Permission checks.
	 */
	public function __construct(
		LessonRepository $lessons,
		LessonService $lesson_service,
		EditionRepository $editions,
		LiveClassRepository $live_classes,
		LiveClassService $live_service,
		MaterialRepository $materials,
		MaterialService $material_service,
		AccessControl $access,
		CommentService $comments
	) {
		$this->lessons          = $lessons;
		$this->lesson_service   = $lesson_service;
		$this->editions         = $editions;
		$this->live_classes     = $live_classes;
		$this->live_service     = $live_service;
		$this->materials        = $materials;
		$this->material_service = $material_service;
		$this->access           = $access;
	}

	/**
	 * URL of the editor for a lesson.
	 *
	 * @param int $lesson_id Lesson id.
	 * @return string
	 */
	public static function url( int $lesson_id ): string {
		return add_query_arg(
			array(
				'page'   => self::SLUG,
				'lesson' => $lesson_id,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Renders the editor.
	 *
	 * @return void
	 */
	public function render(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
		$lesson_id = isset( $_GET['lesson'] ) ? absint( wp_unslash( $_GET['lesson'] ) ) : 0;
		$lesson    = $this->lessons->find( $lesson_id );

		if ( null === $lesson ) {
			wp_die( esc_html__( 'La sesion no existe.', 'aula-virtual' ) );
		}

		if ( ! current_user_can( Capabilities::MANAGE_CURRICULUM ) || ! $this->access->can_manage_editions( (int) $lesson['course_id'] ) ) {
			wp_die( esc_html__( 'No tienes permisos sobre esta sesion.', 'aula-virtual' ), '', array( 'response' => 403 ) );
		}

		$edition = $this->editions->find( (int) $lesson['edition_id'] );
		$notice  = null;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only, produced by our own redirect.
		if ( isset( $_GET['av_notice'], $_GET['av_message'] ) ) {
			$notice = array(
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only.
				'type'    => 'success' === sanitize_key( wp_unslash( $_GET['av_notice'] ) ) ? 'success' : 'error',
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only.
				'message' => sanitize_text_field( wp_unslash( $_GET['av_message'] ) ),
			);
		}

		wp_enqueue_media();

		$data = array(
			'lesson'    => $lesson,
			'edition'   => $edition,
			'live'      => $this->live_classes->for_lesson( $lesson_id ),
			'materials' => $this->materials->for_lesson( $lesson_id ),
			'comments'  => CommentService::enabled() ? $this->comments->thread( $lesson_id ) : array(),
			'timezone'  => (string) ( $edition['timezone'] ?? wp_timezone_string() ),
			'notice'    => $notice,
		);

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- controlled template data.
		extract( $data, EXTR_SKIP );

		require AV_PATH . 'admin/views/lesson-edit.php';
	}

	/**
	 * Saves the lesson fields.
	 *
	 * @return void
	 */
	public function handle_save(): void {
		$lesson_id = $this->guard( self::ACTION_SAVE );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		$this->finish( $lesson_id, $this->lesson_service->update( $lesson_id, wp_unslash( $_POST ) ), __( 'Sesion guardada.', 'aula-virtual' ) );
	}

	/**
	 * Deletes the lesson and returns to the edition.
	 *
	 * @return void
	 */
	public function handle_delete(): void {
		$lesson_id = $this->guard( self::ACTION_DELETE );
		$lesson    = $this->lessons->find( $lesson_id );

		$this->lesson_service->delete( $lesson_id );
		$this->live_service->remove( $lesson_id );

		wp_safe_redirect(
			add_query_arg(
				array(
					'av_notice'  => 'success',
					'av_message' => rawurlencode( __( 'Sesion eliminada.', 'aula-virtual' ) ),
				),
				AdminMenu::editions_url( array( 'edition' => (int) ( $lesson['edition_id'] ?? 0 ) ) )
			)
		);
		exit;
	}

	/**
	 * Saves the live class.
	 *
	 * @return void
	 */
	public function handle_save_live(): void {
		$lesson_id = $this->guard( self::ACTION_SAVE_LIVE, Capabilities::MANAGE_LIVE_CLASS );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		$result = $this->live_service->save( $lesson_id, wp_unslash( $_POST ) );

		$this->finish( $lesson_id, $result instanceof WP_Error ? $result : true, __( 'Clase en vivo guardada.', 'aula-virtual' ) );
	}

	/**
	 * Removes the live class.
	 *
	 * @return void
	 */
	public function handle_remove_live(): void {
		$lesson_id = $this->guard( self::ACTION_REMOVE_LIVE, Capabilities::MANAGE_LIVE_CLASS );

		$this->live_service->remove( $lesson_id );
		$this->finish( $lesson_id, true, __( 'Clase en vivo eliminada.', 'aula-virtual' ) );
	}

	/**
	 * Adds a material.
	 *
	 * @return void
	 */
	public function handle_add_material(): void {
		$lesson_id = $this->guard( self::ACTION_ADD_MATERIAL, Capabilities::MANAGE_MATERIALS );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		$input              = wp_unslash( $_POST );
		$input['lesson_id'] = $lesson_id;

		$result = $this->material_service->add( $input );

		$this->finish( $lesson_id, $result instanceof WP_Error ? $result : true, __( 'Material anadido.', 'aula-virtual' ) );
	}

	/**
	 * Deletes a material.
	 *
	 * @return void
	 */
	public function handle_delete_material(): void {
		$lesson_id = $this->guard( self::ACTION_DELETE_MATERIAL, Capabilities::MANAGE_MATERIALS );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		$material_id = isset( $_POST['material_id'] ) ? absint( wp_unslash( $_POST['material_id'] ) ) : 0;
		$material    = $this->materials->find( $material_id );

		if ( null === $material || (int) $material['lesson_id'] !== $lesson_id ) {
			$this->finish( $lesson_id, new WP_Error( 'av_material_not_found', __( 'El material no pertenece a esta sesion.', 'aula-virtual' ) ), '' );
		}

		$this->material_service->delete( $material_id );
		$this->finish( $lesson_id, true, __( 'Material eliminado.', 'aula-virtual' ) );
	}

	/**
	 * Hides a student comment from the editor.
	 *
	 * @return void
	 */
	public function handle_delete_comment(): void {
		$lesson_id = $this->guard( self::ACTION_DELETE_COMMENT );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		$comment_id = isset( $_POST['comment_id'] ) ? absint( wp_unslash( $_POST['comment_id'] ) ) : 0;

		$this->finish( $lesson_id, $this->comments->remove( $comment_id, get_current_user_id() ), __( 'Comentario eliminado.', 'aula-virtual' ) );
	}

	/**
	 * Verifies nonce, capability and ownership; returns the lesson id.
	 *
	 * @param string $action     Nonce action.
	 * @param string $capability Capability required.
	 * @return int
	 */
	private function guard( string $action, string $capability = Capabilities::MANAGE_CURRICULUM ): int {
		if ( ! current_user_can( $capability ) ) {
			wp_die( esc_html__( 'No tienes permisos para realizar esta accion.', 'aula-virtual' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( $action );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$lesson_id = isset( $_POST['lesson_id'] ) ? absint( wp_unslash( $_POST['lesson_id'] ) ) : 0;
		$lesson    = $this->lessons->find( $lesson_id );

		if ( null === $lesson || ! $this->access->can_manage_editions( (int) $lesson['course_id'] ) ) {
			wp_die( esc_html__( 'No tienes permisos sobre esta sesion.', 'aula-virtual' ), '', array( 'response' => 403 ) );
		}

		return $lesson_id;
	}

	/**
	 * Redirects back to the editor with the outcome.
	 *
	 * @param int           $lesson_id Lesson id.
	 * @param true|WP_Error $result    Service result.
	 * @param string        $success   Message on success.
	 * @return void
	 */
	private function finish( int $lesson_id, $result, string $success ): void {
		$args = $result instanceof WP_Error
			? array( 'av_notice' => 'error', 'av_message' => rawurlencode( $result->get_error_message() ) )
			: array( 'av_notice' => 'success', 'av_message' => rawurlencode( $success ) );

		wp_safe_redirect( add_query_arg( $args, self::url( $lesson_id ) ) );
		exit;
	}
}
