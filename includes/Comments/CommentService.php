<?php
/**
 * Lesson comments rules.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Comments;

use SIQA\AulaVirtual\Core\Events\EventBus;
use SIQA\AulaVirtual\Core\Events\Events;
use SIQA\AulaVirtual\Curriculum\LessonRepository;
use SIQA\AulaVirtual\Curriculum\LessonType;
use SIQA\AulaVirtual\Enrollments\EnrollmentService;
use SIQA\AulaVirtual\Permissions\AccessControl;
use SIQA\AulaVirtual\Security\Sanitizer;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Students comment on a lesson they have access to; instructors of the
 * course reply and moderate. One level of replies.
 */
final class CommentService {

	public const OPTION_ENABLED = 'av_lesson_comments';
	public const MAX_LENGTH     = 2000;
	public const COOLDOWN       = 15;

	/**
	 * Comment persistence.
	 *
	 * @var CommentRepository
	 */
	private CommentRepository $comments;

	/**
	 * Lesson persistence.
	 *
	 * @var LessonRepository
	 */
	private LessonRepository $lessons;

	/**
	 * Access rules.
	 *
	 * @var EnrollmentService
	 */
	private EnrollmentService $enrollments;

	/**
	 * Permission checks.
	 *
	 * @var AccessControl
	 */
	private AccessControl $access;

	/**
	 * Event bus.
	 *
	 * @var EventBus
	 */
	private EventBus $events;

	/**
	 * Constructor.
	 *
	 * @param CommentRepository $comments    Comment persistence.
	 * @param LessonRepository  $lessons     Lesson persistence.
	 * @param EnrollmentService $enrollments Access rules.
	 * @param AccessControl     $access      Permission checks.
	 * @param EventBus          $events      Event bus.
	 */
	public function __construct( CommentRepository $comments, LessonRepository $lessons, EnrollmentService $enrollments, AccessControl $access, EventBus $events ) {
		$this->comments    = $comments;
		$this->lessons     = $lessons;
		$this->enrollments = $enrollments;
		$this->access      = $access;
		$this->events      = $events;
	}

	/**
	 * Whether comments are enabled site-wide.
	 *
	 * @return bool
	 */
	public static function enabled(): bool {
		return (bool) get_option( self::OPTION_ENABLED, true );
	}

	/**
	 * Cleans the text of a comment: plain text, trimmed, bounded.
	 *
	 * @param mixed $content Raw text.
	 * @return string
	 */
	public static function sanitize_content( mixed $content ): string {
		$text = Sanitizer::textarea( $content );
		$text = preg_replace( "/\n{3,}/", "\n\n", $text ) ?? $text;

		return mb_substr( trim( $text ), 0, self::MAX_LENGTH );
	}

