<?php
/**
 * Custom table definitions.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Owns the names and the DDL of every custom table used by the plugin.
 *
 * Transactional data (editions, enrollments, progress, live classes) lives in
 * custom tables instead of post meta: these rows are queried by ranges and
 * joins that meta queries cannot serve efficiently once an institution has a
 * few thousand students.
 */
final class Schema {

	/**
	 * Prefix appended to the WordPress table prefix.
	 */
	public const PREFIX = 'av_';

	/**
	 * Schema version stored in the options table.
	 */
	public const VERSION = '1.2.0';

	/**
	 * Option key holding the installed schema version.
	 */
	public const VERSION_OPTION = 'av_db_version';

	/**
	 * Logical table names handled by the plugin.
	 *
	 * @var array<int, string>
	 */
	private const TABLES = array(
		'editions',
		'modules',
		'lessons',
		'materials',
		'enrollments',
		'progress',
		'registration_requests',
		'enrollment_links',
		'announcements',
		'live_classes',
		'certificates',
		'email_templates',
		'import_jobs',
		'lesson_comments',
		'logs',
		'audit_log',
	);

	/**
	 * Returns the fully qualified name of a table.
	 *
	 * @param string $name Logical table name.
	 * @return string
	 */
	public function table( string $name ): string {
		global $wpdb;

		return $wpdb->prefix . self::PREFIX . $name;
	}

	/**
	 * Returns every fully qualified table name.
	 *
	 * @return array<int, string>
	 */
	public function tables(): array {
		return array_map( array( $this, 'table' ), self::TABLES );
	}

	/**
	 * Returns the logical table names.
	 *
	 * @return array<int, string>
	 */
	public function table_names(): array {
		return self::TABLES;
	}

	/**
	 * Checks whether a table already exists in the database.
	 *
	 * @param string $name Logical table name.
	 * @return bool
	 */
	public function exists( string $name ): bool {
		global $wpdb;

		$table = $this->table( $name );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- schema check, not cacheable.
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );

