<?php
/**
 * Service provider contract.
 *
 * @package SEV\LMS
 */

declare( strict_types = 1 );

namespace SEV\LMS\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every plugin module is exposed to the core through a service provider.
 *
 * `register()` may only bind services into the container. `boot()` is where the
 * module attaches its WordPress hooks, so that every service is available by
 * the time any hook callback runs.
 */
interface ServiceProvider {

	/**
	 * Binds the module services into the container.
	 *
	 * @param Container $container Plugin container.
	 * @return void
	 */
	public function register( Container $container ): void;

	/**
	 * Registers the WordPress hooks owned by the module.
	 *
	 * @param Container $container Plugin container.
	 * @return void
	 */
	public function boot( Container $container ): void;
}
