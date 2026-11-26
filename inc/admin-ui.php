<?php
/**
 * Newsroom admin interface: branded chrome, simplified menu, quick composer.
 *
 * @package Eagle Eye
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Brand the admin.
 */
function eagleeye_admin_assets() {
	$css = EAGLEEYE_DIR . '/assets/css/admin.css';

	wp_enqueue_style(
		'eagleeye-admin',
		EAGLEEYE_URI . '/assets/css/admin.css',
		array(),
		eagleeye_asset_version( $css )
	);
}
add_action( 'admin_enqueue_scripts', 'eagleeye_admin_assets' );

/**
 * Send editors to the newsroom overview instead of the stock dashboard.
 */
function eagleeye_redirect_dashboard() {
	if ( wp_doing_ajax() || is_network_admin() || ! current_user_can( 'edit_posts' ) ) {
		return;
	}

	/*
	 * Do not intercept admin action requests. Site Kit begins its Google
	 * authorization on index.php?action=googlesitekit_proxy_setup_start, and
	 * the redirect below would exit before that handler could run.
	 */
	if ( ! empty( $_REQUEST['action'] ) ) {
		return;
	}

	/*
	 * Let Site Kit's OAuth callback land so its admin_init handler can
	 * exchange the authorization code before any redirect happens.
	 */
	foreach ( array( 'googlesitekit_code', 'googlesitekit_site_code', 'googlesitekit_error_code', 'notification', 'error_code' ) as $ee_oauth_key ) {
		if ( isset( $_REQUEST[ $ee_oauth_key ] ) ) {
			return;
		}
	}

	wp_safe_redirect( admin_url( 'admin.php?page=eagleeye' ) );
	exit;
}
add_action( 'load-index.php', 'eagleeye_redirect_dashboard' );

/**
 * Simplify and rename the menu.
 */
function eagleeye_admin_menu_labels() {
	global $menu, $submenu;

	remove_menu_page( 'index.php' );
	remove_menu_page( 'plugins.php' );
	remove_menu_page( 'tools.php' );

	foreach ( $menu as $key => $item ) {
		if ( isset( $item[2] ) && 'edit.php' === $item[2] ) {
			$menu[ $key ][0] = __( 'Stories', 'eagleeye' );
		}
	}

	if ( isset( $submenu['edit.php'][5] ) ) {
		$submenu['edit.php'][5][0] = __( 'All stories', 'eagleeye' );
	}

	if ( isset( $submenu['edit.php'][10] ) ) {
		$submenu['edit.php'][10][0] = __( 'Write a story', 'eagleeye' );
	}

	foreach ( $menu as $key => $item ) {
		if ( isset( $item[2] ) && 'themes.php' === $item[2] ) {
			$menu[ $key ][0] = __( 'Design', 'eagleeye' );
		}
	}

	// Newsroom-first order inside the Eagle Eye menu.
	if ( isset( $submenu['eagleeye'] ) ) {
		$order = array(
			'eagleeye',
			'eagleeye-write',
			'eagleeye-sections',
			'edit.php?post_type=ee_ad',
			'edit.php?post_type=ee_request',
			'eagleeye-newsletter',
			'eagleeye-rates',
			'eagleeye-email',
		);

		usort( $submenu['eagleeye'], function ( $a, $b ) use ( $order ) {
			$ia = array_search( $a[2], $order, true );
			$ib = array_search( $b[2], $order, true );
			$ia = false === $ia ? 99 : $ia;
			$ib = false === $ib ? 99 : $ib;

			return $ia <=> $ib;
		} );
	}
}
add_action( 'admin_menu', 'eagleeye_admin_menu_labels', 999 );

/**
 * Take away the controls that can break a live site.
 *
 * File editors, theme switching, plugin management and site-wide settings are
 * never needed to publish the news. They stay available to administrators on
 * request, but are removed from everyone else and the code editors are removed
 * entirely so a stray click cannot take the site down.
 */
