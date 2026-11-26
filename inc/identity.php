<?php
/**
 * Newsroom identity: uploaded profile photos and a quieter admin bar.
 *
 * WordPress falls back to a Gravatar silhouette when it has no photo. Newsroom
 * staff upload their own picture here instead, and the same image is then used
 * in the top bar, the sidebar, the comments list and public bylines.
 *
 * @package Eagle Eye
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * User meta key holding the attachment ID of a profile photo.
 */
define( 'EAGLEEYE_PHOTO_META', 'eagleeye_photo_id' );

/**
 * Keep personal newsroom identities out of the public REST API.
 *
 * Signed-in editors still receive the real account data they need to assign
 * stories. Anonymous readers see the publication identity instead.
 *
 * @param WP_REST_Response $response Response object.
 * @param WP_User          $user     User object.
 * @return WP_REST_Response
 */
function eagleeye_mask_public_author_identity( $response, $user ) {
	if ( is_user_logged_in() || ! user_can( $user, 'edit_posts' ) ) {
		return $response;
	}

	$data = $response->get_data();

	if ( is_array( $data ) ) {
		$data['name']        = __( 'Eagle Eye', 'eagleeye' );
		$data['description'] = '';
		$data['url']         = home_url( '/' );
		$data['link']        = home_url( '/latest/' );
		$data['slug']        = 'eagle-eye';
		$response->set_data( $data );
	}

	return $response;
}
add_filter( 'rest_prepare_user', 'eagleeye_mask_public_author_identity', 10, 2 );

/**
 * Attachment ID for a user's photo.
 *
 * @param int $user_id Optional user ID.
 * @return int
 */
function eagleeye_user_photo_id( $user_id = 0 ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();

	if ( ! $user_id ) {
		return 0;
	}

	$id = (int) get_user_meta( $user_id, EAGLEEYE_PHOTO_META, true );

	if ( ! $id || ! wp_attachment_is_image( $id ) ) {
		return 0;
	}

	return $id;
}

/**
 * URL for a user's photo.
 *
 * @param int    $user_id Optional user ID.
 * @param string $size    Image size.
 * @return string
 */
function eagleeye_user_photo_url( $user_id = 0, $size = 'thumbnail' ) {
	$id = eagleeye_user_photo_id( $user_id );

	if ( ! $id ) {
		return '';
	}

	$src = wp_get_attachment_image_src( $id, $size );

	return $src ? (string) $src[0] : '';
}

/**
 * A branded initials avatar, used until someone uploads their own photo.
 *
 * @param int $user_id User ID.
 * @param int $size    Square size in pixels.
 * @return string
 */
function eagleeye_initials_avatar( $user_id, $size = 96 ) {
	$user = get_userdata( $user_id );

	if ( ! $user ) {
		return '';
	}

	$name  = $user->display_name ? $user->display_name : $user->user_login;
	$parts = preg_split( '/\s+/', trim( $name ) );
	$ini   = '';

	foreach ( (array) $parts as $part ) {
		if ( '' !== $part ) {
			$ini .= function_exists( 'mb_substr' ) ? mb_substr( $part, 0, 1 ) : substr( $part, 0, 1 );
		}

		if ( ( function_exists( 'mb_strlen' ) ? mb_strlen( $ini ) : strlen( $ini ) ) >= 2 ) {
			break;
		}
	}

	$ini = $ini ? $ini : ( function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 1 ) : substr( $name, 0, 1 ) );
	$ini = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $ini ) : strtoupper( $ini );

	$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 96 96">'
		. '<rect width="96" height="96" fill="#08080a"/>'
		. '<text x="48" y="61" font-family="Georgia,serif" font-size="36" font-weight="bold" fill="#f6c21c" text-anchor="middle">'
		. esc_html( $ini )
		. '</text></svg>';

	return 'data:image/svg+xml;base64,' . base64_encode( $svg );
}

/**
 * Work out which user an avatar request refers to.
 *
 * @param mixed $id_or_email User ID, WP_User, WP_Post, WP_Comment or email.
 * @return int
 */
