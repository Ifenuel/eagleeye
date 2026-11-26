<?php
/**
 * Corrections and developing-story updates.
 *
 * Editors record a dated correction, clarification or update on a story, and
 * readers see the change history at the end of the article.
 *
 * @package Eagle Eye
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Correction types.
 *
 * @return array<string,string>
 */
function eagleeye_correction_types() {
	return array(
		'correction'    => __( 'Correction', 'eagleeye' ),
		'clarification' => __( 'Clarification', 'eagleeye' ),
		'update'        => __( 'Update', 'eagleeye' ),
	);
}

/**
 * Stored corrections for a story, newest first.
 *
 * @param int $post_id Story ID.
 * @return array<int,array<string,string>>
 */
function eagleeye_corrections( $post_id ) {
	$items = get_post_meta( $post_id, '_ee_corrections', true );

	return is_array( $items ) ? $items : array();
}

/**
 * Register the editor panel.
 */
function eagleeye_corrections_meta_box() {
	add_meta_box(
		'eagleeye_corrections',
		__( 'Corrections and updates', 'eagleeye' ),
		'eagleeye_corrections_meta_box_html',
		'post',
		'normal',
		'default'
	);
}
add_action( 'add_meta_boxes', 'eagleeye_corrections_meta_box' );

/**
 * Render the panel.
 *
 * @param WP_Post $post Story.
 */
function eagleeye_corrections_meta_box_html( $post ) {
	$items = eagleeye_corrections( $post->ID );
	$types = eagleeye_correction_types();

	wp_nonce_field( 'eagleeye_corrections', 'eagleeye_corrections_nonce' );
	?>
	<p class="description"><?php esc_html_e( 'Record significant changes so readers can see what changed and when. Entries appear at the end of the published story.', 'eagleeye' ); ?></p>

	<?php if ( $items ) : ?>
		<table class="widefat striped" style="margin:12px 0">
			<thead>
				<tr>
					<th style="width:110px"><?php esc_html_e( 'Date', 'eagleeye' ); ?></th>
					<th style="width:130px"><?php esc_html_e( 'Type', 'eagleeye' ); ?></th>
					<th><?php esc_html_e( 'Note', 'eagleeye' ); ?></th>
					<th style="width:80px"><?php esc_html_e( 'Remove', 'eagleeye' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $items as $index => $item ) : ?>
					<tr>
						<td><?php echo esc_html( $item['date'] ?? '' ); ?></td>
						<td><?php echo esc_html( $types[ $item['type'] ?? 'correction' ] ?? __( 'Correction', 'eagleeye' ) ); ?></td>
						<td><?php echo nl2br( esc_html( $item['note'] ?? '' ) ); ?></td>
						<td>
							<label>
								<input type="checkbox" name="ee_correction_delete[]" value="<?php echo esc_attr( (string) $index ); ?>">
								<span class="screen-reader-text"><?php esc_html_e( 'Remove this entry', 'eagleeye' ); ?></span>
							</label>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<fieldset style="border:1px solid #dcdcde;padding:12px;margin-top:12px">
		<legend><strong><?php esc_html_e( 'Add an entry', 'eagleeye' ); ?></strong></legend>
		<p>
			<label for="ee-correction-date"><?php esc_html_e( 'Date', 'eagleeye' ); ?></label>
			<input type="date" id="ee-correction-date" name="ee_correction_date" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>">
			<label for="ee-correction-type" style="margin-left:12px"><?php esc_html_e( 'Type', 'eagleeye' ); ?></label>
			<select id="ee-correction-type" name="ee_correction_type">
				<?php foreach ( $types as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="ee-correction-note" class="screen-reader-text"><?php esc_html_e( 'What changed and why', 'eagleeye' ); ?></label>
			<textarea id="ee-correction-note" name="ee_correction_note" rows="3" class="large-text" placeholder="<?php esc_attr_e( 'e.g. Corrected the number of pupils affected. The earlier figure came from a verbal briefing; the education board later issued a written statement.', 'eagleeye' ); ?>"></textarea>
		</p>
		<p class="description" style="margin:0"><?php esc_html_e( 'Save the story to store the entry. To remove an entry, tick Remove and save.', 'eagleeye' ); ?></p>
	</fieldset>
	<?php
}

/**
 * Save corrections, appending new entries and removing ticked ones.
 *
 * @param int $post_id Story ID.
 */
function eagleeye_corrections_save( $post_id ) {
	if ( ! isset( $_POST['eagleeye_corrections_nonce'] ) ) {
		return;
	}

	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['eagleeye_corrections_nonce'] ) ), 'eagleeye_corrections' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( 'post' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$items = eagleeye_corrections( $post_id );
	$types = eagleeye_correction_types();

	if ( ! empty( $_POST['ee_correction_delete'] ) && is_array( $_POST['ee_correction_delete'] ) ) {
		$remove = array_map( 'absint', wp_unslash( $_POST['ee_correction_delete'] ) );

		foreach ( $remove as $index ) {
			unset( $items[ $index ] );
		}

		$items = array_values( $items );
	}

	$note = isset( $_POST['ee_correction_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ee_correction_note'] ) ) : '';

	if ( $note ) {
		$date  = isset( $_POST['ee_correction_date'] ) ? sanitize_text_field( wp_unslash( $_POST['ee_correction_date'] ) ) : '';
		$type  = isset( $_POST['ee_correction_type'] ) ? sanitize_key( wp_unslash( $_POST['ee_correction_type'] ) ) : 'correction';
		$items[] = array(
			'date' => $date ? $date : current_time( 'Y-m-d' ),
			'type' => isset( $types[ $type ] ) ? $type : 'correction',
			'note' => $note,
			'user' => get_current_user_id(),
		);
	}

	if ( $items ) {
		update_post_meta( $post_id, '_ee_corrections', $items );
	} else {
		delete_post_meta( $post_id, '_ee_corrections' );
	}
}
add_action( 'save_post', 'eagleeye_corrections_save' );

