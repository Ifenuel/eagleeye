<?php
/**
 * Reusable presentation helpers.
 *
 * @package Eagle Eye
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Primary category for a post, falling back to the first assigned term.
 *
 * @param int $post_id Post ID.
 * @return WP_Term|null
 */
function eagleeye_primary_category( $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	$terms   = get_the_category( $post_id );

	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		return null;
	}

	// Prefer a term explicitly marked as primary by an editor.
	$primary_id = (int) get_post_meta( $post_id, '_eagleeye_primary_category', true );
	foreach ( $terms as $term ) {
		if ( $primary_id && (int) $term->term_id === $primary_id ) {
			return $term;
		}
	}

	return $terms[0];
}

/**
 * Print a category kicker.
 *
 * @param int    $post_id Post ID.
 * @param string $class   Extra class names.
 */
function eagleeye_kicker( $post_id = 0, $class = '' ) {
	$term = eagleeye_primary_category( $post_id );

	if ( ! $term ) {
		return;
	}

	printf(
		'<a class="kicker kicker--%1$s %2$s" href="%3$s">%4$s</a>',
		esc_attr( $term->slug ),
		esc_attr( $class ),
		esc_url( get_category_link( $term ) ),
		esc_html( $term->name )
	);
}

/**
 * Standfirst / deck for a post.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function eagleeye_dek( $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	$dek     = (string) get_post_meta( $post_id, '_eagleeye_dek', true );

	if ( '' === $dek ) {
		$dek = get_the_excerpt( $post_id );
	}

	return $dek;
}

/**
 * Reading time in minutes.
 *
 * @param int $post_id Post ID.
 * @return int
 */
function eagleeye_reading_time( $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	$content = wp_strip_all_tags( (string) get_post_field( 'post_content', $post_id ) );
	$words   = max( 1, str_word_count( $content ) );

	return max( 1, (int) ceil( $words / 220 ) );
}

/**
 * Publication line.
 *
 * @param bool $with_time Whether to include the time.
 */
function eagleeye_posted_on( $with_time = false ) {
	$format = $with_time ? get_option( 'date_format' ) . ' · ' . get_option( 'time_format' ) : get_option( 'date_format' );

	printf(
		'<time class="entry-date" datetime="%1$s">%2$s</time>',
		esc_attr( get_the_date( DATE_W3C ) ),
		esc_html( get_the_date( $format ) )
	);
}

/**
 * Compact relative time for cards ("18m", "3h", "2d").
 *
 * @param int $post_id Post ID.
 * @return string
 */
function eagleeye_short_time( $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	$diff    = max( 60, time() - (int) get_post_time( 'U', true, $post_id ) );

	if ( $diff < HOUR_IN_SECONDS ) {
		return sprintf( '%dm', max( 1, (int) floor( $diff / MINUTE_IN_SECONDS ) ) );
	}

	if ( $diff < DAY_IN_SECONDS ) {
		return sprintf( '%dh', (int) floor( $diff / HOUR_IN_SECONDS ) );
	}

	if ( $diff < WEEK_IN_SECONDS ) {
		return sprintf( '%dd', (int) floor( $diff / DAY_IN_SECONDS ) );
	}

	return get_the_date( 'M j', $post_id );
}

/**
 * Initials for an author, used for dependency-free avatars.
 *
 * @param WP_User|int|null $user User or ID.
 * @return string
 */
function eagleeye_initials( $user = null ) {
	if ( ! $user instanceof WP_User ) {
		$user = get_userdata( $user ? $user : get_the_author_meta( 'ID' ) );
	}

	if ( ! $user ) {
		return 'EE';
	}

	$name  = trim( $user->display_name ? $user->display_name : $user->user_login );
	$parts = preg_split( '/\s+/', $name );
	$first = mb_substr( $parts[0] ?? '', 0, 1 );
	$last  = count( $parts ) > 1 ? mb_substr( end( $parts ), 0, 1 ) : '';

	return strtoupper( $first . $last );
}

/**
 * Initials avatar with a stable hue.
 *
 * @param WP_User|int|null $user User or ID.
 * @param string           $size Size modifier: sm, md, lg.
 */
