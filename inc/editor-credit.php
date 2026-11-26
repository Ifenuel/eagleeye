<?php
/**
 * "Published by" credit in the block editor sidebar.
 *
 * @package Eagle Eye
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Expose the credit to the block editor so it can be edited in a sidebar panel.
 */
function eagleeye_register_credit_meta() {
	register_post_meta(
		'post',
		'_eagleeye_credit',
		array(
			'type'         => 'string',
			'single'       => true,
			'show_in_rest' => true,
			'auth_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);
}
add_action( 'init', 'eagleeye_register_credit_meta' );

/**
 * Load the sidebar panel while writing a story.
 */
function eagleeye_credit_editor_assets() {
	$file = get_template_directory() . '/assets/js/editor-credit.js';

	wp_enqueue_script(
		'eagleeye-credit-editor',
		get_template_directory_uri() . '/assets/js/editor-credit.js',
		array( 'wp-plugins', 'wp-editor', 'wp-components', 'wp-data', 'wp-element' ),
		file_exists( $file ) ? filemtime( $file ) : '1.0.0',
		true
	);
}
add_action( 'enqueue_block_editor_assets', 'eagleeye_credit_editor_assets' );
