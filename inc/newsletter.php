<?php
/**
 * Newsletter: double opt-in, confirmation, unsubscribe and admin export.
 *
 * @package Eagle Eye
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Subscribers table name.
 *
 * @return string
 */
function eagleeye_subscribers_table() {
	global $wpdb;

	return $wpdb->prefix . 'eagleeye_subscribers';
}

/**
 * Create or upgrade the subscribers table.
 */
function eagleeye_create_subscribers_table() {
	global $wpdb;

	$table   = eagleeye_subscribers_table();
	$charset = $wpdb->get_charset_collate();

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	dbDelta( "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		email varchar(190) NOT NULL,
		source varchar(60) NOT NULL DEFAULT 'site',
		status varchar(20) NOT NULL DEFAULT 'pending',
		token varchar(64) NOT NULL DEFAULT '',
		created_at datetime NOT NULL,
		confirmed_at datetime DEFAULT NULL,
		unsubscribed_at datetime DEFAULT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY email (email),
		KEY token (token)
	) {$charset};" );

	update_option( 'eagleeye_subscribers_db', '2' );
}
add_action( 'after_switch_theme', 'eagleeye_create_subscribers_table' );

/**
 * Run the schema upgrade once.
 */
function eagleeye_maybe_upgrade_subscribers() {
	if ( '2' !== get_option( 'eagleeye_subscribers_db' ) ) {
		eagleeye_create_subscribers_table();
	}
}
add_action( 'init', 'eagleeye_maybe_upgrade_subscribers', 1 );

/**
 * Confirmation URL for a token.
 *
 * @param string $token Token.
 * @return string
 */
function eagleeye_confirm_url( $token ) {
	return add_query_arg( 'ee_confirm', rawurlencode( $token ), home_url( '/' ) ) . '#newsletter';
}

/**
 * Unsubscribe URL for a token.
 *
 * @param string $token Token.
 * @return string
 */
function eagleeye_unsubscribe_url( $token ) {
	return add_query_arg( 'ee_unsubscribe', rawurlencode( $token ), home_url( '/' ) ) . '#newsletter';
}

/**
 * Morning Brief settings.
 *
 * @return array
 */
function eagleeye_brief_settings() {
	return array(
		'enabled' => (bool) get_option( 'eagleeye_brief_enabled', 1 ),
		'hour'    => (int) get_option( 'eagleeye_brief_hour', 7 ),
		'intro'   => (string) get_option( 'eagleeye_brief_intro', __( 'Good morning. Here is what our newsroom is leading with today.', 'eagleeye' ) ),
	);
}

/**
 * Confirmed subscribers who should receive an edition.
 *
 * @return array
 */
