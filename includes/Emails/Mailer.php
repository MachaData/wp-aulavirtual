<?php
/**
 * Outgoing mail.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Emails;

use SIQA\AulaVirtual\Core\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends HTML email through wp_mail() and logs failures.
 *
 * Going through wp_mail() means any SMTP plugin the site already uses keeps
 * working, and the site owner never has to configure a second mail system.
 */
final class Mailer {

	/**
	 * Plugin logger.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Constructor.
	 *
	 * @param Logger $logger Plugin logger.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Sends an HTML email.
	 *
	 * @param string $to      Recipient address.
	 * @param string $subject Subject, already rendered.
	 * @param string $body    HTML body, already rendered and sanitised.
	 * @param string $context Channel used in the log, e.g. the event name.
	 * @return bool
	 */
	public function send( string $to, string $subject, string $body, string $context = 'email' ): bool {
		if ( ! is_email( $to ) ) {
			$this->logger->warning( 'Correo descartado: destinatario no valido.', array( 'context' => $context ), 'emails' );

			return false;
		}

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		$from_name  = get_option( 'av_email_from_name', '' );
		$from_email = get_option( 'av_email_from_address', '' );

		if ( is_string( $from_email ) && is_email( $from_email ) ) {
			$name      = is_string( $from_name ) && '' !== $from_name ? $from_name : (string) get_option( 'blogname' );
			$headers[] = sprintf( 'From: %s <%s>', wp_specialchars_decode( $name, ENT_QUOTES ), $from_email );
		}

		$sent = wp_mail( $to, $subject, $this->wrap( $body ), $headers );

		if ( ! $sent ) {
			$this->logger->error(
				'No se pudo enviar el correo.',
				array(
					'context' => $context,
					// Sin asunto ni correo en claro: el registro general no guarda datos personales.
					'to_hash' => substr( wp_hash( $to ), 0, 12 ),
				),
				'emails'
			);
		}

		return $sent;
	}

	/**
	 * Wraps the body in a minimal, mail-client friendly layout.
	 *
	 * @param string $body HTML body.
	 * @return string
	 */
	private function wrap( string $body ): string {
		$layout = AV_PATH . 'templates/emails/layout.php';

		if ( ! is_readable( $layout ) ) {
			return $body;
		}

		ob_start();
		require $layout;

		return (string) ob_get_clean();
	}
}
