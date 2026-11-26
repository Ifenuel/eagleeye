<?php
/**
 * Server-side secrets.
 *
 * Production credentials live outside the public web root. The preferred path
 * is /home/<account>/private/eagleeye-secrets.php, one level above the domain
 * directory. A constant can override it. Secrets are never read from a file
 * inside the public web root.
 *
 * @package Eagle Eye
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Path to the secrets file, or an empty string when none exists.
 *
 * @return string
 */
function eagleeye_secrets_file() {
	static $file = null;

	if ( null !== $file ) {
		return $file;
	}

	if ( defined( 'EAGLEEYE_SECRETS_FILE' ) && EAGLEEYE_SECRETS_FILE ) {
		$file = (string) EAGLEEYE_SECRETS_FILE;
		return $file;
	}

	// /home/account/domains/domain/public_html -> /home/account/private/...
	$outside = dirname( dirname( dirname( ABSPATH ) ) ) . '/private/eagleeye-secrets.php';

	if ( @file_exists( $outside ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- missing file is expected outside production.
		$file = $outside;
		return $file;
	}

	$file = '';

	return $file;
}

/**
 * Read one secret without ever exposing the file contents.
 *
 * @param string $key Secret name.
 * @return string
 */
function eagleeye_secret( $key ) {
	static $secrets = null;

	if ( null === $secrets ) {
		$secrets = array();
		$file    = eagleeye_secrets_file();

		if ( $file ) {
			$loaded = include $file;

			if ( is_array( $loaded ) ) {
				$secrets = $loaded;
			}
		}
	}

	$key = (string) $key;

	return isset( $secrets[ $key ] ) ? (string) $secrets[ $key ] : '';
}
