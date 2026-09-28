<?php
/**
 * Fixed-window rate limiter on transients.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Counts hits per bucket in a time window. Good enough to stop scripted
 * abuse of public forms; not atomic, so a burst of parallel requests can
 * slip a few over the limit.
 */
final class RateLimiter {

	/**
	 * Registers a hit and says whether the bucket is still under the limit.
	 *
	 * @param string $bucket Logical bucket (form + identity).
	 * @param int    $limit  Maximum hits in the window.
	 * @param int    $window Window in seconds.
	 * @return bool True when the hit is allowed.
	 */
	public static function hit( string $bucket, int $limit, int $window = HOUR_IN_SECONDS ): bool {
		$key   = 'av_rl_' . md5( $bucket );
		$count = (int) get_transient( $key );

		if ( $count >= $limit ) {
			return false;
		}

		set_transient( $key, $count + 1, $window );

		return true;
	}

	/**
	 * Whether the bucket already reached the limit, without counting a hit.
	 *
	 * @param string $bucket Logical bucket.
	 * @param int    $limit  Maximum hits in the window.
	 * @return bool
	 */
	public static function exceeded( string $bucket, int $limit ): bool {
		return (int) get_transient( 'av_rl_' . md5( $bucket ) ) >= $limit;
	}

	/**
	 * Address of the client. Only REMOTE_ADDR is trusted; sites behind a
	 * proxy (Cloudflare) can map the real address with the filter.
	 *
	 * @return string
	 */
	public static function client_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';

		/**
		 * Filters the client address used for rate limits.
		 *
		 * Behind Cloudflare: return $_SERVER['HTTP_CF_CONNECTING_IP'] when the
		 * request really comes from a Cloudflare range.
		 *
		 * @param string $ip Address from REMOTE_ADDR.
		 */
		return (string) apply_filters( 'aula_virtual/client_ip', $ip );
	}

	/**
	 * Normalises an email so plus-addressing does not multiply a limit.
	 *
	 * @param string $email Email.
	 * @return string
	 */
	public static function email_bucket( string $email ): string {
		$email = strtolower( trim( $email ) );

		return (string) preg_replace( '/\+[^@]*@/', '@', $email );
	}
}
