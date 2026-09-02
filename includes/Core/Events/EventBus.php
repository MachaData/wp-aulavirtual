<?php
/**
 * Domain event dispatcher.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Core\Events;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dispatches domain events on top of the WordPress hook system.
 *
 * Using `do_action()` underneath keeps third-party integrations idiomatic while
 * the bus gives the plugin a single, greppable entry point and a stable payload
 * shape (`array<string, mixed>` plus the event name).
 */
final class EventBus {

	/**
	 * Hook prefix used for every domain event.
	 */
	private const HOOK_PREFIX = 'aula_virtual/event/';

	/**
	 * Dispatches an event to its subscribers.
	 *
	 * @param string               $event   Event name, from the Events catalogue.
	 * @param array<string, mixed> $payload Event payload.
	 * @return void
	 */
	public function dispatch( string $event, array $payload = array() ): void {
		$event = sanitize_key( $event );

		if ( '' === $event ) {
			return;
		}

		$payload['event']      = $event;
		$payload['dispatched'] = $payload['dispatched'] ?? current_time( 'mysql', true );

		/**
		 * Fires for a specific Aula Virtual domain event.
		 *
		 * @param array<string, mixed> $payload Event payload.
		 */
		do_action( self::HOOK_PREFIX . $event, $payload );

		/**
		 * Fires for every Aula Virtual domain event.
		 *
		 * @param string               $event   Event name.
		 * @param array<string, mixed> $payload Event payload.
		 */
		do_action( 'aula_virtual/event', $event, $payload );
	}

	/**
	 * Subscribes a listener to an event.
	 *
	 * @param string   $event    Event name.
	 * @param callable $listener Listener receiving the payload array.
	 * @param int      $priority Hook priority.
	 * @return void
	 */
	public function listen( string $event, callable $listener, int $priority = 10 ): void {
		add_action( self::HOOK_PREFIX . sanitize_key( $event ), $listener, $priority, 1 );
	}

	/**
	 * Returns the WordPress hook name backing an event.
	 *
	 * @param string $event Event name.
	 * @return string
	 */
	public static function hook( string $event ): string {
		return self::HOOK_PREFIX . sanitize_key( $event );
	}
}
