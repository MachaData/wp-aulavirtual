<?php
/**
 * Course queries.
 *
 * @package SIQA\AulaVirtual
 */

declare( strict_types = 1 );

namespace SIQA\AulaVirtual\Courses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads courses through WP_Query.
 *
 * Courses are posts, so this repository wraps WP_Query instead of extending the
 * custom-table repository. It exists so that catalogue, admin list and REST
 * screens share one query shape and one meta-priming strategy.
 */
final class CourseRepository {

	/**
	 * Returns a page of courses.
	 *
	 * @param array{search?: string, status?: string|array<int, string>, category?: string, tag?: string, author?: int, orderby?: string, order?: string, page?: int, per_page?: int} $args Query arguments.
	 * @return array{items: array<int, \WP_Post>, total: int, pages: int, page: int, per_page: int}
	 */
	public function paginate( array $args = array() ): array {
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = min( 100, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );

		$query_args = array(
			'post_type'              => CoursePostType::POST_TYPE,
			'post_status'            => $args['status'] ?? 'publish',
			'posts_per_page'         => $per_page,
			'paged'                  => $page,
			'orderby'                => $this->orderby( (string) ( $args['orderby'] ?? 'date' ) ),
			'order'                  => 'ASC' === strtoupper( (string) ( $args['order'] ?? 'DESC' ) ) ? 'ASC' : 'DESC',
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => true,
			'no_found_rows'          => false,
		);

		if ( ! empty( $args['search'] ) ) {
			$query_args['s'] = sanitize_text_field( (string) $args['search'] );
		}

		if ( ! empty( $args['author'] ) ) {
			$query_args['author'] = (int) $args['author'];
		}

		$tax_query = array();

		if ( ! empty( $args['category'] ) ) {
			$tax_query[] = array(
				'taxonomy' => CoursePostType::TAX_CATEGORY,
				'field'    => 'slug',
				'terms'    => sanitize_title( (string) $args['category'] ),
			);
		}

		if ( ! empty( $args['tag'] ) ) {
			$tax_query[] = array(
				'taxonomy' => CoursePostType::TAX_TAG,
				'field'    => 'slug',
				'terms'    => sanitize_title( (string) $args['tag'] ),
			);
		}

		if ( array() !== $tax_query ) {
			$query_args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- catalogue filtering by taxonomy is the intended behaviour.
		}

		$query = new \WP_Query( $query_args );

		return array(
			'items'    => $query->posts,
			'total'    => (int) $query->found_posts,
			'pages'    => (int) $query->max_num_pages,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	/**
	 * Returns a course by id.
	 *
	 * @param int $course_id Course post id.
	 * @return \WP_Post|null
	 */
	public function find( int $course_id ): ?\WP_Post {
		$post = get_post( $course_id );

		if ( ! $post instanceof \WP_Post || CoursePostType::POST_TYPE !== $post->post_type ) {
			return null;
		}

		return $post;
	}

	/**
	 * Validates the requested ordering.
	 *
	 * @param string $orderby Requested ordering.
	 * @return string
	 */
	private function orderby( string $orderby ): string {
		$allowed = array( 'date', 'title', 'modified', 'menu_order', 'ID' );

		return in_array( $orderby, $allowed, true ) ? $orderby : 'date';
	}
}
