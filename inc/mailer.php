<?php
/**
 * Transactional email.
 *
 * Uses Brevo when a key is present, then Resend, otherwise WordPress mail. The
 * key is read from a constant, an environment variable, or a private server
 * file outside public_html, so it never lives in the theme or a public file.
 *
 * @package Eagle Eye
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resend API key, if configured.
 *
 * @return string
 */
function eagleeye_resend_key() {
	if ( defined( 'EAGLEEYE_RESEND_API_KEY' ) && EAGLEEYE_RESEND_API_KEY ) {
		return (string) EAGLEEYE_RESEND_API_KEY;
	}

	$env = getenv( 'EAGLEEYE_RESEND_API_KEY' );
	if ( $env ) {
		return (string) $env;
	}

	$secret = eagleeye_secret( 'resend_key' );
	if ( $secret ) {
		return $secret;
	}

	return '';
}

/**
 * Brevo API key, if configured.
 *
 * Brevo should send from a domain the publication owns and has authenticated.
 * Free-mail senders cannot be authenticated and may be rejected or rewritten.
 *
 * @return string
 */
function eagleeye_brevo_key() {
	if ( defined( 'EAGLEEYE_BREVO_API_KEY' ) && EAGLEEYE_BREVO_API_KEY ) {
		return (string) EAGLEEYE_BREVO_API_KEY;
	}

	$env = getenv( 'EAGLEEYE_BREVO_API_KEY' );
	if ( $env ) {
		return (string) $env;
	}

	$secret = eagleeye_secret( 'brevo_key' );
	if ( $secret ) {
		return $secret;
	}

	return '';
}

/**
 * Active mail transport.
 *
 * @return string brevo|resend|wp_mail
 */
function eagleeye_mail_transport() {
	if ( eagleeye_brevo_key() ) {
		return 'brevo';
	}

	if ( eagleeye_resend_key() ) {
		return 'resend';
	}

	return 'wp_mail';
}

/**
 * A plain-English explanation of the last delivery error.
 *
 * @return string
 */
function eagleeye_mail_error_message() {
	$error = (string) get_option( 'eagleeye_mail_error' );

	if ( ! $error ) {
		return '';
	}

	if ( false !== stripos( $error, 'Brevo' ) ) {
		if ( false !== stripos( $error, 'sender' ) && ( false !== stripos( $error, 'not valid' ) || false !== stripos( $error, 'not verified' ) || false !== stripos( $error, 'verify' ) ) ) {
			return __( 'Brevo has not verified the From address yet. Add that exact address under Brevo → Senders & IP → Senders, click the verification link, then use the same address under Email settings and test again.', 'eagleeye' );
		}

		if ( false !== stripos( $error, 'unauthorized' ) || false !== stripos( $error, '401' ) || false !== stripos( $error, 'API key' ) ) {
			return __( 'Brevo rejected the API key. Create a new transactional API key in Brevo and update the private server secret file.', 'eagleeye' );
		}
	}

	if ( false !== stripos( $error, 'domain is not verified' ) ) {
		return __( 'Resend has not verified eagleeyenews.online yet. Add the DNS records in Resend → Domains, wait for verification, then send the test again.', 'eagleeye' );
	}

	if ( false !== stripos( $error, 'Invalid `to` field' ) ) {
		return __( 'One or more subscriber addresses are test addresses (example.com) and Resend refuses them. Remove the test subscribers or use real addresses.', 'eagleeye' );
	}

	return $error;
}

/**
 * Sender identity.
 *
 * @return array
 */
function eagleeye_mail_identity() {
	$identity = array(
		'from_name'  => get_option( 'eagleeye_mail_from_name', get_bloginfo( 'name' ) ),
		'from_email' => get_option( 'eagleeye_mail_from_email', 'hello@eagleeyenews.online' ),
		'admin'      => get_option( 'eagleeye_mail_admin', get_option( 'admin_email' ) ),
	);

	if ( 'brief@eagleeye.ng' === $identity['from_email'] ) {
		$identity['from_email'] = 'hello@eagleeyenews.online';
	}

	return $identity;
}

