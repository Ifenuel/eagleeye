<?php
/**
 * SEO metadata and structured data.
 *
 * @package Eagle Eye
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Best available description for the current view.
 *
 * @return string
 */
function eagleeye_meta_description() {
	if ( is_singular() ) {
		$description = eagleeye_dek( get_queried_object_id() );
		if ( ! $description ) {
			$description = is_front_page()
				? __( 'Eagle Eye is an independent Nigerian digital news publication covering politics, business, sports, entertainment and world affairs.', 'eagleeye' )
				: get_bloginfo( 'description' );
		}
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$description = term_description();
		if ( ! $description ) {
			$description = sprintf(
				/* translators: %s: archive name. */
				__( 'Latest %s coverage, reporting and analysis from Eagle Eye.', 'eagleeye' ),
				single_term_title( '', false )
			);
		}
	} elseif ( is_search() ) {
		$description = sprintf(
			/* translators: %s: search query. */
			__( 'Search results for “%s” on Eagle Eye.', 'eagleeye' ),
			get_search_query()
		);
	} else {
		$description = __( 'Eagle Eye is an independent Nigerian digital news publication covering politics, business, sports, entertainment and world affairs.', 'eagleeye' );
	}

	return wp_trim_words( wp_strip_all_tags( $description ), 34, '…' );
}

/**
 * Social image for the current view.
 *
 * @return string
 */
function eagleeye_social_image() {
	if ( is_singular() && has_post_thumbnail() ) {
		$image = wp_get_attachment_image_src( get_post_thumbnail_id(), 'eagleeye-lead' );
		if ( $image ) {
			return $image[0];
		}
	}

	$custom_logo = get_theme_mod( 'custom_logo' );
	if ( $custom_logo ) {
		$image = wp_get_attachment_image_src( $custom_logo, 'full' );
		if ( $image ) {
			return $image[0];
		}
	}

	return EAGLEEYE_URI . '/assets/img/social-card.jpg';
}

/**
 * Keep the About page title from repeating the site name.
 *
 * @param array $parts Document title parts.
 * @return array
 */
function eagleeye_document_title_parts( $parts ) {
	if ( is_front_page() ) {
		$parts['title'] = __( 'Eagle Eye – Nigeria News, Politics, Business & World Headlines', 'eagleeye' );
		unset( $parts['tagline'], $parts['site'] );
		return $parts;
	}

	if ( is_page( 'about-eagleeye' ) ) {
		unset( $parts['site'] );
		$parts['tagline'] = get_bloginfo( 'description' );
	}

	return $parts;
}
add_filter( 'document_title_parts', 'eagleeye_document_title_parts' );

/**
 * Output metadata in the head.
 */
function eagleeye_head_meta() {
	$description = eagleeye_meta_description();
	$title       = wp_get_document_title();
	$url         = is_singular() ? get_permalink() : home_url( add_query_arg( array() ) );
	$image       = eagleeye_social_image();
	$image_size  = array( 1200, 630 );

	if ( is_singular() && has_post_thumbnail() ) {
		$thumb = wp_get_attachment_image_src( get_post_thumbnail_id(), 'eagleeye-lead' );

		if ( $thumb ) {
			$image_size = array( (int) $thumb[1], (int) $thumb[2] );
		}
	}

	if ( is_search() || is_404() ) {
		echo '<meta name="robots" content="noindex,follow">' . "\n";
	} else {
		echo '<meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1">' . "\n";
	}

	printf( '<meta name="description" content="%s">' . "\n", esc_attr( $description ) );
	printf( '<meta property="og:site_name" content="%s">' . "\n", esc_attr( get_bloginfo( 'name' ) ) );
	printf( '<meta property="og:type" content="%s">' . "\n", is_singular( 'post' ) ? 'article' : 'website' );
	printf( '<meta property="og:title" content="%s">' . "\n", esc_attr( $title ) );
	printf( '<meta property="og:description" content="%s">' . "\n", esc_attr( $description ) );
	printf( '<meta property="og:url" content="%s">' . "\n", esc_url( $url ) );
	printf( '<meta property="og:image" content="%s">' . "\n", esc_url( $image ) );
	printf( '<meta property="og:image:width" content="%d">' . "\n", $image_size[0] );
	printf( '<meta property="og:image:height" content="%d">' . "\n", $image_size[1] );
	printf( '<meta name="twitter:card" content="summary_large_image">' . "\n" );
	printf( '<meta name="twitter:title" content="%s">' . "\n", esc_attr( $title ) );
	printf( '<meta name="twitter:description" content="%s">' . "\n", esc_attr( $description ) );
	printf( '<meta name="twitter:image" content="%s">' . "\n", esc_url( $image ) );

	if ( is_singular( 'post' ) ) {
		printf( '<meta property="article:published_time" content="%s">' . "\n", esc_attr( get_the_date( DATE_W3C ) ) );
		printf( '<meta property="article:modified_time" content="%s">' . "\n", esc_attr( get_the_modified_date( DATE_W3C ) ) );

		$term = eagleeye_primary_category();
		if ( $term ) {
			printf( '<meta property="article:section" content="%s">' . "\n", esc_attr( $term->name ) );
		}
	}
}
add_action( 'wp_head', 'eagleeye_head_meta', 5 );

