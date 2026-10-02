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
use SIQA\AulaVirtual\Campus\LoginBranding;
use SIQA\AulaVirtual\Core\Container;
use SIQA\AulaVirtual\Core\Plugin;
use SIQA\AulaVirtual\Courses\CoursesServiceProvider;
use SIQA\AulaVirtual\Curriculum\CurriculumServiceProvider;
use SIQA\AulaVirtual\Curriculum\LessonType;
use SIQA\AulaVirtual\Editions\EditionStatus;
use SIQA\AulaVirtual\Editions\EditionsServiceProvider;
use SIQA\AulaVirtual\Emails\EmailDefaults;
use SIQA\AulaVirtual\Emails\EmailsServiceProvider;
use SIQA\AulaVirtual\Emails\TemplateRenderer;
use SIQA\AulaVirtual\Emails\VariableResolver;
use SIQA\AulaVirtual\Admin\AdminMenu;
use SIQA\AulaVirtual\Admin\SettingsScreen;
use SIQA\AulaVirtual\Admin\ImportScreen;
use SIQA\AulaVirtual\Comments\CommentService;
use SIQA\AulaVirtual\Certificates\CertificateController;
use SIQA\AulaVirtual\Certificates\CertificateService;
use SIQA\AulaVirtual\Certificates\CertificatesServiceProvider;
use SIQA\AulaVirtual\Reports\ReportService;
use SIQA\AulaVirtual\Reports\ReportsServiceProvider;
use SIQA\AulaVirtual\Admin\ReportsScreen;
use SIQA\AulaVirtual\Admin\CertificatesScreen;
use SIQA\AulaVirtual\Core\Events\Events;
use SIQA\AulaVirtual\Curriculum\LessonService;
use SIQA\AulaVirtual\Enrollments\RegistrationService;
use SIQA\AulaVirtual\Security\RateLimiter;
use SIQA\AulaVirtual\REST\AbstractController;
use SIQA\AulaVirtual\REST\EditionsController;
use SIQA\AulaVirtual\REST\EnrollmentsController;
use SIQA\AulaVirtual\REST\RegistrationsController;
use SIQA\AulaVirtual\REST\RestServiceProvider;
use SIQA\AulaVirtual\Comments\CommentsServiceProvider;
use SIQA\AulaVirtual\Curriculum\ReleaseSchedule;
use SIQA\AulaVirtual\Materials\DownloadController;
use SIQA\AulaVirtual\Courses\CourseDuplicator;
use SIQA\AulaVirtual\Editions\EditionDuplicator;
use SIQA\AulaVirtual\Imports\ImportParser;
use SIQA\AulaVirtual\Imports\ImportService;
use SIQA\AulaVirtual\Imports\ImportsServiceProvider;
use SIQA\AulaVirtual\Announcements\AnnouncementService;
use SIQA\AulaVirtual\Announcements\AnnouncementsServiceProvider;
use SIQA\AulaVirtual\Videos\VideoEmbed;
use SIQA\AulaVirtual\Admin\LessonScreen;
use SIQA\AulaVirtual\LiveClasses\LiveClassService;
use SIQA\AulaVirtual\LiveClasses\LiveClassesServiceProvider;
use SIQA\AulaVirtual\Materials\MaterialService;
use SIQA\AulaVirtual\Materials\MaterialsServiceProvider;
use SIQA\AulaVirtual\Landing\LandingData;
use SIQA\AulaVirtual\Landing\LandingRenderer;
use SIQA\AulaVirtual\Landing\LandingServiceProvider;
use SIQA\AulaVirtual\Landing\LandingTemplate;
use SIQA\AulaVirtual\Migration\MigrationServiceProvider;
use SIQA\AulaVirtual\Migration\TutorMapping;
use SIQA\AulaVirtual\Migration\TutorMigrator;
use SIQA\AulaVirtual\WooCommerce\OrderHandler;
use SIQA\AulaVirtual\WooCommerce\Settings as WooSettings;
use SIQA\AulaVirtual\WooCommerce\WooCommerceServiceProvider;
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
check( 'declara las 16 tablas del MVP', 16 === count( $definitions ) );
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
		new CertificatesServiceProvider(),
		new LiveClassesServiceProvider(),
		new MaterialsServiceProvider(),
		new AnnouncementsServiceProvider(),
		new ReportsServiceProvider(),
		new CommentsServiceProvider(),
		new EmailsServiceProvider(),
		new WooCommerceServiceProvider(),
		new MigrationServiceProvider(),
		new LandingServiceProvider(),
		new ImportsServiceProvider(),
		new \SIQA\AulaVirtual\Students\StudentsServiceProvider(),
		new AdminServiceProvider(),
		new CampusServiceProvider(),
		new RestServiceProvider(),
	) as $provider
) {
	$provider->register( $plugin_container );
}

$wiring_error = '';
try {
	$campus = $plugin_container->get( CampusController::class );
	$screen = $plugin_container->get( EditionsScreen::class );
	$menu   = $plugin_container->get( AdminMenu::class );
} catch ( Throwable $e ) {
	$wiring_error = $e->getMessage();
	$campus       = null;
	$screen       = null;
	$menu         = null;
}

check( 'el grafo de dependencias del campus se resuelve entero', $campus instanceof CampusController );
check( 'el grafo de dependencias del admin se resuelve entero', $screen instanceof EditionsScreen );
check( 'el menu resuelve solicitudes y emails con sus servicios', $menu instanceof AdminMenu );
check( 'ningun servicio quedo sin registrar', '' === $wiring_error );

$woo_error = '';
try {
	$orders = $plugin_container->get( OrderHandler::class );
} catch ( Throwable $e ) {
	$woo_error = $e->getMessage();
	$orders    = null;
}
check( 'el manejador de pedidos de WooCommerce resuelve sus dependencias', $orders instanceof OrderHandler && '' === $woo_error );
check( 'sin WooCommerce cargado el modulo no engancha nada', ! WooCommerceServiceProvider::is_active() );

$migration_error = '';
try {
	$migrator = $plugin_container->get( TutorMigrator::class );
} catch ( Throwable $e ) {
	$migration_error = $e->getMessage();
	$migrator        = null;
}
check( 'el migrador de Tutor LMS resuelve sus diez dependencias', $migrator instanceof TutorMigrator && '' === $migration_error );

$landing_error = '';
try {
	$landing_template = $plugin_container->get( LandingTemplate::class );
} catch ( Throwable $e ) {
	$landing_error    = $e->getMessage();
	$landing_template = null;
}
check( 'la landing resuelve renderer y plantilla', $landing_template instanceof LandingTemplate && '' === $landing_error );

$lesson_error = '';
try {
	$lesson_screen = $plugin_container->get( LessonScreen::class );
} catch ( Throwable $e ) {
	$lesson_error  = $e->getMessage();
	$lesson_screen = null;
}
check( 'el editor de sesion resuelve clase en vivo y materiales', $lesson_screen instanceof LessonScreen && '' === $lesson_error );

$dup_error = '';
try {
	$edition_dup = $plugin_container->get( EditionDuplicator::class );
	$course_dup  = $plugin_container->get( CourseDuplicator::class );
	$import_scr  = $plugin_container->get( ImportScreen::class );
} catch ( Throwable $e ) {
	$dup_error   = $e->getMessage();
	$edition_dup = null;
	$course_dup  = null;
	$import_scr  = null;
}
check( 'el duplicador de ediciones resuelve modulos, materiales y clases en vivo', $edition_dup instanceof EditionDuplicator && '' === $dup_error );
check( 'el duplicador de cursos resuelve sobre el de ediciones', $course_dup instanceof CourseDuplicator );
check( 'la pantalla de importacion resuelve servicio y trabajos', $import_scr instanceof ImportScreen );

echo "\nDuplicar ediciones\n";
check( 'sin fecha nueva no hay desplazamiento', 0 === EditionDuplicator::day_shift( '2026-03-01 19:00:00', null ) );
check( 'el desplazamiento se mide en dias enteros', 30 === EditionDuplicator::day_shift( '2026-03-01 19:00:00', '2026-03-31 19:00:00' ) );
check( 'un origen sin fecha no desplaza nada', 0 === EditionDuplicator::day_shift( null, '2026-03-31 19:00:00' ) );
check( 'las fechas se desplazan conservando la hora', '2026-04-05 20:30:00' === EditionDuplicator::shift_date( '2026-03-06 20:30:00', 30 ) );
check( 'una fecha vacia sigue vacia', null === EditionDuplicator::shift_date( '', 30 ) );