function eagleeye_avatar_user_id( $id_or_email ) {
	if ( is_numeric( $id_or_email ) ) {
		return (int) $id_or_email;
	}

	if ( $id_or_email instanceof WP_User ) {
		return (int) $id_or_email->ID;
	}

	if ( $id_or_email instanceof WP_Post ) {
		return (int) $id_or_email->post_author;
	}

	if ( $id_or_email instanceof WP_Comment ) {
		if ( ! empty( $id_or_email->user_id ) ) {
			return (int) $id_or_email->user_id;
		}

		return (int) get_user_by( 'email', $id_or_email->comment_author_email )->ID;
	}

	if ( is_string( $id_or_email ) && is_email( $id_or_email ) ) {
		$user = get_user_by( 'email', $id_or_email );

		return $user ? (int) $user->ID : 0;
	}

	return 0;
}

/**
 * Use the uploaded photo instead of Gravatar.
 *
 * @param string $url         Avatar URL.
 * @param mixed  $id_or_email User reference.
 * @param array  $args        Avatar arguments.
 * @return string
 */
function eagleeye_avatar_url( $url, $id_or_email, $args ) {
	$user_id = eagleeye_avatar_user_id( $id_or_email );

	if ( ! $user_id ) {
		return $url;
	}

	$size  = isset( $args['size'] ) && is_numeric( $args['size'] ) ? (int) $args['size'] : 96;
	$photo = eagleeye_user_photo_url( $user_id, array( $size, $size ) );

	if ( $photo ) {
		return $photo;
	}

	$initials = eagleeye_initials_avatar( $user_id, $size );

	return $initials ? $initials : $url;
}
add_filter( 'get_avatar_url', 'eagleeye_avatar_url', 10, 3 );

/**
 * Render the uploaded photo or initials avatar ourselves.
 *
 * esc_url() refuses data: URIs, so building the image tag here keeps the
 * generated initials avatar visible in core screens such as the comments list.
 *
 * @param string|null $avatar      Pre-rendered avatar markup.
 * @param mixed       $id_or_email User reference.
 * @param array       $args        Avatar arguments.
 * @return string|null
 */
function eagleeye_pre_get_avatar( $avatar, $id_or_email, $args ) {
	$user_id = eagleeye_avatar_user_id( $id_or_email );

	if ( ! $user_id ) {
		return $avatar;
	}

	$size = isset( $args['size'] ) && is_numeric( $args['size'] ) ? (int) $args['size'] : 96;
	$url  = eagleeye_user_photo_url( $user_id, array( $size, $size ) );

	if ( ! $url ) {
		$url = eagleeye_initials_avatar( $user_id, $size );
	}

	if ( ! $url ) {
		return $avatar;
	}

	$class = 'avatar avatar-' . $size . ' photo';

	if ( ! empty( $args['class'] ) ) {
		$class .= ' ' . $args['class'];
	}

	return sprintf(
		'<img alt="%1$s" src="%2$s" class="%3$s" width="%4$d" height="%4$d" loading="lazy" decoding="async" />',
		esc_attr( isset( $args['alt'] ) ? $args['alt'] : '' ),
		esc_attr( $url ),
		esc_attr( $class ),
		$size
	);
}
add_filter( 'pre_get_avatar', 'eagleeye_pre_get_avatar', 10, 3 );

/**
 * Profile photo field on the user's own profile and on user-edit screens.
 *
 * @param WP_User $user User being edited.
 */
