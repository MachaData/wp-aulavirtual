<?php
/**
 * Vista previa de la landing sin WordPress, con datos de ejemplo.
 *
 * Uso:
 *   php bin/preview-landing.php [full|min] [#color] > dist/landing.html
 *   (abrir dist/landing.html en el navegador; carga assets/css/landing.css y assets/js/landing.js)
 *
 * "full" usa todas las secciones y dos ediciones; "min" una edicion, sin instructor ni FAQ.
 * Sirve para iterar el diseño de templates/landing/ y assets/css/landing.css.
 *
 * @package SIQA\AulaVirtual
 */

$root = dirname( __DIR__ );
require $root . '/tests/wp-stubs.php';
spl_autoload_register( static function ( string $c ) use ( $root ): void {
	$p = 'SIQA\\AulaVirtual\\';
	if ( str_starts_with( $c, $p ) ) {
		$f = $root . '/includes/' . str_replace( '\\', '/', substr( $c, strlen( $p ) ) ) . '.php';
		if ( is_readable( $f ) ) { require_once $f; }
	}
} );
foreach ( array(
	'esc_attr' => fn( $s ) => htmlspecialchars( (string) $s, ENT_QUOTES ),
	'esc_attr_e' => function ( $s ) { echo htmlspecialchars( (string) $s, ENT_QUOTES ); },
	'esc_attr__' => fn( $s ) => htmlspecialchars( (string) $s, ENT_QUOTES ),
	'esc_html_e' => function ( $s ) { echo htmlspecialchars( (string) $s ); },
	'wpautop' => fn( $s ) => '<p>' . implode( '</p><p>', preg_split( "/\n\s*\n/", trim( $s ) ) ) . '</p>',
	'get_the_title' => fn( $p ) => 'Numerología Básica y Vidas Pasadas',
) as $n => $f ) {
	if ( ! function_exists( $n ) ) { $GLOBALS['__f'][ $n ] = $f; eval( "function $n(...\$a){ return \$GLOBALS['__f']['$n'](...\$a); }" ); }
}
if ( ! function_exists( '_n' ) ) { function _n( $a, $b, $n ) { return 1 === (int) $n ? $a : $b; } }

use SIQA\AulaVirtual\Landing\LandingRenderer;

$variant = $argv[1] ?? 'full';
$accent  = $argv[2] ?? '#c9a45c';

$lesson = fn( $t, $ty, $d ) => array( 'title' => $t, 'type' => $ty, 'duration' => $d );
$curriculum = array(
	array( 'title' => 'Módulo 1 · Fundamentos de la numerología', 'lessons' => array(
		$lesson( 'Qué es la numerología y cómo se usa', 'live', 90 ),
		$lesson( 'Los números del 1 al 9 y sus vibraciones', 'live', 90 ),
		$lesson( 'Guía de estudio del módulo', 'material', 0 ),
	) ),
	array( 'title' => 'Módulo 2 · Tu carta numerológica', 'lessons' => array(
		$lesson( 'Número de vida y número de destino', 'live', 90 ),
		$lesson( 'Números maestros 11, 22 y 33', 'video', 45 ),
		$lesson( 'Práctica: calcula tu carta', 'live', 90 ),
	) ),
	array( 'title' => 'Módulo 3 · Vidas pasadas y karma', 'lessons' => array(
		$lesson( 'Deudas kármicas en la fecha de nacimiento', 'live', 90 ),
		$lesson( 'Lecciones de vidas pasadas', 'live', 90 ),
		$lesson( 'Cierre y certificación', 'live', 60 ),
	) ),
);
$lessons = array_merge( ...array_column( $curriculum, 'lessons' ) );
$edition = array(
	'id' => 1, 'name' => 'Edición Octubre 2026', 'status' => 'Inscripciones abiertas', 'status_key' => 'open', 'modality' => 'En vivo por Zoom',
	'start_date' => '14 de octubre de 2026', 'end_date' => '25 de noviembre de 2026', 'schedule_days' => 'Martes y jueves', 'schedule_time' => '19:00 – 20:30',
	'timezone' => 'America/Lima', 'price' => 'S/ 290', 'capacity' => 30, 'seats_left' => 8,
	'buy_url' => '#comprar', 'register_url' => '',
);
$edition2 = array_merge( $edition, array( 'id' => 2, 'name' => 'Edición Enero 2027', 'status' => 'Próximamente', 'status_key' => 'scheduled', 'start_date' => '12 de enero de 2027', 'end_date' => '', 'seats_left' => null, 'schedule_days' => 'Sábados', 'schedule_time' => '10:00 – 12:00', 'buy_url' => '', 'register_url' => '#inscribirme' ) );
$editions = 'min' === $variant ? array( $edition ) : array( $edition, $edition2 );

