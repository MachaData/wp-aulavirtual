<?php
/**
 * Uninstall routine.
 *
 * Data is only removed when the site owner explicitly enabled the option, which
 * defaults to off. Deleting a plugin must not silently destroy the academic
 * record of an institution.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! get_option( 'av_delete_data_on_uninstall', false ) ) {
	return;
}

$av_autoload = __DIR__ . '/vendor/autoload.php';

if ( is_readable( $av_autoload ) ) {
	require_once $av_autoload;
} else {
	foreach (
		array(
			'includes/Database/Schema.php',
			'includes/Database/Migrator.php',
			'includes/Permissions/Capabilities.php',
			'includes/Permissions/Roles.php',
		) as $av_file
	) {
		require_once __DIR__ . '/' . $av_file;
	}
}

$av_schema = new SIQA\AulaVirtual\Database\Schema();

( new SIQA\AulaVirtual\Database\Migrator( $av_schema ) )->drop_tables();

SIQA\AulaVirtual\Permissions\Roles::remove();

global $wpdb;

// Lista explicita: el prefijo av_ es corto y otros plugins o temas pueden usarlo.
$av_options = array(
	'av_admin_notification_email',
	'av_allow_retake',
	'av_auto_certificate',
	'av_block_wp_admin',
	'av_brand_color',
	'av_bunny_cdn_token_key',
	'av_bunny_library_id',
	'av_bunny_token_key',
	'av_bunny_token_ttl',
	'av_campus_focus_mode',
	'av_campus_slug',
	'av_certificate_logo',
	'av_certificate_signature_name',
	'av_certificate_signature_title',
	'av_course_slug',
	'av_db_version',
	'av_delete_data_on_uninstall',
	'av_email_from_address',
	'av_email_from_name',
	'av_enrollment_auto_approve',
	'av_integration_key',
	'av_lesson_comments',
	'av_log_level',
	'av_pages',
	'av_progress_mode',
	'av_protect_materials',
	'av_roles_version',
	'av_send_welcome_email',
	'av_tutor_migration_map',
	'av_version',
	'av_wc_enroll_status',
	'av_wc_refund_action',
);

foreach ( $av_options as $av_option ) {
	delete_option( $av_option );
}

// Transients del plugin (limites, importaciones con datos de alumnos, informes).
foreach ( array( 'av_rl_', 'av_import_', 'av_tutor_report_', 'av_comment_cooldown_' ) as $av_prefix ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one-off cleanup during uninstall.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_' . $av_prefix ) . '%',
			$wpdb->esc_like( '_transient_timeout_' . $av_prefix ) . '%'
		)
	);
}

// Datos personales que el plugin guarda en los usuarios (telefono, documento).
delete_metadata( 'user', 0, 'av_phone', '', true );
delete_metadata( 'user', 0, 'av_document', '', true );
delete_metadata( 'post', 0, '_av_protected', '', true );

// Course posts and their meta are left untouched on purpose: they are regular
// WordPress content and the site owner can delete them from the admin.