function eagleeye_brief_recipients() {
	global $wpdb;
	$table = eagleeye_subscribers_table();

	return (array) $wpdb->get_results( "SELECT email, token FROM {$table} WHERE status = 'subscribed' ORDER BY confirmed_at ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

/**
 * Stories for the next edition: the last 24 hours, or the latest six if the
 * newsroom has been quiet.
 *
 * @return WP_Post[]
 */
function eagleeye_brief_stories() {
	$stories = get_posts( array(
		'numberposts' => 6,
		'post_status' => 'publish',
		'date_query'  => array(
			array( 'after' => '24 hours ago', 'inclusive' => true ),
		),
	) );

	if ( ! $stories ) {
		$stories = get_posts( array( 'numberposts' => 6, 'post_status' => 'publish' ) );
	}

	return $stories;
}

/**
 * Build one edition of the Morning Brief.
 *
 * @param WP_Post[] $stories Stories.
 * @param string    $token   Subscriber token for the unsubscribe link.
 * @return string
 */
function eagleeye_brief_html( $stories, $token = '' ) {
	$settings = eagleeye_brief_settings();
	$rows     = '';

	foreach ( $stories as $story ) {
		$cats = get_the_category( $story->ID );
		$kicker = $cats ? $cats[0]->name : __( 'News', 'eagleeye' );
		$excerpt = $story->post_excerpt ? $story->post_excerpt : wp_strip_all_tags( $story->post_content );

		$rows .= '<tr><td style="padding:20px 0;border-bottom:1px solid #e3ddd0">'
			. '<div style="font-family:Arial,sans-serif;font-size:10px;font-weight:bold;letter-spacing:2px;color:#e01b2f;text-transform:uppercase;margin-bottom:6px">' . esc_html( $kicker ) . '</div>'
			. '<a href="' . esc_url( get_permalink( $story ) ) . '" style="font-family:Georgia,serif;font-size:21px;line-height:1.25;color:#08080a;text-decoration:none;display:block">' . esc_html( get_the_title( $story ) ) . '</a>'
			. '<p style="font-family:Georgia,serif;font-size:14px;line-height:1.55;color:#6b665c;margin:8px 0 0">' . esc_html( wp_trim_words( $excerpt, 28 ) ) . '</p>'
			. '</td></tr>';
	}

	$body = '<p style="font-size:16px;line-height:1.7;margin:0 0 8px">' . esc_html( $settings['intro'] ) . '</p>'
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:10px">' . $rows . '</table>'
		. '<p style="margin:26px 0 0"><a href="' . esc_url( home_url( '/latest/' ) ) . '" style="display:inline-block;background:#111114;color:#f6c21c;padding:13px 20px;text-decoration:none;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:bold;letter-spacing:1.2px">READ THE LATEST STORIES</a></p>';

	return eagleeye_email_template(
		date_i18n( 'l, j F Y' ),
		$body,
		'',
		array(
			'eyebrow'     => __( 'The Morning Brief', 'eagleeye' ),
			'preheader'   => $settings['intro'],
			'footer_note' => __( 'You are receiving this because you confirmed your subscription to The Eagle Eye Morning Brief.', 'eagleeye' ),
			'unsubscribe' => $token ? eagleeye_unsubscribe_url( $token ) : '',
		)
	);
}

/**
 * Send the Morning Brief to every confirmed subscriber.
 *
 * @param bool $manual Send even when the daily schedule is paused.
 * @return int Number of emails accepted by the mailer.
 */
function eagleeye_send_brief( $manual = false ) {
	$settings = eagleeye_brief_settings();

	if ( ! $manual && ! $settings['enabled'] ) {
		return 0;
	}

	// One sender at a time: a double click, a retried request or overlapping cron
	// cannot mail the list twice.
	if ( get_transient( 'eagleeye_brief_lock' ) ) {
		return 0;
	}

	set_transient( 'eagleeye_brief_lock', time(), 15 * MINUTE_IN_SECONDS );

	$stories = eagleeye_brief_stories();
	$people  = eagleeye_brief_recipients();

	if ( ! $stories || ! $people ) {
		delete_transient( 'eagleeye_brief_lock' );
		return 0;
	}

	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}

	$subject = sprintf( __( 'The Eagle Eye Morning Brief · %s', 'eagleeye' ), date_i18n( 'j F Y' ) );
	$sent    = 0;

	foreach ( $people as $person ) {
		if ( eagleeye_send_email( $person->email, $subject, eagleeye_brief_html( $stories, (string) $person->token ), '', eagleeye_unsubscribe_url( (string) $person->token ) ) ) {
			$sent++;
		}

		usleep( 120000 );
	}

	update_option( 'eagleeye_brief_last_sent', current_time( 'mysql' ) );
	update_option( 'eagleeye_brief_last_count', $sent );
	delete_transient( 'eagleeye_brief_lock' );

	return $sent;
}

/**
 * Schedule or clear the daily edition.
 */
function eagleeye_schedule_brief() {
	$settings = eagleeye_brief_settings();
	$next     = wp_next_scheduled( 'eagleeye_send_brief' );

	if ( ! $settings['enabled'] ) {
		if ( $next ) {
			wp_clear_scheduled_hook( 'eagleeye_send_brief' );
		}

		return;
	}

	if ( $next ) {
		return;
	}

	$zone  = wp_timezone();
	$when  = new DateTime( 'today ' . absint( $settings['hour'] ) . ':00', $zone );
	$now   = new DateTime( 'now', $zone );

	if ( $when <= $now ) {
		$when->modify( '+1 day' );
	}

	wp_schedule_event( $when->getTimestamp(), 'daily', 'eagleeye_send_brief' );
}
add_action( 'init', 'eagleeye_schedule_brief', 6 );
add_action( 'eagleeye_send_brief', 'eagleeye_send_brief' );

/**
 * Next scheduled edition.
 *
 * @return string
 */
function eagleeye_brief_next_send() {
	$next = wp_next_scheduled( 'eagleeye_send_brief' );

	return $next ? wp_date( 'l, j F · g:i a', $next ) : '—';
}

/**
 * Send the edition from the admin page.
 */
function eagleeye_handle_send_brief() {
	if ( ! current_user_can( 'manage_eagleeye_newsletter' ) || ! check_admin_referer( 'eagleeye_send_brief' ) ) {
		wp_die( esc_html__( 'You are not allowed to send this edition.', 'eagleeye' ) );
	}

	if ( ! eagleeye_brief_recipients() ) {
		wp_safe_redirect( add_query_arg( 'ee_brief', 'empty', admin_url( 'admin.php?page=eagleeye-newsletter' ) ) );
		exit;
	}

	$sent = eagleeye_send_brief( true );

	wp_safe_redirect( add_query_arg( array( 'ee_brief' => 'sent', 'count' => $sent ), admin_url( 'admin.php?page=eagleeye-newsletter' ) ) );
	exit;
}
add_action( 'admin_post_eagleeye_send_brief', 'eagleeye_handle_send_brief' );

/**
 * Preview the next edition in the browser.
 */
function eagleeye_handle_brief_preview() {
	if ( ! current_user_can( 'manage_eagleeye_newsletter' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'eagleeye' ) );
	}

	$stories = eagleeye_brief_stories();

	if ( ! $stories ) {
		wp_die( esc_html__( 'Publish a story first and the edition will build itself.', 'eagleeye' ) );
	}

	echo eagleeye_brief_html( $stories ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit;
}
add_action( 'admin_post_eagleeye_brief_preview', 'eagleeye_handle_brief_preview' );

/**
 * Save the edition settings.
 */
function eagleeye_handle_brief_settings() {
	if ( ! current_user_can( 'manage_eagleeye_newsletter' ) || ! check_admin_referer( 'eagleeye_brief_settings' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'eagleeye' ) );
	}

	update_option( 'eagleeye_brief_enabled', empty( $_POST['brief_enabled'] ) ? 0 : 1 );
	update_option( 'eagleeye_brief_hour', min( 23, max( 0, absint( $_POST['brief_hour'] ) ) ) );
	update_option( 'eagleeye_brief_intro', sanitize_textarea_field( wp_unslash( $_POST['brief_intro'] ) ) );

	wp_clear_scheduled_hook( 'eagleeye_send_brief' );
	eagleeye_schedule_brief();

	wp_safe_redirect( add_query_arg( 'ee_brief', 'saved', admin_url( 'admin.php?page=eagleeye-newsletter' ) ) );
	exit;
}
add_action( 'admin_post_eagleeye_brief_settings', 'eagleeye_handle_brief_settings' );

