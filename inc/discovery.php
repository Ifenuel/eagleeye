<?php
/**
 * Search and AI discovery.
 *
 * Everything here is about being found off-site: search console
 * verification, answer-engine crawler access, IndexNow pings, entity
 * signals for the organisation, and a lightweight log of referrals from
 * search and AI assistants.
 *
 * @package Eagle Eye
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Verification meta tags.
 *
 * The tokens are pasted into Settings → Search & AI. Nothing is printed
 * until a token exists, so the head stays clean on a fresh install.
 */
function eagleeye_discovery_verification_meta() {
	$google = trim( (string) get_option( 'eagleeye_google_verification', '' ) );
	$bing   = trim( (string) get_option( 'eagleeye_bing_verification', '' ) );

	if ( $google ) {
		printf( '<meta name="google-site-verification" content="%s">' . "\n", esc_attr( $google ) );
	}

	if ( $bing ) {
		printf( '<meta name="msvalidate.01" content="%s">' . "\n", esc_attr( $bing ) );
	}
}
add_action( 'wp_head', 'eagleeye_discovery_verification_meta', 1 );

/**
 * Extra profile URLs added to the organisation's sameAs list.
 *
 * Use this for properties the site does not already link to, such as a
 * Wikidata item, LinkedIn company page or Google Business Profile.
 *
 * @return string[]
 */
function eagleeye_entity_sameas() {
	$raw   = (string) get_option( 'eagleeye_entity_sameas', '' );
	$lines = preg_split( '/\r\n|\r|\n/', $raw );
	$urls  = array();

	foreach ( (array) $lines as $line ) {
		$line = trim( $line );

		if ( $line && filter_var( $line, FILTER_VALIDATE_URL ) ) {
			$urls[] = esc_url_raw( $line );
		}
	}

	return array_values( array_unique( $urls ) );
}

/**
 * Let answer engines and AI crawlers read the site.
 *
 * Being crawlable does not guarantee a citation, but blocking the bots
 * guarantees you cannot be cited. The rules are explicit so a future
 * robots change does not silently exclude them.
 *
 * @param string $output Robots.txt body.
 * @param bool   $public Whether the site is public.
 * @return string
 */
function eagleeye_discovery_robots( $output, $public ) {
	if ( ! $public ) {
		return $output;
	}

	$bots = array(
		'GPTBot',
		'OAI-SearchBot',
		'ChatGPT-User',
		'PerplexityBot',
		'Perplexity-User',
		'ClaudeBot',
		'Claude-Web',
		'anthropic-ai',
		'Google-Extended',
		'Bingbot',
		'Applebot-Extended',
		'CCBot',
	);

	$output .= "\n# Answer engines and AI crawlers\n";

	foreach ( $bots as $bot ) {
		$output .= "User-agent: {$bot}\nAllow: /\n\n";
	}

	return $output;
}
add_filter( 'robots_txt', 'eagleeye_discovery_robots', 20, 2 );

/**
 * IndexNow key.
 *
 * A 32-character hex key doubles as the verification file name, per the
 * IndexNow protocol. It is created once and stored in an option.
 *
 * @return string
 */
function eagleeye_indexnow_key() {
	$key = (string) get_option( 'eagleeye_indexnow_key', '' );

	if ( ! preg_match( '/^[a-f0-9]{32}$/', $key ) ) {
		$key = bin2hex( random_bytes( 16 ) );
		update_option( 'eagleeye_indexnow_key', $key, false );
	}

	return $key;
}

/**
 * Write the key file to the site root when the filesystem allows it.
 */
