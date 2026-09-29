<?php
/**
 * Email variable resolution.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Emails;

use SIQA\AulaVirtual\Campus\CampusController;
use SIQA\AulaVirtual\Comments\CommentRepository;
use SIQA\AulaVirtual\Curriculum\LessonRepository;
use SIQA\AulaVirtual\Editions\EditionRepository;
use SIQA\AulaVirtual\Editions\EditionService;
use SIQA\AulaVirtual\Enrollments\RegistrationRequestRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns an event payload (ids) into the variables a template can use.
 *
 * Every variable is always present, possibly empty, so a template never
 * breaks because an event did not carry a given id.
 */
final class VariableResolver {

	/**
	 * Edition persistence.
	 *
	 * @var EditionRepository
	 */
	private EditionRepository $editions;

	/**
	 * Request persistence.
	 *
	 * @var RegistrationRequestRepository
	 */
	private RegistrationRequestRepository $requests;

	/**
	 * Lesson persistence.
	 *
	 * @var LessonRepository
	 */
	private LessonRepository $lessons;

	/**
	 * Comment persistence.
	 *
	 * @var CommentRepository
	 */
	private CommentRepository $comments;

	/**
	 * Constructor.
	 *
	 * @param EditionRepository             $editions Edition persistence.
	 * @param RegistrationRequestRepository $requests Request persistence.
	 * @param LessonRepository              $lessons  Lesson persistence.
	 * @param CommentRepository             $comments Comment persistence.
	 */
	public function __construct(
		EditionRepository $editions,
		RegistrationRequestRepository $requests,
		LessonRepository $lessons,
		CommentRepository $comments
	) {
		$this->editions = $editions;
		$this->requests = $requests;
		$this->lessons  = $lessons;
		$this->comments = $comments;
	}

	/**
	 * Names and descriptions of every variable, for the admin screen.
	 *
	 * @return array<string, string>
	 */
	public static function catalogue(): array {
		return array(
			'first_name'       => __( 'Nombre del alumno', 'aula-virtual' ),
			'last_name'        => __( 'Apellido del alumno', 'aula-virtual' ),
			'email'            => __( 'Correo del alumno', 'aula-virtual' ),
			'phone'            => __( 'Teléfono indicado en la solicitud', 'aula-virtual' ),
			'course_name'      => __( 'Nombre del curso', 'aula-virtual' ),
			'course_url'       => __( 'Landing del curso', 'aula-virtual' ),
			'edition_name'     => __( 'Nombre de la edición', 'aula-virtual' ),
			'modality'         => __( 'Modalidad (grabado, en vivo, híbrido)', 'aula-virtual' ),
			'start_date'       => __( 'Fecha de inicio', 'aula-virtual' ),
			'end_date'         => __( 'Fecha de fin', 'aula-virtual' ),
			'schedule_days'    => __( 'Días de clase', 'aula-virtual' ),
			'schedule_time'    => __( 'Horario', 'aula-virtual' ),
			'timezone'         => __( 'Zona horaria de la edición', 'aula-virtual' ),
			'price'            => __( 'Precio informativo de la edición', 'aula-virtual' ),
			'payment_url'      => __( 'Enlace de pago (WooCommerce)', 'aula-virtual' ),
			'set_password_url' => __( 'Enlace seguro para crear la contraseña', 'aula-virtual' ),
			'login_url'        => __( 'Página de ingreso al campus', 'aula-virtual' ),
			'campus_url'       => __( 'Página del campus', 'aula-virtual' ),
			'lesson_name'      => __( 'Nombre de la sesión', 'aula-virtual' ),
			'lesson_url'       => __( 'Enlace de la sesión en el campus', 'aula-virtual' ),
			'comment_author'   => __( 'Nombre de quien escribió el comentario', 'aula-virtual' ),
			'comment_content'  => __( 'Texto del comentario', 'aula-virtual' ),
			'class_url'        => __( 'Enlace de la clase en vivo', 'aula-virtual' ),
			'certificate_url'  => __( 'Enlace del certificado', 'aula-virtual' ),
			'rejection_reason' => __( 'Motivo del rechazo', 'aula-virtual' ),
			'announcement_title'   => __( 'Título del anuncio', 'aula-virtual' ),
			'announcement_content' => __( 'Contenido del anuncio', 'aula-virtual' ),
			'site_name'        => __( 'Nombre del sitio', 'aula-virtual' ),
			'admin_requests_url' => __( 'Pantalla de solicitudes (para avisos al administrador)', 'aula-virtual' ),
		);
	}