/**
 * Send the confirmation email.
 *
 * @param string $email Email.
 * @param string $token Token.
 * @return bool
 */
function eagleeye_send_confirmation( $email, $token ) {
	$link = eagleeye_confirm_url( $token );

	$body = '<p>One click and you are in.</p>'
		. '<p>Confirm your email to start receiving <strong>The Eagle Eye Morning Brief</strong> — the stories shaping Nigeria and the world, in your inbox every morning at 7am.</p>'
		. '<p style="margin:26px 0"><a href="' . esc_url( $link ) . '" style="background:#08080a;color:#f6c21c;padding:14px 22px;text-decoration:none;font-family:Arial,sans-serif;font-size:12px;letter-spacing:1px">CONFIRM MY SUBSCRIPTION</a></p>'
		. '<p style="font-size:13px;color:#6b665c">If the button does not work, paste this address into your browser:<br>' . esc_html( $link ) . '</p>'
		. '<p style="font-size:13px;color:#6b665c">If you did not ask for this, ignore the email and nothing will happen.</p>';

	return eagleeye_send_email(
		$email,
		__( 'Confirm your Eagle Eye Morning Brief', 'eagleeye' ),
		eagleeye_email_template(
			__( 'Confirm your subscription', 'eagleeye' ),
			$body,
			'',
			array(
				'eyebrow'      => __( 'The Morning Brief', 'eagleeye' ),
				'preheader'    => __( 'One click to confirm your Eagle Eye Morning Brief subscription.', 'eagleeye' ),
				'footer_note'  => __( 'If you did not request this, you can safely ignore this email.', 'eagleeye' ),
			)
		)
	);
}

