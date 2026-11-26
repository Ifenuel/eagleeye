<?php
/**
 * Branded newsroom login URL.
 *
 * The public entry point is /login/. WordPress still owns authentication, but
 * logged-out admin visits and logout links are pointed at the branded URL.
 *
 * @package Eagle Eye
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public login URL.
 *
 * @return string
 */
function eagleeye_login_page_url() {
	return home_url( '/login/' );
}

/**
 * Register the /login/ endpoint.
 */
function eagleeye_register_login_route() {
	add_rewrite_rule( '^login/?$', 'index.php?eagleeye_login=1', 'top' );

	if ( '1' !== (string) get_option( 'eagleeye_login_route' ) ) {
		flush_rewrite_rules( false );
		update_option( 'eagleeye_login_route', '1' );
	}
}
add_action( 'init', 'eagleeye_register_login_route' );

/**
 * Expose the login route query var.
 *
 * @param array $vars Query vars.
 * @return array
 */
function eagleeye_login_query_vars( $vars ) {
	$vars[] = 'eagleeye_login';

	return $vars;
}
add_filter( 'query_vars', 'eagleeye_login_query_vars' );

/**
 * Serve the WordPress login screen from /login/.
 */
function eagleeye_render_login_route() {
	if ( ! get_query_var( 'eagleeye_login' ) ) {
		return;
	}

	if ( is_user_logged_in() ) {
		wp_safe_redirect( admin_url() );
		exit;
	}

	nocache_headers();
	require_once ABSPATH . 'wp-login.php';
	exit;
}
add_action( 'template_redirect', 'eagleeye_render_login_route', 1 );

/**
 * Point generated login links at the branded URL.
 *
 * @param string $login_url    Default URL.
 * @param string $redirect     Redirect destination.
 * @param bool   $force_reauth Whether reauthentication is required.
 * @return string
 */
function eagleeye_filter_login_url( $login_url, $redirect, $force_reauth ) {
	$url = eagleeye_login_page_url();

	if ( $redirect ) {
		$url = add_query_arg( 'redirect_to', $redirect, $url );
	}

	if ( $force_reauth ) {
		$url = add_query_arg( 'reauth', '1', $url );
	}

	return $url;
}
add_filter( 'login_url', 'eagleeye_filter_login_url', 10, 3 );

/**
 * Keep the login form posting back to /login/ rather than exposing wp-login.php
 * in the page source.
 *
 * @param string $url    Generated URL.
 * @param string $path   Requested path.
 * @param string $scheme URL scheme context.
 * @return string
 */
function eagleeye_filter_login_post_url( $url, $path, $scheme ) {
	if ( 'login_post' === $scheme && false !== strpos( (string) $path, 'wp-login.php' ) ) {
		return eagleeye_login_page_url();
	}

	return $url;
}
add_filter( 'site_url', 'eagleeye_filter_login_post_url', 10, 3 );

/**
 * Send visitors to the front page after logout, where they can sign in again.
 *
 * @param string $redirect_to Requested destination.
 * @param string $requested   Requested redirect.
 * @return string
 */
function eagleeye_logout_redirect( $redirect_to, $requested ) {
	return home_url( '/' );
}
add_filter( 'logout_redirect', 'eagleeye_logout_redirect', 10, 2 );