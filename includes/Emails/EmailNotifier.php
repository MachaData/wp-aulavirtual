<?php
/**
 * Event to email bridge.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Emails;

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
	 * Constructor.
	 *
	 * @param EmailTemplateRepository $templates Template persistence.
	 * @param VariableResolver        $variables Variable resolution.
	 * @param Mailer                  $mailer    Outgoing mail.
	 */
	public function __construct( EmailTemplateRepository $templates, VariableResolver $variables, Mailer $mailer ) {
		$this->templates = $templates;
		$this->variables = $variables;
		$this->mailer    = $mailer;
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
				$vars = $this->variables->resolve( $payload );
			}

			$this->mailer->send(
				$to,
				TemplateRenderer::render( (string) $template['subject'], $vars ),
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
			$configured = get_option( 'av_admin_notification_email', '' );

			return is_string( $configured ) && is_email( $configured ) ? $configured : (string) get_option( 'admin_email' );
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
}
