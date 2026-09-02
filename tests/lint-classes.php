<?php
/**
 * Loads every class of the plugin to catch missing imports, wrong namespaces
 * and typos that `php -l` cannot see:
 *
 *     php tests/lint-classes.php
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

require_once __DIR__ . '/wp-stubs.php';

// Stubs for the WordPress classes the plugin extends or type-hints.
class WP_Error {

	/** @var array<string, mixed> */
	public array $data;

	public function __construct( public string $code = '', public string $message = '', $data = array() ) {
		$this->data = (array) $data;
	}
}

class WP_Post {

	public int $ID = 0;

	public string $post_type = '';

	public string $post_status = '';

	public string $post_name = '';
}

class WP_User {

	public int $ID = 0;

	/** @var array<int, string> */
	public array $roles = array();
}

class WP_Role {

	public function add_cap( string $cap ): void {
	}

	public function remove_cap( string $cap ): void {
	}
}

class WP_Query {

	/** @var array<int, WP_Post> */
	public array $posts = array();

	public int $found_posts = 0;

	public int $max_num_pages = 0;

	public function __construct( array $args = array() ) {
	}
}

class WP_REST_Response {

	public function __construct( public mixed $data = null ) {
	}

	public function header( string $name, string $value ): void {
	}
}

class WP_REST_Request {

	public function get_param( string $key ): mixed {
		return null;
	}
}

abstract class WP_REST_Controller {

	protected string $namespace = '';

	protected string $rest_base = '';

	protected ?array $schema = null;

	public function add_additional_fields_schema( $schema ) {
		return $schema;
	}

	public function get_public_item_schema() {
		return array();
	}
}

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'SIQA\\AulaVirtual\\';

		if ( ! str_starts_with( $class_name, $prefix ) ) {
			return;
		}

		$path = __DIR__ . '/../includes/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

$failures = 0;
$loaded   = 0;

$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( __DIR__ . '/../includes' ) );

/** @var SplFileInfo $file */
foreach ( $files as $file ) {
	if ( 'php' !== $file->getExtension() ) {
		continue;
	}

	require_once $file->getRealPath();

	$relative = str_replace( array( realpath( __DIR__ . '/../includes' ) . '/', '.php' ), '', $file->getRealPath() );
	$expected = 'SIQA\\AulaVirtual\\' . str_replace( '/', '\\', $relative );

	if ( class_exists( $expected, false ) || interface_exists( $expected, false ) ) {
		++$loaded;
		echo "  ok   {$expected}\n";
		continue;
	}

	++$failures;
	echo "  FAIL {$expected} no se declara en {$relative}.php\n";
}

echo "\n{$loaded} clases cargadas, {$failures} fallos\n";

exit( $failures > 0 ? 1 : 0 );