echo "\nImportacion de alumnos\n";
$csv = ImportParser::parse_csv( "\xEF\xBB\xBFCorreo;Nombre;Apellido\nana@example.test;Ana;Cori\n\"luis@example.test\";Luis;\"Cruz, A\"\n" );
check( 'el CSV con BOM y punto y coma se lee', array( 'Correo', 'Nombre', 'Apellido' ) === $csv[0] && 3 === count( $csv ) );
check( 'las comillas se respetan', 'Cruz, A' === $csv[2][2] );
$csv_comma = ImportParser::parse_csv( "email,first name\nx@example.test,X\n" );
check( 'la coma tambien se detecta como separador', array( 'email', 'first name' ) === $csv_comma[0] );
$guess = ImportParser::guess_mapping( array( 'Correo electrónico', 'Nombres', 'Apellidos', 'Celular', 'DNI' ) );
check( 'las cabeceras en espanol se reconocen', 0 === $guess['email'] && 1 === $guess['first_name'] && 2 === $guess['last_name'] && 3 === $guess['phone'] && 4 === $guess['document'] );
$guess_en = ImportParser::guess_mapping( array( 'First Name', 'E-mail', 'Last name' ) );
check( 'las cabeceras en ingles se reconocen', 1 === $guess_en['email'] && 0 === $guess_en['first_name'] && 2 === $guess_en['last_name'] );
$mapped = ImportService::map_rows( array( array( ' ANA@Example.test ', 'Ana', 'Cori' ), array( '', '', '' ) ), array( 'email' => 0, 'first_name' => 1, 'last_name' => 2 ) );
check( 'el correo se normaliza en minusculas y sin espacios', 'ana@example.test' === $mapped[0]['email'] );
check( 'la linea se conserva para informar errores', 2 === $mapped[0]['line'] );
check( 'las filas vacias se descartan', 1 === count( $mapped ) );

$comments_error = '';
try {
	$comment_service = $plugin_container->get( CommentService::class );
	$download        = $plugin_container->get( DownloadController::class );
} catch ( Throwable $e ) {
	$comments_error  = $e->getMessage();
	$comment_service = null;
	$download        = null;
}
check( 'el servicio de comentarios resuelve sus dependencias', $comment_service instanceof CommentService && '' === $comments_error );
check( 'el endpoint de descarga resuelve materiales y matriculas', $download instanceof DownloadController );

echo "\nLiberacion programada\n";
$enrol = array( 'enrolled_at' => '2026-03-01 10:00:00' );
check( 'inmediata: siempre disponible', null === ReleaseSchedule::available_at( array( 'release_type' => 'immediate' ), $enrol ) );
check( 'por fecha: se abre en esa fecha', '2026-04-01 00:00:00' === ReleaseSchedule::available_at( array( 'release_type' => 'date', 'release_date' => '2026-04-01 00:00:00' ), null ) );
check( 'por fecha vacia: disponible', null === ReleaseSchedule::available_at( array( 'release_type' => 'date', 'release_date' => '' ), null ) );
check( 'por dias: se suma a la fecha de matricula', '2026-03-08 10:00:00' === ReleaseSchedule::available_at( array( 'release_type' => 'offset', 'release_offset' => 7 ), $enrol ) );
check( 'por dias sin matricula: disponible', null === ReleaseSchedule::available_at( array( 'release_type' => 'offset', 'release_offset' => 7 ), null ) );
check( 'antes de la fecha esta bloqueada', ! ReleaseSchedule::is_available( array( 'release_type' => 'date', 'release_date' => '2026-04-01 00:00:00' ), null, '2026-03-31 23:59:59' ) );
check( 'en la fecha exacta se abre', ReleaseSchedule::is_available( array( 'release_type' => 'date', 'release_date' => '2026-04-01 00:00:00' ), null, '2026-04-01 00:00:00' ) );

echo "\nURLs limpias del campus\n";
$rules = CampusController::rewrite_rules( 'aula', 12 );
check( 'tres reglas para la pagina del campus', 3 === count( $rules ) );
check( 'la regla de curso apunta a la pagina con el codigo', 'index.php?page_id=12&av_edicion=$matches[1]' === ( $rules['^aula/curso/([a-z0-9_-]+)/?$'] ?? '' ) );
check( 'una pagina anidada se escapa en la regla', isset( CampusController::rewrite_rules( 'campus/aula', 12 )['^campus/aula/sesion/([0-9]+)/?$'] ) );
check( 'sin pagina no hay reglas', array() === CampusController::rewrite_rules( '', 0 ) );
check( 'la URL de edicion usa el codigo si se conoce', 'curso/numerologia-julio-2027/' === CampusController::pretty_path( array( 'av_edicion' => 5 ), array( 5 => 'numerologia-julio-2027' ) ) );
check( 'la URL de edicion usa el id si no hay codigo', 'curso/5/' === CampusController::pretty_path( array( 'av_edicion' => 5 ) ) );
check( 'la URL de sesion y perfil', 'sesion/9/' === CampusController::pretty_path( array( 'av_leccion' => 9 ) ) && 'perfil/' === CampusController::pretty_path( array( 'av_perfil' => 1 ) ) );
check( 'argumentos combinados no tienen forma limpia', null === CampusController::pretty_path( array( 'av_leccion' => 9, 'x' => 1 ) ) );
check( 'las query vars del campus se anaden', in_array( 'av_edicion', CampusController::query_vars( array( 'p' ) ), true ) );

echo "\nComentarios\n";
check( 'el texto se recorta y se limpia', 'Hola  mundo' === CommentService::sanitize_content( "  Hola  mundo <script>x</script>  " ) || 'Hola  mundo x' === CommentService::sanitize_content( "  Hola  mundo <script>x</script>  " ) );
check( 'no pasa de 2000 caracteres', 2000 === mb_strlen( CommentService::sanitize_content( str_repeat( 'a', 2500 ) ) ) );
check( 'los saltos de linea repetidos se compactan', "a\n\nb" === CommentService::sanitize_content( "a\n\n\n\n\nb" ) );
check( 'la URL de descarga pasa por admin-post', str_contains( DownloadController::url( 7 ), 'action=av_download' ) && str_contains( DownloadController::url( 7 ), 'material=7' ) );

$more_error = '';
try {
	$reports_screen = $plugin_container->get( ReportsScreen::class );
	$certs_screen   = $plugin_container->get( CertificatesScreen::class );
	$cert_service   = $plugin_container->get( CertificateService::class );
} catch ( Throwable $e ) {
	$more_error     = $e->getMessage();
	$reports_screen = null;
	$certs_screen   = null;
	$cert_service   = null;
}
check( 'la pantalla de reportes resuelve repositorio y servicio', $reports_screen instanceof ReportsScreen && '' === $more_error );
check( 'la pantalla de certificados y su servicio resuelven', $certs_screen instanceof CertificatesScreen && $cert_service instanceof CertificateService );

echo "\nReportes\n";
$csv = ReportService::to_csv( array( 'A', 'B' ), array( array( 'x;y', 'plain' ), array( 'con "comillas"', '' ) ) );
check( 'el CSV empieza con BOM UTF-8', str_starts_with( $csv, "\xEF\xBB\xBF" ) );
check( 'el CSV usa punto y coma', str_contains( $csv, "A;B\r\n" ) );
check( 'los valores con punto y coma van entre comillas', str_contains( $csv, '"x;y";plain' ) );
check( 'las comillas dobles se escapan', str_contains( $csv, '"con ""comillas""";' ) );
check( 'una fecha vacia queda vacia', '' === ReportService::format_date( '0000-00-00 00:00:00' ) );
check( 'las fechas salen como Y-m-d H:i', '2026-03-05 14:07' === ReportService::format_date( '2026-03-05 14:07:33' ) );