/**
 * Wrap content in the Eagle Eye email shell.
 *
 * Built with table layout and inline styles for reliable rendering in Gmail,
 * Outlook, Apple Mail and mobile clients. The logo is hosted on the live site;
 * the alt text and footer wordmark keep the brand readable when images are
 * blocked.
 *
 * @param string $heading Heading.
 * @param string $body    HTML body.
 * @param string $cta     Optional CTA markup.
 * @param array  $args    Optional eyebrow, preheader, footer_note and unsubscribe.
 * @return string
 */
function eagleeye_email_template( $heading, $body, $cta = '', $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'eyebrow'      => __( 'Eagle Eye Newsroom', 'eagleeye' ),
			'preheader'    => '',
			'footer_note'  => __( 'This message was sent by Eagle Eye, an independent Nigerian digital news publication.', 'eagleeye' ),
			'unsubscribe'  => '',
		)
	);

	$year     = date_i18n( 'Y' );
	$site     = get_bloginfo( 'name' );
	$home     = home_url( '/' );
	$logo     = EAGLEEYE_URI . '/brand/eagleeye-logo-light.png';
	$email    = 'hello@eagleeyenews.online';
	$phone    = (string) get_theme_mod( 'eagleeye_phone', '07017138501' );
	$address  = trim( (string) get_theme_mod( 'eagleeye_address', '' ) );
	$socials  = function_exists( 'eagleeye_socials' ) ? eagleeye_socials() : array();
	$links    = array();
	$preheader = $args['preheader'] ? $args['preheader'] : wp_strip_all_tags( $heading );

	foreach ( $socials as $social ) {
		if ( empty( $social['url'] ) || empty( $social['label'] ) ) {
			continue;
		}

		$links[] = '<a href="' . esc_url( $social['url'] ) . '" style="color:#6f695e;text-decoration:none">' . esc_html( $social['label'] ) . '</a>';
	}

	$social_html  = $links ? implode( ' <span style="color:#c9c2b4">·</span> ', $links ) : '';
	$unsubscribe = $args['unsubscribe'] ? '<a href="' . esc_url( $args['unsubscribe'] ) . '" style="color:#6f695e;text-decoration:underline">' . esc_html__( 'Unsubscribe', 'eagleeye' ) . '</a>' : '';
	$contact     = $address ? esc_html( $address ) . '<br>' : esc_html__( 'Nigeria', 'eagleeye' ) . '<br>';

	return '<!doctype html><html lang="en" xmlns="http://www.w3.org/1999/xhtml"><head>'
		. '<meta charset="utf-8">'
		. '<meta name="viewport" content="width=device-width,initial-scale=1">'
		. '<meta name="x-apple-disable-message-reformatting">'
		. '<title>' . esc_html( $heading ) . '</title>'
		. '<style>'
		. '@media only screen and (max-width:620px){'
		. '.ee-shell{padding:12px 8px!important}'
		. '.ee-card{width:100%!important}'
		. '.ee-head{padding:26px 18px 22px!important}'
		. '.ee-logo{width:138px!important;height:auto!important}'
		. '.ee-pad{padding:26px 22px!important}'
		. '.ee-title{font-size:26px!important;line-height:1.2!important}'
		. '.ee-footer{padding:22px!important}'
		. '}'
		. '.ee-body p{margin:0 0 15px}'
		. '.ee-body a{color:#b81722}'
		. '</style>'
		. '</head><body style="margin:0;padding:0;background-color:#f2eee5;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%">'
		. '<div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all">' . esc_html( $preheader ) . '</div>'
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f2eee5"><tr><td align="center" class="ee-shell" style="padding:30px 12px">'
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="ee-card" style="width:100%;max-width:640px;background-color:#ffffff;border:1px solid #e4ddcf">'
		. '<tr><td class="ee-head" align="center" style="padding:30px 24px 24px;background-color:#111114;border-top:5px solid #f6c21c">'
		. '<a href="' . esc_url( $home ) . '" style="text-decoration:none"><img src="' . esc_url( $logo ) . '" width="150" height="71" alt="' . esc_attr( $site ) . '" class="ee-logo" style="display:block;width:150px;max-width:150px;height:auto;border:0;outline:none;text-decoration:none"></a>'
		. '<div style="margin-top:13px;font-family:Arial,Helvetica,sans-serif;font-size:9px;font-weight:bold;letter-spacing:2.6px;line-height:1.5;color:#f6c21c;text-transform:uppercase">' . esc_html__( 'Independent Nigerian news · Seeing beyond the headlines', 'eagleeye' ) . '</div>'
		. '</td></tr>'
		. '<tr><td class="ee-pad ee-body" style="padding:36px 40px 12px;background-color:#ffffff">'
		. '<div style="margin:0 0 12px;font-family:Arial,Helvetica,sans-serif;font-size:10px;font-weight:bold;letter-spacing:2.4px;line-height:1.4;color:#d81f2a;text-transform:uppercase">' . esc_html( $args['eyebrow'] ) . '</div>'
		. '<h1 class="ee-title" style="margin:0 0 20px;font-family:Georgia,\'Times New Roman\',serif;font-size:30px;font-weight:600;line-height:1.17;color:#111114">' . esc_html( $heading ) . '</h1>'
		. '<div style="font-family:Georgia,\'Times New Roman\',serif;font-size:16px;line-height:1.7;color:#33333a">' . $body . '</div>'
		. ( $cta ? '<div style="margin-top:26px">' . $cta . '</div>' : '' )
		. '</td></tr>'
		. '<tr><td class="ee-footer" style="padding:24px 40px 32px;background-color:#ffffff;border-top:1px solid #e9e2d5;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.65;color:#777267">'
		. '<p style="margin:0 0 10px"><strong style="color:#111114;font-size:13px;letter-spacing:0.3px">' . esc_html( $site ) . '</strong><br><span style="color:#8d877b">' . esc_html__( 'Independent Nigerian digital news', 'eagleeye' ) . '</span></p>'
		. '<p style="margin:0 0 8px">' . $contact . '<a href="mailto:' . esc_attr( $email ) . '" style="color:#6f695e;text-decoration:none">' . esc_html( $email ) . '</a> · <a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ) . '" style="color:#6f695e;text-decoration:none">' . esc_html( $phone ) . '</a></p>'
		. ( $social_html ? '<p style="margin:0 0 12px">' . $social_html . '</p>' : '' )
		. '<p style="margin:0;color:#8d877b">' . esc_html( $args['footer_note'] ) . '</p>'
		. '<p style="margin:10px 0 0;color:#a39b8d">© ' . esc_html( $year ) . ' ' . esc_html( $site ) . '. ' . esc_html__( 'All rights reserved.', 'eagleeye' ) . ( $unsubscribe ? ' ' . $unsubscribe : '' ) . '</p>'
		. '</td></tr>'
		. '</table></td></tr></table></body></html>';
}

