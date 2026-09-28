<?php
/**
 * Email templates admin screen.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Admin;

use SIQA\AulaVirtual\Emails\EmailDefaults;
use SIQA\AulaVirtual\Emails\EmailNotifier;
use SIQA\AulaVirtual\Emails\EmailTemplateRepository;
use SIQA\AulaVirtual\Emails\VariableResolver;
use SIQA\AulaVirtual\Permissions\Capabilities;
use SIQA\AulaVirtual\Security\Sanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lets the administrator edit every email the plugin sends.
 */
final class EmailsScreen {

	public const SLUG        = 'aula-virtual-emails';
	public const ACTION_SAVE = 'av_save_email_template';
	public const ACTION_TEST = 'av_send_test_email';

	/**
	 * Template persistence.
	 *
	 * @var EmailTemplateRepository
	 */
	private EmailTemplateRepository $templates;

	/**
	 * Notifier, used for test sends.
	 *
	 * @var EmailNotifier
	 */
	private EmailNotifier $notifier;

	/**
	 * Constructor.
	 *
	 * @param EmailTemplateRepository $templates Template persistence.
	 * @param EmailNotifier           $notifier  Notifier.
	 */
	public function __construct( EmailTemplateRepository $templates, EmailNotifier $notifier ) {
		$this->templates = $templates;
		$this->notifier  = $notifier;
	}

	/**
	 * Renders the list or the editor.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( Capabilities::MANAGE_LMS ) ) {
			wp_die( esc_html__( 'No tienes permisos para ver esta pagina.', 'aula-virtual' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
		$template_id = isset( $_GET['template'] ) ? absint( wp_unslash( $_GET['template'] ) ) : 0;

		$notice = null;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only message produced by our own redirect.
		if ( isset( $_GET['av_notice'], $_GET['av_message'] ) ) {
			$notice = array(
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only.
				'type'    => 'success' === sanitize_key( wp_unslash( $_GET['av_notice'] ) ) ? 'success' : 'error',
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only.
				'message' => sanitize_text_field( wp_unslash( $_GET['av_message'] ) ),
			);
		}

		if ( $template_id > 0 ) {
			$template = $this->templates->find( $template_id );

			if ( null === $template ) {
				wp_die( esc_html__( 'La plantilla no existe.', 'aula-virtual' ) );
			}

			$data = array(
				'template'  => $template,
				'events'    => EmailDefaults::events(),
				'variables' => VariableResolver::catalogue(),
				'notice'    => $notice,
			);

			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- controlled template data.
			extract( $data, EXTR_SKIP );

			require AV_PATH . 'admin/views/email-form.php';

			return;
		}

		$data = array(
			'templates' => $this->templates->all_ordered(),
			'events'    => EmailDefaults::events(),
			'notice'    => $notice,
		);

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- controlled template data.
		extract( $data, EXTR_SKIP );

		require AV_PATH . 'admin/views/emails-list.php';
	}

	/**
	 * Saves a template.
	 *
	 * @return void
	 */
	public function handle_save(): void {
		$this->guard( self::ACTION_SAVE );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		$input       = wp_unslash( $_POST );
		$template_id = isset( $input['template_id'] ) ? absint( $input['template_id'] ) : 0;

		if ( null === $this->templates->find( $template_id ) ) {
			wp_die( esc_html__( 'La plantilla no existe.', 'aula-virtual' ) );
		}

		$this->templates->update(
			$template_id,
			array(
				'subject'    => Sanitizer::text( $input['subject'] ?? '' ),
				'body'       => Sanitizer::html( $input['body'] ?? '' ),
				'enabled'    => Sanitizer::bool( $input['enabled'] ?? false ) ? 1 : 0,
				'updated_at' => current_time( 'mysql', true ),
				'updated_by' => get_current_user_id(),
			)
		);

		$this->redirect( $template_id, 'success', __( 'Plantilla guardada.', 'aula-virtual' ) );
	}

	/**
	 * Sends a test email to the current user.
	 *
	 * @return void
	 */
	public function handle_test(): void {
		$this->guard( self::ACTION_TEST );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		$template_id = isset( $_POST['template_id'] ) ? absint( wp_unslash( $_POST['template_id'] ) ) : 0;
		$to          = wp_get_current_user()->user_email;

		$sent = $this->notifier->send_test( $template_id, $to );

		$this->redirect(
			$template_id,
			$sent ? 'success' : 'error',
			$sent
				/* translators: %s: email address. */
				? sprintf( __( 'Correo de prueba enviado a %s.', 'aula-virtual' ), $to )
				: __( 'No se pudo enviar el correo de prueba. Revisa el registro.', 'aula-virtual' )
		);
	}

	/**
	 * Verifies nonce and capability.
	 *
	 * @param string $action Nonce action.
	 * @return void
	 */
	private function guard( string $action ): void {
		if ( ! current_user_can( Capabilities::MANAGE_LMS ) ) {
			wp_die( esc_html__( 'No tienes permisos para realizar esta accion.', 'aula-virtual' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( $action );
	}

	/**
	 * Redirects to the editor with a notice.
	 *
	 * @param int    $template_id Template id.
	 * @param string $type        success or error.
	 * @param string $message     Message.
	 * @return void
	 */
	private function redirect( int $template_id, string $type, string $message ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => self::SLUG,
					'template'   => $template_id,
					'av_notice'  => $type,
					'av_message' => rawurlencode( $message ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
