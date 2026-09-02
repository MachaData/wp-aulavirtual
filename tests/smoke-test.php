<?php
/**
 * Core smoke test.
 *
 * Runs the container, schema, repository, sanitiser and capability map without
 * a WordPress installation:
 *
 *     php tests/smoke-test.php
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

require_once __DIR__ . '/wp-stubs.php';

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

use SIQA\AulaVirtual\Admin\AdminServiceProvider;
use SIQA\AulaVirtual\Admin\EditionsScreen;
use SIQA\AulaVirtual\Campus\CampusController;
use SIQA\AulaVirtual\Campus\CampusServiceProvider;
use SIQA\AulaVirtual\Core\Container;
use SIQA\AulaVirtual\Core\Plugin;
use SIQA\AulaVirtual\Courses\CoursesServiceProvider;
use SIQA\AulaVirtual\Curriculum\CurriculumServiceProvider;
use SIQA\AulaVirtual\Curriculum\LessonType;
use SIQA\AulaVirtual\Editions\EditionStatus;
use SIQA\AulaVirtual\Editions\EditionsServiceProvider;
use SIQA\AulaVirtual\Enrollments\EnrollmentStatus;
use SIQA\AulaVirtual\Enrollments\EnrollmentsServiceProvider;
use SIQA\AulaVirtual\Permissions\PermissionsServiceProvider;
use SIQA\AulaVirtual\Progress\ProgressCalculator;
use SIQA\AulaVirtual\Progress\ProgressServiceProvider;
use SIQA\AulaVirtual\Database\Repository;
use SIQA\AulaVirtual\Database\Schema;
use SIQA\AulaVirtual\Permissions\Capabilities;
use SIQA\AulaVirtual\Security\Sanitizer;

$av_failures = 0;
$av_checks   = 0;

/**
 * Asserts a condition and reports the result.
 *
 * @param string $label     Check description.
 * @param bool   $condition Result of the check.
 * @return void
 */
function check( string $label, bool $condition ): void {
	global $av_failures, $av_checks;

	++$av_checks;

	if ( $condition ) {
		echo "  ok   {$label}\n";

		return;
	}

	++$av_failures;
	echo "  FAIL {$label}\n";
}

/**
 * Repository used to exercise the base class.
 */
final class TestEnrollmentRepository extends Repository {

	protected function table_name(): string {
		return 'enrollments';
	}

	protected function columns(): array {
		return array(
			'id'         => '%d',
			'user_id'    => '%d',
			'edition_id' => '%d',
			'status'     => '%s',
			'notes'      => '%s',
		);
	}

	/**
	 * Exposes the protected clause builders to the test.
	 *
	 * @param array<string, mixed> $where  Conditions.
	 * @param array<int, mixed>    $values Collected values.
	 * @return string
	 */
	public function build_where( array $where, array &$values ): string {
		return $this->where_clause( $where, $values );
	}

	/**
	 * Exposes the order clause builder.
	 *
	 * @param string $order_by Column.
	 * @param string $order    Direction.
	 * @return string
	 */
	public function build_order( string $order_by, string $order ): string {
		return $this->order_clause( $order_by, $order );
	}

	/**
	 * Exposes the format mapper.
	 *
	 * @param array<string, mixed> $data Values.
	 * @return array<int, string>
	 */
	public function build_formats( array $data ): array {
		return $this->formats_for( $this->filter_columns( $data ) );
	}
}

echo "Container\n";
$container = new Container();
$container->singleton( 'counter', static fn(): object => new stdClass() );
check( 'resuelve un servicio registrado', $container->get( 'counter' ) instanceof stdClass );
check( 'devuelve siempre la misma instancia', $container->get( 'counter' ) === $container->get( 'counter' ) );
check( 'reporta servicios conocidos', $container->has( 'counter' ) && ! $container->has( 'missing' ) );

$threw = false;
try {
	$container->get( 'missing' );
} catch ( InvalidArgumentException $e ) {
	$threw = true;
}
check( 'falla al resolver un servicio desconocido', $threw );

$container->singleton( 'a', static fn( Container $c ) => $c->get( 'b' ) );
$container->singleton( 'b', static fn( Container $c ) => $c->get( 'a' ) );
$circular = false;
try {
	$container->get( 'a' );
} catch ( InvalidArgumentException $e ) {
	$circular = str_contains( $e->getMessage(), 'circular' );
}
check( 'detecta dependencias circulares', $circular );

echo "\nSchema\n";
$schema      = new Schema();
$definitions = $schema->definitions();
check( 'declara las 15 tablas del MVP', 15 === count( $definitions ) );
check( 'usa el prefijo de WordPress', 'wp_av_enrollments' === $schema->table( 'enrollments' ) );
check( 'expone los nombres logicos', count( $schema->table_names() ) === count( $definitions ) );