echo "\nCertificados\n";
check( 'un codigo de certificado tiene el formato AV-AAAA-XXXXXX', CertificateService::is_valid_code( 'AV-2026-K7Q2ZM' ) );
check( 'un codigo en minusculas no pasa sin normalizar', ! CertificateService::is_valid_code( 'av-2026-k7q2zm' ) );
check( 'normalize_code limpia y pone en mayusculas', 'AV-2026-K7Q2ZM' === CertificateService::normalize_code( ' av-2026-k7q2zm ' ) );
check( 'la emision automatica esta activa por defecto', CertificateService::auto_issue_enabled() );
check( 'la query var del certificado se registra', in_array( CertificateController::QUERY_VAR, CertificateController::query_vars( array() ), true ) );
check( 'las plantillas de correo de comentarios existen', 2 === count( array_filter( EmailDefaults::definitions(), static fn( array $d ): bool => Events::COMMENT_POSTED === $d['event'] ) ) );

echo "\nAPI REST\n";
$rest_error = '';
try {
	$editions_api = $plugin_container->get( EditionsController::class );
	$regs_api     = $plugin_container->get( RegistrationsController::class );
	$enr_api      = $plugin_container->get( EnrollmentsController::class );
} catch ( Throwable $e ) {
	$rest_error   = $e->getMessage();
	$editions_api = null;
	$regs_api     = null;
	$enr_api      = null;
}
check( "los tres controladores REST resuelven sus dependencias: " . $rest_error, $editions_api instanceof EditionsController && $regs_api instanceof RegistrationsController && $enr_api instanceof EnrollmentsController && '' === $rest_error );
$ed_params = $editions_api->get_collection_params();
check( 'el listado de ediciones filtra por curso, estado y abiertas', isset( $ed_params['course'], $ed_params['status'], $ed_params['open_only'] ) );
check( 'el esquema de edicion enumera los estados reales', EditionStatus::all() === ( $editions_api->get_item_schema()['properties']['status']['enum'] ?? null ) );
check( 'el esquema de matricula enumera estados y origenes', EnrollmentStatus::all() === ( $enr_api->get_item_schema()['properties']['status']['enum'] ?? null ) && EnrollmentStatus::sources() === ( $enr_api->get_item_schema()['properties']['source']['enum'] ?? null ) );
check( 'el limite de inscripciones por IP es 20 por hora', 20 === RegistrationsController::RATE_LIMIT && 'api' === RegistrationsController::LINK_LABEL );
check( 'la clave de integracion viaja en X-AV-Key', 'av_integration_key' === AbstractController::OPTION_INTEGRATION_KEY && 'X-AV-Key' === AbstractController::HEADER_INTEGRATION_KEY );

echo "\nSeguridad de correos\n";
$escaped = VariableResolver::escape_all( array( 'first_name' => '<script>x</script>Ana', 'course_url' => 'javascript:alert(1)', 'comment_content' => 'a<br />b', 'lesson_url' => 'https://example.test/aula/sesion/1/' ) );
check( 'los nombres se escapan antes de entrar al HTML del correo', '&lt;script&gt;x&lt;/script&gt;Ana' === $escaped['first_name'] );
check( 'una URL javascript: se descarta', '' === $escaped['course_url'] );
check( 'el contenido del comentario conserva su HTML ya saneado', 'a<br />b' === $escaped['comment_content'] );
check( 'las URLs validas se conservan', 'https://example.test/aula/sesion/1/' === $escaped['lesson_url'] );

echo "\nSeguridad\n";
$uninitialised = array();
foreach ( array( CampusController::class, EditionsScreen::class, LessonScreen::class, ImportScreen::class, ReportsScreen::class, CertificatesScreen::class, SettingsScreen::class, AdminMenu::class, CommentService::class, CertificateService::class, DownloadController::class, EditionsController::class, RegistrationsController::class, EnrollmentsController::class ) as $av_class ) {
	try {
		$av_obj = $plugin_container->get( $av_class );
	} catch ( Throwable $e ) {
		$uninitialised[] = $av_class . ': ' . $e->getMessage();
		continue;
	}
	foreach ( ( new ReflectionObject( $av_obj ) )->getProperties() as $av_prop ) {
		if ( $av_prop->hasType() && ! $av_prop->isStatic() && ! $av_prop->isInitialized( $av_obj ) ) {
			$uninitialised[] = $av_class . '::$' . $av_prop->getName();
		}
	}
}
check( 'ningun servicio queda con propiedades sin inicializar: ' . implode( ', ', $uninitialised ), array() === $uninitialised );
check( 'solo se aceptan shortcodes de video permitidos', '[video src="https://example.test/a.mp4"]' === LessonService::allowed_shortcode( '[video src="https://example.test/a.mp4"]' ) );
check( 'un shortcode de checkout se rechaza', '' === LessonService::allowed_shortcode( '[woocommerce_checkout]' ) );
check( 'dos shortcodes encadenados se rechazan', '' === LessonService::allowed_shortcode( '[video src="x"][woocommerce_my_account]' ) );
check( 'un shortcode con cierre permitido pasa', '[embed]https://youtu.be/x[/embed]' === LessonService::allowed_shortcode( '[embed]https://youtu.be/x[/embed]' ) );
$GLOBALS['pagenow'] = 'admin-post.php';
check( 'admin-post.php se reconoce para no bloquear el campus', PermissionsServiceProvider::is_admin_post_request() );
$GLOBALS['pagenow'] = 'index.php';
$plain = VariableResolver::plain_all( array( 'first_name' => "O'Brien &amp; Cia", 'comment_content' => "a<br />\nb" ) );
check( 'el asunto del correo sale en texto plano', "O'Brien & Cia" === $plain['first_name'] && 'a b' === $plain['comment_content'] );

check( 'una matricula cancelada puede reactivarse', EnrollmentStatus::can_transition( 'cancelled', 'active' ) );
check( 'una rechazada no vuelve a ningun estado', ! EnrollmentStatus::can_transition( 'rejected', 'active' ) );
check( 'no se salta de pendiente a completada', ! EnrollmentStatus::can_transition( 'pending', 'completed' ) );
check( 'el reembolso puede suspender una completada', EnrollmentStatus::can_transition( 'completed', 'suspended' ) );
check( 'todas las transiciones apuntan a estados reales', array() === array_diff( array_merge( ...array_values( EnrollmentStatus::transitions() ) ), EnrollmentStatus::all() ) && array() === array_diff( EnrollmentStatus::all(), array_keys( EnrollmentStatus::transitions() ) ) );
check( 'el CSV neutraliza formulas de Excel', "'=HYPERLINK(1)" === ReportService::neutralize( '=HYPERLINK(1)' ) && "'+51 955" === ReportService::neutralize( '+51 955' ) && 'Ana' === ReportService::neutralize( 'Ana' ) );
check( 'el export aplica la neutralizacion', str_contains( ReportService::to_csv( array( 'N' ), array( array( '=cmd' ) ) ), "'=cmd" ) );
check( 'los errores del formulario salen de una lista cerrada', 'Indica tu nombre y apellido.' === RegistrationService::error_message( 'av_missing_name' ) && ! str_contains( RegistrationService::error_message( '<b>texto inyectado</b>' ), 'inyectado' ) );
check( 'el limitador corta al superar el limite', RateLimiter::hit( 't1', 2 ) && RateLimiter::hit( 't1', 2 ) && ! RateLimiter::hit( 't1', 2 ) && RateLimiter::exceeded( 't1', 2 ) );
check( 'el alias con + no multiplica el limite por correo', 'ana@example.test' === RateLimiter::email_bucket( 'Ana+spam7@Example.test' ) );
check( 'un codigo de certificado mal formado no llega a la base de datos', null === $cert_service->verify( "AV-2026-K7Q2ZM' OR 1=1" ) );

