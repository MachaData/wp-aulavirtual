<?php
/**
 * Uninstall routine.
 *
 * Data is only removed when the site owner explicitly enabled the option, which
 * defaults to off. Deleting a plugin must not silently destroy the academic
 * record of an institution.
 *
 * @package SEV\LMS
 */

declare( strict_types = 1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! get_option( 'sev_lms_delete_data_on_uninstall', false ) ) {
	return;
}

$sev_lms_autoload = __DIR__ . '/vendor/autoload.php';

if ( is_readable( $sev_lms_autoload ) ) {
	require_once $sev_lms_autoload;
} else {
	foreach (
		array(
			'includes/Database/Schema.php',
			'includes/Database/Migrator.php',
			'includes/Permissions/Capabilities.php',
			'includes/Permissions/Roles.php',
		) as $sev_lms_file
	) {
		require_once __DIR__ . '/' . $sev_lms_file;
	}
}

$sev_lms_schema = new SEV\LMS\Database\Schema();

( new SEV\LMS\Database\Migrator( $sev_lms_schema ) )->drop_tables();

SEV\LMS\Permissions\Roles::remove();

global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one-off cleanup during uninstall.
$sev_lms_options = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( 'sev_lms_' ) . '%'
	)
);

foreach ( (array) $sev_lms_options as $sev_lms_option ) {
	delete_option( $sev_lms_option );
}

// Course posts and their meta are left untouched on purpose: they are regular
// WordPress content and the site owner can delete them from the admin.
