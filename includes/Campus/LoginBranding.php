<?php
/**
 * Branded WordPress login screens (sign in, lost password, create password).
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Campus;

use SIQA\AulaVirtual\Landing\LandingRenderer;
use SIQA\AulaVirtual\Students\AccountHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dresses wp-login.php with the brand: logo, colour and friendlier texts.
 *
 * Only presentation: WordPress keeps validating the key, the password and
 * the nonces. The pages students reach from the welcome email
 * (action=rp / resetpass) get their own heading ("Crea tu contraseña").
 */
final class LoginBranding {

	public const OPTION_ENABLED = 'av_login_branding';
	public const OPTION_LOGO    = 'av_login_logo';

	/**
	 * Registers the login hooks when the option is on.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( ! self::enabled() ) {
			return;
		}

		add_action( 'login_enqueue_scripts', array( self::class, 'enqueue' ) );
		add_filter( 'login_headerurl', array( self::class, 'header_url' ) );
		add_filter( 'login_headertext', array( self::class, 'header_text' ) );
		add_filter( 'login_body_class', array( self::class, 'body_class' ) );
		add_filter( 'login_message', array( self::class, 'message' ) );
		add_filter( 'login_title', array( self::class, 'title' ), 10, 2 );
		add_filter( 'password_hint', array( self::class, 'password_hint' ) );
		add_filter( 'login_display_language_dropdown', '__return_false' );
	}

	/**
	 * Whether the login screens are branded.
	 *
	 * @return bool
	 */
	public static function enabled(): bool {
		return (bool) get_option( self::OPTION_ENABLED, true );
	}

	/**
	 * Loads the stylesheet and the brand variables.
	 *
	 * @return void
	 */
	public static function enqueue(): void {
		wp_enqueue_style( 'av-login', AV_URL . 'assets/css/login.css', array( 'login' ), AV_VERSION );
		wp_add_inline_style( 'av-login', self::inline_css( LandingRenderer::accent(), self::logo_url() ) );
	}

	/**
	 * CSS variables for the brand colour and the logo.
	 *
	 * @param string $accent   Brand colour (#rrggbb).
	 * @param string $logo_url Logo URL, or empty for the text logo.
	 * @return string
	 */
	public static function inline_css( string $accent, string $logo_url ): string {
		$accent = 1 === preg_match( '/^#[0-9a-fA-F]{6}$/', $accent ) ? strtolower( $accent ) : '#1d4ed8';
		$css    = sprintf( 'body.av-login{--av-accent:%1$s;--av-on-accent:%2$s;}', $accent, LandingRenderer::on_color( $accent ) );
		$logo   = self::css_url( $logo_url );

		if ( '' !== $logo ) {
			$css .= sprintf( 'body.av-login #login h1 a{background-image:url("%s");}', $logo );
		}

		return $css;
	}

	/**
	 * Makes a URL safe inside url("…"), or returns an empty string.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function css_url( string $url ): string {
		$url = esc_url_raw( trim( $url ) );

		if ( '' === $url || 1 !== preg_match( '#^https?://#i', $url ) || 1 === preg_match( '/["\'()\\\\\s<>]/', $url ) ) {
			return '';
		}

		return $url;
	}

	/**
	 * Logo: the one set in Settings, then the theme logo, then the site icon.
	 *
	 * @return string URL or empty string (the site name is shown).
	 */
	public static function logo_url(): string {
		$candidates = array(
			(int) get_option( self::OPTION_LOGO, 0 ),
			(int) get_theme_mod( 'custom_logo', 0 ),
		);

		foreach ( $candidates as $attachment_id ) {
			if ( $attachment_id > 0 ) {
				$url = wp_get_attachment_image_url( $attachment_id, 'medium' );

				if ( is_string( $url ) && '' !== $url ) {
					return $url;
				}
			}
		}

		$icon = get_site_icon_url( 192 );

		return is_string( $icon ) ? $icon : '';
	}

	/**
	 * The logo links to the site, not to wordpress.org.
	 *
	 * @return string
	 */
	public static function header_url(): string {
		return home_url( '/' );
	}