echo "\nDirecciones de inscripcion y pantalla de edicion\n";
check( 'una direccion legible valida se acepta', RegistrationService::is_valid_slug( 'numbasica-set2026' ) );
check( 'direcciones demasiado cortas, con mayusculas o guion al borde se rechazan', ! RegistrationService::is_valid_slug( 'ab' ) && ! RegistrationService::is_valid_slug( 'Numbasica' ) && ! RegistrationService::is_valid_slug( '-set2026' ) && ! RegistrationService::is_valid_slug( 'set2026-' ) );
check( 'la direccion se normaliza a minusculas con guiones', 'numerologia-setiembre' === RegistrationService::normalize_slug( 'Numerologia Setiembre' ) );
check( 'el token limpio conserva guiones y tokens aleatorios', 'numbasica-set2026' === RegistrationService::clean_token( 'numbasica-set2026/' ) && '9mVtDFbNYlkTiMENrpMx' === RegistrationService::clean_token( '9mVtDFbNYlkTiMENrpMx' ) );
check( 'la sugerencia usa el codigo y numera si ya existe', 'numbasica-set2026' === EditionsScreen::suggest_slug( 'numbasica-set2026', array( 'abc' ) ) && 'numbasica-set2026-3' === EditionsScreen::suggest_slug( 'numbasica-set2026', array( 'numbasica-set2026', 'numbasica-set2026-2' ) ) );
$av_stats = EditionsScreen::stats(
	array(
		array( 'status' => 'active', 'progress_percentage' => 50 ),
		array( 'status' => 'completed', 'progress_percentage' => 100 ),
		array( 'status' => 'cancelled', 'progress_percentage' => 10 ),
	),
	array( array( 'status' => 'publish' ), array( 'status' => 'draft' ) ),
	4
);
check( 'las cifras de la edicion excluyen matriculas canceladas', 2 === $av_stats['seats_taken'] && 75.0 === $av_stats['avg_progress'] && 1 === $av_stats['completed'] && 1 === $av_stats['published_lessons'] && 4 === $av_stats['pending_requests'] );
check( 'la pantalla conoce sus cuatro pestanas', array( 'sesiones', 'alumnos', 'inscripcion', 'ajustes' ) === EditionsScreen::TABS );

echo "\nClases en vivo\n";
check( 'antes de la ventana el boton no aparece', LiveClassService::WINDOW_BEFORE === LiveClassService::window_state( '2027-07-01 19:00:00', '2027-07-01 21:00:00', 15, 30, '2027-07-01 18:44:59' ) );
check( '15 minutos antes ya se puede entrar', LiveClassService::WINDOW_OPEN === LiveClassService::window_state( '2027-07-01 19:00:00', '2027-07-01 21:00:00', 15, 30, '2027-07-01 18:45:00' ) );
check( 'durante la clase se puede entrar', LiveClassService::WINDOW_OPEN === LiveClassService::window_state( '2027-07-01 19:00:00', '2027-07-01 21:00:00', 15, 30, '2027-07-01 20:10:00' ) );
check( '30 minutos despues del fin todavia se puede entrar', LiveClassService::WINDOW_OPEN === LiveClassService::window_state( '2027-07-01 19:00:00', '2027-07-01 21:00:00', 15, 30, '2027-07-01 21:30:00' ) );
check( 'pasado el margen posterior el boton desaparece', LiveClassService::WINDOW_AFTER === LiveClassService::window_state( '2027-07-01 19:00:00', '2027-07-01 21:00:00', 15, 30, '2027-07-01 21:30:01' ) );
check( 'las 19:00 de Lima son las 00:00 UTC del dia siguiente', '2027-07-02 00:00:00' === LiveClassService::to_utc( '2027-07-01 19:00', 'America/Lima' ) );
check( 'la conversion inversa devuelve la hora local', '2027-07-01 19:00' === LiveClassService::to_local( '2027-07-02 00:00:00', 'America/Lima' ) );
check( 'las 19:00 de Madrid en julio son las 17:00 UTC (horario de verano)', '2027-07-01 17:00:00' === LiveClassService::to_utc( '2027-07-01 19:00', 'Europe/Madrid' ) );
check( 'una fecha vacia no se convierte', null === LiveClassService::to_utc( '', 'America/Lima' ) );
check( 'una zona horaria invalida no rompe', null === LiveClassService::to_utc( '2027-07-01 19:00', 'Marte/Olympus' ) );

$ann_error = '';
try {
	$ann = $plugin_container->get( AnnouncementService::class );
} catch ( Throwable $e ) {
	$ann_error = $e->getMessage();
	$ann       = null;
}
check( 'el servicio de anuncios resuelve sus dependencias', $ann instanceof AnnouncementService && '' === $ann_error );

echo "\nConfiguracion\n";
$all_fields = array();
foreach ( SettingsScreen::tabs() as $tab ) {
	foreach ( $tab['fields'] as $option => $field ) {
		$all_fields[ $option ] = $field;
	}
}
check( 'cada opcion aparece en una sola pestana', count( $all_fields ) === array_sum( array_map( static fn( array $t ): int => count( $t['fields'] ), SettingsScreen::tabs() ) ) );
check( 'todas las opciones llevan prefijo av_', array() === array_filter( array_keys( $all_fields ), static fn( string $o ): bool => ! str_starts_with( $o, 'av_' ) ) );
check( 'un color invalido vuelve al valor por defecto', '#1d4ed8' === SettingsScreen::sanitize_field( $all_fields['av_brand_color'], 'rojo' ) );
check( 'un color valido se guarda en minusculas', '#3e64de' === SettingsScreen::sanitize_field( $all_fields['av_brand_color'], '#3E64DE' ) );
check( 'un slug se normaliza', 'cursos' === SettingsScreen::sanitize_field( $all_fields['av_course_slug'], ' Cursos ' ) );
check( 'un slug vacio vuelve al valor por defecto', 'curso' === SettingsScreen::sanitize_field( $all_fields['av_course_slug'], '' ) );
check( 'el TTL del token respeta el minimo', 300 === SettingsScreen::sanitize_field( $all_fields['av_bunny_token_ttl'], '5' ) );
check( 'un disparador de Woo desconocido vuelve a procesando', 'processing' === SettingsScreen::sanitize_field( $all_fields['av_wc_enroll_status'], 'pagado' ) );
check( 'un correo invalido queda vacio', '' === SettingsScreen::sanitize_field( $all_fields['av_admin_notification_email'], 'no-es-correo' ) );
check( 'un booleano acepta "1" y "on"', true === SettingsScreen::sanitize_field( $all_fields['av_block_wp_admin'], '1' ) && true === SettingsScreen::sanitize_field( $all_fields['av_block_wp_admin'], 'on' ) );
check( 'la plantilla de anuncio usa solo variables del catalogo', array() === array_diff( TemplateRenderer::placeholders( EmailDefaults::definitions()[5]['body'] ), array_keys( VariableResolver::catalogue() ) ) );

echo "\nVideos\n";
check( 'detecta YouTube', 'youtube' === VideoEmbed::detect( 'https://youtu.be/dQw4w9WgXcQ' ) );
check( 'detecta Vimeo', 'vimeo' === VideoEmbed::detect( 'https://vimeo.com/123456' ) );
check( 'detecta Bunny por su dominio de embed', 'bunny' === VideoEmbed::detect( 'https://iframe.mediadelivery.net/embed/12345/abcdef12-1234-1234-1234-abcdef123456' ) );
check( 'detecta Bunny por el CDN', 'bunny' === VideoEmbed::detect( 'https://vz-abc.b-cdn.net/guid/playlist.m3u8' ) );
check( 'detecta un MP4 directo', 'html5' === VideoEmbed::detect( 'https://cdn.test/clase.mp4?x=1' ) );
check( 'detecta codigo incrustado', 'embed' === VideoEmbed::detect( '<iframe src="https://player.vimeo.com/video/1"></iframe>' ) );
check( 'detecta un shortcode', 'shortcode' === VideoEmbed::detect( '[presto_player id=3]' ) );
check( 'una URL desconocida cae en oEmbed generico', 'url' === VideoEmbed::detect( 'https://loom.com/share/x' ) );

