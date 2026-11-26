<?php
/**
 * Editorial queries and lightweight read tracking.
 *
 * @package Eagle Eye
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base arguments shared by editorial queries.
 *
 * @param array $args Extra args.
 * @return array
 */
function eagleeye_query_args( $args = array() ) {
	return wp_parse_args( $args, array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	) );
}

/**
 * Most-read stories from the last 45 days.
 *
 * @param int   $limit Number of posts.
 * @param array $exclude Post IDs to exclude.
 * @return WP_Query
 */
function eagleeye_trending_query( $limit = 5, $exclude = array() ) {
	return new WP_Query( eagleeye_query_args( array(
		'posts_per_page' => $limit,
		'meta_key'       => '_eagleeye_views', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'orderby'        => array( 'meta_value_num' => 'DESC', 'date' => 'DESC' ),
		'date_query'     => array( array( 'after' => '45 days ago' ) ),
		'post__not_in'   => array_map( 'intval', $exclude ),
	) ) );
}

/**
 * Breaking-news stories for the ticker.
 *
 * @param int $limit Number of stories.
 * @return WP_Query
 */
function eagleeye_breaking_query( $limit = 4 ) {
	$query = new WP_Query( eagleeye_query_args( array(
		'posts_per_page' => $limit,
		'meta_key'       => '_eagleeye_breaking', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		'orderby'        => 'date',
		'order'          => 'DESC',
	) ) );

	if ( ! $query->have_posts() ) {
		$query = new WP_Query( eagleeye_query_args( array(
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
		) ) );
	}

	return $query;
}

/**
 * Latest stories for a category slug.
 *
 * @param string $slug  Category slug.
 * @param int    $limit Number of posts.
 * @param array  $exclude Post IDs to exclude.
 * @return WP_Query
 */
function eagleeye_desk_query( $slug, $limit = 4, $exclude = array() ) {
	return new WP_Query( eagleeye_query_args( array(
		'posts_per_page' => $limit,
		'category_name'  => $slug,
		'post__not_in'   => array_map( 'intval', $exclude ),
	) ) );
}

/**
 * Read counter (once per visitor per six hours).
 */
function eagleeye_track_views() {
	if ( ! is_singular( 'post' ) || is_preview() || is_user_logged_in() ) {
		return;
	}

	$post_id = get_queried_object_id();
	$ip      = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
	$key     = 'ee_view_' . $post_id . '_' . md5( $ip . wp_salt( 'nonce' ) );

	if ( get_transient( $key ) ) {
		return;
	}

	$views = (int) get_post_meta( $post_id, '_eagleeye_views', true );
	update_post_meta( $post_id, '_eagleeye_views', $views + 1 );
	set_transient( $key, 1, 6 * HOUR_IN_SECONDS );
}
add_action( 'template_redirect', 'eagleeye_track_views' );

/**
 * Formatted read count.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function eagleeye_views_label( $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	$views   = (int) get_post_meta( $post_id, '_eagleeye_views', true );

	if ( $views >= 1000 ) {
		return number_format_i18n( $views / 1000, 1 ) . 'k';
	}

	return number_format_i18n( $views );
}
