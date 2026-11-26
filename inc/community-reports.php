<?php
/**
 * Citizen reporter submissions for Community Eye and Campus Eye.
 *
 * Reports are stored as private posts and never appear on the public site until
 * an editor reviews and publishes them. Uploaded media is stored outside the
 * public web root and is only available to signed-in editors.
 *
 * @package Eagle Eye
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EAGLEEYE_REPORT_MAX_FILES', 5 );
define( 'EAGLEEYE_REPORT_MAX_FILE_BYTES', 25 * MB_IN_BYTES );
define( 'EAGLEEYE_REPORT_MAX_TOTAL_BYTES', 60 * MB_IN_BYTES );
define( 'EAGLEEYE_REPORT_RATE_LIMIT', 3 );
define( 'EAGLEEYE_REPORT_RATE_WINDOW', 15 * MINUTE_IN_SECONDS );

/**
 * File types accepted from citizen reporters.
 *
 * @return array<string,string>
 */
function eagleeye_citizen_report_allowed_types() {
	return array(
		'jpg|jpeg|jpe' => 'image/jpeg',
		'png'          => 'image/png',
		'webp'         => 'image/webp',
		'mp4'          => 'video/mp4',
		'm4v'          => 'video/x-m4v',
		'mov|qt'       => 'video/quicktime',
	);
}

/**
 * Transient key for the current visitor's report attempts.
 *
 * @return string
 */
function eagleeye_citizen_report_rate_key() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';

	return 'ee_report_rate_' . md5( wp_salt( 'nonce' ) . '|' . $ip );
}

/**
 * Whether this visitor has used the current submission allowance.
 *
 * @return bool
 */
function eagleeye_citizen_report_rate_limited() {
	$data = get_transient( eagleeye_citizen_report_rate_key() );

	return is_array( $data ) && isset( $data['count'], $data['reset'] ) && (int) $data['count'] >= EAGLEEYE_REPORT_RATE_LIMIT && time() < (int) $data['reset'];
}

/**
 * Record one submission attempt against the visitor's allowance.
 */
function eagleeye_citizen_report_rate_hit() {
	$key  = eagleeye_citizen_report_rate_key();
	$data = get_transient( $key );

	if ( ! is_array( $data ) || empty( $data['reset'] ) || time() >= (int) $data['reset'] ) {
		$data = array(
			'count' => 0,
			'reset' => time() + EAGLEEYE_REPORT_RATE_WINDOW,
		);
	}

	$data['count'] = (int) $data['count'] + 1;
	set_transient( $key, $data, EAGLEEYE_REPORT_RATE_WINDOW );
}

/**
 * Register the private citizen-report inbox.
 */
function eagleeye_citizen_report_post_type() {
	register_post_type(
		'eagleeye_report',
		array(
			'labels'             => array(
				'name'               => __( 'Citizen Reports', 'eagleeye' ),
				'singular_name'      => __( 'Citizen Report', 'eagleeye' ),
				'menu_name'          => __( 'Citizen Reports', 'eagleeye' ),
				'all_items'          => __( 'Citizen Reports', 'eagleeye' ),
				'edit_item'          => __( 'Review citizen report', 'eagleeye' ),
				'view_item'          => __( 'View citizen report', 'eagleeye' ),
				'search_items'       => __( 'Search citizen reports', 'eagleeye' ),
				'not_found'          => __( 'No citizen reports yet.', 'eagleeye' ),
				'not_found_in_trash' => __( 'No citizen reports in Trash.', 'eagleeye' ),
			),
			'public'             => false,
			'publicly_queryable' => false,
			'exclude_from_search' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'menu_icon'          => 'dashicons-megaphone',
			'menu_position'      => 27,
			'supports'           => array( 'title', 'editor' ),
			'capabilities'       => array( 'create_posts' => 'do_not_allow' ),
			'map_meta_cap'       => true,
			'rewrite'            => false,
			'query_var'          => false,
		)
	);
}
add_action( 'init', 'eagleeye_citizen_report_post_type' );

/**
 * Private directory for submitted media.
 *
 * It lives under wp-content/eagleeye-reports, with directory access denied
 * at the server level and files served only through an authenticated admin
 * download action.
 *
 * @return string
 */