check( 'Bunny: URL de embed -> biblioteca y video', array( '12345', 'abcdef12-1234-1234-1234-abcdef123456' ) === VideoEmbed::bunny_parse( 'https://iframe.mediadelivery.net/embed/12345/abcdef12-1234-1234-1234-abcdef123456?autoplay=true', '' ) );
check( 'Bunny: URL de reproduccion -> biblioteca y video', array( '12345', 'abcdef12-1234-1234-1234-abcdef123456' ) === VideoEmbed::bunny_parse( 'https://video.bunnycdn.com/play/12345/abcdef12-1234-1234-1234-abcdef123456', '' ) );
check( 'Bunny: solo el GUID usa la biblioteca configurada', array( '777', 'abcdef12-1234-1234-1234-abcdef123456' ) === VideoEmbed::bunny_parse( 'abcdef12-1234-1234-1234-abcdef123456', '777' ) );
check( 'Bunny: solo el GUID sin biblioteca no resuelve', null === VideoEmbed::bunny_parse( 'abcdef12-1234-1234-1234-abcdef123456', '' ) );
check( 'Bunny: el token es SHA-256 de clave + video + expiracion', hash( 'sha256', 'clave' . 'vid' . '1800000000' ) === VideoEmbed::bunny_token( 'clave', 'vid', 1800000000 ) );

update_option( 'av_bunny_library_id', '777' );
update_option( 'av_bunny_token_key', 'secreto' );
$bunny_html = VideoEmbed::render( 'bunny', 'abcdef12-1234-1234-1234-abcdef123456' );
check( 'Bunny: con clave configurada el iframe lleva token y expiracion', str_contains( $bunny_html, 'token=' ) && str_contains( $bunny_html, 'expires=' ) && str_contains( $bunny_html, 'iframe.mediadelivery.net/embed/777/' ) );
delete_option( 'av_bunny_token_key' );
check( 'Bunny: sin clave el iframe no lleva token', ! str_contains( VideoEmbed::render( 'bunny', 'abcdef12-1234-1234-1234-abcdef123456' ), 'token=' ) );
delete_option( 'av_bunny_library_id' );

$cdn = 'https://vz-3c47b71f-11e.b-cdn.net/ea779f39-2171-4024-935e-d46f70abe0e2/playlist.m3u8';
check( 'Bunny CDN: la playlist del CDN se detecta como Bunny', 'bunny' === VideoEmbed::detect( $cdn ) );
check( 'Bunny CDN: con biblioteca configurada se extrae el GUID de la ruta', array( '777', 'ea779f39-2171-4024-935e-d46f70abe0e2' ) === VideoEmbed::bunny_parse( $cdn, '777' ) );
check( 'Bunny CDN: sin biblioteca no se puede convertir a embed', null === VideoEmbed::bunny_parse( $cdn, '' ) );
update_option( 'av_bunny_library_id', '777' );
check( 'Bunny CDN: con biblioteca, la playlist se convierte en el embed de Stream', str_contains( VideoEmbed::render( 'bunny', $cdn ), 'iframe.mediadelivery.net/embed/777/ea779f39-2171-4024-935e-d46f70abe0e2' ) );
delete_option( 'av_bunny_library_id' );
$hls_html = VideoEmbed::render( 'bunny', $cdn );
check( 'Bunny CDN: sin biblioteca se reproduce como HLS directo', str_contains( $hls_html, 'data-hls="' ) && str_contains( $hls_html, 'playlist.m3u8' ) );
check( 'Bunny CDN: sin clave del CDN la URL no se firma', ! str_contains( $hls_html, 'token=' ) );
update_option( 'av_bunny_cdn_token_key', 'clave-cdn' );
check( 'Bunny CDN: con clave del CDN la URL lleva token y expiracion', str_contains( VideoEmbed::render( 'bunny', $cdn ), 'token=' ) );
delete_option( 'av_bunny_cdn_token_key' );
$cdn_token = VideoEmbed::bunny_cdn_token( 'k', '/ea779f39/playlist.m3u8', 1800000000 );
check( 'Bunny CDN: el token es base64url del SHA-256 en bruto, sin relleno', rtrim( strtr( base64_encode( hash( 'sha256', 'k/ea779f39/playlist.m3u8' . '1800000000', true ) ), '+/', '-_' ), '=' ) === $cdn_token && ! str_contains( $cdn_token, '=' ) );

$tutor_cdn = TutorMapping::video( array( 'source' => 'external_url', 'source_external_url' => $cdn, 'runtime' => array( 'hours' => '1', 'minutes' => '59', 'seconds' => '42' ) ) );
check( 'Tutor: una "URL externa" del CDN de Bunny migra como Bunny con su duracion', 'bunny' === $tutor_cdn['provider'] && 119 === $tutor_cdn['minutes'] );
check( 'Tutor: una "URL externa" de YouTube migra como YouTube', 'youtube' === TutorMapping::video( array( 'source' => 'external_url', 'source_external_url' => 'https://www.youtube.com/watch?v=x' ) )['provider'] );

check( 'un iframe de un host permitido se conserva', str_contains( VideoEmbed::render( 'embed', '<iframe src="https://player.vimeo.com/video/1" width="640"></iframe>' ), 'player.vimeo.com' ) );
check( 'un iframe de un host desconocido se descarta entero', '' === VideoEmbed::render( 'embed', '<iframe src="https://malicioso.test/x"></iframe>' ) );
check( 'un MP4 se sirve con la etiqueta video', str_starts_with( VideoEmbed::render( 'html5', 'https://cdn.test/clase.mp4' ), '<video' ) );
check( 'YouTube pasa por oEmbed', str_contains( VideoEmbed::render( '', 'https://youtu.be/abc' ), '<iframe' ) );

$bunny_tutor = TutorMapping::video( array( 'source' => 'bunnynet', 'source_bunnynet' => 'https://iframe.mediadelivery.net/embed/12345/abcdef12-1234-1234-1234-abcdef123456' ) );
check( 'Tutor: la fuente bunnynet migra como Bunny', 'bunny' === $bunny_tutor['provider'] && str_contains( $bunny_tutor['url'], 'mediadelivery' ) );
check( 'Tutor: un embed incrustado conserva el codigo', 'embed' === TutorMapping::video( array( 'source' => 'embedded', 'source_embedded' => '<iframe src="https://player.vimeo.com/video/1"></iframe>' ) )['provider'] );

echo "\nMateriales\n";
check( 'un PDF se admite', MaterialService::is_allowed_extension( 'pdf' ) );
check( 'la extension no distingue mayusculas ni el punto', MaterialService::is_allowed_extension( '.PDF' ) );
check( 'un PHP nunca se admite como material', ! MaterialService::is_allowed_extension( 'php' ) );
check( 'un ejecutable nunca se admite como material', ! MaterialService::is_allowed_extension( 'exe' ) );
check( 'un HTML no se admite como material', ! MaterialService::is_allowed_extension( 'html' ) );

echo "\nLanding por secciones\n";
$defaults = LandingData::defaults( 0 );
check( 'las ocho secciones existen en los valores por defecto', LandingData::sections() === array_keys( $defaults ) );
check( 'el orden es presentacion, beneficios, contenido, instructor, informacion, precio, FAQ y cierre', array( 'hero', 'benefits', 'content', 'instructor', 'info', 'price', 'faq', 'cta' ) === LandingData::sections() );
check( 'todas las secciones nacen visibles', array() === array_filter( $defaults, static fn( array $s ): bool => empty( $s['enabled'] ) ) );