		return $found === $table;
	}

	/**
	 * Builds the CREATE TABLE statements consumed by dbDelta().
	 *
	 * @return array<string, string> Statements keyed by logical table name.
	 */
	public function definitions(): array {
		global $wpdb;

		$collate = $wpdb->get_charset_collate();

		$definitions = array();

		$definitions['editions'] = "CREATE TABLE {$this->table( 'editions' )} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			course_id bigint(20) unsigned NOT NULL DEFAULT 0,
			name varchar(191) NOT NULL DEFAULT '',
			code varchar(64) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'draft',
			modality varchar(20) NOT NULL DEFAULT 'recorded',
			start_date datetime DEFAULT NULL,
			end_date datetime DEFAULT NULL,
			access_start datetime DEFAULT NULL,
			access_end datetime DEFAULT NULL,
			timezone varchar(64) NOT NULL DEFAULT '',
			schedule_days varchar(191) NOT NULL DEFAULT '',
			schedule_time varchar(191) NOT NULL DEFAULT '',
			price_display varchar(64) NOT NULL DEFAULT '',
			capacity int(11) unsigned NOT NULL DEFAULT 0,
			enrollment_methods longtext NULL,
			settings longtext NULL,
			product_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime DEFAULT NULL,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY code (code),
			KEY course_id (course_id),
			KEY status (status),
			KEY product_id (product_id),
			KEY start_date (start_date)
		) {$collate};";

		$definitions['modules'] = "CREATE TABLE {$this->table( 'modules' )} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			course_id bigint(20) unsigned NOT NULL DEFAULT 0,
			edition_id bigint(20) unsigned NOT NULL DEFAULT 0,
			title varchar(191) NOT NULL DEFAULT '',
			description longtext NULL,
			position int(11) NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'publish',
			created_at datetime DEFAULT NULL,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY course_edition (course_id,edition_id),
			KEY position (position)
		) {$collate};";

		$definitions['lessons'] = "CREATE TABLE {$this->table( 'lessons' )} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			course_id bigint(20) unsigned NOT NULL DEFAULT 0,
			edition_id bigint(20) unsigned NOT NULL DEFAULT 0,
			module_id bigint(20) unsigned NOT NULL DEFAULT 0,
			title varchar(191) NOT NULL DEFAULT '',
			description longtext NULL,
			content longtext NULL,
			lesson_type varchar(20) NOT NULL DEFAULT 'video',
			video_provider varchar(32) NOT NULL DEFAULT '',
			video_url text NULL,
			video_meta longtext NULL,
			duration int(11) unsigned NOT NULL DEFAULT 0,
			featured_image bigint(20) unsigned NOT NULL DEFAULT 0,
			is_preview tinyint(1) NOT NULL DEFAULT 0,
			position int(11) NOT NULL DEFAULT 0,
			release_type varchar(20) NOT NULL DEFAULT 'immediate',
			release_date datetime DEFAULT NULL,
			release_offset int(11) NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'publish',
			created_at datetime DEFAULT NULL,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY course_edition (course_id,edition_id),
			KEY module_id (module_id),
			KEY position (position),
			KEY status (status)
		) {$collate};";

		$definitions['materials'] = "CREATE TABLE {$this->table( 'materials' )} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			course_id bigint(20) unsigned NOT NULL DEFAULT 0,
			edition_id bigint(20) unsigned NOT NULL DEFAULT 0,
			lesson_id bigint(20) unsigned NOT NULL DEFAULT 0,
			title varchar(191) NOT NULL DEFAULT '',
			description text NULL,
			attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
			external_url text NULL,
			file_type varchar(20) NOT NULL DEFAULT '',
			downloadable tinyint(1) NOT NULL DEFAULT 1,
			position int(11) NOT NULL DEFAULT 0,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY course_edition (course_id,edition_id),
			KEY lesson_id (lesson_id)
		) {$collate};";

		$definitions['enrollments'] = "CREATE TABLE {$this->table( 'enrollments' )} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			course_id bigint(20) unsigned NOT NULL DEFAULT 0,
			edition_id bigint(20) unsigned NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'pending',
			source varchar(20) NOT NULL DEFAULT 'manual',
			order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			progress_percentage decimal(5,2) NOT NULL DEFAULT 0.00,
			enrolled_at datetime DEFAULT NULL,
			approved_at datetime DEFAULT NULL,
			completed_at datetime DEFAULT NULL,
			expires_at datetime DEFAULT NULL,
			last_activity datetime DEFAULT NULL,
			approved_by bigint(20) unsigned NOT NULL DEFAULT 0,
			notes text NULL,
			created_at datetime DEFAULT NULL,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_edition (user_id,edition_id),
			KEY course_id (course_id),
			KEY edition_status (edition_id,status),
			KEY status (status),
			KEY source (source),
			KEY order_id (order_id)
		) {$collate};";

		$definitions['progress'] = "CREATE TABLE {$this->table( 'progress' )} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			course_id bigint(20) unsigned NOT NULL DEFAULT 0,
			edition_id bigint(20) unsigned NOT NULL DEFAULT 0,
			lesson_id bigint(20) unsigned NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'started',
			percentage decimal(5,2) NOT NULL DEFAULT 0.00,
			time_spent int(11) unsigned NOT NULL DEFAULT 0,
			started_at datetime DEFAULT NULL,
			completed_at datetime DEFAULT NULL,
			last_activity datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_lesson (user_id,lesson_id),
			KEY edition_user (edition_id,user_id),
			KEY course_id (course_id),
			KEY status (status)
		) {$collate};";

		$definitions['registration_requests'] = "CREATE TABLE {$this->table( 'registration_requests' )} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			course_id bigint(20) unsigned NOT NULL DEFAULT 0,
			edition_id bigint(20) unsigned NOT NULL DEFAULT 0,
			link_id bigint(20) unsigned NOT NULL DEFAULT 0,
			first_name varchar(100) NOT NULL DEFAULT '',
			last_name varchar(100) NOT NULL DEFAULT '',
			email varchar(190) NOT NULL DEFAULT '',
			phone varchar(40) NOT NULL DEFAULT '',
			document varchar(40) NOT NULL DEFAULT '',
			custom_data longtext NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			rejection_reason text NULL,
			created_at datetime DEFAULT NULL,
			approved_at datetime DEFAULT NULL,
			approved_by bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY edition_status (edition_id,status),
			KEY status (status),
			KEY email (email)
		) {$collate};";

		$definitions['enrollment_links'] = "CREATE TABLE {$this->table( 'enrollment_links' )} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			course_id bigint(20) unsigned NOT NULL DEFAULT 0,
			edition_id bigint(20) unsigned NOT NULL DEFAULT 0,
			token varchar(64) NOT NULL DEFAULT '',
			label varchar(191) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'active',
			requires_approval tinyint(1) NOT NULL DEFAULT 1,
			max_uses int(11) unsigned NOT NULL DEFAULT 0,
			uses int(11) unsigned NOT NULL DEFAULT 0,
			fields longtext NULL,
			expires_at datetime DEFAULT NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token (token),
			KEY edition_id (edition_id),
			KEY status (status)
		) {$collate};";

		$definitions['announcements'] = "CREATE TABLE {$this->table( 'announcements' )} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			course_id bigint(20) unsigned NOT NULL DEFAULT 0,
			edition_id bigint(20) unsigned NOT NULL DEFAULT 0,
			author_id bigint(20) unsigned NOT NULL DEFAULT 0,
			title varchar(191) NOT NULL DEFAULT '',
			content longtext NULL,
			send_email tinyint(1) NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'publish',
			created_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY course_edition (course_id,edition_id),
			KEY created_at (created_at)
		) {$collate};";

		$definitions['live_classes'] = "CREATE TABLE {$this->table( 'live_classes' )} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			lesson_id bigint(20) unsigned NOT NULL DEFAULT 0,
			edition_id bigint(20) unsigned NOT NULL DEFAULT 0,
			provider varchar(32) NOT NULL DEFAULT 'other',
			meeting_url text NULL,
			meeting_id varchar(191) NOT NULL DEFAULT '',
			access_code varchar(191) NOT NULL DEFAULT '',
			start_datetime datetime DEFAULT NULL,
			end_datetime datetime DEFAULT NULL,
			timezone varchar(64) NOT NULL DEFAULT '',
			open_before int(11) NOT NULL DEFAULT 15,
			close_after int(11) NOT NULL DEFAULT 30,
			message text NULL,
			recording_url text NULL,
			status varchar(20) NOT NULL DEFAULT 'scheduled',
			created_at datetime DEFAULT NULL,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY lesson_id (lesson_id),
			KEY edition_start (edition_id,start_datetime),
			KEY status (status)
		) {$collate};";

		$definitions['certificates'] = "CREATE TABLE {$this->table( 'certificates' )} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			course_id bigint(20) unsigned NOT NULL DEFAULT 0,
			edition_id bigint(20) unsigned NOT NULL DEFAULT 0,
			certificate_code varchar(64) NOT NULL DEFAULT '',
			verification_hash varchar(64) NOT NULL DEFAULT '',
			file_url text NULL,
			status varchar(20) NOT NULL DEFAULT 'issued',
			issued_at datetime DEFAULT NULL,
			issued_by bigint(20) unsigned NOT NULL DEFAULT 0,
			revoked_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY certificate_code (certificate_code),
			KEY user_id (user_id),
			KEY course_edition (course_id,edition_id)
		) {$collate};";

		$definitions['email_templates'] = "CREATE TABLE {$this->table( 'email_templates' )} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event varchar(64) NOT NULL DEFAULT '',
			recipient varchar(32) NOT NULL DEFAULT 'student',
			subject varchar(255) NOT NULL DEFAULT '',
			body longtext NULL,
			enabled tinyint(1) NOT NULL DEFAULT 1,
			updated_at datetime DEFAULT NULL,
			updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY event_recipient (event,recipient)
		) {$collate};";

		$definitions['import_jobs'] = "CREATE TABLE {$this->table( 'import_jobs' )} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			course_id bigint(20) unsigned NOT NULL DEFAULT 0,
			edition_id bigint(20) unsigned NOT NULL DEFAULT 0,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			filename varchar(255) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'pending',
			total_rows int(11) unsigned NOT NULL DEFAULT 0,
			processed_rows int(11) unsigned NOT NULL DEFAULT 0,
			created_users int(11) unsigned NOT NULL DEFAULT 0,
			enrolled int(11) unsigned NOT NULL DEFAULT 0,
			skipped int(11) unsigned NOT NULL DEFAULT 0,
			failed int(11) unsigned NOT NULL DEFAULT 0,
			send_welcome tinyint(1) NOT NULL DEFAULT 0,
			mapping longtext NULL,
			errors longtext NULL,
			created_at datetime DEFAULT NULL,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY edition_id (edition_id)
		) {$collate};";

		$definitions['lesson_comments'] = "CREATE TABLE {$this->table( 'lesson_comments' )} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			course_id bigint(20) unsigned NOT NULL DEFAULT 0,
			edition_id bigint(20) unsigned NOT NULL DEFAULT 0,
			lesson_id bigint(20) unsigned NOT NULL DEFAULT 0,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			parent_id bigint(20) unsigned NOT NULL DEFAULT 0,
			is_staff tinyint(1) NOT NULL DEFAULT 0,
			content text NULL,
			status varchar(20) NOT NULL DEFAULT 'approved',
			created_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY lesson_status (lesson_id,status),
			KEY edition_id (edition_id),
			KEY user_id (user_id)
		) {$collate};";

		$definitions['logs'] = "CREATE TABLE {$this->table( 'logs' )} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			level varchar(20) NOT NULL DEFAULT 'info',
			channel varchar(40) NOT NULL DEFAULT 'general',
			message text NULL,
			context longtext NULL,
			object_type varchar(40) NOT NULL DEFAULT '',
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY level (level),
			KEY channel (channel),
			KEY created_at (created_at)
		) {$collate};";

		$definitions['audit_log'] = "CREATE TABLE {$this->table( 'audit_log' )} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
			action varchar(64) NOT NULL DEFAULT '',
			object_type varchar(40) NOT NULL DEFAULT '',
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			target_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			data longtext NULL,
			created_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY actor_id (actor_id),
			KEY object (object_type,object_id),
			KEY created_at (created_at)
		) {$collate};";

		return $definitions;
	}
}