	/**
	 * Text of the logo link (screen readers, and the fallback without logo).
	 *
	 * @return string
	 */
	public static function header_text(): string {
		return esc_html( wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ) );
	}

	/**
	 * Adds the hook class and marks the logo-less variant.
	 *
	 * @param mixed $classes Body classes.
	 * @return array<int, string>
	 */
	public static function body_class( $classes ): array {
		$classes   = is_array( $classes ) ? $classes : array();
		$classes[] = 'av-login';

		if ( '' === self::css_url( self::logo_url() ) ) {
			$classes[] = 'av-login--text-logo';
		}

		return $classes;
	}

	/**
	 * Current wp-login.php action.
	 *
	 * @return string
	 */
	public static function action(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Solo decide qué texto mostrar.
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : 'login';

		return '' === $action ? 'login' : $action;
	}

	/**
	 * Replaces the WordPress notice of the password pages with a heading.
	 *
	 * @param mixed $message Message HTML from WordPress.
	 * @return string
	 */
	public static function message( $message ): string {
		$message = is_string( $message ) ? $message : '';
		$action  = self::action();

		if ( 'resetpass' === $action && did_action( 'after_password_reset' ) ) {
			return self::intro_html( 'done', '', self::campus_login_url() );
		}

		if ( 'rp' === $action || 'resetpass' === $action ) {
			$user = self::reset_user();

			return self::intro_html(
				null !== $user && AccountHelper::needs_password( $user->ID ) ? 'create' : 'change',
				null !== $user ? (string) $user->first_name : ''
			);
		}

		if ( 'lostpassword' === $action || 'retrievepassword' === $action ) {
			return self::intro_html( 'lost' );
		}

		if ( 'login' === $action && '' === trim( $message ) ) {
			return self::intro_html( 'login' );
		}

		return $message;
	}

	/**
	 * Heading block shown above the form.
	 *
	 * @param string $variant    create | change | done | lost | login.
	 * @param string $first_name Student first name (optional).
	 * @param string $link       Sign-in URL for the "done" variant.
	 * @return string HTML.
	 */
	public static function intro_html( string $variant, string $first_name = '', string $link = '' ): string {
		switch ( $variant ) {
			case 'create':
				$title = __( 'Crea tu contraseña', 'aula-virtual' );
				$text  = __( 'Es el último paso para entrar a tu campus. La usarás junto con tu correo cada vez que ingreses.', 'aula-virtual' );
				break;
			case 'done':
				$title = __( '¡Listo! Contraseña guardada', 'aula-virtual' );
				$text  = __( 'Ya puedes entrar al campus con tu correo y tu nueva contraseña.', 'aula-virtual' );
				break;
			case 'lost':
				$title = __( '¿Olvidaste tu contraseña?', 'aula-virtual' );
				$text  = __( 'Escribe tu correo y te enviaremos un enlace para crear una nueva.', 'aula-virtual' );
				break;
			case 'login':
				$title = __( 'Ingresa a tu cuenta', 'aula-virtual' );
				$text  = __( 'Usa tu correo y tu contraseña.', 'aula-virtual' );
				break;
			case 'change':
			default:
				$title = __( 'Elige tu nueva contraseña', 'aula-virtual' );
				$text  = __( 'La usarás junto con tu correo para entrar al campus.', 'aula-virtual' );
				break;
		}

		$html = '<div class="av-login-intro">';

		if ( '' !== trim( $first_name ) ) {
			/* translators: %s: first name. */
			$html .= '<p class="av-login-intro__hello">' . esc_html( sprintf( __( 'Hola, %s', 'aula-virtual' ), trim( $first_name ) ) ) . '</p>';
		}

		$html .= '<h2 class="av-login-intro__title">' . esc_html( $title ) . '</h2>';
		$html .= '<p class="av-login-intro__text">' . esc_html( $text ) . '</p>';

		if ( 'done' === $variant && '' !== $link ) {
			$html .= '<p class="av-login-intro__action"><a class="button button-primary button-large" href="' . esc_url( $link ) . '">' . esc_html__( 'Entrar al campus', 'aula-virtual' ) . '</a></p>';
		}

		return $html . '</div>';
	}

	/**
	 * Browser tab title of the password pages.
	 *
	 * @param mixed $login_title Full title.
	 * @param mixed $title       Page part of the title.
	 * @return string
	 */
	public static function title( $login_title, $title = '' ): string {
		$login_title = is_string( $login_title ) ? $login_title : '';

		if ( ! in_array( self::action(), array( 'rp', 'resetpass' ), true ) || did_action( 'after_password_reset' ) ) {
			return $login_title;
		}

		$user = self::reset_user();
		$page = null !== $user && AccountHelper::needs_password( $user->ID ) ? __( 'Crea tu contraseña', 'aula-virtual' ) : __( 'Nueva contraseña', 'aula-virtual' );

		return $page . ' ‹ ' . wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
	}

	/**
	 * Shorter password hint.
	 *
	 * @return string
	 */
	public static function password_hint(): string {
		return __( 'Usa al menos 12 caracteres y mezcla mayúsculas, minúsculas, números y algún símbolo.', 'aula-virtual' );
	}

	/**
	 * User of the reset cookie WordPress set after validating the key.
	 *
	 * Only used to pick a text: by the time the form renders, WordPress has
	 * already checked the key (an invalid one redirects to "lostpassword").
	 *
	 * @return \WP_User|null
	 */
	private static function reset_user(): ?\WP_User {
		if ( ! defined( 'COOKIEHASH' ) ) {
			return null;
		}

		$cookie = 'wp-resetpass-' . COOKIEHASH;

		if ( ! isset( $_COOKIE[ $cookie ] ) || ! is_string( $_COOKIE[ $cookie ] ) || ! str_contains( $_COOKIE[ $cookie ], ':' ) ) {
			return null;
		}

		$login = sanitize_user( explode( ':', wp_unslash( $_COOKIE[ $cookie ] ), 2 )[0] );
		$user  = '' === $login ? false : get_user_by( 'login', $login );

		return $user instanceof \WP_User ? $user : null;
	}

	/**
	 * Sign-in URL that lands on the campus.
	 *
	 * @return string
	 */
	private static function campus_login_url(): string {
		$page_id = CampusController::page_id();
		$campus  = $page_id > 0 ? (string) get_permalink( $page_id ) : home_url( '/' );

		return wp_login_url( $campus );
	}
}