$clean = LandingData::sanitize(
	array(
		'hero'     => array(
			'enabled'     => '1',
			'title'       => '<b>Numerologia</b> 2027',
			'video_url'   => 'javascript:alert(1)',
			'image_id'    => '-5',
			'bg_color'    => 'red',
			'button_type' => 'evil',
			'button_url'  => 'https://x.test/comprar',
		),
		'benefits' => array( 'enabled' => '0', 'items' => "Uno\n\n<i>Dos</i>\n   " ),
		'faq'      => array( 'items' => array( array( 'question' => 'Cuando?', 'answer' => '<p>En julio</p><script>x</script>' ), array( 'question' => '', 'answer' => 'huerfana' ) ) ),
		'info'     => array( 'items' => array( array( 'label' => 'Plataforma', 'value' => 'Zoom' ), array( 'label' => '', 'value' => '' ) ) ),
		'cta'      => array( 'whatsapp' => '+51 999-888-777', 'bg_color' => '#1D4ED8' ),
		'hacker'   => array( 'enabled' => '1' ),
	),
	0
);
check( 'descarta secciones desconocidas', ! isset( $clean['hacker'] ) );
check( 'descarta claves desconocidas y conserva las conocidas', isset( $clean['hero']['title'] ) && count( $clean['hero'] ) === count( $defaults['hero'] ) );
check( 'el titulo pierde el HTML', 'Numerologia 2027' === $clean['hero']['title'] );
check( 'una URL de video peligrosa no sobrevive', ! str_starts_with( $clean['hero']['video_url'], 'javascript' ) );
check( 'un id de imagen negativo queda en cero', 0 === $clean['hero']['image_id'] );
check( 'un color que no es hex queda vacio', '' === $clean['hero']['bg_color'] );
check( 'un color hex valido se normaliza a minusculas', '#1d4ed8' === $clean['cta']['bg_color'] );
check( 'un tipo de boton desconocido vuelve al valor por defecto', LandingData::BUTTON_BUY === $clean['hero']['button_type'] );
check( 'el interruptor apagado se respeta', false === $clean['benefits']['enabled'] );
check( 'los beneficios se leen de un textarea, una linea por item, sin HTML ni vacios', array( 'Uno', 'Dos' ) === $clean['benefits']['items'] );
check( 'las FAQ exigen pregunta y limpian la respuesta', 1 === count( $clean['faq']['items'] ) && ! str_contains( $clean['faq']['items'][0]['answer'], 'script' ) );
check( 'los datos adicionales descartan filas vacias', array( array( 'label' => 'Plataforma', 'value' => 'Zoom' ) ) === $clean['info']['items'] );
check( 'el WhatsApp conserva solo digitos y el mas', '+51999888777' === $clean['cta']['whatsapp'] );
check( 'una seccion ausente en el envio queda con sus valores por defecto', $defaults['price'] === $clean['price'] );
check( 'una clave enviada vacia se guarda vacia, no vuelve al defecto', '' === LandingData::sanitize( array( 'price' => array( 'title' => '' ) ), 0 )['price']['title'] );

echo "\nMigracion desde Tutor LMS\n";
$yt = TutorMapping::video( array( 'source' => 'youtube', 'source_youtube' => 'https://youtu.be/abc', 'runtime' => array( 'hours' => '1', 'minutes' => '30', 'seconds' => '0' ) ) );
check( 'un video de YouTube conserva proveedor, URL y duracion en minutos', array( 'youtube', 'https://youtu.be/abc', 90 ) === array( $yt['provider'], $yt['url'], $yt['minutes'] ) );
$vm = TutorMapping::video( array( 'source' => 'vimeo', 'source_vimeo' => 'https://vimeo.com/1', 'runtime' => array( 'hours' => 0, 'minutes' => 0, 'seconds' => 45 ) ) );
check( 'menos de un minuto redondea a un minuto', 1 === $vm['minutes'] && 'vimeo' === $vm['provider'] );
check( 'una URL externa a un MP4 se reclasifica como archivo directo', 'html5' === TutorMapping::video( array( 'source' => 'external_url', 'source_external_url' => 'https://x.test/v.mp4' ) )['provider'] );
check( 'una URL externa generica se queda como url', 'url' === TutorMapping::video( array( 'source' => 'external_url', 'source_external_url' => 'https://loom.com/share/x' ) )['provider'] );
check( 'un video sin URL no deja proveedor colgado', '' === TutorMapping::video( array( 'source' => 'youtube', 'source_youtube' => '' ) )['provider'] );
check( 'un meta que no es array se ignora', array( 'provider' => '', 'url' => '', 'minutes' => 0 ) === TutorMapping::video( 'basura' ) );
check( 'una fuente desconocida se ignora', '' === TutorMapping::video( array( 'source' => 'tiktok', 'source_tiktok' => 'https://t' ) )['url'] );

check( 'la matricula "completed" de Tutor es una matricula activa, no un curso terminado', 'active' === TutorMapping::enrollment_status( 'completed' ) );
check( 'una matricula cancelada en Tutor queda cancelada', 'cancelled' === TutorMapping::enrollment_status( 'cancel' ) );
check( 'una matricula pendiente en Tutor queda pendiente', 'pending' === TutorMapping::enrollment_status( 'pending' ) );
check( 'un estado desconocido no otorga acceso', 'cancelled' === TutorMapping::enrollment_status( 'trash' ) );

check( 'el nivel expert de Tutor es avanzado', 'advanced' === TutorMapping::level( 'expert' ) );
check( 'el nivel all_levels de Tutor es todos', 'all' === TutorMapping::level( 'all_levels' ) );
check( 'los beneficios multilinea se convierten en lista limpia', array( 'Uno', 'Dos' ) === TutorMapping::lines( "Uno\r\n\r\n  Dos  \n" ) );
check( 'un beneficio que no es texto da lista vacia', array() === TutorMapping::lines( null ) );
check( 'la duracion se formatea en horas y minutos', '2 h 15 min' === TutorMapping::duration( array( 'hours' => '2', 'minutes' => '15' ) ) );
check( 'una duracion en cero queda vacia', '' === TutorMapping::duration( array( 'hours' => 0, 'minutes' => 0 ) ) );

echo "\nWooCommerce\n";
check( 'con disparador en procesando, un pedido procesando matricula', WooSettings::should_enroll( 'processing', 'processing' ) );
check( 'con disparador en procesando, un pedido completado tambien matricula', WooSettings::should_enroll( 'completed', 'processing' ) );
check( 'con disparador en completado, procesando espera', ! WooSettings::should_enroll( 'processing', 'completed' ) );
check( 'un pedido en espera nunca matricula', ! WooSettings::should_enroll( 'on-hold', 'processing' ) );
check( 'un pedido pendiente de pago nunca matricula', ! WooSettings::should_enroll( 'pending', 'completed' ) );
check( 'la politica suspender mapea a suspendida', 'suspended' === WooSettings::status_for_refund( 'suspend' ) );
check( 'la politica cancelar mapea a cancelada', 'cancelled' === WooSettings::status_for_refund( 'cancel' ) );
check( 'la politica no hacer nada no toca la matricula', '' === WooSettings::status_for_refund( 'none' ) );
check( 'una politica desconocida no toca la matricula', '' === WooSettings::status_for_refund( 'delete' ) );
check( 'reembolsado y cancelado son los estados que revocan', array( 'refunded', 'cancelled' ) === WooSettings::revoking_statuses() );

echo "\nEmails\n";
$vars = array( 'first_name' => 'Ana', 'course_name' => 'Numerologia' );
check( 'reemplaza variables conocidas', 'Hola Ana, bienvenida a Numerologia' === TemplateRenderer::render( 'Hola {{first_name}}, bienvenida a {{course_name}}', $vars ) );
check( 'tolera espacios y mayusculas dentro de las llaves', 'Ana' === TemplateRenderer::render( '{{ First_Name }}', $vars ) );
check( 'deja visible una variable desconocida en vez de borrarla', '{{typo}}' === TemplateRenderer::render( '{{typo}}', $vars ) );
check( 'lista los marcadores de una plantilla', array( 'first_name', 'course_name' ) === TemplateRenderer::placeholders( '{{first_name}} y {{course_name}} y {{first_name}}' ) );

$catalogue = array_keys( VariableResolver::catalogue() );
$unknown   = array();
foreach ( EmailDefaults::definitions() as $definition ) {
	foreach ( TemplateRenderer::placeholders( $definition['subject'] . ' ' . $definition['body'] ) as $placeholder ) {
		if ( ! in_array( $placeholder, $catalogue, true ) ) {
			$unknown[] = $placeholder;
		}
	}
}
check( 'todas las plantillas por defecto usan solo variables del catalogo', array() === $unknown );

