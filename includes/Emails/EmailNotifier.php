<?php
/**
 * Event to email bridge.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Emails;

use SIQA\AulaVirtual\Comments\CommentRepository;
use SIQA\AulaVirtual\Core\Events\EventBus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Subscribes to every domain event and sends the enabled templates for it.
 *
 * This is the only place where business events turn into email. Enrollment,
 * progress and registration services never call the mailer directly.
 */
final class EmailNotifier {

	/**
	 * Template persistence.
	 *
	 * @var EmailTemplateRepository
	 */
	private EmailTemplateRepository $templates;

	/**
	 * Variable resolution.
	 *
	 * @var VariableResolver
	 */
	private VariableResolver $variables;

	/**
	 * Outgoing mail.
	 *
	 * @var Mailer
	 */
	private Mailer $mailer;

	/**
	 * Comment persistence, to find who wrote the comment being replied to.
	 *
	 * @var CommentRepository
	 */
	private CommentRepository $comments;

	/**
	 * Constructor.
	 *
	 * @param EmailTemplateRepository $templates Template persistence.
	 * @param VariableResolver        $variables Variable resolution.
	 * @param Mailer                  $mailer    Outgoing mail.
	 * @param CommentRepository       $comments  Comment persistence.
	 */
	public function __construct( EmailTemplateRepository $templates, VariableResolver $variables, Mailer $mailer, CommentRepository $comments ) {
		$this->templates = $templates;
		$this->variables = $variables;
		$this->mailer    = $mailer;
		$this->comments  = $comments;
	}

	/**
	 * Attaches the notifier to the event bus.
	 *
	 * @param EventBus $events Event bus.
	 * @return void
	 */
	public function subscribe( EventBus $events ): void {
		foreach ( array_keys( EmailDefaults::events() ) as $event ) {
			$events->listen( $event, array( $this, 'handle' ), 20 );
		}
	}

	/**
	 * Sends the templates enabled for the event carried by the payload.
	 *
	 * @param array<string, mixed> $payload Event payload.
	 * @return void
	 */
	public function handle( array $payload ): void {
		$event = (string) ( $payload['event'] ?? '' );

		if ( '' === $event || ! empty( $payload['silent'] ) ) {
			return;
		}

		$enabled = $this->templates->enabled_for_event( $event );

		if ( array() === $enabled ) {
			return;
		}

		$vars = null;

		foreach ( $enabled as $template ) {
			$to = $this->recipient( (string) $template['recipient'], $payload );

			if ( '' === $to ) {
				continue;
			}

			if ( null === $vars ) {
				$raw          = $this->variables->resolve_raw( $payload );
				$vars         = VariableResolver::escape_all( $raw );
				$subject_vars = VariableResolver::plain_all( $raw );
			}

			$this->mailer->send(
				$to,
				TemplateRenderer::render( (string) $template['subject'], $subject_vars ),
				TemplateRenderer::render( (string) $template['body'], $vars ),
				$event
			);
		}
	}

	/**
	 * Sends one template to an arbitrary address with sample data.
	 *
	 * @param int    $template_id Template id.
	 * @param string $to          Recipient.
	 * @return bool
	 */
	public function send_test( int $template_id, string $to ): bool {
		$template = $this->templates->find( $template_id );

		if ( null === $template ) {
			return false;
		}

		$vars = array_fill_keys( array_keys( VariableResolver::catalogue() ), '' );

		$sample = array(
			'first_name'    => 'Ana',
			'last_name'     => 'Perez',
			'email'         => $to,
			'course_name'   => __( 'Curso de ejemplo', 'aula-virtual' ),
			'edition_name'  => __( 'Edicion de prueba', 'aula-virtual' ),
			'modality'      => __( 'En vivo', 'aula-virtual' ),
			'start_date'    => date_i18n( (string) get_option( 'date_format' ) ),
			'schedule_days' => __( 'Martes y jueves', 'aula-virtual' ),
			'schedule_time' => '19:00 - 21:00',
			'price'         => 'S/ 250',
			'site_name'     => (string) get_option( 'blogname' ),
			'campus_url'    => home_url( '/campus/' ),
			'login_url'     => home_url( '/campus/' ),
			'payment_url'   => home_url( '/' ),
			'set_password_url' => home_url( '/' ),
			'lesson_name'      => __( 'Sesion de ejemplo', 'aula-virtual' ),
			'lesson_url'       => home_url( '/campus/' ),
			'comment_author'   => 'Ana Perez',
			'comment_content'  => __( 'Este es un comentario de prueba.', 'aula-virtual' ),
		);

		$vars = array_merge( $vars, $sample );

		return $this->mailer->send(
			$to,
			'[' . __( 'Prueba', 'aula-virtual' ) . '] ' . TemplateRenderer::render( (string) $template['subject'], $vars ),
			TemplateRenderer::render( (string) $template['body'], $vars ),
			'test'
		);
	}

