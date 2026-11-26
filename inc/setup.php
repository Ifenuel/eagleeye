<?php
/**
 * Theme setup: supports, menus, image sizes.
 *
 * @package Eagle Eye
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register theme features.
 */
function eagleeye_setup() {
	load_theme_textdomain( 'eagleeye', EAGLEEYE_DIR . '/languages' );

	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'custom-logo', array(
		'height'      => 96,
		'width'       => 340,
		'flex-height' => true,
		'flex-width'  => true,
	) );
	add_theme_support( 'html5', array(
		'search-form',
		'comment-form',
		'comment-list',
		'gallery',
		'caption',
		'style',
		'script',
		'navigation-widgets',
	) );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'wp-block-styles' );
	add_editor_style( 'assets/css/editor.css' );

	// Editorial image crops. LocalWP's PHP build has no GD, so WordPress keeps
	// the uploaded originals; these sizes take effect on hosts with GD/Imagick.
	set_post_thumbnail_size( 1200, 800, true );
	add_image_size( 'eagleeye-lead', 1400, 900, true );
	add_image_size( 'eagleeye-card', 760, 520, true );
	add_image_size( 'eagleeye-wide', 1400, 640, true );
	add_image_size( 'eagleeye-thumb', 320, 240, true );
	add_image_size( 'eagleeye-portrait', 700, 900, true );

	register_nav_menus( array(
		'primary' => __( 'Primary Navigation', 'eagleeye' ),
		'footer'  => __( 'Footer — Sections', 'eagleeye' ),
		'legal'   => __( 'Footer — Company', 'eagleeye' ),
	) );

	add_post_type_support( 'post', 'excerpt' );
}
add_action( 'after_setup_theme', 'eagleeye_setup' );

/**
 * Content width for embeds and wide blocks.
 */
function eagleeye_content_width() {
	$GLOBALS['content_width'] = apply_filters( 'eagleeye_content_width', 760 );
}
add_action( 'after_setup_theme', 'eagleeye_content_width', 0 );

/**
 * Widget areas.
 */
function eagleeye_widgets_init() {
	register_sidebar( array(
		'name'          => __( 'Article Sidebar', 'eagleeye' ),
		'id'            => 'sidebar-article',
		'description'   => __( 'Displayed beside single articles. Keep it light for reading comfort.', 'eagleeye' ),
		'before_widget' => '<section id="%1$s" class="widget %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h2 class="widget__title">',
		'after_title'   => '</h2>',
	) );

	register_sidebar( array(
		'name'          => __( 'Homepage Rail', 'eagleeye' ),
		'id'            => 'sidebar-home',
		'description'   => __( 'Optional extra modules below the homepage rail.', 'eagleeye' ),
		'before_widget' => '<section id="%1$s" class="widget %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h2 class="widget__title">',
		'after_title'   => '</h2>',
	) );
}
add_action( 'widgets_init', 'eagleeye_widgets_init' );

/**
 * Body classes that describe the editorial context.
 *
 * @param array $classes Existing classes.
 * @return array
 */
function eagleeye_body_classes( $classes ) {
	if ( is_front_page() ) {
		$classes[] = 'is-front';
	}

	if ( is_singular( 'post' ) ) {
		$classes[] = 'is-article';
	}

	if ( ! is_active_sidebar( 'sidebar-article' ) ) {
		$classes[] = 'no-article-widgets';
	}

	return $classes;
}
add_filter( 'body_class', 'eagleeye_body_classes' );

/**
 * Section links used when no menu has been assigned yet.
 *
 * @param string $class Menu class.
 * @param bool   $footer Whether this is the footer list.
 */