function eagleeye_indexnow_write_key_file() {
	$key  = eagleeye_indexnow_key();
	$path = ABSPATH . $key . '.txt';

	if ( file_exists( $path ) && trim( (string) file_get_contents( $path ) ) === $key ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return;
	}

	@file_put_contents( $path, $key ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.PHP.NoSilencedErrors.Discouraged
}
add_action( 'init', 'eagleeye_indexnow_write_key_file', 5 );

/**
 * Serve the key file through WordPress if it cannot be written to disk.
 */
function eagleeye_indexnow_key_route() {
	add_rewrite_rule( '^([a-f0-9]{32})\.txt$', 'index.php?eagleeye_indexnow_key=$matches[1]', 'top' );
}
add_action( 'init', 'eagleeye_indexnow_key_route' );

/**
 * Register the IndexNow query var.
 *
 * @param array $vars Query vars.
 * @return array
 */
function eagleeye_indexnow_query_var( $vars ) {
	$vars[] = 'eagleeye_indexnow_key';

	return $vars;
}
add_filter( 'query_vars', 'eagleeye_indexnow_query_var' );

/**
 * Answer the key request when it reaches WordPress.
 */
function eagleeye_indexnow_serve_key() {
	$requested = (string) get_query_var( 'eagleeye_indexnow_key' );

	if ( ! $requested ) {
		return;
	}

	nocache_headers();
	header( 'Content-Type: text/plain; charset=UTF-8', true, 200 );

	if ( hash_equals( eagleeye_indexnow_key(), $requested ) ) {
		echo eagleeye_indexnow_key(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	} else {
		status_header( 404 );
	}

	exit;
}
add_action( 'template_redirect', 'eagleeye_indexnow_serve_key', 1 );

/**
 * Tell IndexNow about a URL.
 *
 * IndexNow shares the submission with Bing, Yandex and other partners.
 * Requests are fire-and-forget so publishing never waits on the network.
 *
 * @param string[] $urls URLs to submit.
 */
function eagleeye_indexnow_submit( $urls ) {
	$urls = array_values( array_unique( array_filter( array_map( 'esc_url_raw', (array) $urls ) ) ) );

	if ( ! $urls ) {
		return;
	}

	$key  = eagleeye_indexnow_key();
	$host = wp_parse_url( home_url(), PHP_URL_HOST );
	$body = array(
		'host'        => $host,
		'key'         => $key,
		'keyLocation' => home_url( '/' . $key . '.txt' ),
		'urlList'     => array_slice( $urls, 0, 100 ),
	);

	wp_remote_post(
		'https://api.indexnow.org/indexnow',
		array(
			'timeout'  => 5,
			'blocking' => false,
			'headers'  => array( 'Content-Type' => 'application/json; charset=utf-8' ),
			'body'     => wp_json_encode( $body ),
		)
	);
}

/**
 * Submit a story when it first goes live.
 *
 * @param string  $new_status New status.
 * @param string  $old_status Old status.
 * @param WP_Post $post       Post object.
 */
function eagleeye_indexnow_on_publish( $new_status, $old_status, $post ) {
	if ( 'publish' !== $new_status || 'publish' === $old_status || 'post' !== $post->post_type ) {
		return;
	}

	eagleeye_indexnow_submit( array( get_permalink( $post ), home_url( '/' ) ) );
}
add_action( 'transition_post_status', 'eagleeye_indexnow_on_publish', 10, 3 );

/**
 * Submit a published story again when it is materially updated.
 *
 * Capped at once every six hours per story so quick edits do not spam the
 * endpoint.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $after   Post after the update.
 * @param WP_Post $before  Post before the update.
 */
function eagleeye_indexnow_on_update( $post_id, $after, $before ) {
	if ( 'post' !== $after->post_type || 'publish' !== $after->post_status || 'publish' !== $before->post_status ) {
		return;
	}

	$key = 'ee_indexnow_' . $post_id;

	if ( get_transient( $key ) ) {
		return;
	}

	set_transient( $key, 1, 6 * HOUR_IN_SECONDS );
	eagleeye_indexnow_submit( array( get_permalink( $post_id ) ) );
}
add_action( 'post_updated', 'eagleeye_indexnow_on_update', 10, 3 );

/**
 * Referral log table name.
 *
 * @return string
 */
function eagleeye_referral_table() {
	global $wpdb;

	return $wpdb->prefix . 'ee_referrals';
}

/**
 * Create or upgrade the referral table.
 */
function eagleeye_referral_install() {
	if ( '1' === get_option( 'eagleeye_referral_table_version' ) ) {
		return;
	}

	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table   = eagleeye_referral_table();
	$collate = $wpdb->get_charset_collate();

	dbDelta(
		"CREATE TABLE {$table} (
			day date NOT NULL,
			source varchar(32) NOT NULL,
			path varchar(191) NOT NULL,
			hits int unsigned NOT NULL default 0,
			PRIMARY KEY  (day,source,path),
			KEY source_day (source,day)
		) {$collate};"
	);

	update_option( 'eagleeye_referral_table_version', '1', false );
}
add_action( 'init', 'eagleeye_referral_install', 6 );

/**
 * Work out which search or AI surface sent a reader.
 *
 * Only the referring host and an optional utm_source are inspected. No IP
 * address, user agent or reader identity is stored.
 *
 * @return string Source label, or an empty string when not a tracked surface.
 */
function eagleeye_referral_source() {
	$referer = isset( $_SERVER['HTTP_REFERER'] ) ? strtolower( (string) wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$utm     = isset( $_GET['utm_source'] ) ? strtolower( sanitize_key( wp_unslash( $_GET['utm_source'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$hay     = $referer . ' ' . $utm;

	$map = array(
		'chatgpt'      => 'ChatGPT',
		'openai'       => 'ChatGPT',
		'perplexity'   => 'Perplexity',
		'gemini'       => 'Gemini',
		'bard'         => 'Gemini',
		'claude'       => 'Claude',
		'copilot'      => 'Copilot',
		'news.google'  => 'Google News',
		'bing'         => 'Bing',
		'duckduckgo'   => 'DuckDuckGo',
		'google.'      => 'Google',
	);

	foreach ( $map as $needle => $label ) {
		if ( false !== strpos( $hay, $needle ) ) {
			return $label;
		}
	}

	return '';
}

/**
 * Record a referral from a tracked surface.
 */
function eagleeye_log_referral() {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || is_user_logged_in() ) {
		return;
	}

	if ( 'GET' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : 'GET' ) ) {
		return;
	}

	if ( is_preview() || is_404() || is_feed() || is_robots() ) {
		return;
	}

	$source = eagleeye_referral_source();

	if ( ! $source ) {
		return;
	}

	global $wpdb;

	$path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$path = substr( $path ? $path : '/', 0, 191 );

	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->prepare(
			'INSERT INTO ' . eagleeye_referral_table() . ' (day, source, path, hits) VALUES (%s, %s, %s, 1) ON DUPLICATE KEY UPDATE hits = hits + 1', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			current_time( 'Y-m-d' ),
			$source,
			$path
		)
	);
}
add_action( 'template_redirect', 'eagleeye_log_referral', 99 );

/**
 * Register the settings page.
 */
function eagleeye_discovery_settings_page() {
	add_options_page(
		__( 'Search & AI visibility', 'eagleeye' ),
		__( 'Search & AI', 'eagleeye' ),
		'manage_options',
		'eagleeye-search-ai',
		'eagleeye_discovery_settings_render'
	);
}
add_action( 'admin_menu', 'eagleeye_discovery_settings_page' );

/**
 * Register settings.
 */
function eagleeye_discovery_register_settings() {
	register_setting(
		'eagleeye_discovery',
		'eagleeye_google_verification',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		)
	);

	register_setting(
		'eagleeye_discovery',
		'eagleeye_bing_verification',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		)
	);

	register_setting(
		'eagleeye_discovery',
		'eagleeye_entity_sameas',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_textarea_field',
			'default'           => '',
		)
	);
}
add_action( 'admin_init', 'eagleeye_discovery_register_settings' );