	/**
	 * Resolves the variables for an event payload, escaped for the HTML body.
	 *
	 * @param array<string, mixed> $payload Event payload with ids.
	 * @return array<string, string>
	 */
	public function resolve( array $payload ): array {
		return self::escape_all( $this->resolve_raw( $payload ) );
	}

	/**
	 * Resolves the variables without escaping (for the plain-text subject).
	 *
	 * @param array<string, mixed> $payload Event payload with ids.
	 * @return array<string, string>
	 */
	public function resolve_raw( array $payload ): array {
		$vars = array_fill_keys( array_keys( self::catalogue() ), '' );

		$vars['site_name']  = wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES );
		$vars['campus_url'] = $this->campus_url();
		$vars['login_url']  = $this->campus_url();
		$vars['admin_requests_url'] = admin_url( 'admin.php?page=aula-virtual-solicitudes' );

		$request = isset( $payload['request_id'] ) ? $this->requests->find( (int) $payload['request_id'] ) : null;
		$user    = isset( $payload['user_id'] ) && (int) $payload['user_id'] > 0 ? get_userdata( (int) $payload['user_id'] ) : false;

		if ( null !== $request ) {
			$vars['first_name'] = (string) $request['first_name'];
			$vars['last_name']  = (string) $request['last_name'];
			$vars['email']      = (string) $request['email'];
			$vars['phone']      = (string) $request['phone'];
			$vars['rejection_reason'] = (string) ( $request['rejection_reason'] ?? '' );
		}

		if ( false !== $user ) {
			$vars['first_name'] = '' !== $user->first_name ? $user->first_name : $user->display_name;
			$vars['last_name']  = $user->last_name;
			$vars['email']      = $user->user_email;

			if ( ! empty( $payload['with_password_link'] ) ) {
				$vars['set_password_url'] = $this->set_password_url( $user );
			}
		}

		foreach ( array( 'announcement_title', 'announcement_content' ) as $key ) {
			if ( isset( $payload[ $key ] ) && is_string( $payload[ $key ] ) ) {
				$vars[ $key ] = 'announcement_content' === $key ? wp_kses_post( $payload[ $key ] ) : sanitize_text_field( $payload[ $key ] );
			}
		}

		if ( isset( $payload['reason'] ) && '' !== (string) $payload['reason'] ) {
			$vars['rejection_reason'] = (string) $payload['reason'];
		}

		$edition_id = (int) ( $payload['edition_id'] ?? ( $request['edition_id'] ?? 0 ) );
		$edition    = $edition_id > 0 ? $this->editions->find( $edition_id ) : null;

		if ( null !== $edition ) {
			$modalities = EditionService::modalities();

			$vars['edition_name']  = (string) $edition['name'];
			$vars['modality']      = $modalities[ $edition['modality'] ] ?? (string) $edition['modality'];
			$vars['start_date']    = $this->date( $edition['start_date'] );
			$vars['end_date']      = $this->date( $edition['end_date'] );
			$vars['schedule_days'] = (string) ( $edition['schedule_days'] ?? '' );
			$vars['schedule_time'] = (string) ( $edition['schedule_time'] ?? '' );
			$vars['timezone']      = (string) $edition['timezone'];
			$vars['price']         = (string) ( $edition['price_display'] ?? '' );
			$vars['payment_url']   = $this->payment_url( (int) $edition['product_id'] );
		}

		$course_id = (int) ( $payload['course_id'] ?? ( $edition['course_id'] ?? 0 ) );

		if ( $course_id > 0 ) {
			$vars['course_name'] = get_the_title( $course_id );
			$vars['course_url']  = (string) get_permalink( $course_id );
		}

		if ( isset( $payload['lesson_id'] ) ) {
			$lesson = $this->lessons->find( (int) $payload['lesson_id'] );

			if ( null !== $lesson ) {
				$vars['lesson_name'] = (string) $lesson['title'];
				$vars['lesson_url']  = $this->lesson_url( (int) $lesson['id'] );
			}
		}

		$comment = isset( $payload['comment_id'] ) && (int) $payload['comment_id'] > 0 ? $this->comments->find( (int) $payload['comment_id'] ) : null;

		if ( null !== $comment ) {
			$vars['comment_author']  = $this->display_name( (int) $comment['user_id'] );
			$vars['comment_content'] = nl2br( esc_html( (string) $comment['content'] ) );
		} elseif ( false !== $user ) {
			$vars['comment_author'] = (string) $user->display_name;
		}

