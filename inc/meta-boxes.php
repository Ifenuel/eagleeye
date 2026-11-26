<?php
/**
 * Editorial controls in the post editor.
 *
 * @package Eagle Eye
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the editorial details box.
 */
function eagleeye_add_meta_boxes() {
	add_meta_box(
		'eagleeye_editorial',
		__( 'Eagle Eye Editorial Controls', 'eagleeye' ),
		'eagleeye_render_editorial_box',
		'post',
		'side',
		'high'
	);

	add_meta_box(
		'eagleeye_standfirst',
		__( 'Standfirst & Source', 'eagleeye' ),
		'eagleeye_render_standfirst_box',
		'post',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'eagleeye_add_meta_boxes' );

/**
 * Side panel: placement flags and primary desk.
 *
 * @param WP_Post $post Current post.
 */
function eagleeye_render_editorial_box( $post ) {
	wp_nonce_field( 'eagleeye_save_editorial', 'eagleeye_editorial_nonce' );

	$breaking    = get_post_meta( $post->ID, '_eagleeye_breaking', true );
	$featured    = get_post_meta( $post->ID, '_eagleeye_featured', true );
	$primary_cat = (int) get_post_meta( $post->ID, '_eagleeye_primary_category', true );
	$categories  = get_categories( array( 'hide_empty' => false ) );
	?>
	<p class="eagleeye-field">
		<label>
			<input type="checkbox" name="eagleeye_breaking" value="1" <?php checked( $breaking, '1' ); ?>>
			<strong><?php esc_html_e( 'Breaking news', 'eagleeye' ); ?></strong>
		</label>
		<span class="description"><?php esc_html_e( 'Adds this story to the site-wide ticker.', 'eagleeye' ); ?></span>
	</p>

	<p class="eagleeye-field">
		<label>
			<input type="checkbox" name="eagleeye_featured" value="1" <?php checked( $featured, '1' ); ?>>
			<strong><?php esc_html_e( 'Lead the homepage', 'eagleeye' ); ?></strong>
		</label>
		<span class="description"><?php esc_html_e( 'Featured stories take priority in the hero area.', 'eagleeye' ); ?></span>
	</p>

	<p class="eagleeye-field">
		<label for="eagleeye_primary_category"><strong><?php esc_html_e( 'Primary section', 'eagleeye' ); ?></strong></label>
		<select name="eagleeye_primary_category" id="eagleeye_primary_category" class="widefat">
			<option value="0"><?php esc_html_e( 'First assigned category', 'eagleeye' ); ?></option>
			<?php foreach ( $categories as $category ) : ?>
				<option value="<?php echo esc_attr( $category->term_id ); ?>" <?php selected( $primary_cat, $category->term_id ); ?>>
					<?php echo esc_html( $category->name ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<span class="description"><?php esc_html_e( 'Controls the kicker shown on cards and the article page.', 'eagleeye' ); ?></span>
	</p>
	<?php
}

/**
 * Main column: standfirst and source.
 *
 * @param WP_Post $post Current post.
 */
function eagleeye_render_standfirst_box( $post ) {
	$dek    = (string) get_post_meta( $post->ID, '_eagleeye_dek', true );
	$source = (string) get_post_meta( $post->ID, '_eagleeye_source', true );
	$credit = (string) get_post_meta( $post->ID, '_eagleeye_credit', true );
	?>
	<p class="eagleeye-field">
		<label for="eagleeye_dek"><strong><?php esc_html_e( 'Standfirst (deck)', 'eagleeye' ); ?></strong></label>
		<textarea name="eagleeye_dek" id="eagleeye_dek" rows="3" class="widefat" placeholder="<?php esc_attr_e( 'One or two sentences that set up the story. Leave empty to use the excerpt.', 'eagleeye' ); ?>"><?php echo esc_textarea( $dek ); ?></textarea>
	</p>
	<p class="eagleeye-field">
		<label for="eagleeye_source"><strong><?php esc_html_e( 'Source / credit line', 'eagleeye' ); ?></strong></label>
		<input type="text" name="eagleeye_source" id="eagleeye_source" class="widefat" value="<?php echo esc_attr( $source ); ?>" placeholder="<?php esc_attr_e( 'e.g. Eagle Eye Newsroom, Reuters', 'eagleeye' ); ?>">
	</p>
	<p class="eagleeye-field">
		<label for="eagleeye_credit"><strong><?php esc_html_e( 'Published by (reporter credit)', 'eagleeye' ); ?></strong></label>
		<input type="text" name="eagleeye_credit" id="eagleeye_credit" class="widefat" value="<?php echo esc_attr( $credit ); ?>" placeholder="<?php esc_attr_e( 'e.g. Ifenuel Okon', 'eagleeye' ); ?>">
		<span class="description"><?php esc_html_e( 'Leave empty to publish as Eagle Eye. The name typed here becomes the byline and the &#8220;Published by&#8221; credit on this story.', 'eagleeye' ); ?></span>
	</p>
	<?php
}

/**
 * Persist editorial fields.
 *
 * @param int $post_id Post ID.
 */
function eagleeye_save_editorial( $post_id ) {
	if ( ! isset( $_POST['eagleeye_editorial_nonce'] ) ) {
		return;
	}

	$nonce = sanitize_text_field( wp_unslash( $_POST['eagleeye_editorial_nonce'] ) );

	if ( ! wp_verify_nonce( $nonce, 'eagleeye_save_editorial' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$flags = array(
		'_eagleeye_breaking' => 'eagleeye_breaking',
		'_eagleeye_featured' => 'eagleeye_featured',
	);

	foreach ( $flags as $meta_key => $field ) {
		if ( isset( $_POST[ $field ] ) && '1' === $_POST[ $field ] ) {
			update_post_meta( $post_id, $meta_key, '1' );
		} else {
			delete_post_meta( $post_id, $meta_key );
		}
	}

	if ( isset( $_POST['eagleeye_dek'] ) ) {
		$dek = sanitize_textarea_field( wp_unslash( $_POST['eagleeye_dek'] ) );
		$dek ? update_post_meta( $post_id, '_eagleeye_dek', $dek ) : delete_post_meta( $post_id, '_eagleeye_dek' );
	}

	if ( isset( $_POST['eagleeye_source'] ) ) {
		$source = sanitize_text_field( wp_unslash( $_POST['eagleeye_source'] ) );
		$source ? update_post_meta( $post_id, '_eagleeye_source', $source ) : delete_post_meta( $post_id, '_eagleeye_source' );
	}

	if ( isset( $_POST['eagleeye_credit'] ) ) {
		$credit = sanitize_text_field( wp_unslash( $_POST['eagleeye_credit'] ) );
		$credit ? update_post_meta( $post_id, '_eagleeye_credit', $credit ) : delete_post_meta( $post_id, '_eagleeye_credit' );
	}

	if ( isset( $_POST['eagleeye_primary_category'] ) ) {
		$cat_id = absint( $_POST['eagleeye_primary_category'] );

		if ( $cat_id && term_exists( $cat_id, 'category' ) ) {
			update_post_meta( $post_id, '_eagleeye_primary_category', $cat_id );
		} else {
			delete_post_meta( $post_id, '_eagleeye_primary_category' );
		}
	}
}
add_action( 'save_post_post', 'eagleeye_save_editorial' );

/**
 * Show placement flags in the posts list.
 *
 * @param array $columns Existing columns.
 * @return array
 */
function eagleeye_admin_columns( $columns ) {
	$new = array();

	foreach ( $columns as $key => $label ) {
		if ( 'title' === $key ) {
			$new['ee_thumb'] = __( 'Image', 'eagleeye' );
		}

		$new[ $key ] = $label;

		if ( 'title' === $key ) {
			$new['ee_desk'] = __( 'Desk', 'eagleeye' );
			$new['eagleeye_flags'] = __( 'Placement', 'eagleeye' );
		}
	}

	return $new;
}
add_filter( 'manage_post_posts_columns', 'eagleeye_admin_columns' );

/**
 * Render the placement column.
 *
 * @param string $column  Column key.
 * @param int    $post_id Post ID.
 */
function eagleeye_admin_column_content( $column, $post_id ) {
	if ( 'ee_thumb' === $column ) {
		$thumb = get_the_post_thumbnail( $post_id, array( 64, 64 ), array( 'style' => 'width:48px;height:48px;object-fit:cover;display:block' ) );
		echo $thumb ? wp_kses_post( $thumb ) : '<span aria-hidden="true">—</span>';
		return;
	}

	if ( 'ee_desk' === $column ) {
		$primary = (int) get_post_meta( $post_id, '_eagleeye_primary_category', true );
		$term    = $primary ? get_term( $primary, 'category' ) : null;

		if ( ! $term || is_wp_error( $term ) ) {
			$cats = get_the_category( $post_id );
			$term = $cats ? $cats[0] : null;
		}

		echo $term ? esc_html( $term->name ) : '—';
		return;
	}

	if ( 'eagleeye_flags' !== $column ) {
		return;
	}

	$flags = array();

	if ( eagleeye_flag( '_eagleeye_breaking', $post_id ) ) {
		$flags[] = '<span class="eagleeye-pill eagleeye-pill--breaking">' . esc_html__( 'Breaking', 'eagleeye' ) . '</span>';
	}

	if ( eagleeye_flag( '_eagleeye_featured', $post_id ) ) {
		$flags[] = '<span class="eagleeye-pill">' . esc_html__( 'Featured', 'eagleeye' ) . '</span>';
	}

	echo $flags ? wp_kses_post( implode( ' ', $flags ) ) : '<span aria-hidden="true">—</span>';
}
add_action( 'manage_post_posts_custom_column', 'eagleeye_admin_column_content', 10, 2 );

/**
 * Small styles for the editorial meta box and columns.
 *
 * @param string $hook Admin page hook.
 */
function eagleeye_admin_styles( $hook ) {
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php', 'edit.php' ), true ) ) {
		return;
	}

	echo '<style>
		.eagleeye-field{margin:0 0 14px}
		.eagleeye-field label{display:block;margin-bottom:4px}
		.eagleeye-field .description{display:block;margin-top:4px;color:#646970}
		.eagleeye-pill{display:inline-block;padding:2px 7px;border-radius:999px;background:#eef0f2;color:#1d2327;font-size:11px;font-weight:600}
		.eagleeye-pill--breaking{background:#fce8ea;color:#b3121d}
		.column-ee_thumb{width:64px}
		.column-ee_desk{width:120px}
	</style>';
}
add_action( 'admin_head', 'eagleeye_admin_styles' );
