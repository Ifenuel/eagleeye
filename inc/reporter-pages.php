<?php
/**
 * Reporter pages: every story credited to a name gets its own listing.
 *
 * @package Eagle Eye
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public URL for a credited reporter.
 *
 * @param string $name Credit name.
 * @return string
 */
function eagleeye_credit_url( $name ) {
	return home_url( '/reporter/' . sanitize_title( $name ) . '/' );
}

/**
 * Turn a reporter slug back into the stored credit name.
 *
 * @param string $slug URL slug.
 * @return string Credit name, or an empty string when it does not exist.
 */
function eagleeye_credit_name_from_slug( $slug ) {
	global $wpdb;

	$slug = sanitize_title( $slug );

	if ( '' === $slug ) {
		return '';
	}

	$values = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value <> '' LIMIT 200",
			'_eagleeye_credit'
		)
	);

	foreach ( (array) $values as $value ) {
		if ( sanitize_title( $value ) === $slug ) {
			return (string) $value;
		}
	}

	return '';
}

/**
 * Register the /reporter/<name>/ route.
 */
function eagleeye_reporter_rewrite() {
	add_rewrite_rule( '^reporter/([^/]+)/?$', 'index.php?ee_reporter=$matches[1]', 'top' );
}
add_action( 'init', 'eagleeye_reporter_rewrite' );

/**
 * Allow the reporter query var.
 *
 * @param array $vars Public query vars.
 * @return array
 */
function eagleeye_reporter_query_var( $vars ) {
	$vars[] = 'ee_reporter';

	return $vars;
}
add_filter( 'query_vars', 'eagleeye_reporter_query_var' );

/**
 * Filter the main query to the credited reporter's stories.
 *
 * @param WP_Query $query Query.
 */
function eagleeye_reporter_query( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	$slug = $query->get( 'ee_reporter' );

	if ( ! $slug ) {
		return;
	}

	$name = eagleeye_credit_name_from_slug( $slug );

	if ( '' === $name ) {
		$query->set_404();
		status_header( 404 );
		nocache_headers();
		return;
	}

	$query->set( 'post_type', 'post' );
	$query->set( 'post_status', 'publish' );
	$query->set(
		'meta_query',
		array(
			array(
				'key'     => '_eagleeye_credit',
				'value'   => $name,
				'compare' => '=',
			),
		)
	);
}
add_action( 'pre_get_posts', 'eagleeye_reporter_query' );

/**
 * Use the archive template for reporter pages.
 *
 * @param string $template Template path.
 * @return string
 */
function eagleeye_reporter_template( $template ) {
	if ( get_query_var( 'ee_reporter' ) ) {
		$archive = locate_template( 'archive.php' );

		if ( $archive ) {
			return $archive;
		}
	}

	return $template;
}
add_filter( 'template_include', 'eagleeye_reporter_template' );

/**
 * Title and heading for reporter pages.
 *
 * @param string $title Archive title.
 * @return string
 */
function eagleeye_reporter_archive_title( $title ) {
	$slug = get_query_var( 'ee_reporter' );

	if ( ! $slug ) {
		return $title;
	}

	$name = eagleeye_credit_name_from_slug( $slug );

	return $name ? sprintf( /* translators: %s: reporter name. */ __( 'Stories by %s', 'eagleeye' ), $name ) : $title;
}
add_filter( 'get_the_archive_title', 'eagleeye_reporter_archive_title' );

/**
 * Document title for reporter pages.
 *
 * @param array $parts Title parts.
 * @return array
 */
function eagleeye_reporter_document_title( $parts ) {
	$slug = get_query_var( 'ee_reporter' );

	if ( $slug ) {
		$name = eagleeye_credit_name_from_slug( $slug );

		if ( $name ) {
			$parts['title'] = sprintf( /* translators: %s: reporter name. */ __( 'Stories by %s', 'eagleeye' ), $name );
		}
	}

	return $parts;
}
add_filter( 'document_title_parts', 'eagleeye_reporter_document_title' );
