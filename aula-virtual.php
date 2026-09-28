<?php
/**
 * Plugin Name:       Aula Virtual SIQA
 * Plugin URI:        https://siqafree.pe/
 * Description:       Sistema de gestion academica para WordPress basado en el modelo Curso -> Ediciones -> Matriculas.
 * Version:           0.9.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            FARGIL S.A.C.
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       aula-virtual
 * Domain Path:       /languages
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'AV_VERSION' ) ) {
	return;
}

define( 'AV_VERSION', '0.9.0' );
define( 'AV_MIN_PHP', '8.1' );
define( 'AV_MIN_WP', '6.4' );
define( 'AV_FILE', __FILE__ );
define( 'AV_PATH', plugin_dir_path( __FILE__ ) );
define( 'AV_URL', plugin_dir_url( __FILE__ ) );
define( 'AV_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Verifies that the hosting environment meets the plugin requirements.
 *
 * @return string Empty string when the environment is supported, error message otherwise.
 */
function environment_error(): string {
	global $wp_version;

	if ( version_compare( PHP_VERSION, AV_MIN_PHP, '<' ) ) {
		return sprintf(
			/* translators: 1: required PHP version, 2: current PHP version. */
			__( 'Aula Virtual requiere PHP %1$s o superior. Esta instalacion usa PHP %2$s.', 'aula-virtual' ),
			AV_MIN_PHP,
			PHP_VERSION
		);
	}

	if ( isset( $wp_version ) && version_compare( $wp_version, AV_MIN_WP, '<' ) ) {
		return sprintf(
			/* translators: 1: required WordPress version, 2: current WordPress version. */
			__( 'Aula Virtual requiere WordPress %1$s o superior. Esta instalacion usa WordPress %2$s.', 'aula-virtual' ),
			AV_MIN_WP,
			$wp_version
		);
	}

	return '';
}

/**
 * Registers the PSR-4 autoloader for the plugin namespace.
 *
 * Composer is preferred when the vendor directory is bundled; the fallback keeps
 * the plugin usable on installations where dependencies were not installed.
 *
 * @return void
 */
function register_autoloader(): void {
	$composer = AV_PATH . 'vendor/autoload.php';

	if ( is_readable( $composer ) ) {
		require_once $composer;

		return;
	}

	spl_autoload_register(
		static function ( string $class_name ): void {
			$prefix = 'SIQA\\AulaVirtual\\';

			if ( ! str_starts_with( $class_name, $prefix ) ) {
				return;
			}

			$relative = substr( $class_name, strlen( $prefix ) );
			$path     = AV_PATH . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';

			if ( is_readable( $path ) ) {
				require_once $path;
			}
		}
	);
}

/**
 * Renders an admin notice when the environment is not supported.
 *
 * @param string $message Error message already translated.
 * @return void
 */
function render_environment_notice( string $message ): void {
	add_action(
		'admin_notices',
		static function () use ( $message ): void {
			printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $message ) );
		}
	);
}

$av_environment_error = environment_error();

if ( '' !== $av_environment_error ) {
	render_environment_notice( $av_environment_error );

	return;
}

register_autoloader();

register_activation_hook( __FILE__, array( Database\Installer::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Database\Installer::class, 'deactivate' ) );

/**
 * Returns the shared plugin instance.
 *
 * @return Core\Plugin
 */
function aula_virtual(): Core\Plugin {
	return Core\Plugin::instance();
}

add_action( 'plugins_loaded', static fn() => aula_virtual()->boot(), 5 );
