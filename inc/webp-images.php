<?php
/**
 * WebP siblings for new uploads.
 *
 * .htaccess serves <file>.webp to browsers that accept it, so the JPEG/PNG
 * stays untouched as the fallback.
 *
 * @package Eagle Eye
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Write <path>.webp for one image file.
 *
 * @param string $path Absolute path to a JPEG or PNG.
 * @return bool True when a WebP file exists afterwards.
 */
function eagleeye_write_webp( $path ) {
	if ( ! is_file( $path ) || ! class_exists( 'Imagick' ) ) {
		return false;
	}

	$extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

	if ( ! in_array( $extension, array( 'jpg', 'jpeg', 'png' ), true ) ) {
		return false;
	}

	$target = $path . '.webp';

	if ( file_exists( $target ) ) {
		return true;
	}

	try {
		$image = new Imagick( $path );
		$image->setImageFormat( 'webp' );
		$image->setImageCompressionQuality( 82 );
		$image->stripImage();

		if ( 'png' === $extension ) {
			$image->setImageAlphaChannel( Imagick::ALPHACHANNEL_SET );
		}

		$image->writeImage( $target );
		$image->clear();

		return file_exists( $target );
	} catch ( Exception $e ) {
		return false;
	}
}

/**
 * Generate WebP copies whenever WordPress creates an image's sizes.
 *
 * @param array $metadata      Attachment metadata.
 * @param int   $attachment_id Attachment ID.
 * @return array
 */
function eagleeye_webp_on_upload( $metadata, $attachment_id ) {
	if ( empty( $metadata['file'] ) ) {
		return $metadata;
	}

	$uploads = wp_get_upload_dir();
	$base    = trailingslashit( $uploads['basedir'] ) . dirname( $metadata['file'] );
	$base    = untrailingslashit( $base );

	eagleeye_write_webp( trailingslashit( $uploads['basedir'] ) . $metadata['file'] );

	if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
		foreach ( $metadata['sizes'] as $size ) {
			if ( ! empty( $size['file'] ) ) {
				eagleeye_write_webp( $base . '/' . $size['file'] );
			}
		}
	}

	return $metadata;
}
add_filter( 'wp_generate_attachment_metadata', 'eagleeye_webp_on_upload', 20, 2 );