function eagleeye_photo_field( $user ) {
	if ( ! current_user_can( 'upload_files' ) && get_current_user_id() !== (int) $user->ID ) {
		return;
	}

	$photo_id = eagleeye_user_photo_id( $user->ID );
	$preview  = $photo_id ? wp_get_attachment_image_url( $photo_id, array( 160, 160 ) ) : '';
	?>
	<h2><?php esc_html_e( 'Profile photo', 'eagleeye' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th><label for="ee_photo_pick"><?php esc_html_e( 'Your picture', 'eagleeye' ); ?></label></th>
			<td>
				<?php wp_nonce_field( 'eagleeye_save_photo_' . $user->ID, 'eagleeye_photo_nonce' ); ?>
				<input type="hidden" name="eagleeye_photo_id" id="eagleeye_photo_id" value="<?php echo esc_attr( $photo_id ); ?>">

				<div id="ee-photo-preview" style="margin-bottom:10px">
					<?php if ( $preview ) : ?>
						<img src="<?php echo esc_url( $preview ); ?>" alt="" style="width:96px;height:96px;object-fit:cover;border-radius:50%;border:2px solid #f6c21c">
					<?php endif; ?>
				</div>

				<p>
					<button type="button" class="button" id="ee-photo-pick"><?php esc_html_e( 'Upload or choose a photo', 'eagleeye' ); ?></button>
					<button type="button" class="button-link" id="ee-photo-remove" <?php echo $photo_id ? '' : 'hidden'; ?>><?php esc_html_e( 'Remove', 'eagleeye' ); ?></button>
				</p>
				<p class="description">
					<?php esc_html_e( 'Used in the top bar, the sidebar and on the stories you write. A square picture of at least 200×200 looks best.', 'eagleeye' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<script>
		jQuery(function ($) {
			var frame;

			$('#ee-photo-pick').on('click', function (event) {
				event.preventDefault();

				if (!frame) {
					frame = wp.media({
						title: '<?php echo esc_js( __( 'Choose your profile photo', 'eagleeye' ) ); ?>',
						button: { text: '<?php echo esc_js( __( 'Use this photo', 'eagleeye' ) ); ?>' },
						library: { type: 'image' },
						multiple: false
					});

					frame.on('select', function () {
						var file = frame.state().get('selection').first().toJSON();
						$('#eagleeye_photo_id').val(file.id);
						$('#ee-photo-preview').html('<img src="' + file.url + '" alt="" style="width:96px;height:96px;object-fit:cover;border-radius:50%;border:2px solid #f6c21c">');
						$('#ee-photo-remove').prop('hidden', false);
					});
				}

				frame.open();
			});

			$('#ee-photo-remove').on('click', function (event) {
				event.preventDefault();
				$('#eagleeye_photo_id').val('');
				$('#ee-photo-preview').empty();
				$(this).prop('hidden', true);
			});
		});
	</script>
	<?php
}
// The photo sits at the very top of the profile screen: on a phone the stock
// "Personal Options" block pushed it far below the fold, which read as the
// wrong page. Administrators editing another account get it in the same place.
add_action( 'profile_personal_options', 'eagleeye_photo_field' );
add_action( 'edit_user_profile', 'eagleeye_photo_field' );

/**
 * Mark the profile screen for people who are not site administrators.
 *
 * @param string $classes Body classes.
 * @return string
 */
function eagleeye_profile_body_class( $classes ) {
	global $pagenow;

	if ( in_array( $pagenow, array( 'profile.php', 'user-edit.php' ), true ) && ! current_user_can( 'manage_options' ) ) {
		$classes .= ' ee-newsroom-simple';
	}

	return $classes;
}
add_filter( 'admin_body_class', 'eagleeye_profile_body_class' );

/**
 * Media picker on the profile screen.
 *
 * @param string $hook Current admin page.
 */
function eagleeye_photo_assets( $hook ) {
	if ( ! in_array( $hook, array( 'profile.php', 'user-edit.php' ), true ) ) {
		return;
	}

	wp_enqueue_media();
}
add_action( 'admin_enqueue_scripts', 'eagleeye_photo_assets' );

/**
 * Save the profile photo.
 *
 * @param int $user_id User being saved.
 */
function eagleeye_save_photo( $user_id ) {
	if ( ! isset( $_POST['eagleeye_photo_nonce'] ) ) {
		return;
	}

	$nonce = sanitize_text_field( wp_unslash( $_POST['eagleeye_photo_nonce'] ) );

	if ( ! wp_verify_nonce( $nonce, 'eagleeye_save_photo_' . $user_id ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_user', $user_id ) ) {
		return;
	}

	$photo_id = isset( $_POST['eagleeye_photo_id'] ) ? absint( $_POST['eagleeye_photo_id'] ) : 0;

	if ( $photo_id && wp_attachment_is_image( $photo_id ) ) {
		update_user_meta( $user_id, EAGLEEYE_PHOTO_META, $photo_id );
	} else {
		delete_user_meta( $user_id, EAGLEEYE_PHOTO_META );
	}
}
add_action( 'personal_options_update', 'eagleeye_save_photo' );
add_action( 'edit_user_profile_update', 'eagleeye_save_photo' );

/**
 * Replace the stock admin bar with a small newsroom bar.
 *
 * Removes the WordPress.org logo, update badge and search box, and shows the
 * person's own photo and name instead of "Howdy, username".
 *
 * @param WP_Admin_Bar $bar Admin bar.
 */
function eagleeye_admin_bar_identity( $bar ) {
	$bar->remove_node( 'wp-logo' );
	$bar->remove_node( 'updates' );
	$bar->remove_node( 'search' );

	$user = wp_get_current_user();

	if ( ! $user->exists() ) {
		return;
	}

	$photo = eagleeye_user_photo_url( $user->ID, array( 26, 26 ) );
	$photo = $photo ? $photo : get_avatar_url( $user->ID, array( 'size' => 26 ) );
	$name  = $user->display_name ? $user->display_name : $user->user_login;

	$bar->add_node( array(
		'id'    => 'my-account',
		'title' => '<img src="' . esc_attr( $photo ) . '" alt="" class="ee-bar-photo">'
			. '<span class="ee-bar-name">' . esc_html( $name ) . '</span>',
		'href'  => admin_url( 'profile.php' ),
		'meta'  => array( 'title' => __( 'Your profile and photo', 'eagleeye' ) ),
	) );
}
add_action( 'admin_bar_menu', 'eagleeye_admin_bar_identity', 9999 );

/**
 * Styles and the sidebar identity chip for the admin.
 */
function eagleeye_identity_styles() {
	?>
	<style>
		#wpadminbar #wp-admin-bar-my-account > .ab-item {
			display: flex;
			align-items: center;
			gap: 7px;
			padding-right: 10px;
		}

		#wpadminbar #wp-admin-bar-my-account .ee-bar-photo {
			width: 22px;
			height: 22px;
			border-radius: 50%;
			object-fit: cover;
			border: 1px solid var(--ee-gold, #f6c21c);
			display: block;
		}

		#wpadminbar #wp-admin-bar-my-account .ee-bar-name {
			font-size: 12.5px;
			font-weight: 600;
			letter-spacing: 0.02em;
			white-space: nowrap;
		}

		@media screen and (max-width: 782px) {
			#wpadminbar #wp-admin-bar-my-account .ee-bar-name { display: none; }
		}

		#ee-sidebar-user {
			display: flex;
			align-items: center;
			gap: 10px;
			padding: 12px 14px;
			margin: 4px 8px 12px;
			background: #121215;
			border-left: 3px solid var(--ee-gold, #f6c21c);
			text-decoration: none;
			color: #f2eee5;
		}

		#ee-sidebar-user:hover,
		#ee-sidebar-user:focus {
			background: #1b1b20;
			color: var(--ee-gold, #f6c21c);
		}

		#ee-sidebar-user img {
			width: 34px;
			height: 34px;
			border-radius: 50%;
			object-fit: cover;
			border: 1px solid var(--ee-gold, #f6c21c);
			flex: 0 0 34px;
		}

		#ee-sidebar-user span {
			display: block;
			font-size: 12.5px;
			font-weight: 600;
			line-height: 1.3;
		}

		#ee-sidebar-user em {
			display: block;
			font-style: normal;
			font-size: 10.5px;
			letter-spacing: 0.08em;
			text-transform: uppercase;
			color: #a8a294;
		}
	</style>
	<?php
}
add_action( 'admin_head', 'eagleeye_identity_styles' );