$landing = array(
	'hero' => array( 'enabled' => true, 'kicker' => '', 'title' => 'Numerología Básica y Vidas Pasadas', 'subtitle' => 'Aprende a leer tu fecha de nacimiento, descubre tu propósito y entiende las lecciones que traes de vidas pasadas.', 'text' => '', 'image_id' => 0, 'video_url' => '', 'background_id' => 0, 'bg_color' => '' ),
	'benefits' => array( 'enabled' => true, 'title' => 'Qué vas a lograr', 'image_id' => 0, 'items' => array( 'Calcular e interpretar tu carta numerológica completa', 'Identificar tus números maestros y deudas kármicas', 'Reconocer patrones de vidas pasadas en tu fecha de nacimiento', 'Aplicar la numerología a decisiones de pareja, trabajo y familia', 'Hacer lecturas básicas para otras personas', 'Certificado de participación al finalizar' ) ),
	'content' => array( 'enabled' => true, 'title' => 'Lo que veremos en el curso', 'text' => '', 'video_url' => '', 'from_curriculum' => true, 'items' => array() ),
	'instructor' => array( 'enabled' => true, 'title' => 'Tu guía', 'name' => 'Rocío Aranda', 'bio' => "Numeróloga y terapeuta holística con más de 12 años de experiencia. Ha acompañado a más de 3 000 personas en consultas individuales y talleres en Perú, México y España.\n\nSu enfoque combina la numerología pitagórica con el trabajo de vidas pasadas, siempre de forma práctica y cercana.", 'image_id' => 0, 'links' => array( array( 'label' => 'Instagram', 'url' => '#' ), array( 'label' => 'YouTube', 'url' => '#' ) ) ),
	'info' => array( 'enabled' => true, 'title' => 'Elige tu edición', 'from_editions' => true, 'items' => array() ),
	'price' => array( 'enabled' => true, 'title' => 'Inversión', 'price' => '', 'old_price' => 'S/ 390', 'includes' => array( '9 sesiones en vivo (13 h) con grabación', 'Guía de estudio en PDF por módulo', 'Grupo privado de WhatsApp', 'Acceso al aula virtual por 6 meses', 'Certificado de participación' ), 'note' => 'Pago seguro con tarjeta, Yape o transferencia.', 'button_text' => 'Comprar ahora' ),
	'faq' => array( 'enabled' => true, 'title' => 'Preguntas frecuentes', 'items' => array(
		array( 'question' => '¿Necesito conocimientos previos?', 'answer' => 'No. El curso empieza desde cero y cada sesión incluye ejercicios guiados.' ),
		array( 'question' => '¿Qué pasa si no puedo asistir a una sesión en vivo?', 'answer' => 'Todas las sesiones quedan grabadas en tu aula virtual al día siguiente.' ),
		array( 'question' => '¿Cómo recibo el acceso?', 'answer' => 'Al confirmar tu pago te llega un correo para crear tu contraseña y entrar al aula.' ),
	) ),
	'cta' => array( 'enabled' => true, 'title' => 'Reserva tu lugar', 'text' => '', 'whatsapp' => '51999999999', 'background_id' => 0, 'bg_color' => '' ),
);
if ( 'min' === $variant ) {
	$landing['instructor']['name'] = '';
	$landing['faq']['items'] = array();
	$landing['benefits']['items'] = array_slice( $landing['benefits']['items'], 0, 3 );
}

$btn = fn( $t, $u ) => array( 'text' => $t, 'url' => $u );
$vm = array(
	'editions' => $editions, 'primary' => $edition,
	'buttons' => array( 'hero' => $btn( 'Inscribirme ahora', '#comprar' ), 'price' => $btn( 'Comprar ahora', '#comprar' ), 'cta' => $btn( 'Inscribirme', '#comprar' ) ),
	'curriculum' => $curriculum,
	'stats' => array( 'sessions' => count( $lessons ), 'minutes' => array_sum( array_column( $lessons, 'duration' ) ), 'modules' => 3 ),
	'accent' => $accent, 'on_accent' => LandingRenderer::on_color( $accent ),
	'course' => 1, 'icon' => array( LandingRenderer::class, 'icon' ), 'modalities' => array(),
	'image' => fn( $id, $s = 'large' ) => '', 'video' => fn( $u ) => '',
);

$renderer = new class( $root ) {
	public function __construct( private string $root ) {}
	public function template( string $name, array $data ): string {
		extract( $data ); $renderer = $this;
		ob_start(); include $this->root . '/templates/landing/' . $name . '.php'; return (string) ob_get_clean();
	}
};
$body = $renderer->template( 'landing', array( 'course_id' => 1, 'landing' => $landing, 'vm' => $vm ) );
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Vista previa · Landing Aula Virtual</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="../assets/css/landing.css">
<style>body{margin:0;font-family:Jost,system-ui,sans-serif}</style>
</head>
<body>
<?php echo $body; // phpcs:ignore ?>
<script src="../assets/js/landing.js"></script>
</body>
</html>