	/**
	 * Resolves the address a template goes to.
	 *
	 * @param string               $recipient Recipient type.
	 * @param array<string, mixed> $payload   Event payload.
	 * @return string Empty when nobody should receive it.
	 */
	private function recipient( string $recipient, array $payload ): string {
		if ( EmailTemplateRepository::RECIPIENT_ADMIN === $recipient ) {
			return $this->admin_email();
		}

		if ( EmailTemplateRepository::RECIPIENT_INSTRUCTOR === $recipient ) {
			// Solo avisa al docente cuando escribe un alumno: sus propias
			// respuestas no generan aviso.
			if ( ! empty( $payload['is_staff'] ) ) {
				return '';
			}

			$instructor = $this->instructor_email( (int) ( $payload['course_id'] ?? 0 ) );

			// Si el docente es ademas el autor del comentario respondido, ya
			// recibe el aviso de respuesta: no se le manda dos veces.
			if ( '' !== $instructor && $instructor === $this->parent_author_email( (int) ( $payload['parent_id'] ?? 0 ), (int) ( $payload['user_id'] ?? 0 ) ) ) {
				return '';
			}

			return $instructor;
		}

		if ( EmailTemplateRepository::RECIPIENT_COMMENT_PARENT_AUTHOR === $recipient ) {
			return $this->parent_author_email( (int) ( $payload['parent_id'] ?? 0 ), (int) ( $payload['user_id'] ?? 0 ) );
		}

		if ( isset( $payload['email'] ) && is_email( (string) $payload['email'] ) ) {
			return (string) $payload['email'];
		}

		if ( isset( $payload['user_id'] ) && (int) $payload['user_id'] > 0 ) {
			$user = get_userdata( (int) $payload['user_id'] );

			return false === $user ? '' : $user->user_email;
		}

		return '';
	}

	/**
	 * Address for administrative notices: the configured one or the site's.
	 *
	 * @return string
	 */
	private function admin_email(): string {
		$configured = get_option( 'av_admin_notification_email', '' );

		return is_string( $configured ) && is_email( $configured ) ? $configured : (string) get_option( 'admin_email' );
	}

	/**
	 * Address of the course author, falling back to the administrative one.
	 *
	 * @param int $course_id Course post id.
	 * @return string
	 */
	private function instructor_email( int $course_id ): string {
		$author_id = $course_id > 0 ? (int) get_post_field( 'post_author', $course_id ) : 0;
		$author    = $author_id > 0 ? get_userdata( $author_id ) : false;

		if ( false !== $author && is_email( $author->user_email ) ) {
			return $author->user_email;
		}

		return $this->admin_email();
	}

	/**
	 * Address of the author of the parent comment, when someone else replied.
	 *
	 * @param int $parent_id  Parent comment id (0 for a root comment).
	 * @param int $replier_id User who wrote the reply.
	 * @return string Empty when there is nobody to notify.
	 */
	private function parent_author_email( int $parent_id, int $replier_id ): string {
		if ( $parent_id <= 0 ) {
			return '';
		}

		$parent = $this->comments->find( $parent_id );

		if ( null === $parent || (int) $parent['user_id'] === $replier_id ) {
			return '';
		}

		$author = get_userdata( (int) $parent['user_id'] );

		return false === $author ? '' : $author->user_email;
	}
}
