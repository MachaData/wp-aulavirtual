<?php
/**
 * Settings screen.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Admin;

use SIQA\AulaVirtual\Comments\CommentService;
use SIQA\AulaVirtual\Core\AuditLog;
use SIQA\AulaVirtual\Materials\MaterialService;
use SIQA\AulaVirtual\Courses\CoursePostType;
use SIQA\AulaVirtual\Permissions\Capabilities;
use SIQA\AulaVirtual\Security\Sanitizer;
use SIQA\AulaVirtual\Videos\VideoEmbed;
use SIQA\AulaVirtual\WooCommerce\Settings as WooSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One screen with tabs for every option the plugin reads.
 *
 * The field registry is the single source of truth: it drives rendering,
 * sanitisation and the documentation of each option. Secret fields are never
 * echoed back and are only overwritten when a new value is typed.
 */
final class SettingsScreen {

	public const SLUG        = 'aula-virtual-configuracion';
	public const ACTION_SAVE    = 'av_save_settings';
	public const ACTION_PROTECT = 'av_protect_materials_now';

	/**
	 * Audit trail.
	 *
	 * @var AuditLog
	 */
	private AuditLog $audit;

	/**
	 * Constructor.
	 *
	 * @param AuditLog $audit Audit trail.
	 */
	/**
	 * Material rules.
	 *
	 * @var MaterialService
	 */
	private MaterialService $materials;

	/**
	 * Constructor.
	 *
	 * @param AuditLog        $audit     Audit trail.
	 * @param MaterialService $materials Material rules.
	 */
	public function __construct( AuditLog $audit, MaterialService $materials ) {
		$this->audit     = $audit;
		$this->materials = $materials;
	}