$structure_ok = true;
$primary_ok   = true;
foreach ( $definitions as $name => $sql ) {
	$structure_ok = $structure_ok && str_contains( $sql, "CREATE TABLE wp_av_{$name} (" );
	$primary_ok   = $primary_ok && str_contains( $sql, 'PRIMARY KEY  (id)' );
}
check( 'cada sentencia crea su tabla', $structure_ok );
check( 'cada tabla declara PRIMARY KEY con el doble espacio que exige dbDelta', $primary_ok );
check( 'no quedan fechas cero que rompan el modo estricto de MySQL', ! str_contains( implode( '', $definitions ), '0000-00-00' ) );
check(
	'la matricula es unica por alumno y edicion',
	str_contains( $definitions['enrollments'], 'UNIQUE KEY user_edition (user_id,edition_id)' )
);
check(
	'el progreso es unico por alumno y leccion',
	str_contains( $definitions['progress'], 'UNIQUE KEY user_lesson (user_id,lesson_id)' )
);

echo "\nRepository\n";
$repository = new TestEnrollmentRepository( $schema );
check( 'resuelve el nombre completo de la tabla', 'wp_av_enrollments' === $repository->table() );

$values = array();
$where  = $repository->build_where(
	array(
		'edition_id' => 7,
		'status'     => array( 'active', 'completed' ),
		'notes'      => null,
	),
	$values
);
check(
	'construye igualdades, IN y IS NULL con placeholders',
	' WHERE edition_id = %d AND status IN (%s, %s) AND notes IS NULL' === $where
);
check( 'recoge solo los valores con placeholder', array( 7, 'active', 'completed' ) === $values );

$empty_values = array();
check( 'una lista vacia no devuelve filas', str_contains( $repository->build_where( array( 'status' => array() ), $empty_values ), '1 = 0' ) );

$rejected = false;
try {
	$ignored = array();
	$repository->build_where( array( 'status; DROP TABLE wp_users' => 'x' ), $ignored );
} catch ( InvalidArgumentException $e ) {
	$rejected = true;
}
check( 'rechaza columnas no declaradas', $rejected );

check( 'ignora un ORDER BY no declarado', ' ORDER BY id DESC' === $repository->build_order( 'notes; DROP TABLE', 'DESC' ) );
check( 'acepta un ORDER BY declarado', ' ORDER BY status ASC' === $repository->build_order( 'status', 'asc' ) );
check(
	'mapea los placeholders de escritura y descarta columnas ajenas',
	array( '%d', '%s' ) === $repository->build_formats(
		array(
			'user_id'  => 4,
			'status'   => 'active',
			'is_admin' => true,
		)
	)
);

echo "\nSanitizer\n";
check( 'limita un enum a los valores permitidos', 'all' === Sanitizer::enum( 'hacker', array( 'beginner', 'all' ), 'all' ) );
check( 'acepta un enum valido', 'beginner' === Sanitizer::enum( 'beginner', array( 'beginner', 'all' ), 'all' ) );
check( 'descarta html en texto plano', 'alert(1)' === Sanitizer::text( '<script>alert(1)</script>' ) );
check( 'normaliza fechas a formato MySQL', '2026-07-01 09:30:00' === Sanitizer::datetime( '2026-07-01 09:30:00' ) );
check( 'devuelve null ante una fecha invalida', null === Sanitizer::datetime( 'no es una fecha' ) );
check( 'filtra listas de texto vacias', array( 'Disenar' ) === Sanitizer::text_list( array( 'Disenar', '', '  ' ) ) );
check( 'descarta emails invalidos', '' === Sanitizer::email( 'no-es-email' ) );
check( 'acepta emails validos', 'docente@universidad.pe' === Sanitizer::email( 'docente@universidad.pe' ) );
check(
	'exige pregunta en cada FAQ',
	array( array( 'question' => 'Cuando inicia?', 'answer' => 'En julio.' ) ) === Sanitizer::faq_list(
		array(
			array( 'question' => 'Cuando inicia?', 'answer' => 'En julio.' ),
			array( 'question' => '', 'answer' => 'Huerfana.' ),
		)
	)
);

echo "\nCapabilities\n";
$all = Capabilities::all();
check( 'no repite capacidades', count( $all ) === count( array_unique( $all ) ) );
check( 'el administrador LMS tiene todas las capacidades', array() === array_diff( $all, Capabilities::administrator_capabilities() ) );

$instructor = Capabilities::instructor_capabilities();
check( 'el instructor no administra el LMS', ! in_array( Capabilities::MANAGE_LMS, $instructor, true ) );
check( 'el instructor no modifica precios', ! in_array( Capabilities::MANAGE_PRICES, $instructor, true ) );
check( 'el instructor no toca WooCommerce', ! in_array( Capabilities::MANAGE_COMMERCE, $instructor, true ) );
check( 'el instructor gestiona su temario', in_array( Capabilities::MANAGE_CURRICULUM, $instructor, true ) );
check( 'el instructor no edita cursos ajenos', ! in_array( 'edit_others_av_courses', $instructor, true ) );