/**
 * Show the correction history at the end of a story.
 *
 * @param string $content Story content.
 * @return string
 */
function eagleeye_corrections_append( $content ) {
	if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}

	$items = eagleeye_corrections( get_the_ID() );

	if ( ! $items ) {
		return $content;
	}

	$types = eagleeye_correction_types();
	$html  = '<section class="ee-corrections" aria-labelledby="ee-corrections-title">';
	$html .= '<h2 class="ee-corrections__title" id="ee-corrections-title">' . esc_html__( 'Corrections and updates', 'eagleeye' ) . '</h2>';
	$html .= '<p class="ee-corrections__intro">' . esc_html__( 'This story has been changed since it was first published. The record is kept here for transparency.', 'eagleeye' ) . '</p>';
	$html .= '<ol class="ee-corrections__list">';

	foreach ( array_reverse( $items ) as $item ) {
		$type = $item['type'] ?? 'correction';
		$date = ! empty( $item['date'] ) ? strtotime( $item['date'] ) : 0;

		$html .= '<li class="ee-corrections__item">';
		$html .= '<p class="ee-corrections__meta"><strong>' . esc_html( $types[ $type ] ?? __( 'Correction', 'eagleeye' ) ) . '</strong>';
		$html .= '<time datetime="' . esc_attr( $date ? gmdate( 'Y-m-d', $date ) : '' ) . '">' . esc_html( $date ? date_i18n( 'j F Y', $date ) : '' ) . '</time></p>';
		$html .= '<p class="ee-corrections__note">' . nl2br( esc_html( $item['note'] ?? '' ) ) . '</p>';
		$html .= '</li>';
	}

	$html .= '</ol>';
	$html .= '<p class="ee-corrections__link"><a href="' . esc_url( home_url( '/corrections-and-complaints/' ) ) . '">' . esc_html__( 'Report an error or complaint', 'eagleeye' ) . '</a></p>';
	$html .= '</section>';

	return $content . $html;
}
add_filter( 'the_content', 'eagleeye_corrections_append', 25 );
