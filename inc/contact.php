<?php
/**
 * Contact and advertising enquiries.
 *
 * @package Eagle Eye
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the private enquiry inbox.
 */
function eagleeye_register_messages() {
	register_post_type( 'eagleeye_message', array(
		'labels' => array(
			'name'          => __( 'Enquiries', 'eagleeye' ),
			'singular_name' => __( 'Enquiry', 'eagleeye' ),
			'menu_name'     => __( 'Enquiries', 'eagleeye' ),
			'all_items'     => __( 'Enquiries', 'eagleeye' ),
			'edit_item'     => __( 'View enquiry', 'eagleeye' ),
		),
		'public'              => false,
		'publicly_queryable'  => false,
		'exclude_from_search' => true,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'menu_icon'           => 'dashicons-email-alt',
		'menu_position'       => 26,
		'supports'            => array( 'title', 'editor' ),
		'capabilities'        => array( 'create_posts' => 'do_not_allow' ),
		'map_meta_cap'        => true,
	) );
}
add_action( 'init', 'eagleeye_register_messages' );

/**
 * Handle contact / advertising submissions.
 */
function eagleeye_handle_contact() {
	$redirect = wp_get_referer() ? wp_get_referer() : home_url( '/' );

	if ( ! isset( $_POST['eagleeye_contact_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['eagleeye_contact_nonce'] ) ), 'eagleeye_contact' ) ) {
		wp_safe_redirect( add_query_arg( 'ee_contact', 'invalid', $redirect ) . '#contact-form' );
		exit;
	}

	if ( ! empty( $_POST['ee_company_site'] ) ) {
		wp_safe_redirect( add_query_arg( 'ee_contact', 'sent', $redirect ) . '#contact-form' );
		exit;
	}

	$name    = isset( $_POST['ee_name'] ) ? sanitize_text_field( wp_unslash( $_POST['ee_name'] ) ) : '';
	$email   = isset( $_POST['ee_email'] ) ? sanitize_email( wp_unslash( $_POST['ee_email'] ) ) : '';
	$subject = isset( $_POST['ee_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['ee_subject'] ) ) : '';
	$body    = isset( $_POST['ee_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ee_message'] ) ) : '';
	$type    = isset( $_POST['ee_type'] ) ? sanitize_key( wp_unslash( $_POST['ee_type'] ) ) : '';

	if ( ! $type && $subject ) {
		$type = sanitize_key( $subject );
	}

	$type = $type ? $type : 'general';

	if ( '' === $name || ! is_email( $email ) || mb_strlen( $body ) < 10 ) {
		wp_safe_redirect( add_query_arg( 'ee_contact', 'invalid', $redirect ) . '#contact-form' );
		exit;
	}

	$post_id = wp_insert_post( array(
		'post_type'    => 'eagleeye_message',
		'post_status'  => 'private',
		'post_title'   => sprintf( '%s — %s', $name, $subject ? $subject : __( 'General enquiry', 'eagleeye' ) ),
		'post_content' => $body,
	) );

	if ( is_wp_error( $post_id ) || ! $post_id ) {
		wp_safe_redirect( add_query_arg( 'ee_contact', 'invalid', $redirect ) . '#contact-form' );
		exit;
	}

	update_post_meta( $post_id, '_ee_email', $email );
	update_post_meta( $post_id, '_ee_name', $name );
	update_post_meta( $post_id, '_ee_type', $type );

	$to = get_theme_mod( 'eagleeye_email', get_option( 'admin_email' ) );
	$email_body = '<p><strong>' . esc_html__( 'From:', 'eagleeye' ) . '</strong> ' . esc_html( $name ) . ' &lt;' . esc_html( $email ) . '&gt;</p>'
		. '<p><strong>' . esc_html__( 'Type:', 'eagleeye' ) . '</strong> ' . esc_html( $type ) . '</p>'
		. '<div>' . nl2br( esc_html( $body ) ) . '</div>';

	eagleeye_send_email(
		$to,
		sprintf( '[Eagle Eye] %s', $subject ? $subject : __( 'New enquiry', 'eagleeye' ) ),
		eagleeye_email_template( __( 'New newsroom enquiry', 'eagleeye' ), $email_body ),
		$email
	);

	wp_safe_redirect( add_query_arg( 'ee_contact', 'sent', $redirect ) . '#contact-form' );
	exit;
}
add_action( 'admin_post_nopriv_eagleeye_contact', 'eagleeye_handle_contact' );
add_action( 'admin_post_eagleeye_contact', 'eagleeye_handle_contact' );

/**
 * Enquiry feedback state.
 *
 * @return string
 */
function eagleeye_contact_state() {
	if ( ! isset( $_GET['ee_contact'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return '';
	}

	return sanitize_key( wp_unslash( $_GET['ee_contact'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
}

/**
 * Contact form embeddable from a page template or shortcode.
 *
 * @param array $args Form options.
 */
function eagleeye_contact_form( $args = array() ) {
	$args = wp_parse_args( $args, array(
		'type'    => 'general',
		'subject' => '',
		'button'  => __( 'Send message', 'eagleeye' ),
		'subjects' => array(
			__( 'News tip', 'eagleeye' ),
			__( 'Editorial enquiry', 'eagleeye' ),
			__( 'Advertising', 'eagleeye' ),
			__( 'Partnership', 'eagleeye' ),
			__( 'Other', 'eagleeye' ),
		),
	) );

	$state = eagleeye_contact_state();
	?>
	<form class="contact-form" id="contact-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
		<input type="hidden" name="action" value="eagleeye_contact">
		<input type="hidden" name="ee_type" value="<?php echo esc_attr( $args['type'] ); ?>">
		<?php wp_nonce_field( 'eagleeye_contact', 'eagleeye_contact_nonce' ); ?>

		<?php if ( 'sent' === $state ) : ?>
			<p class="form-notice form-notice--ok" role="status"><?php esc_html_e( 'Thank you — your message has reached the newsroom. We reply to editorial enquiries within two working days.', 'eagleeye' ); ?></p>
		<?php elseif ( 'invalid' === $state ) : ?>
			<p class="form-notice form-notice--error" role="alert"><?php esc_html_e( 'Please check your name, email address and message, then try again.', 'eagleeye' ); ?></p>
		<?php endif; ?>

		<div class="field-row">
			<p class="field">
				<label for="ee-name"><?php esc_html_e( 'Full name', 'eagleeye' ); ?></label>
				<input type="text" id="ee-name" name="ee_name" required autocomplete="name">
			</p>
			<p class="field">
				<label for="ee-email-contact"><?php esc_html_e( 'Email address', 'eagleeye' ); ?></label>
				<input type="email" id="ee-email-contact" name="ee_email" required autocomplete="email">
			</p>
		</div>

		<p class="field">
			<label for="ee-subject"><?php esc_html_e( 'What is this about?', 'eagleeye' ); ?></label>
			<select id="ee-subject" name="ee_subject">
				<?php foreach ( $args['subjects'] as $option ) : ?>
					<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $args['subject'], $option ); ?>><?php echo esc_html( $option ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>

		<p class="field">
			<label for="ee-message"><?php esc_html_e( 'Message', 'eagleeye' ); ?></label>
			<textarea id="ee-message" name="ee_message" rows="7" required minlength="10"></textarea>
		</p>

		<label class="form-honeypot" aria-hidden="true">Company website<input type="text" name="ee_company_site" tabindex="-1" autocomplete="off"></label>

		<p class="field field--submit">
			<button type="submit" class="btn btn--solid"><?php echo esc_html( $args['button'] ); ?></button>
			<span class="field__note"><?php esc_html_e( 'We never share your details.', 'eagleeye' ); ?></span>
		</p>
	</form>
	<?php
}

/**
 * [eagleeye_contact_form] shortcode.
 *
 * @return string
 */
function eagleeye_contact_shortcode() {
	ob_start();
	eagleeye_contact_form();

	return (string) ob_get_clean();
}
add_shortcode( 'eagleeye_contact_form', 'eagleeye_contact_shortcode' );

/**
 * Show sender details in the enquiry list.
 *
 * @param array $columns Columns.
 * @return array
 */
function eagleeye_message_columns( $columns ) {
	$columns['ee_sender'] = __( 'Sender', 'eagleeye' );
	$columns['ee_type']   = __( 'Type', 'eagleeye' );

	return $columns;
}
add_filter( 'manage_eagleeye_message_posts_columns', 'eagleeye_message_columns' );

/**
 * Render enquiry columns.
 *
 * @param string $column Column key.
 * @param int    $post_id Post ID.
 */
function eagleeye_message_column_content( $column, $post_id ) {
	if ( 'ee_sender' === $column ) {
		$name  = get_post_meta( $post_id, '_ee_name', true );
		$email = get_post_meta( $post_id, '_ee_email', true );
		printf( '%s<br><a href="mailto:%s">%s</a>', esc_html( $name ), esc_attr( $email ), esc_html( $email ) );
	}

	if ( 'ee_type' === $column ) {
		echo esc_html( ucfirst( (string) get_post_meta( $post_id, '_ee_type', true ) ) );
	}
}
add_action( 'manage_eagleeye_message_posts_custom_column', 'eagleeye_message_column_content', 10, 2 );