$student = Capabilities::student_capabilities();
check( 'el alumno solo entra al campus', array( 'read', Capabilities::ACCESS_CAMPUS ) === $student );

echo "\nEditionStatus\n";
check( 'sin limites la ventana esta abierta', EditionStatus::window_is_open( null, null, '2027-07-01 10:00:00' ) );
check( 'antes de abrir esta cerrada', ! EditionStatus::window_is_open( '2027-07-01 00:00:00', null, '2027-06-30 23:59:59' ) );
check( 'dentro de la ventana esta abierta', EditionStatus::window_is_open( '2027-07-01 00:00:00', '2027-12-31 23:59:59', '2027-08-15 12:00:00' ) );
check( 'despues de cerrar esta cerrada', ! EditionStatus::window_is_open( null, '2027-12-31 23:59:59', '2028-01-01 00:00:01' ) );
check( 'una cadena vacia no cuenta como limite', EditionStatus::window_is_open( '', '', '2027-07-01 10:00:00' ) );
check( 'una edicion en curso admite matriculas tardias', EditionStatus::accepts_enrollments( EditionStatus::RUNNING ) );
check( 'un borrador no admite matriculas', ! EditionStatus::accepts_enrollments( EditionStatus::DRAFT ) );
check( 'una edicion finalizada no admite matriculas', ! EditionStatus::accepts_enrollments( EditionStatus::FINISHED ) );

echo "\nEnrollmentStatus\n";
check( 'una matricula activa da acceso', EnrollmentStatus::grants_access( EnrollmentStatus::ACTIVE ) );
check( 'terminar el curso no quita el acceso', EnrollmentStatus::grants_access( EnrollmentStatus::COMPLETED ) );
check( 'una matricula pendiente no da acceso', ! EnrollmentStatus::grants_access( EnrollmentStatus::PENDING ) );
check( 'una matricula suspendida no da acceso', ! EnrollmentStatus::grants_access( EnrollmentStatus::SUSPENDED ) );
check( 'una matricula cancelada no ocupa plaza', ! in_array( EnrollmentStatus::CANCELLED, EnrollmentStatus::occupying_seat(), true ) );
check( 'una matricula pendiente si ocupa plaza', in_array( EnrollmentStatus::PENDING, EnrollmentStatus::occupying_seat(), true ) );

echo "\nProgressCalculator\n";
check( 'cero de doce es 0%', 0.0 === ProgressCalculator::percentage( 0, 12 ) );
check( 'tres de doce es 25%', 25.0 === ProgressCalculator::percentage( 3, 12 ) );
check( 'uno de tres redondea a dos decimales', 33.33 === ProgressCalculator::percentage( 1, 3 ) );
check( 'doce de doce es 100%', 100.0 === ProgressCalculator::percentage( 12, 12 ) );
check( 'una edicion sin sesiones es 0%, no 100%', 0.0 === ProgressCalculator::percentage( 0, 0 ) );
check( 'nunca pasa de 100% aunque sobren completadas', 100.0 === ProgressCalculator::percentage( 20, 12 ) );
check( 'una edicion vacia no esta completada', ! ProgressCalculator::is_complete( 0, 0 ) );
check( 'todas las sesiones completadas cierra el curso', ProgressCalculator::is_complete( 12, 12 ) );

echo "\nLessonType\n";
check( 'los tipos disponibles son un subconjunto de los aceptados', array() === array_diff( array_keys( LessonType::available() ), LessonType::all() ) );
check( 'quiz esta aceptado en la columna aunque no este construido', in_array( LessonType::QUIZ, LessonType::all(), true ) );
check( 'quiz no se ofrece todavia en el formulario', ! array_key_exists( LessonType::QUIZ, LessonType::available() ) );

echo "\nCableado de modulos\n";
$plugin_container = Plugin::instance()->container();

foreach (
	array(
		new PermissionsServiceProvider(),
		new CoursesServiceProvider(),
		new EditionsServiceProvider(),
		new CurriculumServiceProvider(),
		new EnrollmentsServiceProvider(),
		new ProgressServiceProvider(),
		new AdminServiceProvider(),
		new CampusServiceProvider(),
	) as $provider
) {
	$provider->register( $plugin_container );
}

$wiring_error = '';
try {
	$campus = $plugin_container->get( CampusController::class );
	$screen = $plugin_container->get( EditionsScreen::class );
} catch ( Throwable $e ) {
	$wiring_error = $e->getMessage();
	$campus       = null;
	$screen       = null;
}

check( 'el grafo de dependencias del campus se resuelve entero', $campus instanceof CampusController, );
check( 'el grafo de dependencias del admin se resuelve entero', $screen instanceof EditionsScreen );
check( 'ningun servicio quedo sin registrar', '' === $wiring_error );

echo "\n{$av_checks} comprobaciones, {$av_failures} fallos\n";

exit( $av_failures > 0 ? 1 : 0 );
