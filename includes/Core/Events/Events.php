<?php
/**
 * Catalogue of internal domain events.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Core\Events;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Names of the domain events emitted by the plugin.
 *
 * Business services dispatch these events; notifications, emails, logging and
 * reporting subscribe to them. Nothing that sends an email should ever be
 * called directly from enrollment or progress logic.
 */
final class Events {

	public const REGISTRATION_REQUEST_CREATED  = 'registration_request_created';
	public const REGISTRATION_REQUEST_APPROVED = 'registration_request_approved';
	public const REGISTRATION_REQUEST_REJECTED = 'registration_request_rejected';

	public const ENROLLMENT_CREATED   = 'enrollment_created';
	public const ENROLLMENT_APPROVED  = 'enrollment_approved';
	public const ENROLLMENT_SUSPENDED = 'enrollment_suspended';
	public const ENROLLMENT_CANCELLED = 'enrollment_cancelled';
	public const ENROLLMENT_EXPIRED   = 'enrollment_expired';
	public const ENROLLMENT_EXPIRING  = 'enrollment_expiring';

	public const LESSON_STARTED   = 'lesson_started';
	public const LESSON_COMPLETED = 'lesson_completed';
	public const LESSON_RELEASED  = 'lesson_released';

	public const COURSE_COMPLETED = 'course_completed';
	public const EDITION_STARTING = 'edition_starting';
	public const EDITION_FINISHED = 'edition_finished';

	public const LIVE_CLASS_UPCOMING  = 'live_class_upcoming';
	public const LIVE_CLASS_RECORDED  = 'live_class_recorded';
	public const ANNOUNCEMENT_CREATED = 'announcement_created';
	public const MATERIAL_ADDED       = 'material_added';
	public const COMMENT_POSTED       = 'comment_posted';

	public const CERTIFICATE_ISSUED = 'certificate_issued';

	public const IMPORT_COMPLETED = 'import_completed';
	public const ORDER_PROCESSED  = 'order_processed';

	/**
	 * Returns every event name declared by the core plugin.
	 *
	 * @return array<int, string>
	 */
	public static function all(): array {
		/**
		 * Filters the list of known domain events.
		 *
		 * Add-ons registering their own events should append them here so that
		 * the email and notification screens can offer them as triggers.
		 *
		 * @param array<int, string> $events Event names.
		 */
		return apply_filters(
			'aula_virtual/events',
			array_values( ( new \ReflectionClass( self::class ) )->getConstants() )
		);
	}
}