function eagleeye_section_menu_fallback( $class = 'nav__menu', $footer = false ) {
	$slugs = array(
		'metro'         => __( 'Nigeria News', 'eagleeye' ),
		'politics'      => __( 'Politics', 'eagleeye' ),
		'international' => __( 'World News', 'eagleeye' ),
		'business'      => __( 'Business', 'eagleeye' ),
		'education'     => __( 'Education', 'eagleeye' ),
		'community-eye' => __( 'Community Eye', 'eagleeye' ),
		'campus-eye'    => __( 'Campus Eye', 'eagleeye' ),
		'entertainment' => __( 'Entertainment', 'eagleeye' ),
		'sports'        => __( 'Sports', 'eagleeye' ),
		'opinion'       => __( 'Opinion', 'eagleeye' ),
		'editorial'     => __( 'Editorial', 'eagleeye' ),
		'investigations' => __( 'Investigations', 'eagleeye' ),
		'interviews'    => __( 'Interviews', 'eagleeye' ),
		'lifestyle'     => __( 'Lifestyle', 'eagleeye' ),
	);

	echo '<ul class="' . esc_attr( $class ) . '">';

	if ( ! $footer ) {
		printf( '<li class="menu-item"><a href="%s">%s</a></li>', esc_url( home_url( '/' ) ), esc_html__( 'Home', 'eagleeye' ) );
	}

	foreach ( $slugs as $slug => $label ) {
		$term = get_category_by_slug( $slug );
		if ( ! $term ) {
			continue;
		}
		printf(
			'<li class="menu-item menu-item--%1$s"><a href="%2$s">%3$s</a></li>',
			esc_attr( $slug ),
			esc_url( get_category_link( $term ) ),
			esc_html( $label )
		);
	}

	echo '</ul>';
}

/**
 * Primary menu fallback.
 *
 * @param array $args Menu args.
 */
function eagleeye_nav_fallback( $args = array() ) {
	eagleeye_section_menu_fallback( $args['menu_class'] ?? 'nav__menu' );
}

/**
 * Footer sections fallback.
 */
function eagleeye_footer_sections_fallback() {
	eagleeye_section_menu_fallback( 'footer__menu', true );
}

/**
 * Footer company links fallback.
 */
function eagleeye_footer_company_fallback() {
	$pages = array(
		'about-eagleeye'         => __( 'About Eagle Eye', 'eagleeye' ),
		'contact-us'             => __( 'Contact Us', 'eagleeye' ),
		'advertise-with-us'      => __( 'Advertise With Us', 'eagleeye' ),
		'privacy-policy'         => __( 'Privacy Policy', 'eagleeye' ),
		'terms-and-conditions'   => __( 'Terms and Conditions', 'eagleeye' ),
		'editorial-standards'    => __( 'Editorial Standards', 'eagleeye' ),
		'corrections-and-complaints' => __( 'Corrections and Complaints', 'eagleeye' ),
		'cookie-notice'          => __( 'Cookie Notice', 'eagleeye' ),
	);

	echo '<ul class="footer__menu">';
	foreach ( $pages as $slug => $label ) {
		$page = get_page_by_path( $slug );
		if ( ! $page ) {
			continue;
		}
		printf( '<li><a href="%s">%s</a></li>', esc_url( get_permalink( $page ) ), esc_html( $label ) );
	}
	echo '</ul>';
}

/**
 * A sensible excerpt length for news decks.
 *
 * @param int $length Default length.
 * @return int
 */
function eagleeye_excerpt_length( $length ) {
	return is_admin() ? $length : 26;
}
add_filter( 'excerpt_length', 'eagleeye_excerpt_length', 20 );

/**
 * Replace the default excerpt ellipsis with a typographic one.
 *
 * @param string $more Default "more" string.
 * @return string
 */
function eagleeye_excerpt_more( $more ) {
	return is_admin() ? $more : '&hellip;';
}
add_filter( 'excerpt_more', 'eagleeye_excerpt_more' );

/**
 * Keep the comment form short: name and email only, no website field.
 *
 * @param array $fields Comment form fields.
 * @return array
 */
function eagleeye_comment_fields( $fields ) {
	unset( $fields['url'] );

	$fields['cookies'] = sprintf(
		'<p class="comment-form-cookies-consent"><input id="wp-comment-cookies-consent" name="wp-comment-cookies-consent" type="checkbox" value="yes" %1$s> <label for="wp-comment-cookies-consent">%2$s</label></p>',
		empty( $_POST['wp-comment-cookies-consent'] ) ? '' : 'checked', // phpcs:ignore WordPress.Security.NonceVerification
		esc_html__( 'Remember my name and email for next time.', 'eagleeye' )
	);

	return $fields;
}
add_filter( 'comment_form_default_fields', 'eagleeye_comment_fields' );

/**
 * Share the comment thread, not the comment form, when comments are read.
 */
function eagleeye_comment_form_args( $args ) {
	$args['title_reply'] = __( 'Join the conversation', 'eagleeye' );
	$args['comment_notes_before'] = '<p class="comment-notes">' . esc_html__( 'Your email address will not be published.', 'eagleeye' ) . '</p>';
	return $args;
}
add_filter( 'comment_form_defaults', 'eagleeye_comment_form_args' );
