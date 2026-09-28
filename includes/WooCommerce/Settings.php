<?php
/**
 * WooCommerce integration settings.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\WooCommerce;

use SIQA\AulaVirtual\Enrollments\EnrollmentStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Which order status enrolls, and what a refund does to the enrollment.
 *
 * Pure rules, kept apart from WooCommerce so they can be tested without it.
 */
final class Settings {

	public const OPTION_ENROLL_STATUS = 'av_wc_enroll_status';
	public const OPTION_REFUND_ACTION = 'av_wc_refund_action';

	public const ENROLL_ON_PROCESSING = 'processing';
	public const ENROLL_ON_COMPLETED  = 'completed';

	public const REFUND_NONE    = 'none';
	public const REFUND_SUSPEND = 'suspend';
	public const REFUND_CANCEL  = 'cancel';

	/**
	 * Order statuses that may trigger an enrollment.
	 *
	 * @return array<int, string>
	 */
	public static function paid_statuses(): array {
		return array( self::ENROLL_ON_PROCESSING, self::ENROLL_ON_COMPLETED );
	}

	/**
	 * Order statuses that may revoke an enrollment.
	 *
	 * @return array<int, string>
	 */
	public static function revoking_statuses(): array {
		return array( 'refunded', 'cancelled' );
	}

	/**
	 * Whether an order reaching `$order_status` should enroll, given the
	 * configured trigger.
	 *
	 * "Processing" means the payment is confirmed; "completed" is a later,
	 * often manual, step. With the trigger on processing, a completed order
	 * also enrolls (it passed through processing, but a manual completion may
	 * skip it). With the trigger on completed, processing waits.
	 *
	 * @param string $order_status Status the order just reached.
	 * @param string $trigger      Configured trigger.
	 * @return bool
	 */
	public static function should_enroll( string $order_status, string $trigger ): bool {
		if ( self::ENROLL_ON_COMPLETED === $trigger ) {
			return self::ENROLL_ON_COMPLETED === $order_status;
		}

		return in_array( $order_status, self::paid_statuses(), true );
	}

	/**
	 * Enrollment status a refund policy maps to, empty for "do nothing".
	 *
	 * @param string $policy Configured refund action.
	 * @return string
	 */
	public static function status_for_refund( string $policy ): string {
		return match ( $policy ) {
			self::REFUND_SUSPEND => EnrollmentStatus::SUSPENDED,
			self::REFUND_CANCEL  => EnrollmentStatus::CANCELLED,
			default              => '',
		};
	}

	/**
	 * Configured enrollment trigger.
	 *
	 * @return string
	 */
	public static function enroll_trigger(): string {
		$value = get_option( self::OPTION_ENROLL_STATUS, self::ENROLL_ON_PROCESSING );

		return is_string( $value ) && in_array( $value, self::paid_statuses(), true ) ? $value : self::ENROLL_ON_PROCESSING;
	}

	/**
	 * Configured refund policy.
	 *
	 * @return string
	 */
	public static function refund_policy(): string {
		$value = get_option( self::OPTION_REFUND_ACTION, self::REFUND_SUSPEND );

		return is_string( $value ) && in_array( $value, array( self::REFUND_NONE, self::REFUND_SUSPEND, self::REFUND_CANCEL ), true )
			? $value
			: self::REFUND_SUSPEND;
	}
}