function eagleeye_citizen_report_private_dir() {
	static $dir = null;

	if ( null !== $dir ) {
		return $dir;
	}

	$dir = trailingslashit( WP_CONTENT_DIR ) . 'eagleeye-reports';

	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}

	if ( ! is_dir( $dir ) || ! wp_is_writable( $dir ) ) {
		$dir = '';
		return $dir;
	}

	if ( ! file_exists( $dir . '/index.php' ) ) {
		@file_put_contents( $dir . '/index.php', "<?php
// Silence is golden.
" ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	$rules = "<IfModule mod_authz_core.c>
	Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
	Order allow,deny
	Deny from all
</IfModule>
";

	if ( ! file_exists( $dir . '/.htaccess' ) || trim( (string) file_get_contents( $dir . '/.htaccess' ) ) !== trim( $rules ) ) {
		@file_put_contents( $dir . '/.htaccess', $rules ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	return $dir;
}

/**
 * Normalise and validate the optional media fields.
 *
 * @return array|WP_Error
 */
function eagleeye_citizen_report_validate_files() {
	$raw = isset( $_FILES['ee_media'] ) ? $_FILES['ee_media'] : null; // phpcs:ignore WordPress.Security.NonceVerification.Missing

	if ( ! is_array( $raw ) || empty( $raw['name'] ) ) {
		return array();
	}

	$names     = (array) $raw['name'];
	$tmp_names = (array) $raw['tmp_name'];
	$errors    = (array) $raw['error'];
	$sizes     = (array) $raw['size'];
	$files     = array();

	foreach ( $names as $index => $name ) {
		$error = isset( $errors[ $index ] ) ? (int) $errors[ $index ] : UPLOAD_ERR_NO_FILE;

		if ( UPLOAD_ERR_NO_FILE === $error ) {
			continue;
		}

		$files[] = array(
			'name'     => sanitize_file_name( (string) $name ),
			'tmp_name' => isset( $tmp_names[ $index ] ) ? (string) $tmp_names[ $index ] : '',
			'error'    => $error,
			'size'     => isset( $sizes[ $index ] ) ? (int) $sizes[ $index ] : 0,
		);
	}

	if ( count( $files ) > EAGLEEYE_REPORT_MAX_FILES ) {
		return new WP_Error( 'too_many_files', __( 'Please upload no more than five files.', 'eagleeye' ) );
	}

	$allowed = eagleeye_citizen_report_allowed_types();
	$total   = 0;

	foreach ( $files as $file ) {
		$total += (int) $file['size'];
	}

	if ( $total > EAGLEEYE_REPORT_MAX_TOTAL_BYTES ) {
		return new WP_Error( 'total_size', __( 'Please keep all files together under 60 MB.', 'eagleeye' ) );
	}

	foreach ( $files as $file ) {
		if ( UPLOAD_ERR_OK !== $file['error'] ) {
			return new WP_Error( 'upload_error', __( 'One of the files could not be uploaded. Please try a smaller file.', 'eagleeye' ) );
		}

		if ( $file['size'] < 1 || $file['size'] > EAGLEEYE_REPORT_MAX_FILE_BYTES ) {
			return new WP_Error( 'file_size', __( 'Each photo or video must be smaller than 25 MB.', 'eagleeye' ) );
		}

		$checked = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $allowed );

		if ( empty( $checked['type'] ) || empty( $checked['ext'] ) ) {
			return new WP_Error( 'file_type', __( 'Please use JPG, PNG, WebP, MP4 or MOV files.', 'eagleeye' ) );
		}
	}

	return $files;
}

/**
 * Move validated files into the private report directory.
 *
 * @param int   $post_id Report post ID.
 * @param array $files   Validated files.
 * @return array|WP_Error
 */
function eagleeye_citizen_report_store_files( $post_id, $files ) {
	$root = eagleeye_citizen_report_private_dir();

	if ( ! $root ) {
		return new WP_Error( 'private_dir', __( 'The upload directory is not writable. Please contact the newsroom.', 'eagleeye' ) );
	}

	$target = trailingslashit( $root ) . 'report-' . absint( $post_id );
	wp_mkdir_p( $target );

	if ( ! is_dir( $target ) ) {
		return new WP_Error( 'private_dir', __( 'The upload directory could not be created.', 'eagleeye' ) );
	}

	$stored = array();

	foreach ( $files as $index => $file ) {
		if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
			eagleeye_citizen_report_delete_files( $post_id );
			return new WP_Error( 'upload_error', __( 'The upload could not be verified.', 'eagleeye' ) );
		}

		$filename = wp_unique_filename( $target, $file['name'] );
		$path     = trailingslashit( $target ) . $filename;

		if ( ! move_uploaded_file( $file['tmp_name'], $path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			eagleeye_citizen_report_delete_files( $post_id );
			return new WP_Error( 'upload_error', __( 'The file could not be saved.', 'eagleeye' ) );
		}

		$checked = wp_check_filetype_and_ext( $path, $filename, eagleeye_citizen_report_allowed_types() );

		if ( empty( $checked['type'] ) || empty( $checked['ext'] ) ) {
			eagleeye_citizen_report_delete_files( $post_id );
			return new WP_Error( 'file_type', __( 'One of the files was not a supported image or video.', 'eagleeye' ) );
		}

		eagleeye_citizen_report_strip_metadata( $path, $checked['type'] );
		$preview = eagleeye_citizen_report_make_preview( $path, $checked['type'], $target, $index );

		$stored[] = array_merge(
			array(
				'file' => $filename,
				'name' => $file['name'],
				'type' => $checked['type'],
				'size' => filesize( $path ),
			),
			$preview
		);
	}

	return $stored;
}

/**
 * Image loader for the private preview and metadata routines.
 *
 * @param string $path Image path.
 * @param string $type MIME type.
 * @return resource|GdImage|null
 */
function eagleeye_citizen_report_image( $path, $type ) {
	if ( ! function_exists( 'imagecreatefromjpeg' ) ) {
		return null;
	}

	$image = null;

	if ( 'image/jpeg' === $type ) {
		$image = @imagecreatefromjpeg( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	} elseif ( 'image/png' === $type ) {
		$image = @imagecreatefrompng( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	} elseif ( 'image/webp' === $type && function_exists( 'imagecreatefromwebp' ) ) {
		$image = @imagecreatefromwebp( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	return $image ? $image : null;
}

/**
 * Re-encode an uploaded photo so EXIF, GPS, device and timestamp metadata
 * cannot be published or leaked by accident.
 *
 * @param string $path Image path.
 * @param string $type MIME type.
 */
function eagleeye_citizen_report_strip_metadata( $path, $type ) {
	$image = eagleeye_citizen_report_image( $path, $type );

	if ( ! $image ) {
		return;
	}

	if ( 'image/jpeg' === $type && function_exists( 'exif_read_data' ) ) {
		$exif = @exif_read_data( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$turn = isset( $exif['Orientation'] ) ? (int) $exif['Orientation'] : 1;
		$angle = 0;

		if ( 3 === $turn ) {
			$angle = 180;
		} elseif ( 6 === $turn ) {
			$angle = -90;
		} elseif ( 8 === $turn ) {
			$angle = 90;
		}

		if ( $angle ) {
			$rotated = imagerotate( $image, $angle, 0 );

			if ( $rotated ) {
				imagedestroy( $image );
				$image = $rotated;
			}
		}
	}

	// Cap the longest edge and recompress, so a reporter on a slow or costly
	// connection is not stuck uploading a 12-megapixel original.
	$width  = imagesx( $image );
	$height = imagesy( $image );
	$limit  = 2000;

	if ( max( $width, $height ) > $limit ) {
		$scale   = $limit / max( $width, $height );
		$new_w   = max( 1, (int) round( $width * $scale ) );
		$new_h   = max( 1, (int) round( $height * $scale ) );
		$resized = imagecreatetruecolor( $new_w, $new_h );

		if ( $resized ) {
			$white = imagecolorallocate( $resized, 255, 255, 255 );
			imagefill( $resized, 0, 0, $white );
			imagecopyresampled( $resized, $image, 0, 0, 0, 0, $new_w, $new_h, $width, $height );
			imagedestroy( $image );
			$image = $resized;
		}
	}

	if ( 'image/png' === $type ) {
		imagealphablending( $image, false );
		imagesavealpha( $image, true );
	}

	if ( 'image/jpeg' === $type ) {
		imagejpeg( $image, $path, 80 );
	} elseif ( 'image/png' === $type ) {
		imagepng( $image, $path, 8 );
	} elseif ( 'image/webp' === $type && function_exists( 'imagewebp' ) ) {
		imagewebp( $image, $path, 82 );
	}

	imagedestroy( $image );
}

/**
 * Build a small in-admin preview, kept in the private report folder.
 *
 * @param string $path  Original image path.
 * @param string $type  MIME type.
 * @param string $dir   Report's private folder.
 * @param int    $index File index.
 * @return array<string,mixed>
 */
function eagleeye_citizen_report_make_preview( $path, $type, $dir, $index ) {
	$image = eagleeye_citizen_report_image( $path, $type );

	if ( ! $image || ! function_exists( 'imagecreatetruecolor' ) ) {
		return array();
	}

	$width  = imagesx( $image );
	$height = imagesy( $image );
	$limit  = 1200;
	$scale  = min( 1, $limit / max( $width, $height ) );
	$new_w  = max( 1, (int) round( $width * $scale ) );
	$new_h  = max( 1, (int) round( $height * $scale ) );

	$preview = imagecreatetruecolor( $new_w, $new_h );
	$white   = imagecolorallocate( $preview, 255, 255, 255 );
	imagefill( $preview, 0, 0, $white );
	imagecopyresampled( $preview, $image, 0, 0, 0, 0, $new_w, $new_h, $width, $height );

	$name = 'preview-' . absint( $index ) . '.jpg';
	$ok   = imagejpeg( $preview, trailingslashit( $dir ) . $name, 78 );

	imagedestroy( $preview );
	imagedestroy( $image );

	if ( ! $ok ) {
		return array();
	}

	return array(
		'preview' => $name,
		'width'   => $width,
		'height'  => $height,
	);
}

/**
 * Remove a report's private upload directory.
 *
 * @param int $post_id Report post ID.
 */
function eagleeye_citizen_report_delete_files( $post_id ) {
	$root = eagleeye_citizen_report_private_dir();
	$dir  = trailingslashit( $root ) . 'report-' . absint( $post_id );

	if ( ! $root || ! is_dir( $dir ) ) {
		return;
	}

	$files = glob( trailingslashit( $dir ) . '*' );

	if ( is_array( $files ) ) {
		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				@unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}
	}

	@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
}

/**
 * Remove private media when an editor permanently deletes a report.
 *
 * @param int $post_id Post ID being deleted.
 */
function eagleeye_citizen_report_cleanup_on_delete( $post_id ) {
	if ( 'eagleeye_report' === get_post_type( $post_id ) ) {
		eagleeye_citizen_report_delete_files( $post_id );
	}
}
add_action( 'before_delete_post', 'eagleeye_citizen_report_cleanup_on_delete' );

/**
 * Submission attempts by this visitor in the current window.
 *
 * @return int
 */
function eagleeye_citizen_report_attempts() {
	$data = get_transient( eagleeye_citizen_report_rate_key() );

	if ( ! is_array( $data ) || empty( $data['reset'] ) || time() >= (int) $data['reset'] ) {
		return 0;
	}

	return isset( $data['count'] ) ? (int) $data['count'] : 0;
}

/**
 * Public CAPTCHA key, when the newsroom has configured one.
 *
 * @return string
 */
function eagleeye_turnstile_site_key() {
	if ( defined( 'EAGLEEYE_TURNSTILE_SITE_KEY' ) && EAGLEEYE_TURNSTILE_SITE_KEY ) {
		return (string) EAGLEEYE_TURNSTILE_SITE_KEY;
	}

	$env = getenv( 'EAGLEEYE_TURNSTILE_SITE_KEY' );

	return $env ? (string) $env : eagleeye_secret( 'turnstile_site_key' );
}

/**
 * Private CAPTCHA secret, never printed.
 *
 * @return string
 */
function eagleeye_turnstile_secret() {
	if ( defined( 'EAGLEEYE_TURNSTILE_SECRET' ) && EAGLEEYE_TURNSTILE_SECRET ) {
		return (string) EAGLEEYE_TURNSTILE_SECRET;
	}

	$env = getenv( 'EAGLEEYE_TURNSTILE_SECRET' );

	return $env ? (string) $env : eagleeye_secret( 'turnstile_secret' );
}

/**
 * Whether this visitor must solve a CAPTCHA.
 *
 * The challenge is only added after repeat attempts, so an ordinary first
 * report is not slowed down.
 *
 * @return bool
 */
function eagleeye_turnstile_required() {
	if ( ! eagleeye_turnstile_site_key() || ! eagleeye_turnstile_secret() ) {
		return false;
	}

	return eagleeye_citizen_report_attempts() >= 2;
}

/**
 * Verify a CAPTCHA response with the challenge provider.
 *
 * @return bool
 */
function eagleeye_turnstile_verify() {
	$secret = eagleeye_turnstile_secret();
	$token  = isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

	if ( ! $secret || ! $token ) {
		return false;
	}

	$response = wp_remote_post(
		'https://challenges.cloudflare.com/turnstile/v0/siteverify',
		array(
			'timeout' => 10,
			'body'    => array(
				'secret'   => $secret,
				'response' => $token,
				'remoteip' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		return false;
	}

	$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

	return is_array( $body ) && ! empty( $body['success'] );
}

/**
 * Heuristic spam score, 0-100. It flags a report for careful review; it does
 * not silently bin a tip that may be genuine.
 *
 * @param string $description Report text.
 * @param string $action      Suggested action.
 * @param string $name        Reporter name.
 * @param string $community   Community.
 * @return int
 */
function eagleeye_citizen_report_abuse_score( $description, $action, $name, $community ) {
	$text  = $description . ' ' . $action . ' ' . $name . ' ' . $community;
	$score = 0;

	if ( mb_strlen( trim( $description ) ) < 40 ) {
		$score += 10;
	}

	$links = preg_match_all( '#https?://#i', $text );
	$score += min( 30, (int) $links * 10 );

	$letters = preg_replace( '/[^A-Za-z]/', '', $text );

	if ( $letters && mb_strlen( $letters ) > 40 ) {
		$upper = preg_match_all( '/[A-Z]/', $letters );

		if ( $upper / mb_strlen( $letters ) > 0.6 ) {
			$score += 15;
		}
	}

	if ( preg_match( '/(.)\1{7,}/u', $text ) ) {
		$score += 10;
	}

	$spam = array( 'betting', 'casino', 'loan offer', 'crypto', 'bitcoin', 'forex', 'giveaway', 'click here', 'promo code', 'jackpot' );

	foreach ( $spam as $word ) {
		if ( false !== stripos( $text, $word ) ) {
			$score += 8;
		}
	}

	return min( 100, $score );
}

/**
 * Normalise report text for a duplicate comparison.
 *
 * @param string $text Text.
 * @return string
 */
function eagleeye_citizen_report_normalise( $text ) {
	$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $text ) : strtolower( (string) $text );

	return trim( preg_replace( '/[^a-z0-9\s]/', ' ', $text ) );
}

/**
 * Find an earlier report that looks like this one.
 *
 * @param int    $post_id     New report ID.
 * @param string $description Report text.
 * @param string $phone       Normalised phone digits.
 * @return int Earlier report ID, or 0.
 */
function eagleeye_citizen_report_find_duplicate( $post_id, $description, $phone ) {
	$text = eagleeye_citizen_report_normalise( $description );

	if ( mb_strlen( $text ) < 30 ) {
		return 0;
	}

	$recent = get_posts(
		array(
			'post_type'      => 'eagleeye_report',
			'post_status'    => array( 'private', 'publish', 'draft' ),
			'posts_per_page' => 200,
			'fields'         => 'ids',
			'post__not_in'   => array( $post_id ),
			'date_query'     => array(
				array( 'after' => gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) ), 'column' => 'post_date_gmt' ),
			),
		)
	);

	foreach ( $recent as $other_id ) {
		$other = eagleeye_citizen_report_normalise( (string) get_post_field( 'post_content', $other_id, 'raw' ) );

		if ( mb_strlen( $other ) < 30 ) {
			continue;
		}

		if ( $other === $text ) {
			return (int) $other_id;
		}

		similar_text( $text, $other, $percent );

		if ( $percent >= 85 ) {
			return (int) $other_id;
		}

		$other_phone = (string) get_post_meta( $other_id, '_ee_phone_digits', true );

		if ( $phone && $other_phone === $phone && $percent >= 65 ) {
			return (int) $other_id;
		}
	}

	return 0;
}

/**
 * How many reports this phone number has sent recently.
 *
 * @param string $phone   Normalised digits.
 * @param int    $post_id Current report.
 * @return int
 */
function eagleeye_citizen_report_count_repeats( $phone, $post_id ) {
	if ( ! $phone ) {
		return 0;
	}

	$query = new WP_Query(
		array(
			'post_type'      => 'eagleeye_report',
			'post_status'    => array( 'private', 'publish', 'draft' ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'post__not_in'   => array( $post_id ),
			'meta_key'       => '_ee_phone_digits', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => $phone, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'date_query'     => array(
				array( 'after' => gmdate( 'Y-m-d H:i:s', strtotime( '-90 days' ) ), 'column' => 'post_date_gmt' ),
			),
		)
	);

	return (int) $query->found_posts + 1;
}

/**
 * Handle a citizen report submission.
 */
function eagleeye_citizen_report_handle() {
	$type     = isset( $_POST['ee_report_type'] ) ? sanitize_key( wp_unslash( $_POST['ee_report_type'] ) ) : '';
	$fallback = 'campus' === $type ? home_url( '/campus-eye/' ) : home_url( '/community-eye/' );
	$redirect = wp_get_referer() ? wp_get_referer() : $fallback;
	$redirect = remove_query_arg( 'ee_report', $redirect );

	$fail = function ( $status ) use ( $redirect ) {
		wp_safe_redirect( add_query_arg( 'ee_report', $status, $redirect ) . '#report-form' );
		exit;
	};

	if ( ! isset( $_POST['eagleeye_citizen_report_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['eagleeye_citizen_report_nonce'] ) ), 'eagleeye_citizen_report' ) ) {
		$fail( 'invalid' );
	}

	if ( ! empty( $_POST['ee_company_site'] ) ) {
		$fail( 'sent' );
	}

	if ( ! in_array( $type, array( 'community', 'campus' ), true ) ) {
		$fail( 'invalid' );
	}

	if ( eagleeye_citizen_report_rate_limited() ) {
		$fail( 'limited' );
	}

	eagleeye_citizen_report_rate_hit();

	if ( eagleeye_turnstile_required() && ! eagleeye_turnstile_verify() ) {
		$fail( 'captcha' );
	}

	$name        = isset( $_POST['ee_name'] ) ? sanitize_text_field( wp_unslash( $_POST['ee_name'] ) ) : '';
	$phone       = isset( $_POST['ee_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['ee_phone'] ) ) : '';
	$community   = isset( $_POST['ee_community'] ) ? sanitize_text_field( wp_unslash( $_POST['ee_community'] ) ) : '';
	$address     = isset( $_POST['ee_address'] ) ? sanitize_text_field( wp_unslash( $_POST['ee_address'] ) ) : '';
	$lga         = isset( $_POST['ee_lga'] ) ? sanitize_text_field( wp_unslash( $_POST['ee_lga'] ) ) : '';
	$school      = isset( $_POST['ee_school'] ) ? sanitize_text_field( wp_unslash( $_POST['ee_school'] ) ) : '';
	$description = isset( $_POST['ee_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ee_description'] ) ) : '';
	$action      = isset( $_POST['ee_action'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ee_action'] ) ) : '';
	$phone_digits = preg_replace( '/\D+/', '', $phone );

	if ( mb_strlen( $description ) < 20 || ! $phone_digits || strlen( $phone_digits ) < 7 || strlen( $phone_digits ) > 15 ) {
		$fail( 'invalid' );
	}

	if ( 'community' === $type && ( ! $community || ! $address || ! $lga ) ) {
		$fail( 'invalid' );
	}

	if ( 'campus' === $type && ( ! $name || ! $school ) ) {
		$fail( 'invalid' );
	}

	if ( empty( $_POST['ee_consent'] ) ) {
		$fail( 'invalid' );
	}

	$files = eagleeye_citizen_report_validate_files();

	if ( is_wp_error( $files ) ) {
		$fail( 'total_size' === $files->get_error_code() ? 'too_large' : 'upload' );
	}

	$title = 'community' === $type
		? sprintf( 'Community Eye — %s (%s)', $community, $lga )
		: sprintf( 'Campus Eye — %s (%s)', $school, $name );

	$content = $description;

	if ( $action ) {
		$content .= "\n\n" . __( 'Suggested action', 'eagleeye' ) . ":\n" . $action;
	}

	$post_id = wp_insert_post(
		array(
			'post_type'    => 'eagleeye_report',
			'post_status'  => 'private',
			'post_title'   => $title,
			'post_content' => $content,
		),
		true
	);

	if ( is_wp_error( $post_id ) || ! $post_id ) {
		$fail( 'error' );
	}

	$stored = eagleeye_citizen_report_store_files( $post_id, $files );

	if ( is_wp_error( $stored ) ) {
		wp_delete_post( $post_id, true );
		$fail( 'upload' );
	}

	update_post_meta( $post_id, '_ee_report_type', $type );
	update_post_meta( $post_id, '_ee_name', $name );
	update_post_meta( $post_id, '_ee_phone', $phone );
	update_post_meta( $post_id, '_ee_community', $community );
	update_post_meta( $post_id, '_ee_address', $address );
	update_post_meta( $post_id, '_ee_lga', $lga );
	update_post_meta( $post_id, '_ee_school', $school );
	update_post_meta( $post_id, '_ee_action', $action );
	update_post_meta( $post_id, '_ee_files', $stored );
	update_post_meta( $post_id, '_ee_submitted_at', current_time( 'mysql' ) );
	update_post_meta( $post_id, '_ee_phone_digits', $phone_digits );
	update_post_meta( $post_id, '_ee_review_status', 'new' );

	$abuse = eagleeye_citizen_report_abuse_score( $description, $action, $name, $community . ' ' . $school );

	if ( $abuse ) {
		update_post_meta( $post_id, '_ee_abuse_score', $abuse );
	}

	$duplicate = eagleeye_citizen_report_find_duplicate( $post_id, $description, $phone_digits );

	if ( $duplicate ) {
		update_post_meta( $post_id, '_ee_duplicate_of', $duplicate );
	}

	update_post_meta( $post_id, '_ee_repeat_reports', eagleeye_citizen_report_count_repeats( $phone_digits, $post_id ) );

	$section = 'community' === $type ? __( 'Community Eye', 'eagleeye' ) : __( 'Campus Eye', 'eagleeye' );
	$details = array(
		__( 'Section', 'eagleeye' )    => $section,
		__( 'Name', 'eagleeye' )       => $name ? $name : __( 'Not provided', 'eagleeye' ),
		__( 'Phone', 'eagleeye' )      => $phone,
		__( 'Community', 'eagleeye' )  => $community,
		__( 'Address', 'eagleeye' )    => $address,
		__( 'LGA', 'eagleeye' )        => $lga,
		__( 'School', 'eagleeye' )     => $school,
	);

	$body = '<p><strong>' . esc_html__( 'A new citizen report is waiting for review.', 'eagleeye' ) . '</strong></p><table cellpadding="6" cellspacing="0" style="border-collapse:collapse">';

	foreach ( $details as $label => $value ) {
		if ( '' === $value ) {
			continue;
		}

		$body .= '<tr><td style="border-bottom:1px solid #eee;color:#777">' . esc_html( $label ) . '</td><td style="border-bottom:1px solid #eee">' . esc_html( $value ) . '</td></tr>';
	}

	$body .= '</table>';
	$body .= '<p><strong>' . esc_html__( 'Report', 'eagleeye' ) . '</strong><br>' . nl2br( esc_html( $description ) ) . '</p>';

	if ( $action ) {
		$body .= '<p><strong>' . esc_html__( 'Suggested action', 'eagleeye' ) . '</strong><br>' . nl2br( esc_html( $action ) ) . '</p>';
	}

	$body .= '<p><strong>' . esc_html__( 'Files', 'eagleeye' ) . '</strong> ' . esc_html( sprintf( _n( '%d file uploaded', '%d files uploaded', count( $stored ), 'eagleeye' ), count( $stored ) ) ) . '</p>';
	$body .= '<p><a href="' . esc_url( admin_url( 'post.php?post=' . absint( $post_id ) . '&action=edit' ) ) . '">' . esc_html__( 'Open and review this report in WordPress', 'eagleeye' ) . '</a></p>';

	$sent = eagleeye_send_email(
		eagleeye_admin_email(),
		sprintf( '[Eagle Eye] New %s report', $section ),
		eagleeye_email_template( __( 'New citizen report', 'eagleeye' ), $body, '', array( 'eyebrow' => __( 'Eagle Eye Citizen Reporter', 'eagleeye' ) ) )
	);

	update_post_meta( $post_id, '_ee_notified', $sent ? '1' : '0' );

	$fail( 'sent' );
}
add_action( 'admin_post_nopriv_eagleeye_citizen_report', 'eagleeye_citizen_report_handle' );
add_action( 'admin_post_eagleeye_citizen_report', 'eagleeye_citizen_report_handle' );

/**
 * Submission feedback state.
 *
 * @return string
 */
function eagleeye_citizen_report_state() {
	if ( ! isset( $_GET['ee_report'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return '';
	}

	return sanitize_key( wp_unslash( $_GET['ee_report'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
}

/**
 * Render a Community Eye or Campus Eye submission form.
 *
 * @param string $type community|campus.
 */
function eagleeye_citizen_report_form( $type = 'community' ) {
	$type    = 'campus' === $type ? 'campus' : 'community';
	$state   = eagleeye_citizen_report_state();
	$is_campus = 'campus' === $type;
	?>
	<section class="report-form-wrap" id="report-form">
		<div class="report-form__head">
			<span class="eyebrow"><?php esc_html_e( 'Eagle Eye Citizen Reporter', 'eagleeye' ); ?></span>
			<h2><?php echo $is_campus ? esc_html__( 'Submit a Campus Eye story', 'eagleeye' ) : esc_html__( 'Report a community story', 'eagleeye' ); ?></h2>
			<p><?php echo $is_campus ? esc_html__( 'Tell us what is happening on your campus. Upload photos or a short video if you have them. Every submission is reviewed by our editorial team before publication.', 'eagleeye' ) : esc_html__( 'Tell us what is happening in your community. Upload photos or a short video if you have them. Every submission is reviewed by our editorial team before publication.', 'eagleeye' ); ?></p>
		</div>

		<form class="contact-form report-form" id="citizen-report-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" enctype="multipart/form-data">
			<input type="hidden" name="action" value="eagleeye_citizen_report">
			<input type="hidden" name="ee_report_type" value="<?php echo esc_attr( $type ); ?>">
			<?php wp_nonce_field( 'eagleeye_citizen_report', 'eagleeye_citizen_report_nonce' ); ?>

			<?php if ( 'sent' === $state ) : ?>
				<p class="form-notice form-notice--ok" role="status"><?php esc_html_e( 'Thank you. Your report has reached the Eagle Eye editorial team. We will verify it and contact you before publishing.', 'eagleeye' ); ?></p>
			<?php elseif ( 'upload' === $state ) : ?>
				<p class="form-notice form-notice--error" role="alert"><?php esc_html_e( 'Your report was not sent because one or more files were not accepted. Use JPG, PNG, WebP, MP4 or MOV files under 25 MB each, up to five files.', 'eagleeye' ); ?></p>
			<?php elseif ( 'too_large' === $state ) : ?>
				<p class="form-notice form-notice--error" role="alert"><?php esc_html_e( 'Your report was not sent because the files were too large together. Keep the total upload under 60 MB.', 'eagleeye' ); ?></p>
			<?php elseif ( 'limited' === $state ) : ?>
				<p class="form-notice form-notice--error" role="alert"><?php esc_html_e( 'You have sent several reports in a short time. Please wait a few minutes before trying again.', 'eagleeye' ); ?></p>
			<?php elseif ( 'captcha' === $state ) : ?>
				<p class="form-notice form-notice--error" role="alert"><?php esc_html_e( 'Please complete the security check and send your report again.', 'eagleeye' ); ?></p>
			<?php elseif ( 'invalid' === $state ) : ?>
				<p class="form-notice form-notice--error" role="alert"><?php esc_html_e( 'Please check the required fields, including a valid phone number and a description of at least 20 characters.', 'eagleeye' ); ?></p>
			<?php elseif ( $state ) : ?>
				<p class="form-notice form-notice--error" role="alert"><?php esc_html_e( 'Something went wrong while sending your report. Please try again.', 'eagleeye' ); ?></p>
			<?php endif; ?>

			<?php if ( $is_campus ) : ?>
				<div class="field-row">
					<p class="field">
						<label for="ee-name"><?php esc_html_e( 'Your name', 'eagleeye' ); ?></label>
						<input type="text" id="ee-name" name="ee_name" required autocomplete="name">
					</p>
					<p class="field">
						<label for="ee-school"><?php esc_html_e( 'School or institution', 'eagleeye' ); ?></label>
						<input type="text" id="ee-school" name="ee_school" required>
					</p>
				</div>

				<p class="field">
					<label for="ee-phone"><?php esc_html_e( 'Phone number', 'eagleeye' ); ?></label>
					<input type="tel" id="ee-phone" name="ee_phone" required autocomplete="tel" inputmode="tel">
					<span class="field__note"><?php esc_html_e( 'Used only to verify your story. It will not be published.', 'eagleeye' ); ?></span>
				</p>

				<p class="field">
					<label for="ee-description"><?php esc_html_e( 'What is the story?', 'eagleeye' ); ?></label>
					<textarea id="ee-description" name="ee_description" rows="7" required minlength="20" maxlength="5000"></textarea>
				</p>

				<p class="field">
					<label for="ee-action"><?php esc_html_e( 'What should be done, or what should we know?', 'eagleeye' ); ?> <span class="field__optional"><?php esc_html_e( '(optional)', 'eagleeye' ); ?></span></label>
					<textarea id="ee-action" name="ee_action" rows="4" maxlength="3000"></textarea>
				</p>
			<?php else : ?>
				<div class="field-row">
					<p class="field">
						<label for="ee-community"><?php esc_html_e( 'Community name or area', 'eagleeye' ); ?></label>
						<input type="text" id="ee-community" name="ee_community" required>
					</p>
					<p class="field">
						<label for="ee-lga"><?php esc_html_e( 'Local Government Area', 'eagleeye' ); ?></label>
						<input type="text" id="ee-lga" name="ee_lga" required>
					</p>
				</div>

				<p class="field">
					<label for="ee-address"><?php esc_html_e( 'Community address', 'eagleeye' ); ?></label>
					<input type="text" id="ee-address" name="ee_address" required>
				</p>

				<div class="field-row">
					<p class="field">
						<label for="ee-phone"><?php esc_html_e( 'Your phone number', 'eagleeye' ); ?></label>
						<input type="tel" id="ee-phone" name="ee_phone" required autocomplete="tel" inputmode="tel">
						<span class="field__note"><?php esc_html_e( 'Used only to verify your report. It will not be published.', 'eagleeye' ); ?></span>
					</p>
					<p class="field">
						<label for="ee-name"><?php esc_html_e( 'Your name', 'eagleeye' ); ?> <span class="field__optional"><?php esc_html_e( '(optional)', 'eagleeye' ); ?></span></label>
						<input type="text" id="ee-name" name="ee_name" autocomplete="name">
					</p>
				</div>

				<p class="field">
					<label for="ee-description"><?php esc_html_e( 'What is happening?', 'eagleeye' ); ?></label>
					<textarea id="ee-description" name="ee_description" rows="7" required minlength="20" maxlength="5000"></textarea>
				</p>

				<p class="field">
					<label for="ee-action"><?php esc_html_e( 'What should government or the authorities do?', 'eagleeye' ); ?> <span class="field__optional"><?php esc_html_e( '(optional)', 'eagleeye' ); ?></span></label>
					<textarea id="ee-action" name="ee_action" rows="4" maxlength="3000"></textarea>
				</p>
			<?php endif; ?>

			<p class="field">
				<label for="ee-media"><?php esc_html_e( 'Photos or videos', 'eagleeye' ); ?> <span class="field__optional"><?php esc_html_e( '(optional)', 'eagleeye' ); ?></span></label>
				<input type="file" id="ee-media" name="ee_media[]" accept=".jpg,.jpeg,.png,.webp,.mp4,.mov,.m4v,image/jpeg,image/png,image/webp,video/mp4,video/quicktime" multiple>
				<span class="field__note"><?php esc_html_e( 'Up to five files, 25 MB each and 60 MB in total. JPG, PNG, WebP, MP4 or MOV. You can take the photo with your phone camera; we shrink photos to save your data and remove location details.', 'eagleeye' ); ?></span>
			</p>

			<p class="field field--check">
				<label>
					<input type="checkbox" name="ee_consent" value="1" required>
					<?php esc_html_e( 'I confirm this report is accurate to the best of my knowledge and may be verified and edited by Eagle Eye before publication.', 'eagleeye' ); ?>
				</label>
			</p>

			<?php if ( eagleeye_turnstile_required() ) : ?>
				<p class="field">
					<span class="field__label"><?php esc_html_e( 'Security check', 'eagleeye' ); ?></span>
					<span class="cf-turnstile" data-sitekey="<?php echo esc_attr( eagleeye_turnstile_site_key() ); ?>" data-theme="light"></span>
				</p>
				<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
			<?php endif; ?>

			<p class="field__note">
				<?php
				printf(
					esc_html__( 'We keep reports and uploaded media for up to %d days unless they are needed for a story, then delete them.', 'eagleeye' ),
					(int) eagleeye_report_retention_days()
				);
				?>
			</p>

			<details class="report-form__help">
				<summary><?php esc_html_e( 'Explain am for Pidgin', 'eagleeye' ); ?></summary>
				<p><?php esc_html_e( 'Tell us wetin happen, the place and the day. If you get photo or video, add am. Your phone number na only for us to call you take check the story; we no go publish am.', 'eagleeye' ); ?></p>
				<p><?php esc_html_e( 'Our editor go check the story before we publish. We no go publish anything we never fit confirm.', 'eagleeye' ); ?></p>
			</details>

			<label class="form-honeypot" aria-hidden="true">Company website<input type="text" name="ee_company_site" tabindex="-1" autocomplete="off"></label>

			<p class="field field--submit">
				<button type="submit" class="btn btn--solid"><?php echo $is_campus ? esc_html__( 'Submit Your Story', 'eagleeye' ) : esc_html__( 'Report Your Community Story', 'eagleeye' ); ?></button>
				<span class="field__note"><?php esc_html_e( 'Your phone number is private and is used only for verification.', 'eagleeye' ); ?></span>
			</p>
		</form>

		<script>
		(function () {
			var form = document.getElementById( 'citizen-report-form' );

			if ( ! form || ! window.eeAnalytics ) {
				return;
			}

			var type = <?php echo wp_json_encode( $type ); ?>;
			var started = false;

			form.addEventListener( 'input', function () {
				if ( started ) {
					return;
				}

				started = true;
				window.eeAnalytics.track( 'report_form_start', { section: type } );
			}, { once: true } );
			<?php if ( 'sent' === $state ) : ?>
			window.eeAnalytics.track( 'report_submitted', { section: type } );
			<?php endif; ?>
		})();
		</script>
	</section>
	<?php
}

/**
 * Report details for editors.
 */
function eagleeye_citizen_report_meta_box() {
	add_meta_box(
		'eagleeye_citizen_report_details',
		__( 'Report details', 'eagleeye' ),
		'eagleeye_citizen_report_meta_box_html',
		'eagleeye_report',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'eagleeye_citizen_report_meta_box' );

/**
 * Render report details.
 *
 * @param WP_Post $post Report post.
 */
function eagleeye_citizen_report_meta_box_html( $post ) {
	$type      = get_post_meta( $post->ID, '_ee_report_type', true );
	$files     = get_post_meta( $post->ID, '_ee_files', true );
	$submitted = get_post_meta( $post->ID, '_ee_submitted_at', true );
	$fields    = array(
		__( 'Section', 'eagleeye' )   => 'campus' === $type ? __( 'Campus Eye', 'eagleeye' ) : __( 'Community Eye', 'eagleeye' ),
		__( 'Name', 'eagleeye' )      => get_post_meta( $post->ID, '_ee_name', true ),
		__( 'Phone', 'eagleeye' )     => get_post_meta( $post->ID, '_ee_phone', true ),
		__( 'Community', 'eagleeye' ) => get_post_meta( $post->ID, '_ee_community', true ),
		__( 'Address', 'eagleeye' )   => get_post_meta( $post->ID, '_ee_address', true ),
		__( 'LGA', 'eagleeye' )       => get_post_meta( $post->ID, '_ee_lga', true ),
		__( 'School', 'eagleeye' )    => get_post_meta( $post->ID, '_ee_school', true ),
	);

	echo '<table class="widefat striped" style="margin-bottom:16px"><tbody>';

	foreach ( $fields as $label => $value ) {
		if ( '' === (string) $value ) {
			continue;
		}

		$display = 'phone' === strtolower( $label ) ? '<a href="tel:' . esc_attr( $value ) . '">' . esc_html( $value ) . '</a>' : esc_html( $value );
		echo '<tr><th style="width:150px">' . esc_html( $label ) . '</th><td>' . $display . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	if ( $submitted ) {
		echo '<tr><th>' . esc_html__( 'Submitted', 'eagleeye' ) . '</th><td>' . esc_html( $submitted ) . '</td></tr>';
	}

	echo '</tbody></table>';

	echo '<h3>' . esc_html__( 'Uploaded files', 'eagleeye' ) . '</h3>';

	if ( empty( $files ) || ! is_array( $files ) ) {
		echo '<p>' . esc_html__( 'No files uploaded.', 'eagleeye' ) . '</p>';
	} else {
		echo '<ul style="margin-left:18px;list-style:disc">';

		foreach ( $files as $index => $file ) {
			$url = eagleeye_citizen_report_file_url( $post->ID, $index );

			printf(
				'<li><a href="%1$s">%2$s</a> <span style="color:#777">(%3$s)</span></li>',
				esc_url( $url ),
				esc_html( $file['name'] ),
				esc_html( size_format( (int) $file['size'] ) )
			);

			if ( ! empty( $file['preview'] ) ) {
				printf(
					'<li style="list-style:none;margin-left:-18px;padding:6px 0 12px"><a href="%1$s" target="_blank" rel="noopener"><img src="%2$s" alt="%3$s" style="max-height:150px;width:auto;border:1px solid #dcdcde"></a><br><span style="color:#777">%4$s</span></li>',
					esc_url( $url ),
					esc_url( eagleeye_citizen_report_file_url( $post->ID, $index, true ) ),
					esc_attr( $file['name'] ),
					esc_html( sprintf( __( 'Photo metadata removed · %1$d × %2$d', 'eagleeye' ), (int) ( $file['width'] ?? 0 ), (int) ( $file['height'] ?? 0 ) ) )
				);
			}
		}

		echo '</ul>';
	}

	$action = get_post_meta( $post->ID, '_ee_action', true );

	if ( $action ) {
		echo '<h3>' . esc_html__( 'Suggested action', 'eagleeye' ) . '</h3><p>' . nl2br( esc_html( $action ) ) . '</p>';
	}

	echo '<p class="description">' . esc_html__( 'This report is private. Publish it as a normal story only after verification and editorial approval.', 'eagleeye' ) . '</p>';
}

/**
 * Time-limited link to a private upload.
 *
 * Links expire after 15 minutes and are tied to the signed-in editor, so a
 * copied URL stops working instead of leaving a permanent path to a tip.
 *
 * @param int  $post_id Report ID.
 * @param int  $index   File index.
 * @param bool $preview Whether to serve the small preview image.
 * @return string
 */
function eagleeye_citizen_report_file_url( $post_id, $index, $preview = false ) {
	$post_id = absint( $post_id );
	$index   = absint( $index );
	$expires = time() + 15 * MINUTE_IN_SECONDS;
	$sig     = hash_hmac(
		'sha256',
		$post_id . '|' . $index . '|' . ( $preview ? 'p' : 'f' ) . '|' . $expires . '|' . get_current_user_id(),
		wp_salt( 'auth' )
	);

	return add_query_arg(
		array(
			'action'  => 'eagleeye_citizen_report_file',
			'report'  => $post_id,
			'file'    => $index,
			'preview' => $preview ? 1 : 0,
			'exp'     => $expires,
			'sig'     => $sig,
		),
		admin_url( 'admin-post.php' )
	);
}

/**
 * Stream a private uploaded file to an authenticated editor.
 */
function eagleeye_citizen_report_download() {
	$post_id = isset( $_GET['report'] ) ? absint( $_GET['report'] ) : 0;
	$index   = isset( $_GET['file'] ) ? absint( $_GET['file'] ) : 0;
	$preview = ! empty( $_GET['preview'] );

	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_die( esc_html__( 'You are not allowed to download this file.', 'eagleeye' ), 403 );
	}

	$expires = isset( $_GET['exp'] ) ? absint( $_GET['exp'] ) : 0;
	$sig     = isset( $_GET['sig'] ) ? sanitize_text_field( wp_unslash( $_GET['sig'] ) ) : '';
	$valid   = false;

	if ( $expires && $sig && $expires >= time() ) {
		$expected = hash_hmac(
			'sha256',
			$post_id . '|' . $index . '|' . ( $preview ? 'p' : 'f' ) . '|' . $expires . '|' . get_current_user_id(),
			wp_salt( 'auth' )
		);
		$valid = hash_equals( $expected, $sig );
	}

	if ( ! $valid ) {
		wp_die( esc_html__( 'This file link has expired. Open the report again to get a new link.', 'eagleeye' ), 403 );
	}

	$files = get_post_meta( $post_id, '_ee_files', true );

	if ( empty( $files[ $index ]['file'] ) ) {
		wp_die( esc_html__( 'File not found.', 'eagleeye' ), 404 );
	}

	$stored = $files[ $index ];
	$name   = $stored['file'];
	$type   = isset( $stored['type'] ) ? (string) $stored['type'] : '';
	$inline = false;

	if ( $preview && ! empty( $stored['preview'] ) ) {
		$name   = $stored['preview'];
		$type   = 'image/jpeg';
		$inline = true;
	}

	if ( ! $type || ! in_array( $type, array_values( eagleeye_citizen_report_allowed_types() ), true ) ) {
		$type = 'application/octet-stream';
	}

	$root = realpath( eagleeye_citizen_report_private_dir() );
	$path = realpath( trailingslashit( eagleeye_citizen_report_private_dir() ) . 'report-' . $post_id . '/' . basename( $name ) );

	if ( ! $root || ! $path || 0 !== strpos( $path, $root . DIRECTORY_SEPARATOR ) || ! is_file( $path ) || ! is_readable( $path ) ) {
		wp_die( esc_html__( 'File not found.', 'eagleeye' ), 404 );
	}

	nocache_headers();
	header( 'Content-Type: ' . $type );
	header( 'Content-Disposition: ' . ( $inline ? 'inline' : 'attachment' ) . '; filename="' . sanitize_file_name( $inline ? $name : $stored['name'] ) . '"' );
	header( 'Content-Length: ' . filesize( $path ) );
	header( 'X-Content-Type-Options: nosniff' );
	readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
	exit;
}
add_action( 'admin_post_eagleeye_citizen_report_file', 'eagleeye_citizen_report_download' );

/**
 * Add report list columns.
 *
 * @param array $columns Columns.
 * @return array
 */
function eagleeye_citizen_report_columns( $columns ) {
	$columns['ee_type']    = __( 'Section', 'eagleeye' );
	$columns['ee_contact'] = __( 'Contact', 'eagleeye' );
	$columns['ee_files']   = __( 'Files', 'eagleeye' );

	return $columns;
}
add_filter( 'manage_eagleeye_report_posts_columns', 'eagleeye_citizen_report_columns' );

/**
 * Render report list columns.
 *
 * @param string $column Column key.
 * @param int    $post_id Report ID.
 */
function eagleeye_citizen_report_column_content( $column, $post_id ) {
	if ( 'ee_type' === $column ) {
		echo 'campus' === get_post_meta( $post_id, '_ee_report_type', true ) ? esc_html__( 'Campus Eye', 'eagleeye' ) : esc_html__( 'Community Eye', 'eagleeye' );
	}

	if ( 'ee_contact' === $column ) {
		$name  = get_post_meta( $post_id, '_ee_name', true );
		$phone = get_post_meta( $post_id, '_ee_phone', true );
		printf( '%1$s<br><a href="tel:%2$s">%3$s</a>', esc_html( $name ? $name : __( 'No name', 'eagleeye' ) ), esc_attr( $phone ), esc_html( $phone ) );
	}

	if ( 'ee_files' === $column ) {
		$files = get_post_meta( $post_id, '_ee_files', true );
		echo esc_html( is_array( $files ) ? (string) count( $files ) : '0' );
	}
}
add_action( 'manage_eagleeye_report_posts_custom_column', 'eagleeye_citizen_report_column_content', 10, 2 );
