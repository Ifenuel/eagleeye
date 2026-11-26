<?php
/**
 * Oyo State coverage plan.
 *
 * Keeps an editor-approved source list and shows which local government areas
 * and sections have had little or no recent coverage, so the desk can plan
 * original follow-up instead of rewriting whatever search surfaces first.
 *
 * @package Eagle Eye
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The 33 local government areas of Oyo State.
 *
 * @return string[]
 */
function eagleeye_oyo_lgas() {
	return array(
		'Afijio',
		'Akinyele',
		'Atiba',
		'Atisbo',
		'Egbeda',
		'Ibadan North',
		'Ibadan North East',
		'Ibadan North West',
		'Ibadan South East',
		'Ibadan South West',
		'Ibarapa Central',
		'Ibarapa East',
		'Ibarapa North',
		'Ido',
		'Irepo',
		'Iseyin',
		'Itesiwaju',
		'Iwajowa',
		'Kajola',
		'Lagelu',
		'Ogbomosho North',
		'Ogbomosho South',
		'Ogo Oluwa',
		'Olorunsogo',
		'Oluyole',
		'Ona Ara',
		'Orelope',
		'Ori Ire',
		'Oyo East',
		'Oyo West',
		'Saki East',
		'Saki West',
		'Surulere',
	);
}

/**
 * Approved source list.
 *
 * @return array<int,array<string,string>>
 */
function eagleeye_coverage_sources() {
	$sources = get_option( 'eagleeye_coverage_sources', array() );

	return is_array( $sources ) ? $sources : array();
}

/**
 * Source categories.
 *
 * @return array<string,string>
 */
function eagleeye_coverage_source_types() {
	return array(
		'agency'    => __( 'Official agency', 'eagleeye' ),
		'outlet'    => __( 'News outlet', 'eagleeye' ),
		'community' => __( 'Community source', 'eagleeye' ),
		'other'     => __( 'Other', 'eagleeye' ),
	);
}

/**
 * Add the coverage page.
 */
function eagleeye_coverage_menu() {
	add_submenu_page(
		'eagleeye',
		__( 'Coverage plan', 'eagleeye' ),
		__( 'Coverage plan', 'eagleeye' ),
		'edit_posts',
		'eagleeye-coverage',
		'eagleeye_coverage_page'
	);
}
add_action( 'admin_menu', 'eagleeye_coverage_menu', 12 );

/**
 * Save or remove a source.
 */
function eagleeye_coverage_handle_source() {
	check_admin_referer( 'eagleeye_coverage_source' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html__( 'You are not allowed to change the source list.', 'eagleeye' ), 403 );
	}

	$sources = eagleeye_coverage_sources();
	$action  = isset( $_POST['ee_source_action'] ) ? sanitize_key( wp_unslash( $_POST['ee_source_action'] ) ) : 'add';

	if ( 'remove' === $action ) {
		$index = isset( $_POST['ee_source_index'] ) ? absint( $_POST['ee_source_index'] ) : -1;

		if ( isset( $sources[ $index ] ) ) {
			unset( $sources[ $index ] );
			$sources = array_values( $sources );
			update_option( 'eagleeye_coverage_sources', $sources, false );
		}

		wp_safe_redirect( add_query_arg( 'ee_source', 'removed', admin_url( 'admin.php?page=eagleeye-coverage' ) ) );
		exit;
	}

	$name = isset( $_POST['ee_source_name'] ) ? sanitize_text_field( wp_unslash( $_POST['ee_source_name'] ) ) : '';
	$url  = isset( $_POST['ee_source_url'] ) ? esc_url_raw( wp_unslash( $_POST['ee_source_url'] ) ) : '';
	$type = isset( $_POST['ee_source_type'] ) ? sanitize_key( wp_unslash( $_POST['ee_source_type'] ) ) : 'agency';
	$note = isset( $_POST['ee_source_note'] ) ? sanitize_text_field( wp_unslash( $_POST['ee_source_note'] ) ) : '';

	if ( $name ) {
		$types     = eagleeye_coverage_source_types();
		$sources[] = array(
			'name' => $name,
			'url'  => $url,
			'type' => isset( $types[ $type ] ) ? $type : 'agency',
			'note' => $note,
		);

		update_option( 'eagleeye_coverage_sources', $sources, false );
	}

	wp_safe_redirect( add_query_arg( 'ee_source', 'saved', admin_url( 'admin.php?page=eagleeye-coverage' ) ) );
	exit;
}
add_action( 'admin_post_eagleeye_coverage_source', 'eagleeye_coverage_handle_source' );

/**
 * Recent coverage by area and section.
 *
 * This is a keyword check on published stories, not proof that a place was
 * actually reported from.
 *
 * @param int $days Look-back window.
 * @return array<string,int>
 */
function eagleeye_coverage_recent( $days = 30 ) {
	$posts = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 300,
			'no_found_rows'  => true,
			'date_query'     => array(
				array(
					'after' => gmdate( 'Y-m-d H:i:s', strtotime( '-' . absint( $days ) . ' days' ) ),
					'column' => 'post_date_gmt',
				),
			),
		)
	);

	$counts = array();

	foreach ( eagleeye_oyo_lgas() as $lga ) {
		$counts[ $lga ] = 0;
	}

	foreach ( $posts as $post ) {
		$haystack = $post->post_title . ' ' . $post->post_content . ' ' . $post->post_excerpt;

		foreach ( array_keys( $counts ) as $lga ) {
			if ( false !== stripos( $haystack, $lga ) ) {
				$counts[ $lga ]++;
			}
		}
	}

	return $counts;
}

/**
 * Render the coverage page.
 */