function eagleeye_avatar( $user = null, $size = 'md' ) {
	if ( ! $user instanceof WP_User ) {
		$user = get_userdata( $user ? $user : get_the_author_meta( 'ID' ) );
	}

	$name = $user ? $user->display_name : __( 'Eagle Eye', 'eagleeye' );
	$seed = crc32( $name );
	$hue  = $seed % 360;

	printf(
		'<span class="avatar avatar--%1$s" style="--avatar-hue:%2$d" aria-hidden="true">%3$s</span>',
		esc_attr( $size ),
		(int) $hue,
		esc_html( eagleeye_initials( $user ) )
	);
}

/**
 * Output a featured image, or a branded fallback.
 *
 * @param string $size Image size.
 * @param array  $attr Extra attributes.
 * @param int    $post_id Post ID.
 */
function eagleeye_thumbnail( $size = 'eagleeye-card', $attr = array(), $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	$attr    = wp_parse_args( $attr, array(
		'class' => 'post-thumb',
		'alt'   => the_title_attribute( array( 'echo' => false, 'post' => $post_id ) ),
	) );

	if ( has_post_thumbnail( $post_id ) ) {
		echo get_the_post_thumbnail( $post_id, $size, $attr ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return;
	}

	printf(
		'<span class="post-thumb post-thumb--fallback" aria-hidden="true"><img src="%s" alt="" width="1400" height="933" loading="lazy" decoding="async"></span>',
		esc_url( EAGLEEYE_URI . '/assets/img/fallback.jpg' )
	);
}

/**
 * Reporter credit for a story.
 *
 * The newsroom normally publishes under the Eagle Eye name. When an editor
 * fills the "Published by" field on a story, that person gets the credit.
 *
 * @param int $post_id Post ID. Defaults to the current post.
 * @return string Credit name, or an empty string when the publication is credited.
 */
function eagleeye_credit( $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_the_ID();

	return trim( (string) get_post_meta( $post_id, '_eagleeye_credit', true ) );
}

/**
 * Public byline for the publication.
 *
 * Newsroom accounts keep their real login, email and permissions in the admin.
 * Readers see the publication name instead of an individual's identity.
 *
 * @param array $args Options kept for template compatibility.
 */
function eagleeye_byline( $args = array() ) {
	$credit = eagleeye_credit();
	$name   = $credit ? sprintf( __( 'By %s', 'eagleeye' ), $credit ) : __( 'By Eagle Eye', 'eagleeye' );

	echo '<span class="byline byline--publication"><span class="byline__text"><span class="byline__name">' . esc_html( $name ) . '</span></span></span>';
}

/**
 * Social share actions for a single article.
 */
function eagleeye_share_links() {
	$url   = get_permalink();
	$title = get_the_title();
	$links = array(
		'x'        => array(
			'label' => 'X',
			'url'   => 'https://x.com/intent/post?url=' . rawurlencode( $url ) . '&text=' . rawurlencode( $title ),
		),
		'facebook' => array(
			'label' => 'Facebook',
			'url'   => 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode( $url ),
		),
		'whatsapp' => array(
			'label' => 'WhatsApp',
			'url'   => 'https://wa.me/?text=' . rawurlencode( $title . ' ' . $url ),
		),
		'linkedin' => array(
			'label' => 'LinkedIn',
			'url'   => 'https://www.linkedin.com/sharing/share-offsite/?url=' . rawurlencode( $url ),
		),
	);

	echo '<div class="share" aria-label="' . esc_attr__( 'Share this story', 'eagleeye' ) . '">';
	echo '<span class="share__label">' . esc_html__( 'Share', 'eagleeye' ) . '</span>';

	foreach ( $links as $key => $link ) {
		printf(
			'<a class="share__link share__link--%1$s" href="%2$s" target="_blank" rel="noopener noreferrer">%3$s</a>',
			esc_attr( $key ),
			esc_url( $link['url'] ),
			esc_html( $link['label'] )
		);
	}

	printf(
		'<button class="share__link share__link--copy" type="button" data-copy-url="%1$s">%2$s</button>',
		esc_url( $url ),
		esc_html__( 'Copy link', 'eagleeye' )
	);
	echo '</div>';
}

/**
 * Breadcrumb trail.
 */
function eagleeye_breadcrumbs() {
	if ( is_front_page() ) {
		return;
	}

	echo '<nav class="crumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'eagleeye' ) . '">';
	printf( '<a href="%s">%s</a>', esc_url( home_url( '/' ) ), esc_html__( 'Home', 'eagleeye' ) );

	if ( is_singular( 'post' ) ) {
		$term = eagleeye_primary_category();
		if ( $term ) {
			printf( '<span class="crumbs__sep">/</span><a href="%s">%s</a>', esc_url( get_category_link( $term ) ), esc_html( $term->name ) );
		}
	} elseif ( is_category() || is_tag() || is_tax() ) {
		printf( '<span class="crumbs__sep">/</span><span>%s</span>', esc_html( single_term_title( '', false ) ) );
	} elseif ( is_search() ) {
		printf( '<span class="crumbs__sep">/</span><span>%s</span>', esc_html__( 'Search', 'eagleeye' ) );
	} elseif ( is_page() ) {
		printf( '<span class="crumbs__sep">/</span><span>%s</span>', esc_html( get_the_title() ) );
	} elseif ( is_archive() ) {
		printf( '<span class="crumbs__sep">/</span><span>%s</span>', esc_html( get_the_archive_title() ) );
	} elseif ( is_404() ) {
		printf( '<span class="crumbs__sep">/</span><span>%s</span>', esc_html__( 'Not found', 'eagleeye' ) );
	}

	echo '</nav>';
}

/**
 * Accessible pagination.
 */
function eagleeye_pagination() {
	the_posts_pagination( array(
		'mid_size'           => 1,
		'prev_text'          => esc_html__( 'Previous', 'eagleeye' ),
		'next_text'          => esc_html__( 'Next', 'eagleeye' ),
		'screen_reader_text' => esc_html__( 'Stories navigation', 'eagleeye' ),
		'class'              => 'pagination',
	) );
}

/**
 * Editorial section heading with optional "more" link.
 *
 * @param string $title Section title.
 * @param string $url   Optional link.
 * @param string $label Link label.
 * @param string $note  Optional note.
 */
function eagleeye_section_head( $title, $url = '', $label = '', $note = '' ) {
	echo '<header class="section-head">';
	echo '<div class="section-head__main">';
	printf( '<h2 class="section-head__title">%s</h2>', esc_html( $title ) );
	if ( $note ) {
		printf( '<p class="section-head__note">%s</p>', esc_html( $note ) );
	}
	echo '</div>';

	if ( $url ) {
		printf(
			'<a class="section-head__more" href="%1$s">%2$s <span aria-hidden="true">&rarr;</span></a>',
			esc_url( $url ),
			esc_html( $label ? $label : __( 'See all', 'eagleeye' ) )
		);
	}
	echo '</header>';
}

/**
 * Category link by slug.
 *
 * @param string $slug Category slug.
 * @return string
 */
function eagleeye_category_url( $slug ) {
	$term = get_category_by_slug( $slug );

	return $term ? get_category_link( $term ) : home_url( '/' );
}

/**
 * Check a boolean post flag.
 *
 * @param string $key Meta key.
 * @param int    $post_id Post ID.
 * @return bool
 */
function eagleeye_flag( $key, $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_the_ID();

	return (bool) get_post_meta( $post_id, $key, true );
}

/**
 * Site tagline used in the masthead.
 *
 * @return string
 */
function eagleeye_tagline() {
	$tagline = get_bloginfo( 'description', 'display' );

	return $tagline ? $tagline : __( 'Seeing Beyond the Headlines', 'eagleeye' );
}

/**
 * The public standards library shown on policy and information pages.
 *
 * @return array
 */
function eagleeye_policy_meta() {
	return array(
		'privacy-policy'          => array(
			'number' => '01',
			'label'  => __( 'Privacy & data', 'eagleeye' ),
			'intro'  => __( 'How Eagle Eye collects, uses and protects personal information across the website, newsletter and advertising enquiries.', 'eagleeye' ),
			'accent' => 'gold',
		),
		'terms-and-conditions'    => array(
			'number' => '02',
			'label'  => __( 'Terms of use', 'eagleeye' ),
			'intro'  => __( 'The terms that govern your use of Eagle Eye, including acceptable use, intellectual property and liability.', 'eagleeye' ),
			'accent' => 'red',
		),
		'editorial-standards'     => array(
			'number' => '03',
			'label'  => __( 'Editorial standards', 'eagleeye' ),
			'intro'  => __( 'The editorial principles, sourcing rules and independence standards that govern everything we publish.', 'eagleeye' ),
			'accent' => 'gold',
		),
		'corrections-and-complaints' => array(
			'number' => '04',
			'label'  => __( 'Corrections & complaints', 'eagleeye' ),
			'intro'  => __( 'How to report an error and how our newsroom reviews, corrects and responds to complaints.', 'eagleeye' ),
			'accent' => 'red',
		),
		'cookie-notice'           => array(
			'number' => '05',
			'label'  => __( 'Cookies & tracking', 'eagleeye' ),
			'intro'  => __( 'What cookies the site uses, why they are set and how you can control them.', 'eagleeye' ),
			'accent' => 'gold',
		),
	);
}

/**
 * Published policy pages, in reading order.
 *
 * @return array
 */
function eagleeye_policy_library() {
	$library = array();

	foreach ( eagleeye_policy_meta() as $slug => $meta ) {
		$page = get_page_by_path( $slug );

		if ( ! $page || 'publish' !== $page->post_status ) {
			continue;
		}

		$library[] = array(
			'slug'  => $slug,
			'title' => get_the_title( $page ),
			'url'   => get_permalink( $page ),
			'number'=> $meta['number'],
			'label' => $meta['label'],
		);
	}

	return $library;
}

/**
 * Add stable IDs to second-level headings and build a table of contents.
 *
 * @param string $content Rendered page content.
 * @param string $prefix  Prefix for generated IDs.
 * @return array
 */
function eagleeye_prepare_page_content( $content, $prefix = 'section' ) {
	$updated = '';

	if ( preg_match( '/<p>\s*<strong>\s*Last updated:?\s*<\/strong>\s*(.*?)<\/p>/is', $content, $match ) ) {
		$updated = trim( wp_strip_all_tags( $match[1] ) );
		$content = preg_replace( '/<p>\s*<strong>\s*Last updated:?\s*<\/strong>\s*.*?<\/p>/is', '', $content, 1 );
	}

	$count = 0;
	$content = preg_replace_callback(
		'/<h2(?![^>]*\bid=)([^>]*)>(.*?)<\/h2>/is',
		function ( $matches ) use ( &$count, $prefix ) {
			$count++;
			$id = sanitize_title( $prefix . '-' . $count );

			return '<h2' . $matches[1] . ' id="' . esc_attr( $id ) . '">' . $matches[2] . '</h2>';
		},
		$content
	);

	$toc = array();

	if ( preg_match_all( '/<h2[^>]*\bid="([^"]+)"[^>]*>(.*?)<\/h2>/is', $content, $matches, PREG_SET_ORDER ) ) {
		foreach ( $matches as $match ) {
			$toc[] = array(
				'id'    => $match[1],
				'title' => trim( wp_strip_all_tags( $match[2] ) ),
			);
		}
	}

	return array(
		'content'   => $content,
		'toc'       => $toc,
		'updated'   => $updated,
		'read_time' => max( 1, (int) ceil( str_word_count( wp_strip_all_tags( $content ) ) / 220 ) ),
	);
}

/**
 * A short, human summary for a page hero.
 *
 * @param string $content Rendered content.
 * @param string $fallback Fallback text.
 * @return string
 */
function eagleeye_page_summary( $content, $fallback = '' ) {
	$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $content ) ) );

	if ( ! $text ) {
		return $fallback;
	}

	return wp_trim_words( $text, 34, '…' );
}

/**
 * Photo credit line for the current story's featured image.
 */
function eagleeye_photo_credit( $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	$credit  = get_post_meta( $post_id, '_ee_photo_credit', true );
	$license = get_post_meta( $post_id, '_ee_photo_license', true );
	$source  = get_post_meta( $post_id, '_ee_photo_source', true );

	if ( ! $credit && ! $license ) {
		return;
	}

	$label = $credit ? $credit : __( 'Eagle Eye', 'eagleeye' );
	if ( $license ) {
		$label .= ' / ' . $license;
	}

	echo '<span class="photo-credit">' . esc_html__( 'Photo: ', 'eagleeye' );
	if ( $source ) {
		printf( '<a href="%1$s" rel="nofollow noopener" target="_blank">%2$s</a>', esc_url( $source ), esc_html( $label ) );
	} else {
		echo esc_html( $label );
	}
	echo '</span>';
}