	/**
	 * Tabs with their fields.
	 *
	 * @return array<string, array{label: string, fields: array<string, array<string, mixed>>}>
	 */
	public static function tabs(): array {
		return array(
			'general'     => array(
				'label'  => __( 'General', 'aula-virtual' ),
				'fields' => array(
					'av_brand_color'               => array(
						'label'   => __( 'Color principal', 'aula-virtual' ),
						'type'    => 'color',
						'default' => '#1d4ed8',
						'help'    => __( 'Se usa en la landing, el campus y los correos.', 'aula-virtual' ),
					),
					'av_admin_notification_email'  => array(
						'label'   => __( 'Correo de avisos al administrador', 'aula-virtual' ),
						'type'    => 'email',
						'default' => '',
						'help'    => __( 'A donde llegan las solicitudes nuevas. Vacio: el correo del sitio.', 'aula-virtual' ),
					),
					'av_email_from_name'           => array(
						'label'   => __( 'Nombre del remitente', 'aula-virtual' ),
						'type'    => 'text',
						'default' => '',
					),
					'av_email_from_address'        => array(
						'label'   => __( 'Correo del remitente', 'aula-virtual' ),
						'type'    => 'email',
						'default' => '',
						'help'    => __( 'Debe ser un correo del dominio configurado en tu SMTP.', 'aula-virtual' ),
					),
					CoursePostType::SLUG_OPTION    => array(
						'label'   => __( 'Slug de las landings de curso', 'aula-virtual' ),
						'type'    => 'slug',
						'default' => CoursePostType::DEFAULT_SLUG,
						'help'    => __( 'Por ejemplo "curso" para /curso/nombre/. Cambiarlo regenera los enlaces permanentes.', 'aula-virtual' ),
					),
				),
			),
			'enrollments' => array(
				'label'  => __( 'Matriculas', 'aula-virtual' ),
				'fields' => array(
					'av_enrollment_auto_approve' => array(
						'label'   => __( 'Aprobar solicitudes automaticamente', 'aula-virtual' ),
						'type'    => 'bool',
						'default' => false,
						'help'    => __( 'Si esta activo, una inscripcion por enlace queda aprobada sin revision. La edicion de pago igual espera el pago.', 'aula-virtual' ),
					),
					'av_block_wp_admin'          => array(
						'label'   => __( 'Bloquear wp-admin a los alumnos', 'aula-virtual' ),
						'type'    => 'bool',
						'default' => true,
					),
					'av_allow_retake'            => array(
						'label'   => __( 'Permitir repetir el curso', 'aula-virtual' ),
						'type'    => 'bool',
						'default' => true,
						'help'    => __( 'El alumno puede reiniciar su progreso en una edicion y volver a hacerla desde la primera sesion.', 'aula-virtual' ),
					),
					CommentService::OPTION_ENABLED => array(
						'label'   => __( 'Comentarios en las sesiones', 'aula-virtual' ),
						'type'    => 'bool',
						'default' => true,
						'help'    => __( 'Los alumnos pueden preguntar debajo de cada sesion; el instructor responde y modera desde el editor de la sesion.', 'aula-virtual' ),
					),
					'av_campus_focus_mode'       => array(
						'label'   => __( 'Modo enfoque en las sesiones', 'aula-virtual' ),
						'type'    => 'bool',
						'default' => false,
						'help'    => __( 'Muestra cada sesion sin la cabecera ni el pie del tema, a pantalla limpia.', 'aula-virtual' ),
					),
				),
			),
			'materials'   => array(
				'label'  => __( 'Materiales', 'aula-virtual' ),
				'fields' => array(
					MaterialService::OPTION_PROTECT => array(
						'label'   => __( 'Proteger los archivos de materiales', 'aula-virtual' ),
						'type'    => 'bool',
						'default' => true,
						'help'    => __( 'Al anadir un material, su archivo se mueve a uploads/aula-virtual/private/, donde el servidor web niega el acceso directo; solo se descarga por el enlace del campus tras comprobar la matricula. En Nginx hace falta la regla de la guia tecnica.', 'aula-virtual' ),
					),
					'av_protect_existing'           => array(
						'label'   => __( 'Materiales ya existentes', 'aula-virtual' ),
						'type'    => 'action',
						'default' => '',
						'action'  => self::ACTION_PROTECT,
						'button'  => __( 'Mover ahora a la carpeta protegida', 'aula-virtual' ),
						'help'    => __( 'Mueve los archivos de todos los materiales registrados (por ejemplo, los migrados desde Tutor). Los enlaces del campus siguen funcionando.', 'aula-virtual' ),
					),
				),
			),
			'videos'      => array(
				'label'  => __( 'Videos', 'aula-virtual' ),
				'fields' => array(
					VideoEmbed::OPTION_BUNNY_LIBRARY   => array(
						'label'   => __( 'Bunny Stream: ID de la biblioteca', 'aula-virtual' ),
						'type'    => 'text',
						'default' => '',
						'help'    => __( 'Con esto, una playlist del CDN se convierte al reproductor oficial de Bunny.', 'aula-virtual' ),
					),
					VideoEmbed::OPTION_BUNNY_TOKEN_KEY => array(
						'label'   => __( 'Bunny Stream: clave de token de la biblioteca', 'aula-virtual' ),
						'type'    => 'secret',
						'default' => '',
						'help'    => __( 'Stream > biblioteca > Security. Solo si la biblioteca exige token.', 'aula-virtual' ),
					),
					VideoEmbed::OPTION_BUNNY_CDN_KEY   => array(
						'label'   => __( 'Bunny CDN: clave de token de la pull zone', 'aula-virtual' ),
						'type'    => 'secret',
						'default' => '',
						'help'    => __( 'Solo para reproduccion HLS directa sin biblioteca configurada.', 'aula-virtual' ),
					),
					VideoEmbed::OPTION_BUNNY_TOKEN_TTL => array(
						'label'   => __( 'Validez del token (segundos)', 'aula-virtual' ),
						'type'    => 'int',
						'default' => 21600,
						'min'     => 300,
					),
				),
			),
			'woocommerce' => array(
				'label'  => __( 'WooCommerce', 'aula-virtual' ),
				'fields' => array(
					WooSettings::OPTION_ENROLL_STATUS => array(
						'label'   => __( 'Matricular cuando el pedido pase a', 'aula-virtual' ),
						'type'    => 'select',
						'default' => WooSettings::ENROLL_ON_PROCESSING,
						'options' => array(
							WooSettings::ENROLL_ON_PROCESSING => __( 'Procesando (pago confirmado)', 'aula-virtual' ),
							WooSettings::ENROLL_ON_COMPLETED  => __( 'Completado', 'aula-virtual' ),
						),
					),
					WooSettings::OPTION_REFUND_ACTION => array(
						'label'   => __( 'Ante reembolso o cancelacion', 'aula-virtual' ),
						'type'    => 'select',
						'default' => WooSettings::REFUND_SUSPEND,
						'options' => array(
							WooSettings::REFUND_NONE    => __( 'No hacer nada', 'aula-virtual' ),
							WooSettings::REFUND_SUSPEND => __( 'Suspender la matricula', 'aula-virtual' ),
							WooSettings::REFUND_CANCEL  => __( 'Cancelar la matricula', 'aula-virtual' ),
						),
					),
				),
			),
			'advanced'    => array(
				'label'  => __( 'Avanzado', 'aula-virtual' ),
				'fields' => array(
					'av_log_level'                => array(
						'label'   => __( 'Nivel de registro', 'aula-virtual' ),
						'type'    => 'select',
						'default' => 'info',
						'options' => array(
							'debug'   => 'debug',
							'info'    => 'info',
							'warning' => 'warning',
							'error'   => 'error',
							'off'     => __( 'Desactivado', 'aula-virtual' ),
						),
					),
					'av_delete_data_on_uninstall' => array(
						'label'   => __( 'Eliminar todos los datos al desinstalar', 'aula-virtual' ),
						'type'    => 'bool',
						'default' => false,
						'help'    => __( 'Apagado por defecto. Solo al desinstalar el plugin (no al desactivarlo) y solo si esta activo.', 'aula-virtual' ),
					),
				),
			),
		);
	}