/**
 * Add the photo chip to the top of the sidebar.
 */
function eagleeye_sidebar_identity() {
	$user = wp_get_current_user();

	if ( ! $user->exists() || ! current_user_can( 'edit_posts' ) ) {
		return;
	}

	$photo = eagleeye_user_photo_url( $user->ID, array( 68, 68 ) );
	$photo = $photo ? $photo : get_avatar_url( $user->ID, array( 'size' => 68 ) );
	$name  = $user->display_name ? $user->display_name : $user->user_login;
	$role  = __( 'Newsroom', 'eagleeye' );

	if ( user_can( $user, 'manage_options' ) ) {
		$role = __( 'Administrator', 'eagleeye' );
	}
	?>
	<script>
		(function () {
			var wrap = document.getElementById('adminmenuwrap');

			if (!wrap || document.getElementById('ee-sidebar-user')) {
				return;
			}

			var link = document.createElement('a');
			link.id = 'ee-sidebar-user';
			link.href = '<?php echo esc_url( admin_url( 'profile.php' ) ); ?>';
			link.title = '<?php echo esc_js( __( 'Your profile and photo', 'eagleeye' ) ); ?>';
			link.innerHTML = '<img src="<?php echo esc_attr( $photo ); ?>" alt=""><span><?php echo esc_js( $name ); ?><em><?php echo esc_js( $role ); ?></em></span>';

			wrap.insertBefore(link, wrap.querySelector('#adminmenu'));
		})();
	</script>
	<?php
}
add_action( 'admin_footer', 'eagleeye_sidebar_identity' );