/**
 * Handle a newsletter signup.
 */
function eagleeye_handle_subscribe() {
	$redirect = wp_get_referer() ? wp_get_referer() : home_url( '/' );

	if ( ! isset( $_POST['eagleeye_newsletter_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['eagleeye_newsletter_nonce'] ) ), 'eagleeye_subscribe' ) ) {
		wp_safe_redirect( add_query_arg( 'ee_newsletter', 'invalid', $redirect ) . '#newsletter' );
		exit;
	}

	if ( ! empty( $_POST['ee_website'] ) ) {
		wp_safe_redirect( add_query_arg( 'ee_newsletter', 'pending', $redirect ) . '#newsletter' );
		exit;
	}

	$email  = isset( $_POST['ee_email'] ) ? sanitize_email( wp_unslash( $_POST['ee_email'] ) ) : '';
	$source = isset( $_POST['ee_source'] ) ? sanitize_key( wp_unslash( $_POST['ee_source'] ) ) : 'site';

	if ( ! is_email( $email ) ) {
		wp_safe_redirect( add_query_arg( 'ee_newsletter', 'invalid', $redirect ) . '#newsletter' );
		exit;
	}

	global $wpdb;

	$table = eagleeye_subscribers_table();
	$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE email = %s", $email ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	if ( $row && 'subscribed' === $row->status ) {
		wp_safe_redirect( add_query_arg( 'ee_newsletter', 'exists', $redirect ) . '#newsletter' );
		exit;
	}

	$token = wp_generate_password( 40, false, false );

	if ( $row ) {
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'status'          => 'pending',
				'token'           => $token,
				'unsubscribed_at' => null,
			),
			array( 'id' => $row->id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
	} else {
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'email'      => $email,
				'source'     => $source ? $source : 'site',
				'status'     => 'pending',
				'token'      => $token,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);
	}

	$sent = eagleeye_send_confirmation( $email, $token );

	if ( ! $sent ) {
		wp_safe_redirect( add_query_arg( 'ee_newsletter', 'mail_error', $redirect ) . '#newsletter' );
		exit;
	}

	wp_safe_redirect( add_query_arg( 'ee_newsletter', 'pending', $redirect ) . '#newsletter' );
	exit;
}
add_action( 'admin_post_nopriv_eagleeye_subscribe', 'eagleeye_handle_subscribe' );
add_action( 'admin_post_eagleeye_subscribe', 'eagleeye_handle_subscribe' );

/**
 * Confirm or unsubscribe from an emailed link.
 */
