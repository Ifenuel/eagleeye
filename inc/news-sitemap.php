<?php
/**
 * Google News sitemap.
 *
 * News crawlers want a small, fast feed of what was published in the last two
 * days — not the full archive. WordPress's own sitemap is organised for web
 * search, so this adds the dedicated one at /news-sitemap.xml.
 *
 * @package Eagle Eye
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Route /news-sitemap.xml to the sitemap renderer.
 */
function eagleeye_news_sitemap_rule() {
	add_rewrite_rule( '^news-sitemap\.xml$', 'index.php?eagleeye_news_sitemap=1', 'top' );
}
add_action( 'init', 'eagleeye_news_sitemap_rule' );

/**
 * Expose the sitemap query var.
 *
 * @param array $vars Query vars.
 * @return array
 */
function eagleeye_news_sitemap_vars( $vars ) {
	$vars[] = 'eagleeye_news_sitemap';

	return $vars;
}
add_filter( 'query_vars', 'eagleeye_news_sitemap_vars' );

/**
 * Flush rewrite rules once after this feature is first deployed.
 */
function eagleeye_news_sitemap_install() {
	if ( '1' === get_option( 'eagleeye_news_sitemap_version' ) ) {
		return;
	}

	flush_rewrite_rules( false );
	update_option( 'eagleeye_news_sitemap_version', '1', false );
}
add_action( 'init', 'eagleeye_news_sitemap_install', 99 );

/**
 * Stories published in the last two days, newest first.
 *
 * Google accepts at most 1,000 URLs in a news sitemap.
 *
 * @return WP_Post[]
 */
function eagleeye_news_sitemap_posts() {
	return get_posts(
		array(
			'post_type'              => 'post',
			'post_status'            => 'publish',
			'posts_per_page'         => 1000,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_term_cache' => false,
			'date_query'             => array(
				array( 'after' => '48 hours ago', 'inclusive' => true ),
			),
		)
	);
}

/**
 * Serve the news sitemap.
 */
function eagleeye_render_news_sitemap() {
	if ( ! get_query_var( 'eagleeye_news_sitemap' ) ) {
		return;
	}

	$publication = get_bloginfo( 'name' );
	$language    = str_replace( '_', '-', get_bloginfo( 'language' ) );
	$posts       = eagleeye_news_sitemap_posts();

	nocache_headers();
	header( 'Content-Type: application/xml; charset=UTF-8', true, 200 );

	echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
	echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">' . "\n";

	foreach ( $posts as $post ) {
		echo "\t<url>\n";
		echo "\t\t<loc>" . esc_url( get_permalink( $post ) ) . "</loc>\n";
		echo "\t\t<news:news>\n";
		echo "\t\t\t<news:publication>\n";
		echo "\t\t\t\t<news:name>" . esc_html( $publication ) . "</news:name>\n";
		echo "\t\t\t\t<news:language>" . esc_html( $language ) . "</news:language>\n";
		echo "\t\t\t</news:publication>\n";
		echo "\t\t\t<news:publication_date>" . esc_html( get_the_date( DATE_W3C, $post ) ) . "</news:publication_date>\n";
		echo "\t\t\t<news:title>" . esc_html( wp_strip_all_tags( get_the_title( $post ) ) ) . "</news:title>\n";
		echo "\t\t</news:news>\n";
		echo "\t</url>\n";
	}

	echo '</urlset>' . "\n";
	exit;
}
add_action( 'template_redirect', 'eagleeye_render_news_sitemap' );

/**
 * Keep /news-sitemap.xml as written instead of redirecting it to a trailing
 * slash. Search crawlers should be given one canonical address.
 *
 * @param string|false $redirect URL WordPress wants to redirect to.
 * @return string|false
 */
function eagleeye_news_sitemap_no_canonical( $redirect ) {
	if ( get_query_var( 'eagleeye_news_sitemap' ) ) {
		return false;
	}

	return $redirect;
}
add_filter( 'redirect_canonical', 'eagleeye_news_sitemap_no_canonical' );

/**
 * Point robots.txt at the news sitemap so crawlers find it without a console.
 *
 * @param string $output Robots.txt body.
 * @param bool   $public Whether the site is public.
 * @return string
 */
function eagleeye_robots_news_sitemap( $output, $public ) {
	if ( $public ) {
		$output .= "\nSitemap: " . esc_url_raw( home_url( '/news-sitemap.xml' ) ) . "\n";
	}

	return $output;
}
add_filter( 'robots_txt', 'eagleeye_robots_news_sitemap', 10, 2 );