$pairs = array_map( static fn( array $d ): string => $d['event'] . '/' . $d['recipient'], EmailDefaults::definitions() );
check( 'no hay dos plantillas por defecto para el mismo evento y destinatario', count( $pairs ) === count( array_unique( $pairs ) ) );
check( 'cada plantilla por defecto pertenece a un evento notificable', array() === array_diff( array_column( EmailDefaults::definitions(), 'event' ), array_keys( EmailDefaults::events() ) ) );
check( 'la bienvenida lleva el bloque de acceso (crear contrasena o entrar)', str_contains( EmailDefaults::definitions()[4]['body'], '{{access_instructions}}' ) );
check( 'existe la plantilla de reenvio de acceso con enlace y validez', 1 === count( array_filter( EmailDefaults::definitions(), static fn( array $d ): bool => \SIQA\AulaVirtual\Core\Events\Events::ACCESS_LINK_SENT === $d['event'] && str_contains( $d['body'], '{{set_password_url}}' ) && str_contains( $d['body'], '{{link_expiry}}' ) ) ) );
$av_new = VariableResolver::access_instructions( true, 'https://example.test/wp-login.php?action=rp&key=abc', 'https://example.test/aula/', '3 días' );
$av_old = VariableResolver::access_instructions( false, 'https://example.test/wp-login.php?action=lostpassword', 'https://example.test/aula/', '3 días' );
check( 'una cuenta nueva recibe el boton de crear contrasena con la validez', str_contains( $av_new, 'Crear mi contraseña' ) && str_contains( $av_new, 'action=rp' ) && str_contains( $av_new, '3 días' ) );
check( 'quien ya tiene contrasena recibe "entra con tu contrasena de siempre"', str_contains( $av_old, 'contraseña de siempre' ) && str_contains( $av_old, 'lostpassword' ) && ! str_contains( $av_old, 'Crear mi contraseña' ) );
check( 'el bloque de acceso no se vuelve a escapar en el correo', $av_new === VariableResolver::escape_all( array( 'access_instructions' => $av_new ) )['access_instructions'] );
check( 'la validez se expresa en dias o en horas', '3 días' === \SIQA\AulaVirtual\Students\AccountHelper::lifetime_label( 72 ) && '24 horas' === \SIQA\AulaVirtual\Students\AccountHelper::lifetime_label( 24 ) && '1 hora' === \SIQA\AulaVirtual\Students\AccountHelper::lifetime_label( 1 ) );
check( 'la validez por defecto es de 72 horas y no pasa de una semana', 72 === \SIQA\AulaVirtual\Students\AccountHelper::link_hours() && 168 * 3600 >= \SIQA\AulaVirtual\Students\AccountHelper::link_lifetime( 86400 ) );
check( 'contrasena corta, distinta o con espacios se rechaza', \SIQA\AulaVirtual\Students\StudentService::validate_password( 'corta', 'corta' ) instanceof WP_Error && \SIQA\AulaVirtual\Students\StudentService::validate_password( 'largaSegura1', 'otraCosa12' ) instanceof WP_Error && \SIQA\AulaVirtual\Students\StudentService::validate_password( ' largaSegura1', ' largaSegura1' ) instanceof WP_Error );
check( 'una contrasena valida pasa', true === \SIQA\AulaVirtual\Students\StudentService::validate_password( 'largaSegura1', 'largaSegura1' ) );
check( 'la ficha del alumno tiene cuatro pestanas', array( 'matriculas', 'acceso', 'datos', 'historial' ) === \SIQA\AulaVirtual\Admin\StudentsScreen::TABS );


echo "\nLanding\n";
check( 'sobre dorado el texto del boton es oscuro', '#111111' === LandingRenderer::on_color( '#c9a45c' ) );
check( 'sobre azul o negro el texto del boton es blanco', '#ffffff' === LandingRenderer::on_color( '#1d4ed8' ) && '#ffffff' === LandingRenderer::on_color( '#111111' ) );
check( 'un color invalido no rompe el calculo', '#ffffff' === LandingRenderer::on_color( 'rojo' ) );
$GLOBALS['av_test_options']['av_brand_color'] = '#C9A45C';
check( 'el acento sale del color de marca configurado', '#c9a45c' === LandingRenderer::accent() );
$GLOBALS['av_test_options']['av_brand_color'] = 'url(javascript:1)';
check( 'un color de marca invalido vuelve al azul por defecto', '#1d4ed8' === LandingRenderer::accent() );
unset( $GLOBALS['av_test_options']['av_brand_color'] );
check( 'duraciones legibles', '45 min' === LandingRenderer::duration_label( 45 ) && '2 h' === LandingRenderer::duration_label( 120 ) && '10 h 45 min' === LandingRenderer::duration_label( 645 ) && '' === LandingRenderer::duration_label( 0 ) );
check( 'los iconos son SVG sin datos externos y uno desconocido queda vacio', str_starts_with( LandingRenderer::icon( 'calendar' ), '<svg' ) && ! str_contains( LandingRenderer::icon( 'calendar' ), 'http' ) && '' === LandingRenderer::icon( 'nada' ) );
check( 'el titulo "Borrador automatico" cuenta como vacio', LandingData::is_placeholder_title( 'Borrador automático' ) && LandingData::is_placeholder_title( 'Auto Draft' ) && LandingData::is_placeholder_title( '  ' ) && ! LandingData::is_placeholder_title( 'Numerología' ) );
$av_legacy = LandingData::normalize( array( 'hero' => array( 'title' => 'Numerología' ), 'benefits' => array( 'title' => 'Que vas a lograr' ), 'price' => array( 'title' => 'Inversion' ) ), 0 );
check( 'los titulos antiguos sin tildes se corrigen', 'Qué vas a lograr' === $av_legacy['benefits']['title'] && 'Inversión' === $av_legacy['price']['title'] && 'Numerología' === $av_legacy['hero']['title'] );
$av_tpl = array();
foreach ( glob( __DIR__ . '/../templates/landing/sections/*.php' ) as $av_file ) {
	$av_tpl[ basename( $av_file, '.php' ) ] = (string) file_get_contents( $av_file );
}
check( 'cada seccion del menu tiene su ancla', array() === array_filter( array( 'benefits', 'content', 'instructor', 'info', 'price', 'faq', 'cta', 'hero' ), static fn( string $k ): bool => ! str_contains( $av_tpl[ $k ] ?? '', 'id="av-' . $k . '"' ) ) );
check( 'el temario trata las sesiones como datos (titulo, tipo, duracion)', str_contains( $av_tpl['content'], "\$av_lesson['title']" ) && str_contains( $av_tpl['content'], "\$av_lesson['duration']" ) );

echo "\nPantalla de acceso\n";
check( 'la marca en el acceso viene activa por defecto', LoginBranding::enabled() );
check( 'el acceso usa el color y el texto legible del boton', str_contains( LoginBranding::inline_css( '#C9A45C', '' ), '--av-accent:#c9a45c;--av-on-accent:#111111;' ) && ! str_contains( LoginBranding::inline_css( '#C9A45C', '' ), 'background-image' ) );
check( 'un color invalido en el acceso vuelve al azul', str_contains( LoginBranding::inline_css( 'red;}body{x', '' ), '--av-accent:#1d4ed8;' ) );
check( 'el logo entra en el CSS del acceso', str_contains( LoginBranding::inline_css( '#c9a45c', 'https://example.test/logo.png' ), 'url("https://example.test/logo.png")' ) );
check( 'una URL de logo que rompe el CSS se descarta', '' === LoginBranding::css_url( 'https://example.test/a").x{' ) && '' === LoginBranding::css_url( 'javascript:alert(1)' ) && '' === LoginBranding::css_url( '' ) );
check( 'sin logo propio usa el del tema y luego el icono del sitio', 'https://example.test/uploads/logo-7.png' === ( static function (): string { $GLOBALS['av_test_theme_mods']['custom_logo'] = 7; $u = LoginBranding::logo_url(); unset( $GLOBALS['av_test_theme_mods']['custom_logo'] ); return $u; } )() && '' === LoginBranding::logo_url() );
$GLOBALS['av_test_options'][ LoginBranding::OPTION_LOGO ] = 12;
check( 'el logo configurado gana al del tema', 'https://example.test/uploads/logo-12.png' === LoginBranding::logo_url() );
unset( $GLOBALS['av_test_options'][ LoginBranding::OPTION_LOGO ] );
$av_intro = LoginBranding::intro_html( 'create', '<b>Ana</b>' );
check( 'la cuenta nueva ve "Crea tu contrasena" y su nombre escapado', str_contains( $av_intro, 'Crea tu contraseña' ) && str_contains( $av_intro, '&lt;b&gt;Ana' ) && ! str_contains( $av_intro, '<b>' ) );
check( 'al guardar se ofrece entrar al campus', str_contains( LoginBranding::intro_html( 'done', '', 'https://example.test/wp-login.php' ), 'Entrar al campus' ) && ! str_contains( LoginBranding::intro_html( 'change' ), 'Entrar al campus' ) );
check( 'la sugerencia de contrasena pide 12 caracteres', str_contains( LoginBranding::password_hint(), '12 caracteres' ) );
check( 'las opciones del acceso estan en Configuracion', isset( $all_fields[ LoginBranding::OPTION_ENABLED ], $all_fields[ LoginBranding::OPTION_LOGO ] ) && 'bool' === $all_fields[ LoginBranding::OPTION_ENABLED ]['type'] );
check( 'la hoja de estilos del acceso existe', is_readable( __DIR__ . '/../assets/css/login.css' ) && str_contains( (string) file_get_contents( __DIR__ . '/../assets/css/login.css' ), 'body.av-login' ) );
check( 'las opciones del acceso se borran al desinstalar', str_contains( (string) file_get_contents( __DIR__ . '/../uninstall.php' ), "'av_login_logo'" ) );

