<?php
/**
 * Default email templates.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Emails;

use SIQA\AulaVirtual\Core\Events\Events;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The templates installed the first time, all editable afterwards.
 *
 * Seeding never overwrites: a template the administrator already edited is
 * left alone, and only missing (event, recipient) pairs are added.
 */
final class EmailDefaults {

	/**
	 * Installs the missing default templates.
	 *
	 * @param EmailTemplateRepository $templates Template persistence.
	 * @return int Templates created.
	 */
	public static function seed( EmailTemplateRepository $templates ): int {
		$created = 0;
		$now     = current_time( 'mysql', true );

		foreach ( self::definitions() as $definition ) {
			if ( null !== $templates->find_for( $definition['event'], $definition['recipient'] ) ) {
				continue;
			}

			$templates->insert(
				array(
					'event'      => $definition['event'],
					'recipient'  => $definition['recipient'],
					'subject'    => $definition['subject'],
					'body'       => $definition['body'],
					'enabled'    => $definition['enabled'] ? 1 : 0,
					'updated_at' => $now,
					'updated_by' => 0,
				)
			);

			++$created;
		}

		return $created;
	}

	/**
	 * Events that can carry an email, with their label for the admin screen.
	 *
	 * @return array<string, string>
	 */
	public static function events(): array {
		return array(
			Events::REGISTRATION_REQUEST_CREATED  => __( 'Solicitud recibida', 'aula-virtual' ),
			Events::REGISTRATION_REQUEST_APPROVED => __( 'Solicitud aprobada', 'aula-virtual' ),
			Events::REGISTRATION_REQUEST_REJECTED => __( 'Solicitud rechazada', 'aula-virtual' ),
			Events::ENROLLMENT_APPROVED           => __( 'Bienvenida: matricula activa', 'aula-virtual' ),
			Events::ENROLLMENT_SUSPENDED          => __( 'Matricula suspendida', 'aula-virtual' ),
			Events::ENROLLMENT_EXPIRING           => __( 'Acceso proximo a expirar', 'aula-virtual' ),
			Events::LESSON_RELEASED               => __( 'Nueva sesion disponible', 'aula-virtual' ),
			Events::LIVE_CLASS_UPCOMING           => __( 'Proxima clase en vivo', 'aula-virtual' ),
			Events::MATERIAL_ADDED                => __( 'Nuevo material', 'aula-virtual' ),
			Events::ANNOUNCEMENT_CREATED          => __( 'Nuevo anuncio', 'aula-virtual' ),
			Events::EDITION_STARTING              => __( 'Curso proximo a iniciar', 'aula-virtual' ),
			Events::COURSE_COMPLETED              => __( 'Curso finalizado', 'aula-virtual' ),
			Events::CERTIFICATE_ISSUED            => __( 'Certificado disponible', 'aula-virtual' ),
		);
	}

	/**
	 * Default template definitions.
	 *
	 * @return array<int, array{event: string, recipient: string, subject: string, body: string, enabled: bool}>
	 */
	public static function definitions(): array {
		$student = EmailTemplateRepository::RECIPIENT_STUDENT;
		$admin   = EmailTemplateRepository::RECIPIENT_ADMIN;

		return array(
			array(
				'event'     => Events::REGISTRATION_REQUEST_CREATED,
				'recipient' => $student,
				'subject'   => 'Recibimos tu inscripcion a {{course_name}}',
				'body'      => "<p>Hola {{first_name}},</p>\n<p>Recibimos tu solicitud de inscripcion a <strong>{{course_name}}</strong> ({{edition_name}}).</p>\n<p>La revisaremos y te escribiremos a este correo con los siguientes pasos.</p>\n<p>{{site_name}}</p>",
				'enabled'   => true,
			),
			array(
				'event'     => Events::REGISTRATION_REQUEST_CREATED,
				'recipient' => $admin,
				'subject'   => 'Nueva solicitud: {{first_name}} {{last_name}} - {{course_name}}',
				'body'      => "<p>Hay una nueva solicitud de inscripcion.</p>\n<p><strong>{{first_name}} {{last_name}}</strong><br>{{email}}<br>{{phone}}</p>\n<p>Curso: {{course_name}} ({{edition_name}})</p>\n<p><a href=\"{{admin_requests_url}}\">Revisar solicitudes</a></p>",
				'enabled'   => true,
			),
			array(
				'event'     => Events::REGISTRATION_REQUEST_APPROVED,
				'recipient' => $student,
				'subject'   => 'Tu inscripcion a {{course_name}} fue aprobada',
				'body'      => "<p>Hola {{first_name}},</p>\n<p>Tu solicitud para <strong>{{course_name}}</strong> ({{edition_name}}) fue aprobada.</p>\n<p>Para confirmar tu plaza completa el pago aqui:</p>\n<p><a href=\"{{payment_url}}\">Pagar {{price}}</a></p>\n<p>Apenas se confirme el pago recibiras el acceso al campus.</p>\n<p>{{site_name}}</p>",
				'enabled'   => true,
			),
			array(
				'event'     => Events::REGISTRATION_REQUEST_REJECTED,
				'recipient' => $student,
				'subject'   => 'Sobre tu inscripcion a {{course_name}}',
				'body'      => "<p>Hola {{first_name}},</p>\n<p>Por ahora no pudimos aprobar tu solicitud para <strong>{{course_name}}</strong>.</p>\n<p>{{rejection_reason}}</p>\n<p>Si crees que se trata de un error, responde a este correo.</p>\n<p>{{site_name}}</p>",
				'enabled'   => true,
			),
			array(
				'event'     => Events::ENROLLMENT_APPROVED,
				'recipient' => $student,
				'subject'   => 'Bienvenido a {{course_name}}',
				'body'      => "<p>Hola {{first_name}},</p>\n<p>Ya estas matriculado en <strong>{{course_name}}</strong> ({{edition_name}}).</p>\n<ul>\n<li>Modalidad: {{modality}}</li>\n<li>Inicio: {{start_date}}</li>\n<li>Fin: {{end_date}}</li>\n<li>Dias: {{schedule_days}}</li>\n<li>Horario: {{schedule_time}} ({{timezone}})</li>\n</ul>\n<p>Crea tu contrasena para entrar al campus:</p>\n<p><a href=\"{{set_password_url}}\">Crear mi contrasena</a></p>\n<p>Luego podras ingresar siempre desde <a href=\"{{campus_url}}\">{{campus_url}}</a>.</p>\n<p>{{site_name}}</p>",
				'enabled'   => true,
			),
			array(
				'event'     => Events::COURSE_COMPLETED,
				'recipient' => $student,
				'subject'   => 'Completaste {{course_name}}',
				'body'      => "<p>Felicitaciones, {{first_name}}.</p>\n<p>Completaste todas las sesiones de <strong>{{course_name}}</strong> ({{edition_name}}).</p>\n<p>{{site_name}}</p>",
				'enabled'   => true,
			),
		);
	}
}