	/**
	 * Posts a comment or a reply.
	 *
	 * @param int    $user_id   Author.
	 * @param int    $lesson_id Lesson.
	 * @param mixed  $content   Raw text.
	 * @param int    $parent_id Parent comment for replies.
	 * @return int|WP_Error Comment id.
	 */
	public function post( int $user_id, int $lesson_id, mixed $content, int $parent_id = 0 ) {
		if ( ! self::enabled() ) {
			return new WP_Error( 'av_comments_disabled', __( 'Los comentarios están desactivados.', 'aula-virtual' ), array( 'status' => 403 ) );
		}

		$lesson = $this->lessons->find( $lesson_id );

		if ( null === $lesson || LessonType::STATUS_PUBLISH !== $lesson['status'] ) {
			return new WP_Error( 'av_lesson_not_found', __( 'La sesión no existe o no está publicada.', 'aula-virtual' ), array( 'status' => 404 ) );
		}

		$is_staff = $this->access->can_manage_editions( (int) $lesson['course_id'] );

		if ( ! $is_staff && ! $this->enrollments->has_access( $user_id, (int) $lesson['edition_id'] ) ) {
			return new WP_Error( 'av_no_access', __( 'No tienes acceso a esta sesión.', 'aula-virtual' ), array( 'status' => 403 ) );
		}

		$text = self::sanitize_content( $content );

		if ( '' === $text ) {
			return new WP_Error( 'av_comment_empty', __( 'Escribe algo antes de enviar.', 'aula-virtual' ), array( 'status' => 400 ) );
		}

		if ( $parent_id > 0 ) {
			$parent = $this->comments->find( $parent_id );

			if ( null === $parent || (int) $parent['lesson_id'] !== $lesson_id ) {
				return new WP_Error( 'av_parent_not_found', __( 'El comentario al que respondes ya no existe.', 'aula-virtual' ), array( 'status' => 404 ) );
			}

			// Un solo nivel: responder a una respuesta cuelga del comentario raiz.
			$parent_id = (int) $parent['parent_id'] > 0 ? (int) $parent['parent_id'] : $parent_id;
		}

		$cooldown_key = 'av_comment_cooldown_' . $user_id;

		if ( ! $is_staff && false !== get_transient( $cooldown_key ) ) {
			return new WP_Error( 'av_comment_too_fast', __( 'Espera unos segundos antes de comentar de nuevo.', 'aula-virtual' ), array( 'status' => 429 ) );
		}

		$comment_id = $this->comments->insert(
			array(
				'course_id'  => (int) $lesson['course_id'],
				'edition_id' => (int) $lesson['edition_id'],
				'lesson_id'  => $lesson_id,
				'user_id'    => $user_id,
				'parent_id'  => $parent_id,
				'is_staff'   => $is_staff ? 1 : 0,
				'content'    => $text,
				'status'     => CommentRepository::STATUS_APPROVED,
				'created_at' => current_time( 'mysql', true ),
			)
		);

		if ( 0 === $comment_id ) {
			return new WP_Error( 'av_comment_not_saved', __( 'No se pudo guardar el comentario.', 'aula-virtual' ) );
		}

		set_transient( $cooldown_key, 1, self::COOLDOWN );

		$this->events->dispatch(
			Events::COMMENT_POSTED,
			array(
				'comment_id' => $comment_id,
				'lesson_id'  => $lesson_id,
				'edition_id' => (int) $lesson['edition_id'],
				'course_id'  => (int) $lesson['course_id'],
				'user_id'    => $user_id,
				'parent_id'  => $parent_id,
				'is_staff'   => $is_staff,
			)
		);

		return $comment_id;
	}

	/**
	 * Hides a comment. Authors can remove their own; course staff any.
	 *
	 * @param int $comment_id Comment id.
	 * @param int $user_id    Who asks.
	 * @return true|WP_Error
	 */
	public function remove( int $comment_id, int $user_id ) {
		$comment = $this->comments->find( $comment_id );

		if ( null === $comment ) {
			return new WP_Error( 'av_comment_not_found', __( 'El comentario no existe.', 'aula-virtual' ), array( 'status' => 404 ) );
		}

		$own   = (int) $comment['user_id'] === $user_id;
		$staff = $this->access->can_manage_editions( (int) $comment['course_id'] );

		if ( ! $own && ! $staff ) {
			return new WP_Error( 'av_comment_forbidden', __( 'No puedes eliminar este comentario.', 'aula-virtual' ), array( 'status' => 403 ) );
		}

		$this->comments->update( $comment_id, array( 'status' => CommentRepository::STATUS_HIDDEN ) );

		return true;
	}

	/**
	 * Returns the comments of a lesson as a tree: roots with their replies.
	 *
	 * @param int $lesson_id Lesson id.
	 * @return array<int, array<string, mixed>> Roots, each with `replies` and `author`.
	 */
	public function thread( int $lesson_id ): array {
		$roots   = array();
		$replies = array();

		foreach ( $this->comments->for_lesson( $lesson_id ) as $row ) {
			$row['author']  = self::author_name( (int) $row['user_id'] );
			$row['replies'] = array();

			if ( (int) $row['parent_id'] > 0 ) {
				$replies[ (int) $row['parent_id'] ][] = $row;
			} else {
				$roots[ (int) $row['id'] ] = $row;
			}
		}

		foreach ( $replies as $parent_id => $list ) {
			if ( isset( $roots[ $parent_id ] ) ) {
				$roots[ $parent_id ]['replies'] = $list;
			}
		}

		return array_values( $roots );
	}

	/**
	 * Display name of a comment author.
	 *
	 * @param int $user_id User id.
	 * @return string
	 */
	public static function author_name( int $user_id ): string {
		$user = get_userdata( $user_id );

		return false === $user ? __( 'Usuario eliminado', 'aula-virtual' ) : (string) $user->display_name;
	}
}