echo "\nCampus: enlaces y diseño\n";
$av_rules = CampusController::rewrite_rules( 'aula-virtual', 12 );
check( 'con las reglas guardadas no se regeneran los enlaces', ! CampusController::rules_missing( $av_rules + array( 'otra/?$' => 'index.php' ), $av_rules ) );
check( 'si falta una regla del campus se regeneran los enlaces', CampusController::rules_missing( array_slice( $av_rules, 1, null, true ), $av_rules ) && CampusController::rules_missing( '', $av_rules ) );
check( 'si la pagina del campus cambia de nombre las reglas viejas no sirven', CampusController::rules_missing( CampusController::rewrite_rules( 'campus', 12 ), $av_rules ) );
check( 'sin pagina del campus no hay nada que regenerar', ! CampusController::rules_missing( array(), array() ) );
check( 'el campus usa el color de marca y valida el valor', str_contains( CampusController::inline_css( '#B8962E' ), '--av-accent:#b8962e;' ) && str_contains( CampusController::inline_css( 'red;}x{' ), '--av-accent:#1d4ed8;' ) );
check( 'la hoja de estilos del campus existe', is_readable( __DIR__ . '/../assets/css/campus.css' ) && str_contains( (string) file_get_contents( __DIR__ . '/../assets/css/campus.css' ), '.av-c-lesson' ) );
check( 'hay iconos de candado, usuario y salida', '' !== LandingRenderer::icon( 'lock' ) && '' !== LandingRenderer::icon( 'user' ) && '' !== LandingRenderer::icon( 'logout' ) );
$av_campus_tpl = array();
foreach ( glob( __DIR__ . '/../templates/campus/*.php' ) as $av_file ) {
	$av_campus_tpl[ basename( $av_file, '.php' ) ] = (string) file_get_contents( $av_file );
}
check( 'el temario marca la siguiente sesion y ofrece continuar', str_contains( $av_campus_tpl['edition'], 'is-next' ) && str_contains( $av_campus_tpl['edition'], 'Tu siguiente sesión' ) );
check( 'la sesion tiene anterior, siguiente y marcar como completada', str_contains( $av_campus_tpl['lesson'], 'av-c-lesson-nav' ) && str_contains( $av_campus_tpl['lesson'], 'ACTION_COMPLETE' ) );
check( 'las plantillas del campus no usan header ni footer (el tema los estiliza)', array() === array_filter( $av_campus_tpl, static fn( string $t ): bool => (bool) preg_match( '/<(header|footer)[\s>]/', $t ) && ! str_contains( $t, '<!DOCTYPE' ) ) );

echo "\nCampus: contenido del curso\n";
$av_item = static fn( int $id, int $module, bool $done = false, bool $open = true, int $minutes = 10 ): array => array( 'lesson' => array( 'id' => $id, 'module_id' => $module, 'duration' => $minutes, 'title' => 'S' . $id ), 'completed' => $done, 'available' => $open );
$av_outline = CampusController::build_outline(
	array( array( 'id' => 7, 'title' => 'Fundamentos' ), array( 'id' => 8, 'title' => 'Vacío' ), array( 'id' => 9, 'title' => 'Karma' ) ),
	array( $av_item( 1, 7, true ), $av_item( 2, 7 ), $av_item( 3, 0 ), $av_item( 4, 9, false, false, 0 ), $av_item( 5, 99 ) ),
	array( 2 => array( array( 'title' => 'Guía' ) ) )
);
check( 'las sesiones se agrupan por seccion y las secciones vacias no se muestran', array( '', 'Fundamentos', 'Karma' ) === array_column( $av_outline, 'title' ) );
check( 'las sesiones sin seccion (o de una seccion borrada) van primero', array( 3, 5 ) === array_map( static fn( array $i ): int => $i['lesson']['id'], $av_outline[0]['items'] ) );
check( 'la numeracion sigue el orden del curso', array( 1, 2 ) === array_column( $av_outline[1]['items'], 'number' ) && 3 === $av_outline[0]['items'][0]['number'] && 5 === $av_outline[0]['items'][1]['number'] );
check( 'cada seccion cuenta completadas, total y minutos', 1 === $av_outline[1]['done'] && 2 === $av_outline[1]['total'] && 20 === $av_outline[1]['minutes'] );
check( 'los materiales quedan en su sesion', 'Guía' === $av_outline[1]['items'][1]['materials'][0]['title'] && array() === $av_outline[1]['items'][0]['materials'] );
check( 'sin sesiones no hay secciones', array() === CampusController::build_outline( array( array( 'id' => 1, 'title' => 'X' ) ), array() ) );
check( 'la sesion tiene panel de contenido con recursos y boton para abrirlo', str_contains( $av_campus_tpl['lesson'], 'id="av-outline"' ) && str_contains( $av_campus_tpl['lesson'], 'data-av-outline-toggle' ) && str_contains( $av_campus_tpl['lesson'], 'av-outline__res' ) );
check( 'la sesion tiene pestanas de descripcion, materiales y preguntas', str_contains( $av_campus_tpl['lesson'], "'about'" ) && str_contains( $av_campus_tpl['lesson'], "'materials'" ) && str_contains( $av_campus_tpl['lesson'], "'comments'" ) );
check( 'el script del campus existe y abre el panel en celular', str_contains( (string) file_get_contents( __DIR__ . '/../assets/js/campus.js' ), 'is-outline-open' ) );
check( 'el temario de la edicion muestra las secciones', str_contains( $av_campus_tpl['edition'], 'av-c-module' ) );

echo "\nEditor de sesión\n";
$av_le  = (string) file_get_contents( __DIR__ . '/../admin/views/lesson-edit.php' );
$av_ajs = (string) file_get_contents( __DIR__ . '/../assets/js/admin.js' );
$av_asp = (string) file_get_contents( __DIR__ . '/../includes/Admin/AdminServiceProvider.php' );
check( 'el selector de materiales no depende de un script en linea que corre antes que wp.media', ! str_contains( $av_le, 'wp.media(' ) && str_contains( $av_le, 'data-av-material-choose' ) );
check( 'admin.js busca wp.media al hacer clic', str_contains( $av_ajs, '[data-av-material-choose]' ) && str_contains( $av_ajs, 'window.wp.media' ) );
check( 'la pantalla de sesion carga la biblioteca de medios antes que admin.js', str_contains( $av_asp, 'wp_enqueue_media();' ) && str_contains( $av_asp, "array( 'media-editor' )" ) );
check( 'los campos laterales pertenecen al formulario de la sesion', 5 <= substr_count( $av_le, 'form="<?php echo esc_attr( $av_form ); ?>"' ) );
check( 'la clase en vivo se guarda con inicio y duracion', str_contains( $av_le, 'name="start_local"' ) && str_contains( $av_le, 'name="duration"' ) && ! str_contains( $av_le, 'name="end_local"' ) );
check( 'se avisa de cambios sin guardar', str_contains( $av_le, 'data-av-dirty-watch' ) && str_contains( $av_ajs, 'beforeunload' ) );

echo "\n{$av_checks} comprobaciones, {$av_failures} fallos\n";

exit( $av_failures > 0 ? 1 : 0 );
