<?php
/**
 * Video provider registry and embedding.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Videos;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns a provider + URL (or code) into safe player markup.
 *
 * Providers: YouTube, Vimeo, Bunny Stream (with signed embeds for private
 * libraries), direct MP4/WebM, external URL (oEmbed), embedded iframe code
 * and shortcode. New providers are added through the `aula_virtual/video_providers`
 * filter without touching the templates.
 */
final class VideoEmbed {

	public const YOUTUBE   = 'youtube';
	public const VIMEO     = 'vimeo';
	public const BUNNY     = 'bunny';
	public const HTML5     = 'html5';
	public const URL       = 'url';
	public const EMBED     = 'embed';
	public const SHORTCODE = 'shortcode';

	public const OPTION_BUNNY_LIBRARY   = 'av_bunny_library_id';
	public const OPTION_BUNNY_TOKEN_KEY = 'av_bunny_token_key';
	public const OPTION_BUNNY_TOKEN_TTL = 'av_bunny_token_ttl';
	public const OPTION_BUNNY_CDN_KEY   = 'av_bunny_cdn_token_key';

	/**
	 * Providers offered in the editors, with labels.
	 *
	 * @return array<string, string>
	 */
	public static function providers(): array {
		/**
		 * Filters the video providers available in the plugin.
		 *
		 * @param array<string, string> $providers Provider key => label.
		 */
		return (array) apply_filters(
			'aula_virtual/video_providers',
			array(
				''              => __( 'Detectar automáticamente', 'aula-virtual' ),
				self::YOUTUBE   => 'YouTube',
				self::VIMEO     => 'Vimeo',
				self::BUNNY     => 'Bunny Stream',
				self::HTML5     => __( 'Archivo MP4 / WebM', 'aula-virtual' ),
				self::URL       => __( 'URL externa (oEmbed)', 'aula-virtual' ),
				self::EMBED     => __( 'Código incrustado (iframe)', 'aula-virtual' ),
				self::SHORTCODE => __( 'Shortcode', 'aula-virtual' ),
			)
		);
	}

	/**
	 * Guesses the provider from a URL or code.
	 *
	 * @param string $source URL or embed code.
	 * @return string Provider key, empty when unknown.
	 */
	public static function detect( string $source ): string {
		$source = trim( $source );

		if ( '' === $source ) {
			return '';
		}

		if ( str_starts_with( $source, '[' ) && str_ends_with( $source, ']' ) ) {
			return self::SHORTCODE;
		}

		if ( str_contains( $source, '<iframe' ) ) {
			return self::EMBED;
		}

		$host = strtolower( (string) wp_parse_url( $source, PHP_URL_HOST ) );

		if ( '' === $host ) {
			return '';
		}

		if ( str_contains( $host, 'youtube.com' ) || str_contains( $host, 'youtu.be' ) ) {
			return self::YOUTUBE;
		}

		if ( str_contains( $host, 'vimeo.com' ) ) {
			return self::VIMEO;
		}

		if ( str_contains( $host, 'mediadelivery.net' ) || str_contains( $host, 'b-cdn.net' ) || str_contains( $host, 'bunnycdn.com' ) ) {
			return self::BUNNY;
		}

		if ( preg_match( '/\.(mp4|webm|ogv|m3u8)(\?.*)?$/i', $source ) ) {
			return self::HTML5;
		}

		return self::URL;
	}

