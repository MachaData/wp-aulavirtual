<?php
/**
 * Plugin logger.
 *
 * @package SEV\LMS
 */

declare( strict_types = 1 );

namespace SEV\LMS\Core;

use SEV\LMS\Database\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Writes plugin events to the log table.
 *
 * Verbosity is configurable so production sites can keep only warnings and
 * errors. Context is stored as JSON and callers are expected to keep personal
 * data out of it: ids are enough to reconstruct what happened.
 */
final class Logger {

	public const DEBUG    = 'debug';
	public const INFO     = 'info';
	public const WARNING  = 'warning';
	public const ERROR    = 'error';
	public const CRITICAL = 'critical';

	/**
	 * Option controlling the minimum level that gets persisted.
	 */
	public const LEVEL_OPTION = 'sev_lms_log_level';

	/**
	 * Severity order used to compare levels.
	 *
	 * @var array<string, int>
	 */
	private const SEVERITY = array(
		self::DEBUG    => 10,
		self::INFO     => 20,
		self::WARNING  => 30,
		self::ERROR    => 40,
		self::CRITICAL => 50,
	);

	/**
	 * Table definitions.
	 *
	 * @var Schema
	 */
	private Schema $schema;

	/**
	 * Constructor.
	 *
	 * @param Schema $schema Table definitions.
	 */
	public function __construct( Schema $schema ) {
		$this->schema = $schema;
	}

	/**
	 * Logs a debug message.
	 *
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Context data.
	 * @param string               $channel Channel name.
	 * @return void
	 */
	public function debug( string $message, array $context = array(), string $channel = 'general' ): void {
		$this->log( self::DEBUG, $message, $context, $channel );
	}

	/**
	 * Logs an informational message.
	 *
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Context data.
	 * @param string               $channel Channel name.
	 * @return void
	 */
	public function info( string $message, array $context = array(), string $channel = 'general' ): void {
		$this->log( self::INFO, $message, $context, $channel );
	}

	/**
	 * Logs a warning.
	 *
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Context data.
	 * @param string               $channel Channel name.
	 * @return void
	 */
	public function warning( string $message, array $context = array(), string $channel = 'general' ): void {
		$this->log( self::WARNING, $message, $context, $channel );
	}

	/**
	 * Logs an error.
	 *
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Context data.
	 * @param string               $channel Channel name.
	 * @return void
	 */
	public function error( string $message, array $context = array(), string $channel = 'general' ): void {
		$this->log( self::ERROR, $message, $context, $channel );
	}

	/**
	 * Persists a log entry when its level passes the configured threshold.
	 *
	 * @param string               $level   Log level.
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Context data.
	 * @param string               $channel Channel name.
	 * @return void
	 */
	public function log( string $level, string $message, array $context = array(), string $channel = 'general' ): void {
		global $wpdb;

		$level = isset( self::SEVERITY[ $level ] ) ? $level : self::INFO;

		if ( ! $this->should_log( $level ) ) {
			return;
		}

		$object_type = isset( $context['object_type'] ) ? sanitize_key( (string) $context['object_type'] ) : '';
		$object_id   = isset( $context['object_id'] ) ? (int) $context['object_id'] : 0;

		unset( $context['object_type'], $context['object_id'] );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- log table write.
		$wpdb->insert(
			$this->schema->table( 'logs' ),
			array(
				'level'       => $level,
				'channel'     => sanitize_key( $channel ),
				'message'     => sanitize_textarea_field( $message ),
				'context'     => wp_json_encode( $context ),
				'object_type' => $object_type,
				'object_id'   => $object_id,
				'user_id'     => get_current_user_id(),
				'created_at'  => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s' )
		);
	}

	/**
	 * Whether a level is at or above the configured threshold.
	 *
	 * @param string $level Level to check.
	 * @return bool
	 */
	private function should_log( string $level ): bool {
		$threshold = get_option( self::LEVEL_OPTION, self::INFO );
		$threshold = is_string( $threshold ) ? $threshold : self::INFO;

		if ( 'off' === $threshold ) {
			return false;
		}

		if ( ! isset( self::SEVERITY[ $threshold ] ) ) {
			$threshold = self::INFO;
		}

		return self::SEVERITY[ $level ] >= self::SEVERITY[ $threshold ];
	}
}