	/**
	 * Moves every material file into the protected folder.
	 *
	 * @return void
	 */
	public function handle_protect(): void {
		if ( ! current_user_can( Capabilities::MANAGE_LMS ) ) {
			wp_die( esc_html__( 'No tienes permisos para realizar esta accion.', 'aula-virtual' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( self::ACTION_PROTECT );

		$report = $this->materials->protect_all();

		$this->audit->record( AuditLog::SETTINGS_UPDATED, 'materials', 0, array( 'protect_all' => $report ) );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => self::SLUG,
					'tab'        => 'materials',
					'av_notice'  => 0 === $report['failed'] ? 'success' : 'error',
					'av_message' => rawurlencode(
						sprintf(
							/* translators: 1: moved, 2: already protected, 3: failed. */
							__( '%1$d archivos movidos, %2$d ya protegidos, %3$d con error.', 'aula-virtual' ),
							$report['moved'],
							$report['skipped'],
							$report['failed']
						)
					),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Sanitises one value according to its field definition.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @param mixed                $value Raw value.
	 * @return mixed Clean value.
	 */
	public static function sanitize_field( array $field, mixed $value ): mixed {
		switch ( $field['type'] ) {
			case 'bool':
				return Sanitizer::bool( $value );
			case 'int':
				$int = Sanitizer::int( $value );
				return isset( $field['min'] ) ? max( (int) $field['min'], $int ) : $int;
			case 'email':
				return Sanitizer::email( $value );
			case 'color':
				$value = is_scalar( $value ) ? trim( (string) $value ) : '';
				return preg_match( '/^#[0-9a-fA-F]{6}$/', $value ) ? strtolower( $value ) : (string) $field['default'];
			case 'slug':
				$slug = sanitize_title( is_scalar( $value ) ? (string) $value : '' );
				return '' === $slug ? (string) $field['default'] : $slug;
			case 'select':
				return Sanitizer::enum( $value, array_keys( $field['options'] ), (string) $field['default'] );
			case 'secret':
			case 'text':
			default:
				return Sanitizer::text( $value );
		}
	}

	/**
	 * Renders the screen.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( Capabilities::MANAGE_LMS ) ) {
			wp_die( esc_html__( 'No tienes permisos para ver esta pagina.', 'aula-virtual' ) );
		}

		$tabs = self::tabs();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
		$current = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
		$current = isset( $tabs[ $current ] ) ? $current : 'general';

		$notice = null;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only, produced by our own redirect.
		if ( isset( $_GET['av_notice'], $_GET['av_message'] ) ) {
			$notice = array(
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only.
				'type'    => 'success' === sanitize_key( wp_unslash( $_GET['av_notice'] ) ) ? 'success' : 'error',
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only.
				'message' => sanitize_text_field( wp_unslash( $_GET['av_message'] ) ),
			);
		}

		$values = array();

		foreach ( $tabs[ $current ]['fields'] as $option => $field ) {
			$values[ $option ] = get_option( $option, $field['default'] );
		}

		$pages     = get_option( 'av_pages', array() );
		$campus_id = is_array( $pages ) && isset( $pages['campus'] ) ? (int) $pages['campus'] : 0;

		$data = array(
			'tabs'       => $tabs,
			'current'    => $current,
			'values'     => $values,
			'notice'     => $notice,
			'campus_url' => $campus_id > 0 ? (string) get_permalink( $campus_id ) : '',
			'campus_edit' => $campus_id > 0 ? (string) get_edit_post_link( $campus_id ) : '',
		);

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- controlled template data.
		extract( $data, EXTR_SKIP );

		require AV_PATH . 'admin/views/settings.php';
	}

	/**
	 * Saves one tab.
	 *
	 * @return void
	 */
	public function handle_save(): void {
		if ( ! current_user_can( Capabilities::MANAGE_LMS ) ) {
			wp_die( esc_html__( 'No tienes permisos para realizar esta accion.', 'aula-virtual' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( self::ACTION_SAVE );

		$tabs = self::tabs();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$tab = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : 'general';

		if ( ! isset( $tabs[ $tab ] ) ) {
			wp_die( esc_html__( 'Pestana no valida.', 'aula-virtual' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$input   = wp_unslash( $_POST );
		$changed = array();

		foreach ( $tabs[ $tab ]['fields'] as $option => $field ) {
			$raw = $input[ $option ] ?? ( 'bool' === $field['type'] ? '0' : null );

			// A secret left blank keeps its current value.
			if ( 'secret' === $field['type'] && ( null === $raw || '' === trim( (string) $raw ) ) ) {
				continue;
			}

			if ( null === $raw || 'action' === $field['type'] ) {
				continue;
			}

			$clean = self::sanitize_field( $field, $raw );

			if ( get_option( $option, $field['default'] ) !== $clean ) {
				update_option( $option, $clean, false );
				$changed[] = $option;
			}
		}

		if ( in_array( CoursePostType::SLUG_OPTION, $changed, true ) ) {
			CoursePostType::register();
			flush_rewrite_rules();
		}

		if ( array() !== $changed ) {
			$this->audit->record( AuditLog::SETTINGS_UPDATED, 'settings', 0, array( 'options' => $changed ) );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => self::SLUG,
					'tab'        => $tab,
					'av_notice'  => 'success',
					'av_message' => rawurlencode( __( 'Configuracion guardada.', 'aula-virtual' ) ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