	/**
	 * Renders the player markup for a lesson video.
	 *
	 * @param string $provider Provider key, empty to detect.
	 * @param string $source   URL or code.
	 * @return string Sanitised HTML, empty when nothing can be shown.
	 */
	public static function render( string $provider, string $source ): string {
		$source   = trim( $source );
		$provider = '' === $provider ? self::detect( $source ) : $provider;

		if ( '' === $source ) {
			return '';
		}

		/**
		 * Short-circuits the rendering of a video provider.
		 *
		 * Return non-empty markup to take over; used by add-ons registering
		 * their own providers.
		 *
		 * @param string $html     Markup so far (empty).
		 * @param string $provider Provider key.
		 * @param string $source   URL or code.
		 */
		$custom = (string) apply_filters( 'aula_virtual/render_video', '', $provider, $source );

		if ( '' !== $custom ) {
			return $custom;
		}

		switch ( $provider ) {
			case self::BUNNY:
				$embed = self::bunny_embed_url( $source );

				if ( '' !== $embed ) {
					return self::iframe( $embed );
				}

				// Sin biblioteca configurada, una URL del CDN (playlist.m3u8) se
				// reproduce directamente, firmada si la pull zone exige token.
				return self::html5( self::bunny_cdn_url( $source ) );

			case self::HTML5:
				return self::html5( $source );

			case self::EMBED:
				return self::sanitize_iframe( $source );

			case self::SHORTCODE:
				$source = \SIQA\AulaVirtual\Curriculum\LessonService::allowed_shortcode( $source );

				return '' === $source ? '' : wp_kses_post( do_shortcode( $source ) );

			case self::YOUTUBE:
			case self::VIMEO:
			case self::URL:
			default:
				$embed = wp_oembed_get( esc_url_raw( $source ) );

				if ( false !== $embed && '' !== $embed ) {
					// wp_kses_post() strips iframes, which is what oEmbed returns
					// for YouTube and Vimeo: keep the iframe only if its host is
					// allow-listed, otherwise fall back to a plain link.
					$safe = str_contains( $embed, '<iframe' ) ? self::sanitize_iframe( $embed ) : wp_kses_post( $embed );

					if ( '' !== $safe ) {
						return $safe;
					}
				}

				$url = esc_url_raw( $source );

				return '' === $url ? '' : '<a class="av-video__link" href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Abrir el video', 'aula-virtual' ) . '</a>';
		}
	}

	/**
	 * Builds the Bunny Stream embed URL, signing it when the library uses
	 * token authentication.
	 *
	 * Accepts a full embed URL, a play URL (`video.bunnycdn.com/play/…`), a
	 * `{library}/{video}` pair or just the video GUID when the library id is
	 * configured.
	 *
	 * @param string $source Bunny reference.
	 * @return string Embed URL, empty when it cannot be resolved.
	 */
	public static function bunny_embed_url( string $source ): string {
		$parsed = self::bunny_parse( $source, (string) get_option( self::OPTION_BUNNY_LIBRARY, '' ) );

		if ( null === $parsed ) {
			return '';
		}

		[ $library, $video ] = $parsed;

		$url = 'https://iframe.mediadelivery.net/embed/' . rawurlencode( $library ) . '/' . rawurlencode( $video );
		$key = (string) get_option( self::OPTION_BUNNY_TOKEN_KEY, '' );

		$args = array(
			'autoplay' => 'false',
			'preload'  => 'true',
		);

		if ( '' !== $key ) {
			$ttl     = min( DAY_IN_SECONDS, max( 300, (int) get_option( self::OPTION_BUNNY_TOKEN_TTL, 6 * HOUR_IN_SECONDS ) ) );
			$expires = time() + $ttl;

			$args['token']   = self::bunny_token( $key, $video, $expires );
			$args['expires'] = (string) $expires;
		}

		return add_query_arg( $args, $url );
	}

	/**
	 * Extracts library id and video GUID from any Bunny reference.
	 *
	 * @param string $source          Bunny reference.
	 * @param string $default_library Library id from settings.
	 * @return array{0: string, 1: string}|null
	 */
	public static function bunny_parse( string $source, string $default_library ): ?array {
		$source = trim( $source );

		if ( preg_match( '#(?:embed|play)/(\d+)/([0-9a-f-]{36}|[A-Za-z0-9-]+)#i', $source, $m ) ) {
			return array( $m[1], $m[2] );
		}

		if ( preg_match( '#^(\d+)/([0-9a-f-]{36})$#i', $source, $m ) ) {
			return array( $m[1], $m[2] );
		}

		if ( preg_match( '#^[0-9a-f-]{36}$#i', $source ) && '' !== $default_library ) {
			return array( $default_library, $source );
		}

		// URL del CDN de Bunny Stream: https://vz-xxxx.b-cdn.net/{guid}/playlist.m3u8
		// El GUID esta en la ruta; la biblioteca solo se conoce por configuracion.
		if ( '' !== $default_library && preg_match( '#b-cdn\.net/([0-9a-f-]{36})/#i', $source, $m ) ) {
			return array( $default_library, $m[1] );
		}

		return null;
	}

	/**
	 * Signs a Bunny CDN (pull zone) URL when the zone uses token authentication.
	 *
	 * Bunny's URL token: base64url( sha256_raw( key . path . expires ) ), sent
	 * as `?token=…&expires=…`. Without a configured key the URL is returned
	 * untouched.
	 *
	 * @param string $url CDN URL.
	 * @return string
	 */
	public static function bunny_cdn_url( string $url ): string {
		$url = esc_url_raw( $url );
		$key = (string) get_option( self::OPTION_BUNNY_CDN_KEY, '' );

		if ( '' === $url || '' === $key ) {
			return $url;
		}

		$ttl     = min( DAY_IN_SECONDS, max( 300, (int) get_option( self::OPTION_BUNNY_TOKEN_TTL, 6 * HOUR_IN_SECONDS ) ) );
		$expires = time() + $ttl;

		return add_query_arg(
			array(
				'token'   => self::bunny_cdn_token( $key, (string) wp_parse_url( $url, PHP_URL_PATH ), $expires ),
				'expires' => (string) $expires,
			),
			$url
		);
	}

	/**
	 * Bunny CDN token for a path: base64url of the raw SHA-256 of key + path + expires.
	 *
	 * @param string $key     Pull zone token authentication key.
	 * @param string $path    URL path, starting with "/".
	 * @param int    $expires Unix timestamp.
	 * @return string
	 */
	public static function bunny_cdn_token( string $key, string $path, int $expires ): string {
		$raw = hash( 'sha256', $key . $path . $expires, true );

		return rtrim( strtr( base64_encode( $raw ), '+/', '-_' ), '=' );
	}

	/**
	 * Player for a direct file. HLS playlists get the hls.js loader for the
	 * browsers that cannot play them natively.
	 *
	 * @param string $url File or playlist URL.
	 * @return string
	 */
	private static function html5( string $url ): string {
		$url = esc_url_raw( $url );

		if ( '' === $url ) {
			return '';
		}

		if ( preg_match( '/\.m3u8(\?.*)?$/i', $url ) ) {
			if ( function_exists( 'wp_enqueue_script' ) ) {
				wp_enqueue_script( 'av-player', AV_URL . 'assets/js/player.js', array(), AV_VERSION, true );
			}

			return '<video controls playsinline preload="metadata" controlsList="nodownload" data-hls="' . esc_url( $url ) . '"></video>';
		}

		return '<video controls playsinline preload="metadata" controlsList="nodownload" src="' . esc_url( $url ) . '"></video>';
	}

	/**
	 * Bunny Stream embed token: SHA-256 of key + video id + expiration.
	 *
	 * @param string $key     Token authentication key of the library.
	 * @param string $video   Video GUID.
	 * @param int    $expires Unix timestamp.
	 * @return string
	 */
	public static function bunny_token( string $key, string $video, int $expires ): string {
		return hash( 'sha256', $key . $video . $expires );
	}

	/**
	 * Wraps a URL in a responsive iframe.
	 *
	 * @param string $url Player URL.
	 * @return string
	 */
	private static function iframe( string $url ): string {
		if ( '' === $url ) {
			return '';
		}

		return '<iframe src="' . esc_url( $url ) . '" loading="lazy" allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>';
	}

	/**
	 * Keeps only an iframe from an allow-listed host out of pasted embed code.
	 *
	 * @param string $code Pasted code.
	 * @return string
	 */
	private static function sanitize_iframe( string $code ): string {
		if ( ! preg_match( '/<iframe[^>]*\ssrc=["\']([^"\']+)["\']/i', $code, $m ) ) {
			return '';
		}

		$src  = esc_url_raw( html_entity_decode( $m[1] ) );
		$host = strtolower( (string) wp_parse_url( $src, PHP_URL_HOST ) );

		/**
		 * Filters the hosts allowed in pasted iframe codes.
		 *
		 * @param array<int, string> $hosts Host suffixes.
		 */
		$allowed = (array) apply_filters(
			'aula_virtual/embed_hosts',
			array( 'youtube.com', 'youtube-nocookie.com', 'player.vimeo.com', 'iframe.mediadelivery.net', 'drive.google.com', 'loom.com', 'wistia.com', 'wistia.net', 'fast.wistia.net', 'streamable.com' )
		);

		foreach ( $allowed as $suffix ) {
			if ( $host === $suffix || str_ends_with( $host, '.' . $suffix ) ) {
				return self::iframe( $src );
			}
		}

		return '';
	}
}