function eagleeye_lock_down_menus() {
	// Code editors: removing them prevents a single bad paste from breaking the site.
	remove_submenu_page( 'themes.php', 'theme-editor.php' );
	remove_submenu_page( 'plugins.php', 'plugin-editor.php' );
	remove_submenu_page( 'tools.php', 'theme-editor.php' );

	if ( current_user_can( 'manage_options' ) ) {
		return;
	}

	foreach ( array( 'themes.php', 'plugins.php', 'options-general.php', 'users.php', 'tools.php' ) as $page ) {
		remove_menu_page( $page );
	}

	// Belt and braces: drop any customizer entry a plugin may have added.
	remove_submenu_page( 'themes.php', 'customize.php' );
}
add_action( 'admin_menu', 'eagleeye_lock_down_menus', 999 );

/**
 * Add the composer and sections pages.
 */
function eagleeye_admin_tools_menu() {
	add_submenu_page(
		'eagleeye',
		__( 'Write a story', 'eagleeye' ),
		__( 'Write a story', 'eagleeye' ),
		'edit_posts',
		'eagleeye-write',
		'eagleeye_composer_page',
		1
	);

	add_submenu_page(
		'eagleeye',
		__( 'Sections', 'eagleeye' ),
		__( 'Sections', 'eagleeye' ),
		'edit_posts',
		'eagleeye-sections',
		'eagleeye_sections_page',
		2
	);
}
add_action( 'admin_menu', 'eagleeye_admin_tools_menu', 11 );

/**
 * Admin bar shortcut.
 *
 * @param WP_Admin_Bar $bar Bar.
 */
function eagleeye_admin_bar( $bar ) {
	$bar->add_node( array(
		'id'    => 'eagleeye-newsroom',
		'title' => __( 'Newsroom', 'eagleeye' ),
		'href'  => admin_url( 'admin.php?page=eagleeye' ),
		'meta'  => array( 'title' => __( 'Newsroom overview', 'eagleeye' ) ),
	) );

	$bar->add_node( array(
		'id'     => 'eagleeye-new-story',
		'parent' => 'eagleeye-newsroom',
		'title'  => '＋ ' . __( 'Write a story', 'eagleeye' ),
		'href'   => admin_url( 'admin.php?page=eagleeye-write' ),
	) );
}
add_action( 'admin_bar_menu', 'eagleeye_admin_bar', 40 );

/**
 * Land on the newsroom after sign-in.
 *
 * @param string           $redirect Redirect URL.
 * @param string           $request  Requested URL.
 * @param WP_User|WP_Error $user     User.
 * @return string
 */
function eagleeye_login_redirect( $redirect, $request, $user ) {
	if ( is_wp_error( $user ) || ! user_can( $user, 'edit_posts' ) ) {
		return $redirect;
	}

	if ( empty( $request ) || false !== strpos( $redirect, 'wp-admin/index.php' ) ) {
		return admin_url( 'admin.php?page=eagleeye' );
	}

	return $redirect;
}
add_filter( 'login_redirect', 'eagleeye_login_redirect', 10, 3 );

/**
 * Footer credit.
 *
 * @param string $text Footer text.
 * @return string
 */
function eagleeye_admin_footer( $text ) {
	return __( 'Eagle Eye newsroom · Powered by Ifenuel Tech Hub', 'eagleeye' );
}
add_filter( 'admin_footer_text', 'eagleeye_admin_footer' );

/**
 * Remind editors that starter stories are ordinary posts they can replace.
 */
function eagleeye_stories_notice() {
	$screen = get_current_screen();

	if ( ! $screen || ! in_array( $screen->id, array( 'edit-post', 'post' ), true ) ) {
		return;
	}

	echo '<div class="notice notice-info"><p><strong>' . esc_html__( 'Every story is editable.', 'eagleeye' ) . '</strong> '
		. esc_html__( 'Click a headline to replace the text, swap the featured image and choose a section. The starter stories are normal posts, not locked templates.', 'eagleeye' ) . ' '
		. '<a href="' . esc_url( admin_url( 'admin.php?page=eagleeye-write' ) ) . '">' . esc_html__( 'Open the fast composer', 'eagleeye' ) . '</a> '
		. esc_html__( 'or use the full editor for video, galleries and embeds.', 'eagleeye' ) . '</p></div>';
}
add_action( 'admin_notices', 'eagleeye_stories_notice' );

