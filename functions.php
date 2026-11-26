<?php
/**
 * Eagle Eye theme bootstrap.
 *
 * @package Eagle Eye
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EAGLEEYE_VERSION', '1.1.0' );

/**
 * Cache-busting version for a theme asset.
 *
 * Combines the theme version with the file's modification time, so a release
 * always produces a new URL even when a deploy preserves file mtimes.
 *
 * @param string $path Absolute path to the asset.
 * @return string
 */
function eagleeye_asset_version( $path ) {
	$stamp = file_exists( $path ) ? (string) filemtime( $path ) : '0';

	return EAGLEEYE_VERSION . '.' . $stamp;
}
define( 'EAGLEEYE_DIR', get_template_directory() );
$eagleeye_theme_uri = get_template_directory_uri();

// Keep email images and live assets on HTTPS even when WordPress is run from
// WP-CLI or behind a proxy that does not pass the original scheme through.
if ( 0 === strpos( home_url( '/' ), 'https://' ) ) {
	$eagleeye_theme_uri = set_url_scheme( $eagleeye_theme_uri, 'https' );
}

define( 'EAGLEEYE_URI', $eagleeye_theme_uri );
unset( $eagleeye_theme_uri );

require_once EAGLEEYE_DIR . '/inc/setup.php';
require_once EAGLEEYE_DIR . '/inc/assets.php';
require_once EAGLEEYE_DIR . '/inc/brand.php';
require_once EAGLEEYE_DIR . '/inc/template-tags.php';
require_once EAGLEEYE_DIR . '/inc/video.php';
require_once EAGLEEYE_DIR . '/inc/customizer.php';
require_once EAGLEEYE_DIR . '/inc/meta-boxes.php';
require_once EAGLEEYE_DIR . '/inc/editor-credit.php';
require_once EAGLEEYE_DIR . '/inc/reporter-pages.php';
require_once EAGLEEYE_DIR . '/inc/webp-images.php';
require_once EAGLEEYE_DIR . '/inc/queries.php';
require_once EAGLEEYE_DIR . '/inc/secrets.php';
require_once EAGLEEYE_DIR . '/inc/mailer.php';
require_once EAGLEEYE_DIR . '/inc/ads-manager.php';
require_once EAGLEEYE_DIR . '/inc/ads.php';
require_once EAGLEEYE_DIR . '/inc/media-kit.php';
require_once EAGLEEYE_DIR . '/inc/newsletter.php';
require_once EAGLEEYE_DIR . '/inc/contact.php';
require_once EAGLEEYE_DIR . '/inc/community-reports.php';
require_once EAGLEEYE_DIR . '/inc/report-workflow.php';
require_once EAGLEEYE_DIR . '/inc/corrections.php';
require_once EAGLEEYE_DIR . '/inc/coverage.php';
require_once EAGLEEYE_DIR . '/inc/backup-health.php';
require_once EAGLEEYE_DIR . '/inc/seo.php';
require_once EAGLEEYE_DIR . '/inc/discovery.php';
require_once EAGLEEYE_DIR . '/inc/news-sitemap.php';
require_once EAGLEEYE_DIR . '/inc/analytics.php';
require_once EAGLEEYE_DIR . '/inc/analytics-events.php';
require_once EAGLEEYE_DIR . '/inc/admin.php';
require_once EAGLEEYE_DIR . '/inc/admin-ui.php';
require_once EAGLEEYE_DIR . '/inc/identity.php';
require_once EAGLEEYE_DIR . '/inc/lock.php';
require_once EAGLEEYE_DIR . '/inc/login-url.php';
