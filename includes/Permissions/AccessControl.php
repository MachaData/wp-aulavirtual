<?php
/**
 * Central permission checks.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Permissions;

use SIQA\AulaVirtual\Courses\CoursePostType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single place where the plugin answers "is this user allowed to do X".
 *
 * REST controllers, admin screens and campus templates all go through this
 * class so that a permission rule is written once. Content access that depends
 * on an enrollment is resolved by the Enrollments module through the
 * `aula_virtual/can_access_edition` filter.
 */
final class AccessControl {

	/**
	 * Checks a plugin capability for a user.
	 *
	 * @param string   $capability Capability name.
	 * @param int|null $user_id    User id, defaults to the current user.
	 * @return bool
	 */
	public function can( string $capability, ?int $user_id = null ): bool {
		if ( null === $user_id ) {
			return current_user_can( $capability );
		}

		return user_can( $user_id, $capability );
	}

	/**
	 * Ids of the courses the user authored (any status).
	 *
	 * @param int|null $user_id User id, defaults to the current user.
	 * @return array<int, int>
	 */
	public function own_course_ids( ?int $user_id = null ): array {
		$user_id = $user_id ?? get_current_user_id();

		if ( $user_id <= 0 ) {
			return array();
		}

		$ids = get_posts(
			array(
				'post_type'      => \SIQA\AulaVirtual\Courses\CoursePostType::POST_TYPE,
				'post_status'    => 'any',
				'author'         => $user_id,
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
			)
		);

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Whether the user may administer the LMS as a whole.
	 *
	 * @param int|null $user_id User id, defaults to the current user.
	 * @return bool
	 */
	public function can_manage( ?int $user_id = null ): bool {
		return $this->can( Capabilities::MANAGE_LMS, $user_id );
	}

	/**
	 * Whether the user may edit a specific course.
	 *
	 * Ownership is delegated to `map_meta_cap`, so an instructor passes only
	 * for the courses they authored.
	 *
	 * @param int      $course_id Course post id.
	 * @param int|null $user_id   User id, defaults to the current user.
	 * @return bool
	 */
	public function can_edit_course( int $course_id, ?int $user_id = null ): bool {
		$post = get_post( $course_id );

		if ( ! $post instanceof \WP_Post || CoursePostType::POST_TYPE !== $post->post_type ) {
			return false;
		}

		$capability = Capabilities::course_capabilities()['edit_post'];

		if ( null === $user_id ) {
			return current_user_can( $capability, $course_id );
		}

		return user_can( $user_id, $capability, $course_id );
	}

	/**
	 * Whether the user may manage the editions of a course.
	 *
	 * @param int      $course_id Course post id.
	 * @param int|null $user_id   User id, defaults to the current user.
	 * @return bool
	 */
	public function can_manage_editions( int $course_id, ?int $user_id = null ): bool {
		return $this->can( Capabilities::MANAGE_EDITIONS, $user_id ) && $this->can_edit_course( $course_id, $user_id );
	}

	/**
	 * Whether the user may consume the content of an edition.
	 *
	 * Managers always pass. For students the answer depends on an active
	 * enrollment, which the Enrollments module resolves through the filter
	 * below once that module is available.
	 *
	 * @param int      $edition_id Edition id.
	 * @param int|null $user_id    User id, defaults to the current user.
	 * @return bool
	 */
	public function can_access_edition( int $edition_id, ?int $user_id = null ): bool {
		$user_id = $user_id ?? get_current_user_id();

		$allowed = $this->can( Capabilities::VIEW_STUDENTS, $user_id );

		/**
		 * Filters whether a user can access the content of an edition.
		 *
		 * @param bool $allowed    Result so far.
		 * @param int  $edition_id Edition id.
		 * @param int  $user_id    User being checked.
		 */
		return (bool) apply_filters( 'aula_virtual/can_access_edition', $allowed, $edition_id, $user_id );
	}
}