/**
 * Plain-text alternative generated from the HTML message.
 *
 * @param string $html HTML message.
 * @return string
 */
function eagleeye_email_text( $html ) {
	$text = preg_replace( '#<(style|script)[^>]*>.*?</\1>#is', '', (string) $html );
	$text = preg_replace( '#<(br|/p|/div|/h1|/h2|/h3|/tr|/li)[^>]*>#i', "\n", (string) $text );
	$text = wp_strip_all_tags( (string) $text );
	$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
	$text = preg_replace( "/[ \t]+\n/", "\n", $text );
	$text = preg_replace( "/\n{3,}/", "\n\n", $text );

	return trim( (string) $text );
}

/**
 * Send one message through Brevo's transactional API.
 *
 * @param string|array $to      Recipient or recipients.
 * @param string       $subject Subject.
 * @param string       $html    HTML body.
 * @param string       $reply   Optional reply-to.
 * @param string       $unsubscribe Optional unsubscribe URL.
 * @return bool
 */
function eagleeye_brevo_send( $to, $subject, $html, $reply = '', $unsubscribe = '' ) {
	$brevo_key = eagleeye_brevo_key();

	if ( ! $brevo_key ) {
		return false;
	}

	$identity   = eagleeye_mail_identity();
	$recipients = array();

	foreach ( (array) $to as $address ) {
		foreach ( explode( ',', (string) $address ) as $email ) {
			$email = trim( preg_replace( '/^.*<([^>]+)>.*$/', '$1', trim( $email ) ) );

			if ( is_email( $email ) ) {
				$recipients[] = array( 'email' => $email );
			}
		}
	}

	if ( ! $recipients ) {
		return false;
	}

	$payload = array(
		'sender'      => array(
			'name'  => $identity['from_name'],
			'email' => $identity['from_email'],
		),
		'to'          => $recipients,
		'subject'     => $subject,
		'htmlContent' => $html,
		'textContent' => eagleeye_email_text( $html ),
	);

	// hello@ is a sender identity. Until it has a real mailbox, route replies to
	// the monitored newsroom inbox so reader replies are not lost.
	$reply = $reply ? $reply : $identity['admin'];

	if ( $reply ) {
		$payload['replyTo'] = array( 'email' => $reply );
	}

	if ( $unsubscribe ) {
		$payload['headers'] = array( 'List-Unsubscribe' => '<' . $unsubscribe . '>' );
	}

	$response = wp_remote_post( 'https://api.brevo.com/v3/smtp/email', array(
		'timeout' => 20,
		'headers' => array(
			'api-key'      => $brevo_key,
			'accept'       => 'application/json',
			'content-type' => 'application/json',
		),
		'body'    => wp_json_encode( $payload ),
	) );

	if ( is_wp_error( $response ) ) {
		update_option( 'eagleeye_mail_error', $response->get_error_message(), false );
		return false;
	}

	$code = (int) wp_remote_retrieve_response_code( $response );

	if ( $code >= 200 && $code < 300 ) {
		delete_option( 'eagleeye_mail_error' );
		return true;
	}

	update_option( 'eagleeye_mail_error', 'Brevo HTTP ' . $code . ': ' . wp_remote_retrieve_body( $response ), false );
	return false;
}