function eagleeye_handle_newsletter_links() {
	if ( isset( $_GET['ee_confirm'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$token = sanitize_text_field( wp_unslash( $_GET['ee_confirm'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		global $wpdb;
		$table = eagleeye_subscribers_table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE token = %s", $token ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $row && 'subscribed' !== $row->status ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				array(
					'status'       => 'subscribed',
					'confirmed_at' => current_time( 'mysql' ),
				),
				array( 'id' => $row->id ),
				array( '%s', '%s' ),
				array( '%d' )
			);

			$body = '<p>You are on the list.</p>'
				. '<p><strong>The Eagle Eye Morning Brief</strong> arrives every morning at 7am with the stories shaping Nigeria and the world, selected by our editors.</p>'
				. '<p style="font-size:13px;color:#6b665c">You can leave any time using the unsubscribe link at the foot of every email.</p>';

			eagleeye_send_email(
				$row->email,
				__( 'You are subscribed to the Eagle Eye Morning Brief', 'eagleeye' ),
				eagleeye_email_template(
					__( 'Welcome to The Eagle Eye Morning Brief', 'eagleeye' ),
					$body,
					'',
					array(
						'eyebrow'      => __( 'The Morning Brief', 'eagleeye' ),
						'preheader'    => __( 'You are on the list. Your first edition arrives tomorrow at 7am.', 'eagleeye' ),
						'footer_note'  => __( 'You are receiving this because you confirmed your subscription to The Eagle Eye Morning Brief.', 'eagleeye' ),
						'unsubscribe'  => eagleeye_unsubscribe_url( (string) $row->token ),
					)
				),
				'',
				eagleeye_unsubscribe_url( (string) $row->token )
			);

			eagleeye_send_email(
				eagleeye_admin_email(),
				__( 'New newsletter subscriber', 'eagleeye' ),
				eagleeye_email_template(
					__( 'New subscriber', 'eagleeye' ),
					'<p><strong>' . esc_html( $row->email ) . '</strong> confirmed their subscription (' . esc_html( $row->source ) . ').</p>',
					'',
					array(
						'eyebrow'     => __( 'Newsroom notification', 'eagleeye' ),
						'footer_note' => __( 'This is an internal Eagle Eye notification.', 'eagleeye' ),
					)
				)
			);
		}

		wp_safe_redirect( add_query_arg( 'ee_newsletter', 'confirmed', home_url( '/' ) ) . '#newsletter' );
		exit;
	}

	if ( isset( $_GET['ee_unsubscribe'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$token = sanitize_text_field( wp_unslash( $_GET['ee_unsubscribe'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		global $wpdb;
		$table = eagleeye_subscribers_table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE token = %s", $token ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $row ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				array(
					'status'          => 'unsubscribed',
					'unsubscribed_at' => current_time( 'mysql' ),
				),
				array( 'id' => $row->id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		}

		wp_safe_redirect( add_query_arg( 'ee_newsletter', 'unsubscribed', home_url( '/' ) ) . '#newsletter' );
		exit;
	}
}
add_action( 'init', 'eagleeye_handle_newsletter_links', 5 );

/**
 * Feedback message for the current signup state.
 *
 * @return array{type:string,text:string}
 */
function eagleeye_newsletter_message() {
	if ( ! isset( $_GET['ee_newsletter'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return array( 'type' => '', 'text' => '' );
	}

	$state = sanitize_key( wp_unslash( $_GET['ee_newsletter'] ) ); // phpcs:ignore WordPress.Security.NonceVerification

	$messages = array(
		'subscribed'   => array( 'success', __( 'You are on the list. The next briefing lands in your inbox.', 'eagleeye' ) ),
		'confirmed'    => array( 'success', __( 'Confirmed. Welcome to The Eagle Eye Morning Brief — your first edition arrives tomorrow at 7am.', 'eagleeye' ) ),
		'pending'      => array( 'success', __( 'Almost there — check your inbox and click the confirmation link.', 'eagleeye' ) ),
		'exists'       => array( 'success', __( 'That email is already subscribed. You are all set.', 'eagleeye' ) ),
		'unsubscribed' => array( 'success', __( 'You have been removed from the list. You can rejoin at any time.', 'eagleeye' ) ),
		'invalid'      => array( 'error', __( 'Please enter a valid email address and try again.', 'eagleeye' ) ),
		'mail_error'   => array( 'error', __( 'We could not send the confirmation email. Please try again later or contact the newsroom.', 'eagleeye' ) ),
	);

	return isset( $messages[ $state ] )
		? array( 'type' => $messages[ $state ][0], 'text' => $messages[ $state ][1] )
		: array( 'type' => '', 'text' => '' );
}

/**
 * Newsletter signup form.
 *
 * @param string $context Layout context: band|rail|footer|inline.
 */
function eagleeye_newsletter_form( $context = 'band' ) {
	$message = eagleeye_newsletter_message();
	$title   = eagleeye_option( 'eagleeye_newsletter_title', __( 'The Eagle Eye Morning Brief', 'eagleeye' ) );
	$text    = eagleeye_option( 'eagleeye_newsletter_text', __( 'The stories shaping Nigeria and the world, selected by our editors and delivered every morning at 7am.', 'eagleeye' ) );
	$note    = eagleeye_option( 'eagleeye_newsletter_note', __( 'Free. One briefing every morning at 7am. Unsubscribe anytime.', 'eagleeye' ) );
	$anchor  = 'band' === $context ? 'newsletter' : 'newsletter-' . $context;
	?>
	<div class="newsletter newsletter--<?php echo esc_attr( $context ); ?>" id="<?php echo esc_attr( $anchor ); ?>">
		<div class="newsletter__intro">
			<span class="newsletter__eyebrow"><?php esc_html_e( 'Newsletter', 'eagleeye' ); ?></span>
			<h2 class="newsletter__title"><?php echo esc_html( $title ); ?></h2>
			<p class="newsletter__text"><?php echo esc_html( $text ); ?></p>
		</div>
		<form class="newsletter__form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<input type="hidden" name="action" value="eagleeye_subscribe">
			<input type="hidden" name="ee_source" value="<?php echo esc_attr( $context ); ?>">
			<?php wp_nonce_field( 'eagleeye_subscribe', 'eagleeye_newsletter_nonce' ); ?>
			<div class="newsletter__field">
				<label class="screen-reader-text" for="ee-email-<?php echo esc_attr( $context ); ?>"><?php esc_html_e( 'Email address', 'eagleeye' ); ?></label>
				<input type="email" id="ee-email-<?php echo esc_attr( $context ); ?>" name="ee_email" required autocomplete="email" placeholder="<?php esc_attr_e( 'Your email address', 'eagleeye' ); ?>">
				<button type="submit" class="btn btn--solid"><?php esc_html_e( 'Subscribe', 'eagleeye' ); ?></button>
			</div>
			<label class="newsletter__honeypot" aria-hidden="true">Website<input type="text" name="ee_website" tabindex="-1" autocomplete="off"></label>
			<p class="newsletter__note"><?php echo esc_html( $note ); ?></p>
			<?php if ( $message['text'] ) : ?>
				<p class="newsletter__message newsletter__message--<?php echo esc_attr( $message['type'] ); ?>" role="status"><?php echo esc_html( $message['text'] ); ?></p>
			<?php endif; ?>
		</form>
	</div>
	<?php
}

/**
 * [eagleeye_newsletter] shortcode.
 *
 * @return string
 */
function eagleeye_newsletter_shortcode() {
	ob_start();
	eagleeye_newsletter_form( 'inline' );

	return (string) ob_get_clean();
}
add_shortcode( 'eagleeye_newsletter', 'eagleeye_newsletter_shortcode' );

/**
 * Admin page listing subscribers.
 */
function eagleeye_newsletter_admin_page() {
	if ( ! current_user_can( 'manage_eagleeye_newsletter' ) ) {
		return;
	}

	global $wpdb;
	$table = eagleeye_subscribers_table();

	$recent      = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 150" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$export      = wp_nonce_url( admin_url( 'admin-post.php?action=eagleeye_export_subscribers' ), 'eagleeye_export' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Eagle Eye Newsletter', 'eagleeye' ); ?></h1>
		<p><?php esc_html_e( 'One briefing, every morning. The edition builds itself from your latest stories and goes to every confirmed subscriber.', 'eagleeye' ); ?></p>
		<?php
		$settings = eagleeye_brief_settings();
		$people   = eagleeye_brief_recipients();
		$last     = get_option( 'eagleeye_brief_last_sent' );
		$last_qty = (int) get_option( 'eagleeye_brief_last_count' );
		$preview  = admin_url( 'admin-post.php?action=eagleeye_brief_preview' );

		if ( isset( $_GET['ee_brief'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$state = sanitize_key( wp_unslash( $_GET['ee_brief'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			$count = isset( $_GET['count'] ) ? absint( $_GET['count'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
			$text  = 'sent' === $state
				? sprintf( __( 'Edition sent to %s subscribers.', 'eagleeye' ), number_format_i18n( $count ) )
				: ( 'empty' === $state ? __( 'No confirmed subscribers yet, so the edition was not sent.', 'eagleeye' ) : __( 'Edition settings saved.', 'eagleeye' ) );

			echo '<div class="notice ' . ( 'empty' === $state ? 'notice-warning' : 'notice-success' ) . ' is-dismissible"><p>' . esc_html( $text ) . '</p></div>';
		}

		$mail_error = get_option( 'eagleeye_mail_error' );

		if ( $last && 0 === $last_qty && $mail_error ) {
			echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'The last edition did not reach anyone.', 'eagleeye' ) . '</strong> '
				. esc_html( eagleeye_mail_error_message() ) . ' '
				. '<a href="' . esc_url( admin_url( 'admin.php?page=eagleeye-email' ) ) . '">' . esc_html__( 'Fix email delivery', 'eagleeye' ) . '</a></p></div>';
		}
		?>

		<div class="ee-brief">
			<div class="ee-brief__head">
				<div>
					<h2><?php esc_html_e( 'The Morning Brief', 'eagleeye' ); ?></h2>
					<p><?php esc_html_e( 'Built automatically from the latest stories, one email for every confirmed subscriber, with their own unsubscribe link.', 'eagleeye' ); ?></p>
				</div>
				<span class="ee-brief__badge <?php echo $settings['enabled'] ? 'is-on' : 'is-off'; ?>">
					<?php echo $settings['enabled'] ? esc_html__( 'Sending daily', 'eagleeye' ) : esc_html__( 'Paused', 'eagleeye' ); ?>
				</span>
			</div>

			<div class="ee-brief__facts">
				<div><span><?php esc_html_e( 'Next edition', 'eagleeye' ); ?></span><strong><?php echo esc_html( $settings['enabled'] ? eagleeye_brief_next_send() : '—' ); ?></strong></div>
				<div><span><?php esc_html_e( 'Last edition', 'eagleeye' ); ?></span><strong><?php echo $last ? esc_html( mysql2date( 'j M Y · g:i a', $last ) ) : '—'; ?></strong><em><?php echo $last ? ( $last_qty ? esc_html( sprintf( __( '%s delivered', 'eagleeye' ), number_format_i18n( $last_qty ) ) ) : esc_html__( 'Nothing delivered', 'eagleeye' ) ) : esc_html__( 'Not run yet', 'eagleeye' ); ?></em></div>
				<div><span><?php esc_html_e( 'Recipients ready', 'eagleeye' ); ?></span><strong><?php echo esc_html( number_format_i18n( count( $people ) ) ); ?></strong><em><?php esc_html_e( 'confirmed subscribers', 'eagleeye' ); ?></em></div>
			</div>

			<div class="ee-brief__actions">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Send this edition to every confirmed subscriber now?', 'eagleeye' ) ); ?>');">
					<input type="hidden" name="action" value="eagleeye_send_brief">
					<?php wp_nonce_field( 'eagleeye_send_brief' ); ?>
					<button type="submit" class="button <?php echo $people ? 'button-primary' : ''; ?>" <?php disabled( ! $people ); ?>><?php esc_html_e( 'Send the edition now', 'eagleeye' ); ?></button>
				</form>
				<a class="button" href="<?php echo esc_url( $preview ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Preview this edition', 'eagleeye' ); ?></a>
				<?php if ( ! eagleeye_brevo_key() && ! eagleeye_resend_key() ) : ?>
					<a class="ee-brief__link" href="<?php echo esc_url( admin_url( 'admin.php?page=eagleeye-email' ) ); ?>"><?php esc_html_e( 'Connect Brevo so the email actually leaves the server →', 'eagleeye' ); ?></a>
				<?php endif; ?>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ee-brief__settings">
				<input type="hidden" name="action" value="eagleeye_brief_settings">
				<?php wp_nonce_field( 'eagleeye_brief_settings' ); ?>

				<label class="ee-brief__toggle">
					<input type="checkbox" name="brief_enabled" value="1" <?php checked( $settings['enabled'] ); ?>>
					<span><?php esc_html_e( 'Send the Morning Brief automatically every day', 'eagleeye' ); ?></span>
				</label>

				<div class="ee-brief__row">
					<label for="brief_hour"><?php esc_html_e( 'Send at', 'eagleeye' ); ?></label>
					<select id="brief_hour" name="brief_hour">
						<?php for ( $h = 5; $h <= 11; $h++ ) : ?>
							<option value="<?php echo esc_attr( $h ); ?>" <?php selected( $settings['hour'], $h ); ?>><?php echo esc_html( gmdate( 'g:00 a', $h * 3600 ) ); ?></option>
						<?php endfor; ?>
					</select>
					<span class="description"><?php esc_html_e( 'Site time. WordPress cron needs a visit to the site to run; on live hosting add a real cron job for guaranteed delivery.', 'eagleeye' ); ?></span>
				</div>

				<label class="ee-brief__label" for="brief_intro"><?php esc_html_e( 'Editor’s note at the top', 'eagleeye' ); ?></label>
				<textarea id="brief_intro" name="brief_intro" rows="2" class="large-text"><?php echo esc_textarea( $settings['intro'] ); ?></textarea>

				<p><button class="button" name="eagleeye_brief_settings" value="1"><?php esc_html_e( 'Save schedule', 'eagleeye' ); ?></button></p>
			</form>
		</div>

		<div class="ee-subscribers-head">
			<h2><?php esc_html_e( 'Subscribers', 'eagleeye' ); ?></h2>
			<a class="button" href="<?php echo esc_url( $export ); ?>"><?php esc_html_e( 'Export CSV', 'eagleeye' ); ?></a>
		</div>
		<?php if ( $recent ) : ?>
			<table class="widefat striped" style="max-width:900px">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Email', 'eagleeye' ); ?></th>
						<th><?php esc_html_e( 'Source', 'eagleeye' ); ?></th>
						<th><?php esc_html_e( 'Status', 'eagleeye' ); ?></th>
						<th><?php esc_html_e( 'Joined', 'eagleeye' ); ?></th>
						<th><?php esc_html_e( 'Confirmed', 'eagleeye' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $recent as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row->email ); ?></td>
							<td><?php echo esc_html( $row->source ); ?></td>
							<td><?php echo esc_html( ucfirst( (string) $row->status ) ); ?></td>
							<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' H:i', $row->created_at ) ); ?></td>
							<td><?php echo $row->confirmed_at ? esc_html( mysql2date( get_option( 'date_format' ) . ' H:i', $row->confirmed_at ) ) : '—'; ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><?php esc_html_e( 'No subscribers yet. The forms on the homepage, articles and footer feed this list.', 'eagleeye' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Export subscribers as CSV.
 */
function eagleeye_export_subscribers() {
	if ( ! current_user_can( 'manage_eagleeye_newsletter' ) || ! check_admin_referer( 'eagleeye_export' ) ) {
		wp_die( esc_html__( 'You are not allowed to export this list.', 'eagleeye' ) );
	}

	global $wpdb;
	$table = eagleeye_subscribers_table();
	$rows  = $wpdb->get_results( "SELECT email, source, status, created_at, confirmed_at FROM {$table} ORDER BY created_at DESC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=eagleeye-subscribers-' . gmdate( 'Y-m-d' ) . '.csv' );

	$out = fopen( 'php://output', 'w' );
	fputcsv( $out, array( 'email', 'source', 'status', 'created_at', 'confirmed_at' ) );

	foreach ( (array) $rows as $row ) {
		fputcsv( $out, $row );
	}

	fclose( $out );
	exit;
}
add_action( 'admin_post_eagleeye_export_subscribers', 'eagleeye_export_subscribers' );
