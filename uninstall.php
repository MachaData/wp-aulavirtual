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

// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one-off cleanup during uninstall.
$av_options = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( 'av_' ) . '%'
	)
);

foreach ( (array) $av_options as $av_option ) {
	delete_option( $av_option );
}

// Course posts and their meta are left untouched on purpose: they are regular
// WordPress content and the site owner can delete them from the admin.
