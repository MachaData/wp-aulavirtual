<?php
/**
 * Schema installation and versioned migrations.
 *
 * @package SEV\LMS
 */

declare( strict_types = 1 );

namespace SEV\LMS\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Applies the schema and any data migration required by a version bump.
 *
 * dbDelta() is idempotent for structure, so the migrator only runs it when the
 * stored schema version differs from the shipped one. Data migrations that
 * dbDelta cannot express (backfills, renames, drops) are registered per version
 * in {@see Migrator::data_migrations()}.
 */
final class Migrator {

	/**
	 * Transient guarding against concurrent migrations.
	 */
	private const LOCK_KEY = 'sev_lms_migration_lock';

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
	 * Returns the schema version currently installed.
	 *
	 * @return string
	 */
	public function installed_version(): string {
		$version = get_option( Schema::VERSION_OPTION, '' );

		return is_string( $version ) ? $version : '';
	}

	/**
	 * Whether the database is behind the shipped schema.
	 *
	 * @return bool
	 */
	public function needs_migration(): bool {
		return version_compare( $this->installed_version(), Schema::VERSION, '<' );
	}

	/**
	 * Runs the migration when needed, guarding against concurrent requests.
	 *
	 * @return bool True when a migration ran.
	 */
	public function maybe_migrate(): bool {
		if ( ! $this->needs_migration() ) {
			return false;
		}

		if ( get_transient( self::LOCK_KEY ) ) {
			return false;
		}

		set_transient( self::LOCK_KEY, 1, MINUTE_IN_SECONDS * 5 );

		try {
			$this->migrate();
		} finally {
			delete_transient( self::LOCK_KEY );
		}

		return true;
	}

	/**
	 * Creates or updates every table and applies pending data migrations.
	 *
	 * @return void
	 */
	public function migrate(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$from = $this->installed_version();

		foreach ( $this->schema->definitions() as $sql ) {
			dbDelta( $sql );
		}

		foreach ( $this->data_migrations() as $version => $migration ) {
			if ( '' !== $from && version_compare( $from, (string) $version, '>=' ) ) {
				continue;
			}

			$migration();
		}

		update_option( Schema::VERSION_OPTION, Schema::VERSION, false );

		/**
		 * Fires after the SEV LMS schema has been installed or upgraded.
		 *
		 * @param string $to   Schema version now installed.
		 * @param string $from Schema version found before migrating.
		 */
		do_action( 'sev_lms/schema_migrated', Schema::VERSION, $from );
	}

	/**
	 * Data migrations keyed by the schema version that introduces them.
	 *
	 * Structural changes belong in {@see Schema::definitions()}; this list is
	 * only for backfills and clean-ups that dbDelta cannot perform.
	 *
	 * @return array<string, callable>
	 */
	private function data_migrations(): array {
		return array();
	}

	/**
	 * Drops every plugin table. Only used by the uninstall routine.
	 *
	 * @return void
	 */
	public function drop_tables(): void {
		global $wpdb;

		foreach ( $this->schema->tables() as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names cannot be prepared.
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}

		delete_option( Schema::VERSION_OPTION );
	}
}