/**
 * JSON-LD graph for the current view.
 */
function eagleeye_structured_data() {
	$graph = array();

	$graph[] = array(
		'@type'         => 'NewsMediaOrganization',
		'@id'           => home_url( '/#organization' ),
		'name'          => get_bloginfo( 'name' ),
		'alternateName' => array( 'Eagle Eye News', 'eagleeyenews.online' ),
		'description'   => __( 'Eagle Eye is an independent Nigerian digital news publication covering politics, business, sports, entertainment and world affairs.', 'eagleeye' ),
		'url'           => home_url( '/' ),
		'email'         => 'hello@eagleeyenews.online',
		'telephone'     => '+2347048735721',
		'areaServed'    => array(
			'@type' => 'Country',
			'name'  => 'Nigeria',
		),
		'knowsAbout'    => array( 'Nigerian news', 'Politics', 'Business', 'Sports', 'Entertainment', 'World news' ),
		'logo'          => array(
			'@type'  => 'ImageObject',
			'url'    => EAGLEEYE_URI . '/assets/img/logo-mark.png',
			'width'  => 512,
			'height' => 512,
		),
		'contactPoint'  => array(
			'@type'             => 'ContactPoint',
			'contactType'       => 'customer service',
			'email'             => 'hello@eagleeyenews.online',
			'telephone'         => '+2347048735721',
			'availableLanguage' => 'English',
		),
		'sameAs'        => array_values( array_unique( array_merge( wp_list_pluck( eagleeye_socials(), 'url' ), eagleeye_entity_sameas() ) ) ),
		'publishingPrinciples'      => home_url( '/editorial-standards/' ),
		'correctionsPolicy'         => home_url( '/corrections-and-complaints/' ),
		'actionableFeedbackPolicy'  => home_url( '/contact-us/' ),
		'masthead'                  => home_url( '/about-eagleeye/' ),
	);

	$graph[] = array(
		'@type'           => 'WebSite',
		'@id'             => home_url( '/#website' ),
		'url'             => home_url( '/' ),
		'name'            => get_bloginfo( 'name' ),
		'alternateName'   => 'Eagle Eye News',
		'description'     => get_bloginfo( 'description' ),
		'publisher'       => array( '@id' => home_url( '/#organization' ) ),
		'inLanguage'      => get_bloginfo( 'language' ),
		'potentialAction' => array(
			'@type'       => 'SearchAction',
			'target'      => array(
				'@type'       => 'EntryPoint',
				'urlTemplate' => home_url( '/?s={search_term_string}' ),
			),
			'query-input' => 'required name=search_term_string',
		),
	);

	if ( is_author() ) {
		$author = get_queried_object();

		if ( $author instanceof WP_User ) {
			$person = array(
				'@type'       => 'Person',
				'@id'         => get_author_posts_url( $author->ID ) . '#person',
				'name'        => $author->display_name,
				'url'         => get_author_posts_url( $author->ID ),
				'description' => wp_strip_all_tags( get_the_author_meta( 'description', $author->ID ) ),
				'worksFor'    => array( '@id' => home_url( '/#organization' ) ),
			);

			$portrait = eagleeye_user_photo_url( $author->ID, array( 320, 320 ) );

			if ( $portrait ) {
				$person['image'] = $portrait;
			}

			$graph[] = $person;
		}
	}

	if ( is_singular( 'post' ) ) {
		$post_id = get_queried_object_id();
		$term    = eagleeye_primary_category( $post_id );
		$credit  = eagleeye_credit( $post_id );

		$article = array(
			'@type'            => 'NewsArticle',
			'@id'              => get_permalink() . '#article',
			'headline'         => wp_strip_all_tags( get_the_title() ),
			'description'      => eagleeye_dek( $post_id ),
			'datePublished'    => get_the_date( DATE_W3C, $post_id ),
			'dateModified'     => get_the_modified_date( DATE_W3C, $post_id ),
			'url'              => get_permalink(),
			'mainEntityOfPage' => array( '@id' => get_permalink() ),
			'author'           => $credit
				? array(
					'@type' => 'Person',
					'name'  => $credit,
				)
				: array(
					'@type' => 'Organization',
					'name'  => __( 'Eagle Eye', 'eagleeye' ),
					'url'   => home_url( '/' ),
				),
			'publisher'        => array( '@id' => home_url( '/#organization' ) ),
			'inLanguage'       => get_bloginfo( 'language' ),
			'isAccessibleForFree' => true,
		);

		if ( has_post_thumbnail( $post_id ) ) {
			$image = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), 'eagleeye-lead' );
			if ( $image ) {
				$article['image'] = array(
					'@type'  => 'ImageObject',
					'url'    => $image[0],
					'width'  => $image[1],
					'height' => $image[2],
				);
			}
		}

		if ( $term ) {
			$article['articleSection'] = $term->name;
		}

		$keywords = wp_get_post_terms( $post_id, array( 'category' ), array( 'fields' => 'names' ) );

		if ( $keywords && ! is_wp_error( $keywords ) ) {
			$article['keywords'] = implode( ', ', array_unique( $keywords ) );
		}

		$graph[] = $article;

		$graph[] = array(
			'@type'           => 'BreadcrumbList',
			'itemListElement' => array_filter( array(
				array(
					'@type'    => 'ListItem',
					'position' => 1,
					'name'     => __( 'Home', 'eagleeye' ),
					'item'     => home_url( '/' ),
				),
				$term ? array(
					'@type'    => 'ListItem',
					'position' => 2,
					'name'     => $term->name,
					'item'     => get_category_link( $term ),
				) : null,
				array(
					'@type'    => 'ListItem',
					'position' => $term ? 3 : 2,
					'name'     => wp_strip_all_tags( get_the_title() ),
					'item'     => get_permalink(),
				),
			) ),
		);
	}

	printf(
		'<script type="application/ld+json">%s</script>' . "\n",
		wp_json_encode( array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
	);
}
add_action( 'wp_head', 'eagleeye_structured_data', 6 );