		/**
		 * Filters the variables available to email templates.
		 *
		 * @param array<string, string> $vars    Resolved variables.
		 * @param array<string, mixed>  $payload Event payload.
		 */
		return array_map( 'strval', (array) apply_filters( 'aula_virtual/email_variables', $vars, $payload ) );
	}

	/**
	 * Flattens every value to one line of plain text (email subject).
	 *
	 * @param array<string, string> $vars Resolved variables.
	 * @return array<string, string>
	 */
	public static function plain_all( array $vars ): array {
		foreach ( $vars as $key => $value ) {
			$vars[ $key ] = trim( preg_replace( '/[\r\n]+/', ' ', wp_strip_all_tags( html_entity_decode( (string) $value, ENT_QUOTES, 'UTF-8' ) ) ) ?? '' );
		}

		return $vars;
	}

	/**
	 * Escapes every value for insertion into the HTML email.
	 *
	 * @param array<string, string> $vars Resolved variables.
	 * @return array<string, string>
	 */
	public static function escape_all( array $vars ): array {
		$html_allowed = array( 'comment_content', 'announcement_content' );

		foreach ( $vars as $key => $value ) {
			$value = (string) $value;

			if ( in_array( $key, $html_allowed, true ) ) {
				continue;
			}

			$vars[ $key ] = str_ends_with( $key, '_url' ) ? esc_url( $value ) : esc_html( $value );
		}

		return $vars;
	}

	/**
	 * Formats a stored UTC datetime with the site date format.
	 *
	 * @param mixed $value Stored value.
	 * @return string
	 */
	private function date( mixed $value ): string {
		if ( ! is_string( $value ) || '' === $value ) {
			return '';
		}

		return (string) date_i18n( (string) get_option( 'date_format' ), (int) strtotime( $value . ' UTC' ) );
	}

	/**
	 * Builds a one-step WooCommerce checkout URL for the edition product.
	 *
	 * @param int $product_id WooCommerce product id.
	 * @return string
	 */
	private function payment_url( int $product_id ): string {
		if ( $product_id <= 0 || ! function_exists( 'wc_get_checkout_url' ) ) {
			return '';
		}

		return add_query_arg( array( 'add-to-cart' => $product_id ), wc_get_checkout_url() );
	}

	/**
	 * Builds the secure password-setting link for a user.
	 *
	 * Passwords are never sent by email: the student chooses their own through
	 * the WordPress reset flow, which expires the key after use.
	 *
	 * @param \WP_User $user User.
	 * @return string
	 */
	private function set_password_url( \WP_User $user ): string {
		$key = get_password_reset_key( $user );

		if ( is_wp_error( $key ) ) {
			return '';
		}

		return network_site_url( 'wp-login.php?action=rp&key=' . rawurlencode( $key ) . '&login=' . rawurlencode( $user->user_login ), 'login' );
	}

	/**
	 * Display name of a user, empty when the account no longer exists.
	 *
	 * @param int $user_id User id.
	 * @return string
	 */
	private function display_name( int $user_id ): string {
		$user = $user_id > 0 ? get_userdata( $user_id ) : false;

		return false === $user ? '' : (string) $user->display_name;
	}

	/**
	 * Returns the campus URL of a lesson, clean when permalinks are enabled.
	 *
	 * Mirrors {@see CampusController::campus_url()} without pulling the whole
	 * controller (and its dependencies) into the email module.
	 *
	 * @param int $lesson_id Lesson id.
	 * @return string
	 */
	private function lesson_url( int $lesson_id ): string {
		$args = array( CampusController::QUERY_LESSON => $lesson_id );
		$base = $this->campus_url();

		if ( CampusController::page_id() > 0 && '' !== (string) get_option( 'permalink_structure', '' ) ) {
			$pretty = CampusController::pretty_path( $args );

			if ( null !== $pretty ) {
				return trailingslashit( $base ) . $pretty;
			}
		}

		return add_query_arg( $args, $base );
	}

	/**
	 * Returns the campus page URL.
	 *
	 * @return string
	 */
	private function campus_url(): string {
		$pages   = get_option( 'av_pages', array() );
		$page_id = is_array( $pages ) && isset( $pages['campus'] ) ? (int) $pages['campus'] : 0;

		return $page_id > 0 ? (string) get_permalink( $page_id ) : home_url( '/campus/' );
	}
}