/**
 * Send an email through Brevo, Resend or wp_mail.
 *
 * @param string $to      Recipient.
 * @param string $subject Subject.
 * @param string $html    HTML body.
 * @param string $reply   Optional reply-to.
 * @param string $unsubscribe Optional unsubscribe URL.
 * @return bool
 */
function eagleeye_send_email( $to, $subject, $html, $reply = '', $unsubscribe = '' ) {
	if ( eagleeye_brevo_key() ) {
		return eagleeye_brevo_send( $to, $subject, $html, $reply, $unsubscribe );
	}

	$identity = eagleeye_mail_identity();
	$key      = eagleeye_resend_key();
	$reply    = $reply ? $reply : $identity['admin'];

	if ( $key ) {
		$payload = array(
			'from'    => sprintf( '%s <%s>', $identity['from_name'], $identity['from_email'] ),
			'to'      => array( $to ),
			'subject' => $subject,
			'html'    => $html,
			'text'    => eagleeye_email_text( $html ),
		);

		$reply = $reply ? $reply : $identity['admin'];

		if ( $reply ) {
			$payload['reply_to'] = $reply;
		}

		if ( $unsubscribe ) {
			$payload['headers'] = array( 'List-Unsubscribe' => '<' . $unsubscribe . '>' );
		}

		$response = wp_remote_post( 'https://api.resend.com/emails', array(
			'timeout' => 20,
			'headers' => array(
				'Authorization' => 'Bearer ' . $key,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $payload ),
		) );

		if ( is_wp_error( $response ) ) {
			update_option( 'eagleeye_mail_error', $response->get_error_message(), false );
			return false;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( $code >= 200 && $code < 300 ) {
			delete_option( 'eagleeye_mail_error' );
			return true;
		}

		update_option( 'eagleeye_mail_error', 'Resend HTTP ' . $code . ': ' . wp_remote_retrieve_body( $response ), false );
		return false;
	}

	$headers = array( 'Content-Type: text/html; charset=UTF-8' );

	if ( $reply ) {
		$headers[] = 'Reply-To: ' . $reply;
	}

	if ( $unsubscribe ) {
		$headers[] = 'List-Unsubscribe: <' . $unsubscribe . '>';
	}

	$sent = wp_mail( $to, $subject, $html, $headers );

	if ( ! $sent ) {
		update_option( 'eagleeye_mail_error', 'wp_mail() returned false. No mail transport is configured.', false );
	}

	return (bool) $sent;
}

/**
 * Route WordPress core email through Brevo when a key is configured.
 *
 * Password resets, new-user notices and other core notifications use wp_mail()
 * directly. This keeps them on the authenticated sending domain instead of the
 * web server's unauthenticated PHP mail path.
 *
 * @param bool|null $return Short-circuit result.
 * @param array     $atts   wp_mail() arguments.
 * @return bool|null
 */
function eagleeye_route_core_mail_via_brevo( $return, $atts ) {
	if ( null !== $return || ! eagleeye_brevo_key() ) {
		return $return;
	}

	$to      = isset( $atts['to'] ) ? $atts['to'] : '';
	$subject = isset( $atts['subject'] ) ? (string) $atts['subject'] : '';
	$message = isset( $atts['message'] ) ? (string) $atts['message'] : '';
	$reply   = '';

	foreach ( (array) ( isset( $atts['headers'] ) ? $atts['headers'] : array() ) as $header ) {
		if ( 0 === stripos( trim( (string) $header ), 'reply-to:' ) ) {
			$reply = trim( substr( trim( (string) $header ), 9 ) );
		}
	}

	$body = '<div style="white-space:normal">' . nl2br( esc_html( $message ) ) . '</div>';

	return eagleeye_brevo_send(
		$to,
		$subject,
		eagleeye_email_template( $subject, $body ),
		$reply
	);
}
add_filter( 'pre_wp_mail', 'eagleeye_route_core_mail_via_brevo', 10, 2 );

/**
 * Admin notification address.
 *
 * @return string
 */
function eagleeye_admin_email() {
	$identity = eagleeye_mail_identity();

	return $identity['admin'] ? $identity['admin'] : get_option( 'admin_email' );
}

/**
 * Settings page: sender identity and test.
 */
function eagleeye_mail_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( isset( $_POST['eagleeye_mail_settings'] ) && check_admin_referer( 'eagleeye_mail_settings' ) ) {
		update_option( 'eagleeye_mail_from_name', sanitize_text_field( wp_unslash( $_POST['from_name'] ) ) );
		update_option( 'eagleeye_mail_from_email', sanitize_email( wp_unslash( $_POST['from_email'] ) ) );
		update_option( 'eagleeye_mail_admin', sanitize_email( wp_unslash( $_POST['admin_email'] ) ) );

		if ( ! empty( $_POST['posthog_key'] ) ) {
			update_option( 'eagleeye_posthog_key', sanitize_text_field( wp_unslash( $_POST['posthog_key'] ) ), false );
		}

		if ( ! empty( $_POST['posthog_key_clear'] ) ) {
			delete_option( 'eagleeye_posthog_key' );
		}

		echo '<div class="notice notice-success"><p>' . esc_html__( 'Email settings saved.', 'eagleeye' ) . '</p></div>';
	}

	if ( isset( $_POST['eagleeye_mail_test'] ) && check_admin_referer( 'eagleeye_mail_test' ) ) {
		$test_to = isset( $_POST['test_email'] ) ? sanitize_email( wp_unslash( $_POST['test_email'] ) ) : '';

		if ( ! is_email( $test_to ) ) {
			$test_to = eagleeye_admin_email();
		}

		$sent = eagleeye_send_email(
			$test_to,
			__( 'Eagle Eye test email', 'eagleeye' ),
			eagleeye_email_template(
				__( 'Your mailer is connected', 'eagleeye' ),
				'<p>' . esc_html__( 'This is a test from your Eagle Eye newsroom. Newsletter confirmations, media-kit receipts and booking alerts will arrive like this.', 'eagleeye' ) . '</p>'
			)
		);

		printf(
			'<div class="notice %1$s"><p>%2$s</p></div>',
			$sent ? 'notice-success' : 'notice-error',
			$sent ? esc_html__( 'Test sent. Check the inbox.', 'eagleeye' ) : esc_html__( 'The mailer refused the message. See the error below.', 'eagleeye' )
		);
	}

	$identity  = eagleeye_mail_identity();
	$key       = eagleeye_resend_key();
	$brevo     = eagleeye_brevo_key();
	$transport = eagleeye_mail_transport();
	$error     = get_option( 'eagleeye_mail_error' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Email & notifications', 'eagleeye' ); ?></h1>
		<p><?php esc_html_e( 'Newsletter double opt-in, media-kit receipts and booking alerts all use this sender.', 'eagleeye' ); ?></p>

		<table class="widefat" style="max-width:720px;margin:18px 0">
			<tbody>
				<tr>
					<td><strong><?php esc_html_e( 'Transport', 'eagleeye' ); ?></strong></td>
					<td>
						<?php if ( 'brevo' === $transport ) : ?>
							<span style="color:#0a7d33">Brevo API</span>
						<?php elseif ( 'resend' === $transport ) : ?>
							<span style="color:#0a7d33">Resend API</span>
						<?php else : ?>
							<span style="color:#a15c00">WordPress mail (no API key configured)</span>
						<?php endif; ?>
					</td>
				</tr>
				<?php if ( $error ) : ?>
					<tr>
						<td><strong><?php esc_html_e( 'Last error', 'eagleeye' ); ?></strong></td>
						<td style="color:#b31224"><?php echo esc_html( eagleeye_mail_error_message() ); ?></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>

		<form method="post" style="max-width:720px">
			<?php wp_nonce_field( 'eagleeye_mail_settings' ); ?>
			<table class="form-table">
				<tr>
					<th><label for="from_name"><?php esc_html_e( 'From name', 'eagleeye' ); ?></label></th>
					<td><input type="text" class="regular-text" id="from_name" name="from_name" value="<?php echo esc_attr( $identity['from_name'] ); ?>"></td>
				</tr>
				<tr>
					<th><label for="from_email"><?php esc_html_e( 'From address', 'eagleeye' ); ?></label></th>
					<td><input type="email" class="regular-text" id="from_email" name="from_email" value="<?php echo esc_attr( $identity['from_email'] ); ?>">
					<p class="description">
						<?php if ( $brevo ) : ?>
							<?php esc_html_e( 'Use an address on the domain authenticated in Brevo → Senders & IP → Domains. Free addresses such as Gmail or Yahoo are not suitable.', 'eagleeye' ); ?>
						<?php else : ?>
							<?php esc_html_e( 'Must be a verified sending domain in Resend (for example hello@eagleeyenews.online).', 'eagleeye' ); ?>
						<?php endif; ?>
					</p></td>
				</tr>
				<tr>
					<th><label for="brevo_key"><?php esc_html_e( 'Brevo API key', 'eagleeye' ); ?></label></th>
					<td>
						<strong style="color:<?php echo $brevo ? '#0a7d33' : '#b31224'; ?>"><?php echo $brevo ? esc_html__( 'Configured on the server', 'eagleeye' ) : esc_html__( 'Not configured', 'eagleeye' ); ?></strong>
						<p class="description"><?php esc_html_e( 'For security, the key is stored in a private file outside public_html. It is never saved in the database, the theme or a page. Change it over SSH when rotating the key.', 'eagleeye' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="admin_email"><?php esc_html_e( 'Newsroom notifications', 'eagleeye' ); ?></label></th>
					<td><input type="email" class="regular-text" id="admin_email" name="admin_email" value="<?php echo esc_attr( $identity['admin'] ); ?>">
					<p class="description"><?php esc_html_e( 'Media-kit requests, ad bookings and new subscribers are reported here.', 'eagleeye' ); ?></p></td>
				</tr>
				<tr>
					<th><label for="posthog_key"><?php esc_html_e( 'Analytics key', 'eagleeye' ); ?></label></th>
					<td>
						<input type="password" class="regular-text" id="posthog_key" name="posthog_key" value="" autocomplete="new-password" placeholder="<?php echo eagleeye_posthog_key() ? esc_attr__( 'Configured — leave blank to keep it', 'eagleeye' ) : esc_attr__( 'phc_…', 'eagleeye' ); ?>">
						<p class="description"><?php esc_html_e( 'Optional. Adds page analytics so you can see which stories are actually read. The key is the PostHog project API key (Settings → Project → Project API key, starts with phc_). Leave it empty and the site loads no analytics script at all.', 'eagleeye' ); ?></p>
						<?php if ( get_option( 'eagleeye_posthog_key' ) ) : ?>
							<label><input type="checkbox" name="posthog_key_clear" value="1"> <?php esc_html_e( 'Remove the stored analytics key', 'eagleeye' ); ?></label>
						<?php endif; ?>
					</td>
				</tr>
			</table>
			<p><button class="button button-primary" name="eagleeye_mail_settings" value="1"><?php esc_html_e( 'Save settings', 'eagleeye' ); ?></button></p>
		</form>

		<form method="post" style="margin-top:10px;max-width:720px">
			<?php wp_nonce_field( 'eagleeye_mail_test' ); ?>
			<p>
				<label for="test_email"><strong><?php esc_html_e( 'Send the test to', 'eagleeye' ); ?></strong></label><br>
				<input type="email" class="regular-text" id="test_email" name="test_email" value="<?php echo esc_attr( $identity['admin'] ); ?>">
				<span class="description"><?php esc_html_e( 'Use an inbox you can open now. This does not change the newsroom notification address.', 'eagleeye' ); ?></span>
			</p>
			<p><button class="button" name="eagleeye_mail_test" value="1"><?php esc_html_e( 'Send a test email', 'eagleeye' ); ?></button></p>
		</form>

		<h2 style="margin-top:32px"><?php esc_html_e( 'Connect Brevo', 'eagleeye' ); ?></h2>
		<p style="max-width:720px"><?php esc_html_e( 'Brevo needs a domain the publication owns and has authenticated. Gmail, Yahoo and Outlook addresses cannot be authenticated, and providers may reject or rewrite them. Buy a domain first, then authenticate it in Brevo. The free plan includes 300 sends per day and adds a small “Sent with Brevo” line.', 'eagleeye' ); ?></p>
		<ol style="max-width:720px;line-height:1.7">
			<li><?php esc_html_e( 'Create a Brevo account and add the domain under Senders & IP → Domains.', 'eagleeye' ); ?></li>
			<li><?php esc_html_e( 'Publish Brevo’s DKIM, SPF and DMARC DNS records, then wait for the domain to show as authenticated.', 'eagleeye' ); ?></li>
			<li><?php esc_html_e( 'Create the sender address on that domain, then go to SMTP & API → API keys and create a transactional key.', 'eagleeye' ); ?></li>
			<li><?php esc_html_e( 'Add the key to /home/<account>/private/eagleeye-secrets.php, outside public_html (never paste it into a page, the database or the theme).', 'eagleeye' ); ?></li>
			<li><?php esc_html_e( 'Set From address above to that sender, put an inbox you can open in “Send the test to”, and send the test.', 'eagleeye' ); ?></li>
		</ol>
		<p><code>&lt;?php return array(<br>&nbsp;&nbsp;'resend_key' =&gt; '',<br>&nbsp;&nbsp;'brevo_key'&nbsp;&nbsp;=&gt; 'xkeysib-xxxxxxxx',<br>);</code></p>
		<p class="description"><?php esc_html_e( 'Brevo takes priority when its key is present. Resend can stay configured for later, when you own a domain.', 'eagleeye' ); ?></p>

		<h2 style="margin-top:32px"><?php esc_html_e( 'Resend (optional — requires a domain you own)', 'eagleeye' ); ?></h2>
		<ol style="max-width:720px;line-height:1.7">
			<li><?php esc_html_e( 'Buy or control a domain, add it in Resend → Domains, and publish the DNS records it gives you.', 'eagleeye' ); ?></li>
			<li><?php esc_html_e( 'Wait for the domain to show as verified, then keep using the existing API key.', 'eagleeye' ); ?></li>
			<li><?php esc_html_e( 'Set the From address to an address on that domain.', 'eagleeye' ); ?></li>
		</ol>
		<p><code>&lt;?php return array( 'resend_key' =&gt; 're_xxxxxxxx' );</code></p>
		<p class="description"><?php esc_html_e( 'On live hosting, define EAGLEEYE_RESEND_API_KEY in wp-config.php instead.', 'eagleeye' ); ?></p>
	</div>
	<?php
}