/**
 * Send personal author archives to the latest news index.
 *
 * Bylines belong to the publication, not to an individual. The account and its
 * permissions stay intact in the admin; only the public profile is withdrawn.
 */
function eagleeye_redirect_author_archives() {
	if ( is_author() ) {
		wp_safe_redirect( home_url( '/latest/' ), 301 );
		exit;
	}
}
add_action( 'template_redirect', 'eagleeye_redirect_author_archives' );

/**
 * Withdraw the public author sitemap.
 *
 * Author archives now redirect to the news index, so listing personal profile
 * URLs in the sitemap would only send readers to a redirect.
 *
 * @param object|false $provider Sitemap provider.
 * @param string       $name     Provider name.
 * @return object|false
 */
function eagleeye_remove_author_sitemap( $provider, $name ) {
	return 'users' === $name ? false : $provider;
}
add_filter( 'wp_sitemaps_add_provider', 'eagleeye_remove_author_sitemap', 10, 2 );

/**
 * Small head cleanups that keep the source lean.
 */
function eagleeye_head_cleanup() {
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );
}
add_action( 'init', 'eagleeye_head_cleanup' );

/**
 * Drop the WordPress emoji script for performance.
 */
function eagleeye_disable_emojis() {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
}
add_action( 'init', 'eagleeye_disable_emojis' );