/**
 * Settings screen.
 */
function eagleeye_discovery_settings_render() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$key      = eagleeye_indexnow_key();
	$key_url  = home_url( '/' . $key . '.txt' );
	$notice   = isset( $_GET['ee_indexnow'] ) ? sanitize_key( wp_unslash( $_GET['ee_indexnow'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Search & AI visibility', 'eagleeye' ); ?></h1>

		<?php if ( 'sent' === $notice ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'IndexNow submission sent.', 'eagleeye' ); ?></p></div>
		<?php elseif ( 'key' === $notice ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'A new IndexNow key was generated.', 'eagleeye' ); ?></p></div>
		<?php endif; ?>

		<p><?php esc_html_e( 'Verification tokens are printed in the page head. Paste them from Google Search Console and Bing Webmaster Tools; no file upload is needed.', 'eagleeye' ); ?></p>

		<form method="post" action="options.php">
			<?php settings_fields( 'eagleeye_discovery' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="eagleeye_google_verification"><?php esc_html_e( 'Google verification code', 'eagleeye' ); ?></label></th>
					<td>
						<input name="eagleeye_google_verification" id="eagleeye_google_verification" type="text" class="regular-text" value="<?php echo esc_attr( get_option( 'eagleeye_google_verification', '' ) ); ?>">
						<p class="description"><?php esc_html_e( 'The content value of the google-site-verification meta tag from Search Console.', 'eagleeye' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="eagleeye_bing_verification"><?php esc_html_e( 'Bing verification code', 'eagleeye' ); ?></label></th>
					<td>
						<input name="eagleeye_bing_verification" id="eagleeye_bing_verification" type="text" class="regular-text" value="<?php echo esc_attr( get_option( 'eagleeye_bing_verification', '' ) ); ?>">
						<p class="description"><?php esc_html_e( 'The content value of the msvalidate.01 meta tag from Bing Webmaster Tools.', 'eagleeye' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="eagleeye_entity_sameas"><?php esc_html_e( 'Profile URLs', 'eagleeye' ); ?></label></th>
					<td>
						<textarea name="eagleeye_entity_sameas" id="eagleeye_entity_sameas" rows="4" class="large-text code" placeholder="https://www.wikidata.org/wiki/Q..."><?php echo esc_textarea( get_option( 'eagleeye_entity_sameas', '' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'One URL per line. Adds LinkedIn, Wikidata, Google Business Profile and similar profiles to the organisation sameAs list. Facebook and Instagram are already included automatically.', 'eagleeye' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'IndexNow key', 'eagleeye' ); ?></th>
					<td>
						<code><?php echo esc_html( $key ); ?></code>
						<p class="description">
							<?php
							printf(
								/* translators: %s: key file URL. */
								esc_html__( 'Key file: %s. Stories are submitted automatically when published or updated.', 'eagleeye' ),
								'<a href="' . esc_url( $key_url ) . '" target="_blank" rel="noopener">' . esc_html( $key_url ) . '</a>'
							);
							?>
						</p>
						<p>
							<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=eagleeye_indexnow_ping' ), 'eagleeye_indexnow_ping' ) ); ?>"><?php esc_html_e( 'Submit site to IndexNow now', 'eagleeye' ); ?></a>
							<a class="button button-link" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=eagleeye_indexnow_regenerate' ), 'eagleeye_indexnow_regenerate' ) ); ?>"><?php esc_html_e( 'Generate a new key', 'eagleeye' ); ?></a>
						</p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<hr>
		<h2><?php esc_html_e( 'Where to finish setup', 'eagleeye' ); ?></h2>
		<ul>
			<li><a href="https://search.google.com/search-console" target="_blank" rel="noopener">Google Search Console</a> — add the domain, paste the token above, submit both sitemaps.</li>
			<li><a href="https://www.bing.com/webmasters" target="_blank" rel="noopener">Bing Webmaster Tools</a> — add the site, paste the token, submit the sitemap.</li>
			<li><a href="https://publishercenter.google.com/" target="_blank" rel="noopener">Google Publisher Center</a> — Google News and Discover publication setup.</li>
			<li><a href="https://www.google.com/alerts" target="_blank" rel="noopener">Google Alerts</a> — create alerts for “Eagle Eye”, “eagleeyenews.online” and your editors’ names.</li>
			<li><a href="https://business.google.com/" target="_blank" rel="noopener">Google Business Profile</a> — create the publication profile, then add its URL above as a profile URL.</li>
		</ul>
	</div>
	<?php
}

/**
 * Handle the manual IndexNow submission.
 */
function eagleeye_indexnow_manual_ping() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'eagleeye' ) );
	}

	check_admin_referer( 'eagleeye_indexnow_ping' );

	$recent = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);

	$urls = array( home_url( '/' ) );

	foreach ( $recent as $post_id ) {
		$urls[] = get_permalink( $post_id );
	}

	eagleeye_indexnow_submit( $urls );

	wp_safe_redirect( add_query_arg( 'ee_indexnow', 'sent', admin_url( 'options-general.php?page=eagleeye-search-ai' ) ) );
	exit;
}
add_action( 'admin_post_eagleeye_indexnow_ping', 'eagleeye_indexnow_manual_ping' );

/**
 * Regenerate the IndexNow key.
 */
function eagleeye_indexnow_regenerate() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'eagleeye' ) );
	}

	check_admin_referer( 'eagleeye_indexnow_regenerate' );

	$old = (string) get_option( 'eagleeye_indexnow_key', '' );
	update_option( 'eagleeye_indexnow_key', bin2hex( random_bytes( 16 ) ), false );

	if ( $old ) {
		@unlink( ABSPATH . $old . '.txt' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	eagleeye_indexnow_write_key_file();
	flush_rewrite_rules( false );

	wp_safe_redirect( add_query_arg( 'ee_indexnow', 'key', admin_url( 'options-general.php?page=eagleeye-search-ai' ) ) );
	exit;
}
add_action( 'admin_post_eagleeye_indexnow_regenerate', 'eagleeye_indexnow_regenerate' );

/**
 * Discovery report: referrals, verification status and a small audit.
 */
function eagleeye_discovery_report_page() {
	add_management_page(
		__( 'Discovery & AI referrals', 'eagleeye' ),
		__( 'Discovery', 'eagleeye' ),
		'manage_options',
		'eagleeye-discovery',
		'eagleeye_discovery_report_render'
	);
}
add_action( 'admin_menu', 'eagleeye_discovery_report_page' );

/**
 * Render the discovery report.
 */
function eagleeye_discovery_report_render() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	global $wpdb;

	$table = eagleeye_referral_table();
	$since = gmdate( 'Y-m-d', strtotime( '-30 days' ) );

	$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->prepare( "SELECT source, SUM(hits) AS hits FROM {$table} WHERE day >= %s GROUP BY source ORDER BY hits DESC", $since ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		ARRAY_A
	);

	$pages = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->prepare( "SELECT path, SUM(hits) AS hits FROM {$table} WHERE day >= %s GROUP BY path ORDER BY hits DESC LIMIT 10", $since ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		ARRAY_A
	);

	$google = trim( (string) get_option( 'eagleeye_google_verification', '' ) );
	$bing   = trim( (string) get_option( 'eagleeye_bing_verification', '' ) );

	$small_images = get_transient( 'eagleeye_discovery_small_images' );

	if ( false === $small_images ) {
		$small_images = 0;
		$recent       = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'date_query'     => array( array( 'after' => '90 days ago' ) ),
			)
		);

		foreach ( $recent as $post_id ) {
			$image = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), 'full' );

			if ( ! $image || (int) $image[1] < 1200 ) {
				$small_images++;
			}
		}

		set_transient( 'eagleeye_discovery_small_images', $small_images, HOUR_IN_SECONDS );
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Discovery & AI referrals', 'eagleeye' ); ?></h1>
		<p><?php esc_html_e( 'Referrals from search engines and AI assistants over the last 30 days. Only the source, path and day are stored — no IP addresses or reader identities.', 'eagleeye' ); ?></p>

		<h2><?php esc_html_e( 'Referrals by source', 'eagleeye' ); ?></h2>
		<?php if ( $rows ) : ?>
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'Source', 'eagleeye' ); ?></th><th><?php esc_html_e( 'Visits', 'eagleeye' ); ?></th></tr></thead>
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<tr><td><?php echo esc_html( $row['source'] ); ?></td><td><?php echo esc_html( (string) $row['hits'] ); ?></td></tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><?php esc_html_e( 'No tracked referrals yet. Data appears when someone arrives from Google, Bing, ChatGPT, Perplexity, Gemini, Claude or Copilot.', 'eagleeye' ); ?></p>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Top landing pages', 'eagleeye' ); ?></h2>
		<?php if ( $pages ) : ?>
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'Path', 'eagleeye' ); ?></th><th><?php esc_html_e( 'Visits', 'eagleeye' ); ?></th></tr></thead>
				<tbody>
					<?php foreach ( $pages as $row ) : ?>
						<tr><td><code><?php echo esc_html( $row['path'] ); ?></code></td><td><?php echo esc_html( (string) $row['hits'] ); ?></td></tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><?php esc_html_e( 'No landing pages recorded yet.', 'eagleeye' ); ?></p>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Readiness', 'eagleeye' ); ?></h2>
		<table class="widefat striped">
			<tbody>
				<tr>
					<td><?php esc_html_e( 'Google Search Console', 'eagleeye' ); ?></td>
					<td><?php echo $google ? '<span style="color:#008a20">✓ ' . esc_html__( 'verification token set', 'eagleeye' ) . '</span>' : esc_html__( 'not verified — add the token under Settings → Search & AI', 'eagleeye' ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Bing Webmaster Tools', 'eagleeye' ); ?></td>
					<td><?php echo $bing ? '<span style="color:#008a20">✓ ' . esc_html__( 'verification token set', 'eagleeye' ) . '</span>' : esc_html__( 'not verified — add the token under Settings → Search & AI', 'eagleeye' ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'News sitemap', 'eagleeye' ); ?></td>
					<td><a href="<?php echo esc_url( home_url( '/news-sitemap.xml' ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( home_url( '/news-sitemap.xml' ) ); ?></a></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Full sitemap', 'eagleeye' ); ?></td>
					<td><a href="<?php echo esc_url( home_url( '/wp-sitemap.xml' ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( home_url( '/wp-sitemap.xml' ) ); ?></a></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'IndexNow key', 'eagleeye' ); ?></td>
					<td><a href="<?php echo esc_url( home_url( '/' . eagleeye_indexnow_key() . '.txt' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'key file', 'eagleeye' ); ?></a> — <?php esc_html_e( 'stories submit automatically on publish and update.', 'eagleeye' ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Discover image size', 'eagleeye' ); ?></td>
					<td>
						<?php
						if ( 0 === (int) $small_images ) {
							echo '<span style="color:#008a20">✓ ' . esc_html__( 'last 100 stories all have a 1200px+ image', 'eagleeye' ) . '</span>';
						} else {
							printf(
								/* translators: %d: number of stories. */
								esc_html__( '%d of the last 100 published stories have no image or one narrower than 1200px.', 'eagleeye' ),
								(int) $small_images
							);
						}
						?>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
	<?php
}
