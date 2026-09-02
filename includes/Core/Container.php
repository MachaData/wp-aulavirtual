<?php
/**
 * Minimal service container.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Core;

use InvalidArgumentException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores factories and resolved instances for the plugin services.
 *
 * The container is deliberately small: it only supports explicit factories and
 * shared instances, which is all the plugin needs to keep services testable
 * without pulling a full dependency-injection framework into WordPress.
 */
final class Container {

	/**
	 * Registered factories keyed by service id.
	 *
	 * @var array<string, callable>
	 */
	private array $factories = array();

	/**
	 * Resolved shared instances keyed by service id.
	 *
	 * @var array<string, mixed>
	 */
	private array $instances = array();

	/**
	 * Service ids currently being resolved, used to detect circular references.
	 *
	 * @var array<string, bool>
	 */
	private array $resolving = array();

	/**
	 * Registers a shared service factory.
	 *
	 * @param string   $id      Service identifier, usually a class name.
	 * @param callable $factory Factory receiving the container and returning the service.
	 * @return void
	 */
	public function singleton( string $id, callable $factory ): void {
		$this->factories[ $id ] = $factory;
		unset( $this->instances[ $id ] );
	}

	/**
	 * Registers an already built instance.
	 *
	 * @param string $id       Service identifier.
	 * @param mixed  $instance Service instance.
	 * @return void
	 */
	public function instance( string $id, mixed $instance ): void {
		$this->instances[ $id ] = $instance;
	}

	/**
	 * Checks whether a service is known to the container.
	 *
	 * @param string $id Service identifier.
	 * @return bool
	 */
	public function has( string $id ): bool {
		return array_key_exists( $id, $this->instances ) || isset( $this->factories[ $id ] );
	}

	/**
	 * Resolves a service.
	 *
	 * @param string $id Service identifier.
	 * @return mixed
	 * @throws InvalidArgumentException When the service is unknown or circular.
	 */
	public function get( string $id ): mixed {
		if ( array_key_exists( $id, $this->instances ) ) {
			return $this->instances[ $id ];
		}

		if ( ! isset( $this->factories[ $id ] ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Aula Virtual: el servicio "%s" no esta registrado.', esc_html( $id ) )
			);
		}

		if ( isset( $this->resolving[ $id ] ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Aula Virtual: dependencia circular al resolver "%s".', esc_html( $id ) )
			);
		}

		$this->resolving[ $id ] = true;

		try {
			$this->instances[ $id ] = ( $this->factories[ $id ] )( $this );
		} finally {
			unset( $this->resolving[ $id ] );
		}

		return $this->instances[ $id ];
	}
}
