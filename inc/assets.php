<?php
/**
 * Front-end assets.
 *
 * @package Eagle Eye
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue styles and scripts.
 */
function eagleeye_assets() {
	$css = EAGLEEYE_DIR . '/assets/css/main.css';
	$js  = EAGLEEYE_DIR . '/assets/js/main.js';
	$brand_css = EAGLEEYE_DIR . '/assets/css/brand.css';
	$refresh_css = EAGLEEYE_DIR . '/assets/css/refresh.css';
	$motion_css = EAGLEEYE_DIR . '/assets/css/motion.css';
	$front_css  = EAGLEEYE_DIR . '/assets/css/frontpage.css';
	$gsap      = EAGLEEYE_DIR . '/assets/js/gsap.min.js';
	$scroll    = EAGLEEYE_DIR . '/assets/js/ScrollTrigger.min.js';
	$motion    = EAGLEEYE_DIR . '/assets/js/motion.js';

	wp_enqueue_style(
		'eagleeye-fonts',
		EAGLEEYE_URI . '/assets/css/fonts.css',
		array(),
		EAGLEEYE_VERSION
	);

	wp_enqueue_style(
		'eagleeye-style',
		EAGLEEYE_URI . '/assets/css/main.css',
		array( 'eagleeye-fonts' ),
		eagleeye_asset_version( $css )
	);

	wp_enqueue_style(
		'eagleeye-brand',
		EAGLEEYE_URI . '/assets/css/brand.css',
		array( 'eagleeye-style' ),
		eagleeye_asset_version( $brand_css )
	);

	wp_enqueue_style(
		'eagleeye-refresh',
		EAGLEEYE_URI . '/assets/css/refresh.css',
		array( 'eagleeye-brand' ),
		eagleeye_asset_version( $refresh_css )
	);

	wp_enqueue_style(
		'eagleeye-motion',
		EAGLEEYE_URI . '/assets/css/motion.css',
		array( 'eagleeye-refresh' ),
		eagleeye_asset_version( $motion_css )
	);

	/* Front-page composition sits last: it composes the masthead and hero
	   rather than repainting the design system. */
	wp_enqueue_style(
		'eagleeye-frontpage',
		EAGLEEYE_URI . '/assets/css/frontpage.css',
		array( 'eagleeye-motion' ),
		eagleeye_asset_version( $front_css )
	);

	wp_enqueue_script(
		'eagleeye-script',
		EAGLEEYE_URI . '/assets/js/main.js',
		array(),
		eagleeye_asset_version( $js ),
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);

	wp_localize_script( 'eagleeye-script', 'eagleeyeData', array(
		'homeUrl' => home_url( '/' ),
		'labels'  => array(
			'menuOpen'  => __( 'Open navigation', 'eagleeye' ),
			'menuClose' => __( 'Close navigation', 'eagleeye' ),
			'copied'    => __( 'Link copied', 'eagleeye' ),
			'copy'      => __( 'Copy link', 'eagleeye' ),
		),
	) );

	/* GSAP and ScrollTrigger are roughly 100 KB of JavaScript. On a phone they
	   cost real data and buy effects most readers scroll straight past, so they
	   are only sent to larger screens. The rest of the motion layer (reading
	   progress, the policy contents rail) still loads and works without them. */
	$motion_deps = array();

	if ( ! wp_is_mobile() ) {
		wp_enqueue_script(
			'eagleeye-gsap',
			EAGLEEYE_URI . '/assets/js/gsap.min.js',
			array(),
			eagleeye_asset_version( $gsap ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		wp_enqueue_script(
			'eagleeye-scrolltrigger',
			EAGLEEYE_URI . '/assets/js/ScrollTrigger.min.js',
			array( 'eagleeye-gsap' ),
			eagleeye_asset_version( $scroll ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		$motion_deps = array( 'eagleeye-scrolltrigger' );
	}

	wp_enqueue_script(
		'eagleeye-motion',
		EAGLEEYE_URI . '/assets/js/motion.js',
		$motion_deps,
		eagleeye_asset_version( $motion ),
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'eagleeye_assets' );

/**
 * Flag JavaScript support before first paint so reveal animations never hide
 * content when scripting is unavailable.
 */
function eagleeye_js_flag() {
	echo '<script>document.documentElement.classList.add("js");</script>' . "\n";
}
add_action( 'wp_head', 'eagleeye_js_flag', 1 );

/**
 * Preload the two most visually important font files.
 */
function eagleeye_preload_fonts() {
	$fonts = array( 'fraunces-latin.woff2', 'inter-latin.woff2' );

	foreach ( $fonts as $font ) {
		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
			esc_url( EAGLEEYE_URI . '/assets/fonts/' . $font )
		);
	}
}
add_action( 'wp_head', 'eagleeye_preload_fonts', 2 );

/**
 * Inline theme tokens so the Customizer accent colour applies immediately.
 */
function eagleeye_accent_css() {
	$accent = get_theme_mod( 'eagleeye_accent', '#d81f2a' );

	if ( ! preg_match( '/^#[0-9a-f]{6}$/i', (string) $accent ) ) {
		$accent = '#d81f2a';
	}

	printf(
		'<style id="eagleeye-accent">:root{--signal:%1$s;--signal-ink:%1$s}</style>' . "\n",
		esc_attr( $accent )
	);
}
add_action( 'wp_head', 'eagleeye_accent_css', 20 );
