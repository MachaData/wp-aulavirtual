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
			Events::ENROLLMENT_APPROVED           => __( 'Bienvenida: matrícula activa', 'aula-virtual' ),
			Events::ENROLLMENT_SUSPENDED          => __( 'Matrícula suspendida', 'aula-virtual' ),
			Events::ENROLLMENT_EXPIRING           => __( 'Acceso próximo a expirar', 'aula-virtual' ),
			Events::LESSON_RELEASED               => __( 'Nueva sesión disponible', 'aula-virtual' ),
			Events::LIVE_CLASS_UPCOMING           => __( 'Próxima clase en vivo', 'aula-virtual' ),
			Events::MATERIAL_ADDED                => __( 'Nuevo material', 'aula-virtual' ),
			Events::ANNOUNCEMENT_CREATED          => __( 'Nuevo anuncio', 'aula-virtual' ),
			Events::EDITION_STARTING              => __( 'Curso próximo a iniciar', 'aula-virtual' ),
			Events::COURSE_COMPLETED              => __( 'Curso finalizado', 'aula-virtual' ),
			Events::CERTIFICATE_ISSUED            => __( 'Certificado disponible', 'aula-virtual' ),
			Events::COMMENT_POSTED                => __( 'Nuevo comentario en una sesión', 'aula-virtual' ),
			Events::ACCESS_LINK_SENT              => __( 'Enlace de acceso (reenviado por el administrador)', 'aula-virtual' ),
		);
	}

	/**
	 * Recipient types a template can go to, with their label for the admin screen.
	 *
	 * @return array<string, string>
	 */
	public static function recipients(): array {
		return array(
			EmailTemplateRepository::RECIPIENT_STUDENT               => __( 'Alumno', 'aula-virtual' ),
			EmailTemplateRepository::RECIPIENT_ADMIN                 => __( 'Administrador', 'aula-virtual' ),
			EmailTemplateRepository::RECIPIENT_INSTRUCTOR            => __( 'Docente del curso', 'aula-virtual' ),
			EmailTemplateRepository::RECIPIENT_COMMENT_PARENT_AUTHOR => __( 'Autor del comentario respondido', 'aula-virtual' ),
		);
	}

	/**
	 * Default template definitions.
	 *
	 * @return array<int, array{event: string, recipient: string, subject: string, body: string, enabled: bool}>
	 */
	public static function definitions(): array {
		$student    = EmailTemplateRepository::RECIPIENT_STUDENT;
		$admin      = EmailTemplateRepository::RECIPIENT_ADMIN;
		$instructor = EmailTemplateRepository::RECIPIENT_INSTRUCTOR;
		$replied    = EmailTemplateRepository::RECIPIENT_COMMENT_PARENT_AUTHOR;

		return array(
			array(
				'event'     => Events::REGISTRATION_REQUEST_CREATED,
				'recipient' => $student,
				'subject'   => 'Recibimos tu inscripción a {{course_name}}',
				'body'      => "<p>Hola {{first_name}},</p>\n<p>Recibimos tu solicitud de inscripción a <strong>{{course_name}}</strong> ({{edition_name}}).</p>\n<p>La revisaremos y te escribiremos a este correo con los siguientes pasos.</p>\n<p>{{site_name}}</p>",
				'enabled'   => true,
			),
			array(
				'event'     => Events::REGISTRATION_REQUEST_CREATED,
				'recipient' => $admin,
				'subject'   => 'Nueva solicitud: {{first_name}} {{last_name}} - {{course_name}}',
				'body'      => "<p>Hay una nueva solicitud de inscripción.</p>\n<p><strong>{{first_name}} {{last_name}}</strong><br>{{email}}<br>{{phone}}</p>\n<p>Curso: {{course_name}} ({{edition_name}})</p>\n<p><a href=\"{{admin_requests_url}}\">Revisar solicitudes</a></p>",
				'enabled'   => true,
			),
			array(
				'event'     => Events::REGISTRATION_REQUEST_APPROVED,
				'recipient' => $student,
				'subject'   => 'Tu inscripción a {{course_name}} fue aprobada',
				'body'      => "<p>Hola {{first_name}},</p>\n<p>Tu solicitud para <strong>{{course_name}}</strong> ({{edition_name}}) fue aprobada.</p>\n<p>Para confirmar tu plaza completa el pago aquí:</p>\n<p><a href=\"{{payment_url}}\">Pagar {{price}}</a></p>\n<p>Apenas se confirme el pago recibirás el acceso al campus.</p>\n<p>{{site_name}}</p>",
				'enabled'   => true,
			),
			array(
				'event'     => Events::REGISTRATION_REQUEST_REJECTED,
				'recipient' => $student,
				'subject'   => 'Sobre tu inscripción a {{course_name}}',
				'body'      => "<p>Hola {{first_name}},</p>\n<p>Por ahora no pudimos aprobar tu solicitud para <strong>{{course_name}}</strong>.</p>\n<p>{{rejection_reason}}</p>\n<p>Si crees que se trata de un error, responde a este correo.</p>\n<p>{{site_name}}</p>",
				'enabled'   => true,
			),
			array(
				'event'     => Events::ENROLLMENT_APPROVED,
				'recipient' => $student,
				'subject'   => 'Bienvenido a {{course_name}}',
				'body'      => "<p>Hola {{first_name}},</p>\n<p>Ya estás matriculado en <strong>{{course_name}}</strong> ({{edition_name}}).</p>\n<ul>\n<li>Modalidad: {{modality}}</li>\n<li>Inicio: {{start_date}}</li>\n<li>Fin: {{end_date}}</li>\n<li>Días: {{schedule_days}}</li>\n<li>Horario: {{schedule_time}} ({{timezone}})</li>\n</ul>\n{{access_instructions}}\n<p>{{site_name}}</p>",
				'enabled'   => true,
			),
			array(
				'event'     => Events::ANNOUNCEMENT_CREATED,
				'recipient' => $student,
				'subject'   => '{{course_name}}: {{announcement_title}}',
				'body'      => "<p>Hola {{first_name}},</p>\n<p>Hay un nuevo anuncio en <strong>{{course_name}}</strong> ({{edition_name}}):</p>\n<h2>{{announcement_title}}</h2>\n{{announcement_content}}\n<p><a href=\"{{campus_url}}\">Ir al campus</a></p>\n<p>{{site_name}}</p>",
				'enabled'   => true,
			),
			array(
				'event'     => Events::COURSE_COMPLETED,
				'recipient' => $student,
				'subject'   => 'Completaste {{course_name}}',
				'body'      => "<p>Felicitaciones, {{first_name}}.</p>\n<p>Completaste todas las sesiones de <strong>{{course_name}}</strong> ({{edition_name}}).</p>\n<p>{{site_name}}</p>",
				'enabled'   => true,
			),
			// Nuevas plantillas se anaden al final: la prueba de humo referencia
			// las anteriores por indice.
			array(
				'event'     => Events::COMMENT_POSTED,
				'recipient' => $instructor,
				'subject'   => 'Nueva pregunta en {{lesson_name}}',
				'body'      => "<p>Hola,</p>\n<p><strong>{{first_name}}</strong> dejó una pregunta en la sesión <strong>{{lesson_name}}</strong> de <strong>{{course_name}}</strong> ({{edition_name}}):</p>\n<blockquote>{{comment_content}}</blockquote>\n<p><a href=\"{{lesson_url}}\">Responder en el campus</a></p>\n<p>{{site_name}}</p>",
				'enabled'   => true,
			),
			array(
				'event'     => Events::COMMENT_POSTED,
				'recipient' => $replied,
				'subject'   => 'Respondieron a tu comentario en {{lesson_name}}',
				'body'      => "<p>Hola,</p>\n<p><strong>{{comment_author}}</strong> respondió a tu comentario en la sesión <strong>{{lesson_name}}</strong> de <strong>{{course_name}}</strong>:</p>\n<blockquote>{{comment_content}}</blockquote>\n<p><a href=\"{{lesson_url}}\">Ver la conversación</a></p>\n<p>{{site_name}}</p>",
				'enabled'   => true,
			),
			array(
				'event'     => Events::ACCESS_LINK_SENT,
				'recipient' => $student,
				'subject'   => 'Tu acceso a {{site_name}}',
				'body'      => "<p>Hola {{first_name}},</p>\n<p>Aquí tienes tu enlace para crear o cambiar tu contraseña del campus:</p>\n<p><a href=\"{{set_password_url}}\">Crear mi contraseña</a></p>\n<p>El enlace vence en {{link_expiry}}. Si venció, pide otro desde «Olvidé mi contraseña» en el campus.</p>\n<p>Luego entra siempre desde <a href=\"{{campus_url}}\">{{campus_url}}</a> con tu correo y tu contraseña.</p>\n<p>{{site_name}}</p>",
				'enabled'   => true,
			),
		);
	}
}