/**
 * Remind editors that the legal pages are starting drafts, not legal advice.
 */
function eagleeye_legal_review_notice() {
	$screen = get_current_screen();

	if ( ! $screen || ! in_array( $screen->id, array( 'edit-page', 'page' ), true ) ) {
		return;
	}

	echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Legal pages are starting drafts.', 'eagleeye' ) . '</strong> '
		. esc_html__( 'Privacy Policy, Terms and Conditions, Editorial Standards, Corrections and Complaints, and Cookie Notice should be reviewed by a Nigerian lawyer before launch. They are not legal advice.', 'eagleeye' ) . '</p></div>';
}
add_action( 'admin_notices', 'eagleeye_legal_review_notice' );

/**
 * Quick story composer.
 */
function eagleeye_composer_page() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}

	wp_enqueue_media();

	$desk = isset( $_GET['cat'] ) ? absint( $_GET['cat'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
	$saved = isset( $_GET['saved'] ) ? sanitize_key( wp_unslash( $_GET['saved'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$story_id = isset( $_GET['story'] ) ? absint( $_GET['story'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
	$authors  = current_user_can( 'edit_others_posts' ) ? get_users( array(
		'role__in' => array( 'author', 'administrator' ),
		'orderby'  => 'display_name',
		'order'    => 'ASC',
	) ) : array();

	if ( ! $authors ) {
		$authors = array( wp_get_current_user() );
	}
	?>
	<div class="wrap ee-composer<?php echo current_user_can( 'manage_options' ) ? '' : ' ee-composer--simple'; ?>">
		<h1><?php esc_html_e( 'Write a story', 'eagleeye' ); ?></h1>
		<p class="ee-composer__intro"><?php esc_html_e( 'Choose the section, write the headline and standfirst, then add the reporting. You can save a draft or publish straight away.', 'eagleeye' ); ?></p>

		<?php if ( $saved ) : ?>
			<div class="notice notice-success is-dismissible"><p>
				<?php echo 'publish' === $saved ? esc_html__( 'Published. It is live on the site.', 'eagleeye' ) : ( 'future' === $saved ? esc_html__( 'Scheduled. It will publish automatically at the time you chose.', 'eagleeye' ) : esc_html__( 'Draft saved. Find it under All stories.', 'eagleeye' ) ); ?>
				<?php if ( $story_id ) : ?>
					<a href="<?php echo esc_url( get_edit_post_link( $story_id ) ); ?>"><?php esc_html_e( 'Edit this story', 'eagleeye' ); ?></a> ·
					<a href="<?php echo esc_url( get_permalink( $story_id ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View it', 'eagleeye' ); ?></a>
				<?php endif; ?>
			</p></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ee-composer__form">
			<input type="hidden" name="action" value="eagleeye_quick_post">
			<?php wp_nonce_field( 'eagleeye_quick_post', 'eagleeye_quick_nonce' ); ?>

			<div class="ee-composer__grid">
				<div class="ee-composer__main">
					<label class="ee-composer__label" for="ee_title"><?php esc_html_e( 'Headline', 'eagleeye' ); ?></label>
					<input type="text" id="ee_title" name="ee_title" class="ee-composer__title" required placeholder="<?php esc_attr_e( 'Write the headline the way it should appear on the front page', 'eagleeye' ); ?>">

					<label class="ee-composer__label" for="ee_dek"><?php esc_html_e( 'Standfirst', 'eagleeye' ); ?></label>
					<textarea id="ee_dek" name="ee_dek" rows="3" placeholder="<?php esc_attr_e( 'One or two sentences that tell the reader why this matters. Used under the headline and in cards.', 'eagleeye' ); ?>"></textarea>

					<label class="ee-composer__label"><?php esc_html_e( 'Featured image', 'eagleeye' ); ?></label>
					<div class="ee-media">
						<input type="hidden" name="ee_image_id" id="ee_image_id" value="">
						<div id="ee-image-preview" class="ee-media__preview" hidden></div>
						<p class="ee-media__actions">
							<button type="button" class="button" id="ee-image-pick"><?php esc_html_e( 'Choose or upload an image', 'eagleeye' ); ?></button>
							<button type="button" class="button-link" id="ee-image-remove" hidden><?php esc_html_e( 'Remove', 'eagleeye' ); ?></button>
						</p>
						<p class="description"><?php esc_html_e( 'The picture on the homepage card and at the top of the story. For pictures and video inside the story, use Add Media in the editor below.', 'eagleeye' ); ?></p>
					</div>

					<label class="ee-composer__label"><?php esc_html_e( 'Story', 'eagleeye' ); ?></label>
					<?php
					wp_editor( '', 'ee_content', array(
						'textarea_name' => 'ee_content',
						'textarea_rows' => 16,
						'media_buttons' => true,
						'teeny'         => false,
						'quicktags'     => current_user_can( 'manage_options' ),
					) );
					?>
				</div>

				<aside class="ee-composer__side">
					<div class="ee-composer__box">
						<label class="ee-composer__label" for="ee_cat"><?php esc_html_e( 'Section', 'eagleeye' ); ?></label>
						<?php
						wp_dropdown_categories( array(
							'name'              => 'ee_cat',
							'id'                => 'ee_cat',
							'selected'          => $desk,
							'hide_empty'        => false,
							'hierarchical'      => true,
							'show_option_none'  => __( 'Choose a section', 'eagleeye' ),
							'option_none_value' => 0,
							'class'             => 'ee-composer__select',
						) );
						?>

						<?php if ( current_user_can( 'edit_others_posts' ) ) : ?>
							<label class="ee-composer__label" for="ee_author"><?php esc_html_e( 'Byline / author', 'eagleeye' ); ?></label>
							<select id="ee_author" name="ee_author" class="ee-composer__select">
								<?php foreach ( $authors as $author ) : ?>
									<option value="<?php echo esc_attr( $author->ID ); ?>" <?php selected( get_current_user_id(), $author->ID ); ?>><?php echo esc_html( $author->display_name ); ?></option>
								<?php endforeach; ?>
							</select>
							<span class="description"><?php esc_html_e( 'Which newsroom account owns this story. The name readers see comes from the credit field below.', 'eagleeye' ); ?></span>
						<?php else : ?>
							<label class="ee-composer__label"><?php esc_html_e( 'Byline / author', 'eagleeye' ); ?></label>
							<p><?php echo esc_html( wp_get_current_user()->display_name ); ?></p>
						<?php endif; ?>

						<label class="ee-composer__label" for="ee_credit"><?php esc_html_e( 'Credit shown to readers', 'eagleeye' ); ?></label>
						<input type="text" id="ee_credit" name="ee_credit" class="ee-composer__select" placeholder="<?php esc_attr_e( 'e.g. Chinedu Okafor', 'eagleeye' ); ?>">
						<span class="description"><?php esc_html_e( 'The name readers see as the reporter. Leave empty to publish as Eagle Eye.', 'eagleeye' ); ?></span>

						<label class="ee-composer__check">
							<input type="checkbox" name="ee_breaking" value="1">
							<span><?php esc_html_e( 'Breaking news — add to the ticker', 'eagleeye' ); ?></span>
						</label>

						<label class="ee-composer__check">
							<input type="checkbox" name="ee_lead" value="1">
							<span><?php esc_html_e( 'Lead the homepage', 'eagleeye' ); ?></span>
						</label>

						<label class="ee-composer__label" for="ee_date"><?php esc_html_e( 'Publish time', 'eagleeye' ); ?></label>
						<input type="datetime-local" id="ee_date" name="ee_date" class="ee-composer__select">
						<span class="description"><?php esc_html_e( 'Leave empty to publish now. A future time schedules the story.', 'eagleeye' ); ?></span>

						<label class="ee-composer__label" for="ee_tags"><?php esc_html_e( 'Tags', 'eagleeye' ); ?></label>
						<input type="text" id="ee_tags" name="ee_tags" class="ee-composer__select" placeholder="<?php esc_attr_e( 'Abuja, INEC, Budget', 'eagleeye' ); ?>">
						<span class="description"><?php esc_html_e( 'Comma-separated. They power related stories and search.', 'eagleeye' ); ?></span>
					</div>

					<div class="ee-composer__box ee-composer__box--muted">
						<strong><?php esc_html_e( 'Before you publish', 'eagleeye' ); ?></strong>
						<ul>
							<li><?php esc_html_e( 'Choose a featured image above — it becomes the card picture.', 'eagleeye' ); ?></li>
							<li><?php esc_html_e( 'Check names, figures and the section.', 'eagleeye' ); ?></li>
							<li><?php esc_html_e( 'Add pictures, video or galleries with Add Media in the story editor.', 'eagleeye' ); ?></li>
							<li><?php esc_html_e( 'Breaking stories appear in the red ticker immediately.', 'eagleeye' ); ?></li>
						</ul>
					</div>

					<div class="ee-composer__actions">
						<button type="submit" name="ee_status" value="draft" class="button"><?php esc_html_e( 'Save draft', 'eagleeye' ); ?></button>
						<?php if ( current_user_can( 'publish_posts' ) ) : ?>
							<button type="submit" name="ee_status" value="publish" class="button button-primary"><?php esc_html_e( 'Publish now', 'eagleeye' ); ?></button>
						<?php endif; ?>
					</div>
				</aside>
			</div>
		</form>

		<script>
			jQuery(function ($) {
				var frame;
				$('#ee-image-pick').on('click', function (event) {
					event.preventDefault();
					if (!frame) {
						frame = wp.media({ title: 'Featured image', button: { text: 'Use this image' }, multiple: false });
						frame.on('select', function () {
							var file = frame.state().get('selection').first().toJSON();
							$('#ee_image_id').val(file.id);
							$('#ee-image-preview').html('<img src="' + file.url + '" alt="">').prop('hidden', false);
							$('#ee-image-remove').prop('hidden', false);
						});
					}
					frame.open();
				});
				$('#ee-image-remove').on('click', function (event) {
					event.preventDefault();
					$('#ee_image_id').val('');
					$('#ee-image-preview').empty().prop('hidden', true);
					$(this).prop('hidden', true);
				});
			});
		</script>
	</div>
	<?php
}

/**
 * Create a story from the composer.
 */
function eagleeye_handle_quick_post() {
	if ( ! isset( $_POST['eagleeye_quick_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['eagleeye_quick_nonce'] ) ), 'eagleeye_quick_post' ) ) {
		wp_die( esc_html__( 'The form expired. Please try again.', 'eagleeye' ) );
	}

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'eagleeye' ) );
	}

	$title   = isset( $_POST['ee_title'] ) ? sanitize_text_field( wp_unslash( $_POST['ee_title'] ) ) : '';
	$dek     = isset( $_POST['ee_dek'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ee_dek'] ) ) : '';
	$content = isset( $_POST['ee_content'] ) ? wp_kses_post( wp_unslash( $_POST['ee_content'] ) ) : '';
	$cat     = isset( $_POST['ee_cat'] ) ? absint( $_POST['ee_cat'] ) : 0;
	$status  = ( isset( $_POST['ee_status'] ) && 'publish' === $_POST['ee_status'] && current_user_can( 'publish_posts' ) ) ? 'publish' : 'draft';
	$image   = isset( $_POST['ee_image_id'] ) ? absint( $_POST['ee_image_id'] ) : 0;
	$tags    = isset( $_POST['ee_tags'] ) ? sanitize_text_field( wp_unslash( $_POST['ee_tags'] ) ) : '';
	$when    = isset( $_POST['ee_date'] ) ? sanitize_text_field( wp_unslash( $_POST['ee_date'] ) ) : '';
	$credit  = isset( $_POST['ee_credit'] ) ? sanitize_text_field( wp_unslash( $_POST['ee_credit'] ) ) : '';
	$date    = '';

	if ( $when && 'publish' === $status ) {
		try {
			$zone = wp_timezone();
			$at   = new DateTime( $when, $zone );
			$now  = new DateTime( 'now', $zone );

			if ( $at > $now ) {
				$status = 'future';
			}

			$date = $at->format( 'Y-m-d H:i:s' );
		} catch ( Exception $e ) {
			$date = '';
		}
	}

	if ( ! $title ) {
		wp_safe_redirect( add_query_arg( 'empty', '1', admin_url( 'admin.php?page=eagleeye-write' ) ) );
		exit;
	}

	$author_id = get_current_user_id();

	if ( current_user_can( 'edit_others_posts' ) && isset( $_POST['ee_author'] ) ) {
		$candidate = get_userdata( absint( $_POST['ee_author'] ) );

		if ( $candidate && user_can( $candidate, 'edit_posts' ) ) {
			$author_id = (int) $candidate->ID;
		}
	}

	$post_id = wp_insert_post( array(
		'post_title'    => $title,
		'post_content'  => $content,
		'post_excerpt'  => $dek,
		'post_status'   => $status,
		'post_type'     => 'post',
		'post_category' => $cat ? array( $cat ) : array(),
		'post_author'   => $author_id,
		'post_date'     => $date ? $date : '',
	), true );

	if ( is_wp_error( $post_id ) ) {
		wp_die( esc_html( $post_id->get_error_message() ) );
	}

	if ( $credit ) {
		update_post_meta( $post_id, '_eagleeye_credit', $credit );
	}

	if ( ! empty( $_POST['ee_breaking'] ) ) {
		update_post_meta( $post_id, '_eagleeye_breaking', '1' );
	}

	if ( ! empty( $_POST['ee_lead'] ) ) {
		update_post_meta( $post_id, '_eagleeye_featured', '1' );
	}

	if ( $image && wp_attachment_is_image( $image ) ) {
		set_post_thumbnail( $post_id, $image );
	}

	if ( $tags ) {
		wp_set_post_tags( $post_id, $tags, false );
	}

	wp_safe_redirect( add_query_arg( array( 'saved' => $status, 'story' => $post_id ), admin_url( 'admin.php?page=eagleeye-write' ) ) );
	exit;
}
add_action( 'admin_post_eagleeye_quick_post', 'eagleeye_handle_quick_post' );

/**
 * Sections overview: every desk with counts and actions.
 */
function eagleeye_sections_page() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}

	$terms = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false ) );
	?>
	<div class="wrap ee-sections">
		<h1><?php esc_html_e( 'Sections', 'eagleeye' ); ?></h1>
		<p><?php esc_html_e( 'Every section on the site. Write into a section, or adjust its name and description.', 'eagleeye' ); ?></p>

		<div class="ee-sections__grid">
			<?php foreach ( $terms as $term ) : ?>
				<div class="ee-sections__card">
					<span class="ee-sections__count"><?php echo esc_html( number_format_i18n( $term->count ) ); ?></span>
					<h2><?php echo esc_html( $term->name ); ?></h2>
					<p><?php echo esc_html( wp_trim_words( $term->description ? $term->description : __( 'No description yet.', 'eagleeye' ), 14 ) ); ?></p>
					<div class="ee-sections__actions">
						<a class="button button-primary" href="<?php echo esc_url( add_query_arg( 'cat', $term->term_id, admin_url( 'admin.php?page=eagleeye-write' ) ) ); ?>"><?php esc_html_e( 'Write', 'eagleeye' ); ?></a>
						<a class="button" href="<?php echo esc_url( get_category_link( $term ) ); ?>"><?php esc_html_e( 'View', 'eagleeye' ); ?></a>
						<a class="button" href="<?php echo esc_url( add_query_arg( array( 'taxonomy' => 'category', 'tag_ID' => $term->term_id ), admin_url( 'term.php' ) ) ); ?>"><?php esc_html_e( 'Edit', 'eagleeye' ); ?></a>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
}