function eagleeye_coverage_page() {
	$sources   = eagleeye_coverage_sources();
	$types     = eagleeye_coverage_source_types();
	$counts    = eagleeye_coverage_recent( 30 );
	$days      = 30;
	$gaps      = array_keys( array_filter( $counts, function ( $count ) { return 0 === (int) $count; } ) );
	$covered   = count( $counts ) - count( $gaps );
	$categories = get_categories( array( 'hide_empty' => false ) );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Coverage plan', 'eagleeye' ); ?></h1>
		<p class="description" style="max-width:760px"><?php esc_html_e( 'Keep a deliberate list of trusted local sources and watch which parts of Oyo State have gone quiet. The counts are keyword matches in recently published stories, so treat them as a prompt to check, not as a claim about reporting.', 'eagleeye' ); ?></p>

		<?php if ( isset( $_GET['ee_source'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Source list updated.', 'eagleeye' ); ?></p></div>
		<?php endif; ?>

		<div class="card" style="max-width:none;padding:18px;margin:18px 0">
			<h2 style="margin-top:0"><?php echo esc_html( sprintf( __( 'Last %d days', 'eagleeye' ), $days ) ); ?></h2>
			<p>
				<?php
				printf(
					esc_html__( '%1$d of %2$d local government areas were mentioned in published stories. %3$d were not mentioned at all.', 'eagleeye' ),
					(int) $covered,
					(int) count( $counts ),
					(int) count( $gaps )
				);
				?>
			</p>
			<table class="widefat striped" style="max-width:900px">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Local Government Area', 'eagleeye' ); ?></th>
						<th style="width:140px"><?php esc_html_e( 'Stories', 'eagleeye' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $counts as $lga => $count ) : ?>
						<tr>
							<td><?php echo esc_html( $lga ); ?></td>
							<td>
								<?php if ( 0 === (int) $count ) : ?>
									<span style="color:#8a4b00;font-weight:600"><?php esc_html_e( 'No mention', 'eagleeye' ); ?></span>
								<?php else : ?>
									<?php echo esc_html( number_format_i18n( (int) $count ) ); ?>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<div class="card" style="max-width:none;padding:18px;margin:18px 0">
			<h2 style="margin-top:0"><?php esc_html_e( 'Sections with no recent stories', 'eagleeye' ); ?></h2>
			<?php
			$quiet = array();

			foreach ( $categories as $category ) {
				if ( 0 === (int) $category->count ) {
					$quiet[] = $category->name;
				}
			}
			?>
			<p>
				<?php echo $quiet ? esc_html( implode( ', ', $quiet ) ) : esc_html__( 'Every section has at least one published story.', 'eagleeye' ); ?>
			</p>
		</div>

		<div class="card" style="max-width:none;padding:18px;margin:18px 0">
			<h2 style="margin-top:0"><?php esc_html_e( 'Editor-approved sources', 'eagleeye' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Official agencies, local outlets and community contacts the desk has agreed to check. This list guides research; it does not grant rights to reuse another outlet’s text, photos or video.', 'eagleeye' ); ?></p>

			<?php if ( $sources ) : ?>
				<table class="widefat striped" style="margin-bottom:18px">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Source', 'eagleeye' ); ?></th>
							<th style="width:150px"><?php esc_html_e( 'Type', 'eagleeye' ); ?></th>
							<th><?php esc_html_e( 'Why the desk trusts it', 'eagleeye' ); ?></th>
							<th style="width:100px"><?php esc_html_e( 'Remove', 'eagleeye' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $sources as $index => $source ) : ?>
							<tr>
								<td>
									<?php if ( ! empty( $source['url'] ) ) : ?>
										<a href="<?php echo esc_url( $source['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $source['name'] ); ?></a>
									<?php else : ?>
										<?php echo esc_html( $source['name'] ); ?>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $types[ $source['type'] ?? 'other' ] ?? __( 'Other', 'eagleeye' ) ); ?></td>
								<td><?php echo esc_html( $source['note'] ?? '' ); ?></td>
								<td>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<input type="hidden" name="action" value="eagleeye_coverage_source">
										<input type="hidden" name="ee_source_action" value="remove">
										<input type="hidden" name="ee_source_index" value="<?php echo esc_attr( (string) $index ); ?>">
										<?php wp_nonce_field( 'eagleeye_coverage_source' ); ?>
										<button type="submit" class="button button-small"><?php esc_html_e( 'Remove', 'eagleeye' ); ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p><?php esc_html_e( 'No sources added yet.', 'eagleeye' ); ?></p>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="eagleeye_coverage_source">
				<input type="hidden" name="ee_source_action" value="add">
				<?php wp_nonce_field( 'eagleeye_coverage_source' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="ee-source-name"><?php esc_html_e( 'Name', 'eagleeye' ); ?></label></th>
						<td><input type="text" id="ee-source-name" name="ee_source_name" class="regular-text" required></td>
					</tr>
					<tr>
						<th scope="row"><label for="ee-source-url"><?php esc_html_e( 'Website', 'eagleeye' ); ?></label></th>
						<td><input type="url" id="ee-source-url" name="ee_source_url" class="regular-text" placeholder="https://"></td>
					</tr>
					<tr>
						<th scope="row"><label for="ee-source-type"><?php esc_html_e( 'Type', 'eagleeye' ); ?></label></th>
						<td>
							<select id="ee-source-type" name="ee_source_type">
								<?php foreach ( $types as $key => $label ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ee-source-note"><?php esc_html_e( 'Why the desk trusts it', 'eagleeye' ); ?></label></th>
						<td><input type="text" id="ee-source-note" name="ee_source_note" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Official statements and press releases', 'eagleeye' ); ?>"></td>
					</tr>
				</table>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Add source', 'eagleeye' ); ?></button></p>
			</form>
		</div>
	</div>
	<?php
}
