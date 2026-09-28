<?php
/**
 * Reads Tutor LMS data from the same database.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Migration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read-only access to Tutor LMS content and enrollments.
 *
 * Tutor stores courses, topics and lessons as posts, enrollments as
 * `tutor_enrolled` posts (author = student, parent = course), lesson
 * completion as user meta and course completion as comments. Nothing here
 * writes, and nothing here is called unless Tutor's post types exist.
 */
final class TutorReader {

	public const POST_COURSE     = 'courses';
	public const POST_TOPIC      = 'topics';
	public const POST_LESSON     = 'lesson';
	public const POST_ENROLLMENT = 'tutor_enrolled';

	/**
	 * Whether Tutor LMS data is present on this site.
	 *
	 * @return bool
	 */
	public static function is_available(): bool {
		global $wpdb;

		if ( defined( 'TUTOR_VERSION' ) || function_exists( 'tutor' ) ) {
			return true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one-off availability check.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", self::POST_COURSE ) ) > 0;
	}

	/**
	 * Every Tutor course, with counts.
	 *
	 * @return array<int, array{id: int, title: string, status: string, lessons: int, enrolled: int, completed: int, product_id: int}>
	 */
	public function courses(): array {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_COURSE,
				'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$courses = array();

		foreach ( $posts as $post ) {
			$courses[] = array(
				'id'         => (int) $post->ID,
				'title'      => get_the_title( $post ),
				'status'     => $post->post_status,
				'lessons'    => count( $this->lessons( (int) $post->ID ) ),
				'enrolled'   => $this->count_enrollments( (int) $post->ID ),
				'completed'  => $this->count_completions( (int) $post->ID ),
				'product_id' => (int) get_post_meta( (int) $post->ID, '_tutor_course_product_id', true ),
			);
		}

		return $courses;
	}

	/**
	 * A Tutor course post.
	 *
	 * @param int $course_id Course id.
	 * @return \WP_Post|null
	 */
	public function course( int $course_id ): ?\WP_Post {
		$post = get_post( $course_id );

		return $post instanceof \WP_Post && self::POST_COURSE === $post->post_type ? $post : null;
	}

	/**
	 * Topics of a course in order.
	 *
	 * @param int $course_id Course id.
	 * @return array<int, \WP_Post>
	 */
	public function topics( int $course_id ): array {
		return get_posts(
			array(
				'post_type'      => self::POST_TOPIC,
				'post_parent'    => $course_id,
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 200,
				'orderby'        => array( 'menu_order' => 'ASC', 'ID' => 'ASC' ),
			)
		);
	}

	/**
	 * Lessons of a topic in order.
	 *
	 * @param int $topic_id Topic id.
	 * @return array<int, \WP_Post>
	 */
	public function topic_lessons( int $topic_id ): array {
		return get_posts(
			array(
				'post_type'      => self::POST_LESSON,
				'post_parent'    => $topic_id,
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 500,
				'orderby'        => array( 'menu_order' => 'ASC', 'ID' => 'ASC' ),
			)
		);
	}

	/**
	 * Every lesson of a course, across its topics, in order.
	 *
	 * @param int $course_id Course id.
	 * @return array<int, \WP_Post>
	 */
	public function lessons( int $course_id ): array {
		$lessons = array();

		foreach ( $this->topics( $course_id ) as $topic ) {
			foreach ( $this->topic_lessons( (int) $topic->ID ) as $lesson ) {
				$lessons[] = $lesson;
			}
		}

		return $lessons;
	}

	/**
	 * Enrollments of a course.
	 *
	 * @param int $course_id Course id.
	 * @param int $limit     Maximum rows.
	 * @param int $offset    Rows to skip.
	 * @return array<int, array{id: int, user_id: int, status: string, date: string, order_id: int}>
	 */
	public function enrollments( int $course_id, int $limit = 500, int $offset = 0 ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- read-only migration source.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_author, post_status, post_date_gmt FROM {$wpdb->posts}
				 WHERE post_type = %s AND post_parent = %d
				 ORDER BY ID ASC LIMIT %d OFFSET %d",
				self::POST_ENROLLMENT,
				$course_id,
				$limit,
				$offset
			),
			ARRAY_A
		);

		$enrollments = array();

		foreach ( (array) $rows as $row ) {
			$enrollments[] = array(
				'id'       => (int) $row['ID'],
				'user_id'  => (int) $row['post_author'],
				'status'   => (string) $row['post_status'],
				'date'     => (string) $row['post_date_gmt'],
				'order_id' => (int) get_post_meta( (int) $row['ID'], '_tutor_enrolled_by_order_id', true ),
			);
		}

		return $enrollments;
	}

	/**
	 * Number of enrollments of a course.
	 *
	 * @param int $course_id Course id.
	 * @return int
	 */
	public function count_enrollments( int $course_id ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- read-only migration source.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_parent = %d",
				self::POST_ENROLLMENT,
				$course_id
			)
		);
	}

	/**
	 * Number of students who completed a course.
	 *
	 * @param int $course_id Course id.
	 * @return int
	 */
	public function count_completions( int $course_id ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- read-only migration source.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_type = %s AND comment_post_ID = %d",
				'course_completed',
				$course_id
			)
		);
	}

	/**
	 * When a student completed a course, or empty.
	 *
	 * @param int $user_id   Student id.
	 * @param int $course_id Course id.
	 * @return string MySQL datetime (GMT) or empty string.
	 */
	public function completed_course_at( int $user_id, int $course_id ): string {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- read-only migration source.
		$date = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT comment_date_gmt FROM {$wpdb->comments}
				 WHERE comment_type = %s AND comment_post_ID = %d AND user_id = %d
				 ORDER BY comment_ID DESC LIMIT 1",
				'course_completed',
				$course_id,
				$user_id
			)
		);

		return is_string( $date ) ? $date : '';
	}

	/**
	 * When a student completed a lesson, or empty.
	 *
	 * @param int $user_id   Student id.
	 * @param int $lesson_id Tutor lesson id.
	 * @return string MySQL datetime (GMT) or empty string.
	 */
	public function completed_lesson_at( int $user_id, int $lesson_id ): string {
		$value = get_user_meta( $user_id, '_tutor_completed_lesson_id_' . $lesson_id, true );

		if ( '' === $value || false === $value || null === $value ) {
			return '';
		}

		$timestamp = is_numeric( $value ) ? (int) $value : strtotime( (string) $value );

		return false === $timestamp || $timestamp <= 0 ? current_time( 'mysql', true ) : gmdate( 'Y-m-d H:i:s', $timestamp );
	}

	/**
	 * Category names of a course.
	 *
	 * @param int $course_id Course id.
	 * @return array<int, string>
	 */
	public function category_names( int $course_id ): array {
		$terms = get_the_terms( $course_id, 'course-category' );

		if ( ! is_array( $terms ) ) {
			return array();
		}

		return array_values( array_map( static fn( \WP_Term $term ): string => $term->name, $terms ) );
	}
}
