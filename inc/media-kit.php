<?php
/**
 * Rate card and media-kit requests.
 *
 * Requests are stored as private records, priced from the rate card, emailed
 * to the requester with a reference and amount due, and reported to the
 * newsroom inbox. Payment is confirmed in admin.
 *
 * @package Eagle Eye
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Default advertising packages.
 *
 * @return array
 */
function eagleeye_rate_card() {
	$saved = get_option( 'eagleeye_rate_card', array() );

	$defaults = array(
		'leaderboard' => array(
			'label'  => __( 'Homepage leaderboard', 'eagleeye' ),
			'size'   => '970 × 250',
			'price'  => 450000,
			'period' => __( 'per month', 'eagleeye' ),
			'detail' => __( 'First placement under the breaking ticker, on every homepage visit.', 'eagleeye' ),
		),
		'sidebar'     => array(
			'label'  => __( 'Sidebar display', 'eagleeye' ),
			'size'   => '300 × 250',
			'price'  => 250000,
			'period' => __( 'per month', 'eagleeye' ),
			'detail' => __( 'Beside the homepage feed and every article, through the full read.', 'eagleeye' ),
		),
		'inarticle'   => array(
			'label'  => __( 'In-article unit', 'eagleeye' ),
			'size'   => '728 × 90',
			'price'  => 300000,
			'period' => __( 'per month', 'eagleeye' ),
			'detail' => __( 'Native position inside the body copy of every published story.', 'eagleeye' ),
		),
		'category'    => array(
			'label'  => __( 'Category sponsorship', 'eagleeye' ),
			'size'   => '970 × 250',
			'price'  => 200000,
			'period' => __( 'per month', 'eagleeye' ),
			'detail' => __( 'Own a section — Politics, Business, Sports or any other section archive.', 'eagleeye' ),
		),
		'newsletter'  => array(
			'label'  => __( 'Morning Brief sponsorship', 'eagleeye' ),
			'size'   => 'Native',
			'price'  => 180000,
			'period' => __( 'per send', 'eagleeye' ),
			'detail' => __( 'A dedicated line in the daily newsletter to opted-in subscribers.', 'eagleeye' ),
		),
	);

	foreach ( $defaults as $key => $package ) {
		if ( isset( $saved[ $key ] ) && is_array( $saved[ $key ] ) ) {
			$defaults[ $key ] = array_merge( $package, array_filter( $saved[ $key ], 'strlen' ) );
		}
	}

	return $defaults;
}

/**
 * Format a naira amount.
 *
 * @param int|float $amount Amount.
 * @return string
 */
function eagleeye_naira( $amount ) {
	return '₦' . number_format_i18n( (float) $amount );
}

/**
 * Bank details printed on every invoice.
 *
 * @return array
 */
function eagleeye_bank_details() {
	return array(
		'bank'           => (string) get_option( 'eagleeye_bank_name', '' ),
		'account_name'   => (string) get_option( 'eagleeye_bank_account_name', '' ),
		'account_number' => (string) get_option( 'eagleeye_bank_account_number', '' ),
		'note'           => (string) get_option( 'eagleeye_bank_note', __( 'Use the invoice reference as the transfer narration.', 'eagleeye' ) ),
	);
}

/**
 * Payment instructions block for invoices.
 *
 * @return string
 */
function eagleeye_bank_block() {
	$bank = eagleeye_bank_details();

	if ( ! $bank['account_number'] ) {
		return '<p style="font-size:13px;color:#6b665c">Bank details will be confirmed by the advertising team before payment.</p>';
	}

	return '<table role="presentation" cellpadding="7" cellspacing="0" style="border-collapse:collapse;font-size:15px;margin:8px 0 4px">'
		. '<tr><td style="color:#6b665c">Bank</td><td><strong>' . esc_html( $bank['bank'] ) . '</strong></td></tr>'
		. '<tr><td style="color:#6b665c">Account name</td><td><strong>' . esc_html( $bank['account_name'] ) . '</strong></td></tr>'
		. '<tr><td style="color:#6b665c">Account number</td><td><strong style="font-size:19px;letter-spacing:1px">' . esc_html( $bank['account_number'] ) . '</strong></td></tr>'
		. '</table>'
		. '<p style="font-size:13px;color:#6b665c">' . esc_html( $bank['note'] ) . '</p>';
}

/**
 * Link the client uses to tell the newsroom they have paid.
 *
 * @param int $post_id Request ID.
 * @return string
 */
function eagleeye_request_claim_url( $post_id ) {
	$token = (string) get_post_meta( $post_id, '_ee_token', true );

	return $token ? add_query_arg( 'ee_paid', rawurlencode( $token ), home_url( '/' ) ) : '';
}

/**
 * Update a request status once, with a timestamp and an internal note.
 *
 * @param int    $post_id Request ID.
 * @param string $status  New status.
 * @param string $note    Note for the record.
 */
function eagleeye_set_request_status( $post_id, $status, $note = '' ) {
	update_post_meta( $post_id, '_ee_status', $status );
	update_post_meta( $post_id, '_ee_status_at', current_time( 'mysql' ) );

	if ( $note ) {
		$log = get_post_meta( $post_id, '_ee_log', true );
		$log = is_array( $log ) ? $log : array();
		$log[] = current_time( 'mysql' ) . ' · ' . $note;
		update_post_meta( $post_id, '_ee_log', array_slice( $log, -20 ) );
	}
}

/**
 * Paystack secret, when the optional integration is turned on.
 *
 * @return string
 */
function eagleeye_paystack_secret() {
	return eagleeye_secret( 'paystack_secret' );
}

/**
 * The invoice email body for a stored request.
 *
 * @param int $post_id Request ID.
 * @return string
 */
function eagleeye_invoice_body( $post_id ) {
	$name      = (string) get_post_meta( $post_id, '_ee_name', true );
	$company   = (string) get_post_meta( $post_id, '_ee_company', true );
	$reference = (string) get_post_meta( $post_id, '_ee_reference', true );
	$amount    = (int) get_post_meta( $post_id, '_ee_amount', true );
	$label     = (string) get_post_meta( $post_id, '_ee_package_label', true );
	$period    = (string) get_post_meta( $post_id, '_ee_period', true );
	$token     = (string) get_post_meta( $post_id, '_ee_token', true );
	$claim_url = $token ? add_query_arg( 'ee_paid', rawurlencode( $token ), home_url( '/' ) ) : '';

	$body = '<p>Dear ' . esc_html( $name ) . ',</p>'
		. '<p>Thank you for choosing Eagle Eye. This is your invoice for <strong>' . esc_html( $company ) . '</strong>, reference <strong>' . esc_html( $reference ) . '</strong>.</p>'
		. '<table role="presentation" cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;font-size:15px;margin:18px 0">'
		. '<tr style="background:#f4f0e6"><th align="left" style="padding:10px">Placement</th><th align="right" style="padding:10px">Amount</th></tr>'
		. '<tr><td style="padding:10px;border-bottom:1px solid #e3ddd0">' . esc_html( $label ) . ( $period ? '<br><span style="font-size:12px;color:#8d877b">' . esc_html( $period ) . '</span>' : '' ) . '</td>'
		. '<td align="right" style="padding:10px;border-bottom:1px solid #e3ddd0">' . esc_html( eagleeye_naira( $amount ) ) . '</td></tr>'
		. '<tr><td style="padding:10px"><strong>Total due</strong></td><td align="right" style="padding:10px"><strong style="font-size:20px">' . esc_html( eagleeye_naira( $amount ) ) . '</strong></td></tr>'
		. '</table>'
		. '<h2 style="font-size:18px;margin:26px 0 6px">Pay by bank transfer</h2>'
		. eagleeye_bank_block();

	if ( $claim_url ) {
		$body .= '<p style="margin:26px 0"><a href="' . esc_url( $claim_url ) . '" style="background:#08080a;color:#f6c21c;padding:14px 22px;text-decoration:none;font-family:Arial,sans-serif;font-size:11px;letter-spacing:1px">I HAVE PAID — NOTIFY THE NEWSROOM</a></p>';
	}

	$phone = (string) get_theme_mod( 'eagleeye_phone', '' );
	$dial  = preg_replace( '/\D+/', '', $phone );

	if ( $dial && '0' === substr( $dial, 0, 1 ) ) {
		$dial = '234' . substr( $dial, 1 );
	}

	$body .= '<p>When you tell us you have paid, our team is alerted immediately. We confirm the transfer, agree your dates and request the banner creative, then the placement goes live. You will receive a payment confirmation from us.</p>'
		. ( $dial ? '<p style="font-size:13px;color:#6b665c">A photo of the transfer receipt is the quickest confirmation: <a href="https://wa.me/' . esc_attr( $dial ) . '" style="color:#08080a">WhatsApp ' . esc_html( $phone ) . '</a>.</p>' : '' )
		. '<p style="font-size:13px;color:#6b665c">Keep your reference <strong>' . esc_html( $reference ) . '</strong> on the transfer. If you need to add anything, reply to this email' . ( $phone ? ' or call ' . esc_html( $phone ) : '' ) . '.</p>'
		. '<p>— The Eagle Eye advertising team</p>';

	return $body;
}

/**
 * Register request records.
 */
function eagleeye_register_request_type() {
	register_post_type( 'ee_request', array(
		'labels' => array(
			'name'          => __( 'Media kit requests', 'eagleeye' ),
			'singular_name' => __( 'Media kit request', 'eagleeye' ),
			'menu_name'     => __( 'Media kit', 'eagleeye' ),
			'edit_item'     => __( 'Media kit request', 'eagleeye' ),
			'all_items'     => __( 'Media kit requests', 'eagleeye' ),
			'not_found'     => __( 'No requests yet.', 'eagleeye' ),
		),
		'public'          => false,
		'show_ui'         => true,
		'show_in_menu'    => 'eagleeye',
		'supports'        => array( 'title' ),
		'capability_type' => 'post',
		'map_meta_cap'    => true,
		'menu_icon'       => 'dashicons-email-alt',
		'rewrite'         => false,
	) );
}
add_action( 'init', 'eagleeye_register_request_type' );

/**
 * Handle a media-kit request.
 */
function eagleeye_handle_media_kit() {
	$redirect = wp_get_referer() ? wp_get_referer() : home_url( '/advertise-with-us/' );

	if ( ! isset( $_POST['eagleeye_kit_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['eagleeye_kit_nonce'] ) ), 'eagleeye_media_kit' ) ) {
		wp_safe_redirect( add_query_arg( 'ee_kit', 'invalid', $redirect ) . '#media-kit' );
		exit;
	}

	if ( ! empty( $_POST['ee_website'] ) ) {
		wp_safe_redirect( add_query_arg( 'ee_kit', 'sent', $redirect ) . '#media-kit' );
		exit;
	}

	$company  = isset( $_POST['ee_company'] ) ? sanitize_text_field( wp_unslash( $_POST['ee_company'] ) ) : '';
	$name     = isset( $_POST['ee_name'] ) ? sanitize_text_field( wp_unslash( $_POST['ee_name'] ) ) : '';
	$email    = isset( $_POST['ee_email'] ) ? sanitize_email( wp_unslash( $_POST['ee_email'] ) ) : '';
	$phone    = isset( $_POST['ee_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['ee_phone'] ) ) : '';
	$package  = isset( $_POST['ee_package'] ) ? sanitize_key( wp_unslash( $_POST['ee_package'] ) ) : '';
	$budget   = isset( $_POST['ee_budget'] ) ? sanitize_text_field( wp_unslash( $_POST['ee_budget'] ) ) : '';
	$start    = isset( $_POST['ee_start'] ) ? sanitize_text_field( wp_unslash( $_POST['ee_start'] ) ) : '';
	$message  = isset( $_POST['ee_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ee_message'] ) ) : '';

	$rates   = eagleeye_rate_card();
	$chosen  = $rates[ $package ] ?? null;
	$amount   = $chosen ? (int) $chosen['price'] : 0;
	$label    = $chosen ? $chosen['label'] : __( 'Custom package', 'eagleeye' );

	if ( ! $company || ! $name || ! is_email( $email ) ) {
		wp_safe_redirect( add_query_arg( 'ee_kit', 'invalid', $redirect ) . '#media-kit' );
		exit;
	}

	// Idempotency: a double click or a browser retry must not create a second
	// request or a second invoice for the same brand, package and address.
	$lock = 'ee_kit_' . md5( strtolower( $email ) . '|' . $package . '|' . strtolower( $company ) );

	if ( get_transient( $lock ) ) {
		wp_safe_redirect( add_query_arg( 'ee_kit', 'sent', $redirect ) . '#media-kit' );
		exit;
	}

	set_transient( $lock, 1, 2 * MINUTE_IN_SECONDS );

	$reference = 'EE-' . gmdate( 'ymd' ) . '-' . strtoupper( wp_generate_password( 4, false, false ) );
	$token     = wp_generate_password( 40, false, false );
	$subject   = sprintf( '%s — %s', $company, $label );

	$post_id = wp_insert_post( array(
		'post_type'   => 'ee_request',
		'post_status' => 'private',
		'post_title'  => $subject,
	), true );

	if ( is_wp_error( $post_id ) ) {
		wp_safe_redirect( add_query_arg( 'ee_kit', 'error', $redirect ) . '#media-kit' );
		exit;
	}

	$meta = array(
		'_ee_company'   => $company,
		'_ee_name'      => $name,
		'_ee_email'     => $email,
		'_ee_phone'     => $phone,
		'_ee_package'   => $package,
		'_ee_package_label' => $label,
		'_ee_period'    => $chosen ? $chosen['period'] : '',
		'_ee_amount'    => $amount,
		'_ee_budget'    => $budget,
		'_ee_start'     => $start,
		'_ee_message'   => $message,
		'_ee_reference' => $reference,
		'_ee_token'     => $token,
		'_ee_status'    => 'invoiced',
	);

	foreach ( $meta as $key => $value ) {
		update_post_meta( $post_id, $key, $value );
	}

	$admins = array_filter( array( eagleeye_admin_email(), get_option( 'admin_email' ) ) );

	$admin_body = '<p><strong>' . esc_html( $company ) . '</strong> has requested the media kit.</p>'
		. '<table cellpadding="6" cellspacing="0" style="border-collapse:collapse;font-size:15px">'
		. '<tr><td><strong>Contact</strong></td><td>' . esc_html( $name ) . '</td></tr>'
		. '<tr><td><strong>Email</strong></td><td>' . esc_html( $email ) . '</td></tr>'
		. '<tr><td><strong>Phone</strong></td><td>' . esc_html( $phone ? $phone : '—' ) . '</td></tr>'
		. '<tr><td><strong>Package</strong></td><td>' . esc_html( $label ) . '</td></tr>'
		. '<tr><td><strong>Quoted amount</strong></td><td>' . esc_html( eagleeye_naira( $amount ) ) . '</td></tr>'
		. '<tr><td><strong>Budget note</strong></td><td>' . esc_html( $budget ? $budget : '—' ) . '</td></tr>'
		. '<tr><td><strong>Preferred start</strong></td><td>' . esc_html( $start ? $start : '—' ) . '</td></tr>'
		. '<tr><td><strong>Reference</strong></td><td>' . esc_html( $reference ) . '</td></tr>'
		. '</table>'
		. '<p style="margin-top:16px"><strong>Brief</strong><br>' . nl2br( esc_html( $message ? $message : '—' ) ) . '</p>'
		. '<p style="margin-top:18px;font-size:13px;color:#6b665c">The client can mark the transfer as sent from the invoice. You will be emailed when they do, then mark it paid in the request record.</p>';

	foreach ( $admins as $admin ) {
		eagleeye_send_email(
			$admin,
			sprintf( __( 'Media kit request: %s (%s)', 'eagleeye' ), $company, $reference ),
			eagleeye_email_template( __( 'New media kit request', 'eagleeye' ), $admin_body, '<p style="margin-top:20px"><a href="' . esc_url( admin_url( 'post.php?post=' . $post_id . '&action=edit' ) ) . '" style="background:#08080a;color:#f6c21c;padding:12px 18px;text-decoration:none;font-family:Arial,sans-serif;font-size:12px">Open the request</a></p>' ),
			$email
		);
	}

	$requester_body = eagleeye_invoice_body( $post_id );

	eagleeye_send_email(
		$email,
		sprintf( __( 'Invoice %s — Eagle Eye advertising', 'eagleeye' ), $reference ),
		eagleeye_email_template( __( 'Your invoice', 'eagleeye' ), $requester_body ),
		eagleeye_admin_email()
	);

	wp_safe_redirect( add_query_arg( array( 'ee_kit' => 'sent', 'ref' => $reference, 'invoice' => $token ), $redirect ) . '#invoice' );
	exit;
}
add_action( 'admin_post_nopriv_eagleeye_media_kit', 'eagleeye_handle_media_kit' );
add_action( 'admin_post_eagleeye_media_kit', 'eagleeye_handle_media_kit' );

/**
 * Client says they have paid. Record it once and alert the newsroom.
 */
function eagleeye_handle_payment_claim() {
	if ( ! isset( $_GET['ee_paid'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}

	$token = sanitize_text_field( wp_unslash( $_GET['ee_paid'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$page  = get_page_by_path( 'advertise-with-us' );
	$back  = $page ? get_permalink( $page ) : home_url( '/' );

	$found = $token ? get_posts( array(
		'post_type'      => 'ee_request',
		'post_status'    => 'private',
		'numberposts'    => 1,
		'meta_key'       => '_ee_token',
		'meta_value'     => $token,
	) ) : array();

	if ( ! $found ) {
		wp_safe_redirect( add_query_arg( 'ee_kit', 'invalid', $back ) . '#media-kit' );
		exit;
	}

	$request_id = (int) $found[0]->ID;
	$status     = (string) get_post_meta( $request_id, '_ee_status', true );

	// Idempotent: refreshing the link must not duplicate notes or emails.
	if ( in_array( $status, array( 'claimed', 'paid', 'completed' ), true ) ) {
		wp_safe_redirect( add_query_arg( 'ee_kit', 'claimed', $back ) . '#media-kit' );
		exit;
	}

	eagleeye_set_request_status( $request_id, 'claimed', __( 'Client used the “I have paid” link.', 'eagleeye' ) );
	update_post_meta( $request_id, '_ee_claimed_at', current_time( 'mysql' ) );

	$company   = (string) get_post_meta( $request_id, '_ee_company', true );
	$reference = (string) get_post_meta( $request_id, '_ee_reference', true );
	$amount    = (int) get_post_meta( $request_id, '_ee_amount', true );
	$client    = (string) get_post_meta( $request_id, '_ee_email', true );

	eagleeye_send_email(
		eagleeye_admin_email(),
		sprintf( __( 'Payment claimed: %s (%s)', 'eagleeye' ), $company, $reference ),
		eagleeye_email_template(
			__( 'A client says they have paid', 'eagleeye' ),
			'<p><strong>' . esc_html( $company ) . '</strong> has marked invoice <strong>' . esc_html( $reference ) . '</strong> (' . esc_html( eagleeye_naira( $amount ) ) . ') as paid.</p>'
			. '<p>Check the transfer, then open the request and mark it paid. The client is waiting for a confirmation and the next steps for their creative.</p>',
			'<p style="margin-top:20px"><a href="' . esc_url( admin_url( 'post.php?post=' . $request_id . '&action=edit' ) ) . '" style="background:#08080a;color:#f6c21c;padding:12px 18px;text-decoration:none;font-family:Arial,sans-serif;font-size:12px">Open the booking</a></p>'
		)
	);

	if ( $client ) {
		eagleeye_send_email(
			$client,
			sprintf( __( 'We have your payment notice (%s)', 'eagleeye' ), $reference ),
			eagleeye_email_template(
				__( 'Thank you — we are checking it now', 'eagleeye' ),
				'<p>Dear ' . esc_html( (string) get_post_meta( $request_id, '_ee_name', true ) ) . ',</p>'
				. '<p>We have your notice for invoice <strong>' . esc_html( $reference ) . '</strong>. Our team is confirming the transfer and will email you a receipt, your confirmed dates and the artwork specifications.</p>'
				. '<p>If you have a transfer receipt, reply to this email with it and we will match it faster.</p>'
			)
		);
	}

	wp_safe_redirect( add_query_arg( array( 'ee_kit' => 'claimed', 'ref' => $reference, 'invoice' => $token ), $back ) . '#invoice' );
	exit;
}
add_action( 'init', 'eagleeye_handle_payment_claim', 20 );

/**
 * Paystack webhook. Dormant until the integration is switched on; when active
 * it verifies the signature and marks the matching invoice paid exactly once.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function eagleeye_paystack_webhook( $request ) {
	if ( ! get_option( 'eagleeye_paystack_enabled' ) ) {
		return new WP_REST_Response( array( 'error' => 'Paystack is not enabled.' ), 501 );
	}

	$secret = eagleeye_paystack_secret();
	$sig    = (string) $request->get_header( 'x-paystack-signature' );
	$body   = $request->get_body();

	if ( ! $secret || ! $sig || ! hash_equals( hash_hmac( 'sha512', $body, $secret ), $sig ) ) {
		return new WP_REST_Response( array( 'error' => 'Invalid signature.' ), 401 );
	}

	$event = json_decode( $body, true );

	if ( ! is_array( $event ) || ( $event['event'] ?? '' ) !== 'charge.success' ) {
		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	$reference = sanitize_text_field( (string) ( $event['data']['metadata']['ee_reference'] ?? '' ) );

	if ( ! $reference ) {
		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	$found = get_posts( array(
		'post_type'   => 'ee_request',
		'post_status' => 'private',
		'numberposts' => 1,
		'meta_key'    => '_ee_reference',
		'meta_value'  => $reference,
	) );

	if ( ! $found ) {
		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	$request_id = (int) $found[0]->ID;

	if ( 'paid' === get_post_meta( $request_id, '_ee_status', true ) ) {
		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	eagleeye_confirm_request_payment( $request_id );
	update_post_meta( $request_id, '_ee_paystack_reference', sanitize_text_field( (string) ( $event['data']['reference'] ?? '' ) ) );

	return new WP_REST_Response( array( 'received' => true ), 200 );
}

/**
 * Register the dormant Paystack endpoint.
 */
function eagleeye_register_paystack_webhook() {
	register_rest_route( 'eagleeye/v1', '/paystack', array(
		'methods'             => 'POST',
		'callback'            => 'eagleeye_paystack_webhook',
		'permission_callback' => '__return_true',
	) );
}
add_action( 'rest_api_init', 'eagleeye_register_paystack_webhook' );

/**
 * Feedback for the media-kit form.
 *
 * @return array{type:string,text:string}
 */
function eagleeye_media_kit_message() {
	if ( ! isset( $_GET['ee_kit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return array( 'type' => '', 'text' => '' );
	}

	$state = sanitize_key( wp_unslash( $_GET['ee_kit'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$ref   = isset( $_GET['ref'] ) ? sanitize_text_field( wp_unslash( $_GET['ref'] ) ) : '';

	if ( 'sent' === $state ) {
		return array(
			'type' => 'success',
			'text' => sprintf(
				/* translators: %s: reference. */__('Your invoice%s is ready. Keep the reference for your bank transfer.', 'eagleeye'),
				$ref ? ' — ' . $ref : ''
			),
		);
	}

	if ( 'claimed' === $state ) {
		return array(
			'type' => 'success',
			'text' => sprintf(
				/* translators: %s: reference. */
				__( 'Thank you%s. The newsroom has been alerted and will confirm your transfer.', 'eagleeye' ),
				$ref ? ' — ' . $ref : ''
			),
		);
	}

	if ( 'invalid' === $state ) {
		return array( 'type' => 'error', 'text' => __( 'Please complete your company, name and a valid email address.', 'eagleeye' ) );
	}

	if ( 'error' === $state ) {
		$contact_email = get_theme_mod( 'eagleeye_email', '' );
		$contact_text  = $contact_email
			? sprintf( __( 'Something went wrong saving your request. Please try again or email %s.', 'eagleeye' ), $contact_email )
			: __( 'Something went wrong saving your request. Please try again, or call the number on the Advertise page.', 'eagleeye' );

		return array( 'type' => 'error', 'text' => $contact_text );
	}

	return array( 'type' => '', 'text' => '' );
}

/**
 * Rate card + request settings.
 */
function eagleeye_rate_card_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( isset( $_POST['eagleeye_rate_card'] ) && check_admin_referer( 'eagleeye_rate_card' ) ) {
		$rates = array();

		foreach ( eagleeye_rate_card() as $key => $package ) {
			$rates[ $key ] = array(
				'label'  => isset( $_POST['rate'][ $key ]['label'] ) ? sanitize_text_field( wp_unslash( $_POST['rate'][ $key ]['label'] ) ) : $package['label'],
				'price'  => isset( $_POST['rate'][ $key ]['price'] ) ? absint( $_POST['rate'][ $key ]['price'] ) : $package['price'],
				'period' => isset( $_POST['rate'][ $key ]['period'] ) ? sanitize_text_field( wp_unslash( $_POST['rate'][ $key ]['period'] ) ) : $package['period'],
			);
		}

		update_option( 'eagleeye_rate_card', $rates );
		echo '<div class="notice notice-success"><p>' . esc_html__( 'Rate card updated.', 'eagleeye' ) . '</p></div>';
	}

	if ( isset( $_POST['eagleeye_payment_settings'] ) && check_admin_referer( 'eagleeye_payment_settings' ) ) {
		update_option( 'eagleeye_bank_name', sanitize_text_field( wp_unslash( $_POST['bank_name'] ) ) );
		update_option( 'eagleeye_bank_account_name', sanitize_text_field( wp_unslash( $_POST['bank_account_name'] ) ) );
		update_option( 'eagleeye_bank_account_number', sanitize_text_field( wp_unslash( $_POST['bank_account_number'] ) ) );
		update_option( 'eagleeye_bank_note', sanitize_textarea_field( wp_unslash( $_POST['bank_note'] ) ) );
		echo '<div class="notice notice-success"><p>' . esc_html__( 'Payment details saved. New invoices use them immediately.', 'eagleeye' ) . '</p></div>';
	}

	$rates = eagleeye_rate_card();
	$bank  = eagleeye_bank_details();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Rate card', 'eagleeye' ); ?></h1>
		<p><?php esc_html_e( 'These prices appear on the Advertise page and are used to quote every media-kit request automatically.', 'eagleeye' ); ?></p>

		<form method="post">
			<?php wp_nonce_field( 'eagleeye_rate_card' ); ?>
			<table class="widefat striped" style="max-width:900px">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Package', 'eagleeye' ); ?></th>
						<th><?php esc_html_e( 'Price (₦)', 'eagleeye' ); ?></th>
						<th><?php esc_html_e( 'Billing period', 'eagleeye' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rates as $key => $package ) : ?>
						<tr>
							<td><input type="text" name="rate[<?php echo esc_attr( $key ); ?>][label]" value="<?php echo esc_attr( $package['label'] ); ?>" style="width:100%"></td>
							<td><input type="number" step="1000" min="0" name="rate[<?php echo esc_attr( $key ); ?>][price]" value="<?php echo esc_attr( $package['price'] ); ?>" style="width:140px"></td>
							<td><input type="text" name="rate[<?php echo esc_attr( $key ); ?>][period]" value="<?php echo esc_attr( $package['period'] ); ?>" style="width:170px"></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p><button class="button button-primary" name="eagleeye_rate_card" value="1"><?php esc_html_e( 'Save rate card', 'eagleeye' ); ?></button></p>
		</form>

		<h2 style="margin-top:34px"><?php esc_html_e( 'Payment details', 'eagleeye' ); ?></h2>
		<p><?php esc_html_e( 'These bank details are printed on every client invoice. Leave the account number empty until the account is ready and the invoice will ask the client to contact the team.', 'eagleeye' ); ?></p>

		<form method="post">
			<?php wp_nonce_field( 'eagleeye_payment_settings' ); ?>
			<table class="form-table" style="max-width:820px">
				<tr>
					<th><label for="bank_name"><?php esc_html_e( 'Bank', 'eagleeye' ); ?></label></th>
					<td><input type="text" class="regular-text" id="bank_name" name="bank_name" value="<?php echo esc_attr( $bank['bank'] ); ?>" placeholder="e.g. Zenith Bank"></td>
				</tr>
				<tr>
					<th><label for="bank_account_name"><?php esc_html_e( 'Account name', 'eagleeye' ); ?></label></th>
					<td><input type="text" class="regular-text" id="bank_account_name" name="bank_account_name" value="<?php echo esc_attr( $bank['account_name'] ); ?>" placeholder="Eagle Eye Media Ltd"></td>
				</tr>
				<tr>
					<th><label for="bank_account_number"><?php esc_html_e( 'Account number', 'eagleeye' ); ?></label></th>
					<td><input type="text" class="regular-text" id="bank_account_number" name="bank_account_number" value="<?php echo esc_attr( $bank['account_number'] ); ?>" inputmode="numeric" placeholder="0123456789"></td>
				</tr>
				<tr>
					<th><label for="bank_note"><?php esc_html_e( 'Payment note', 'eagleeye' ); ?></label></th>
					<td><textarea id="bank_note" name="bank_note" rows="2" class="large-text"><?php echo esc_textarea( $bank['note'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Shown under the account details, for example: use your reference as the transfer narration.', 'eagleeye' ); ?></p></td>
				</tr>
			</table>
			<p><button class="button button-primary" name="eagleeye_payment_settings" value="1"><?php esc_html_e( 'Save payment details', 'eagleeye' ); ?></button></p>
		</form>

		<div class="notice notice-info inline" style="max-width:820px;margin:18px 0 0">
			<p><strong><?php esc_html_e( 'Paystack is wired but switched off.', 'eagleeye' ); ?></strong>
				<?php esc_html_e( 'Card payment can be added later without changing the booking flow. The signed webhook endpoint is reserved at:', 'eagleeye' ); ?>
				<code><?php echo esc_html( rest_url( 'eagleeye/v1/paystack' ) ); ?></code></p>
		</div>

		<h2><?php esc_html_e( 'Media kit requests', 'eagleeye' ); ?></h2>
		<p><a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=ee_request' ) ); ?>"><?php esc_html_e( 'Open the request inbox', 'eagleeye' ); ?></a></p>
		<p class="description"><?php esc_html_e( 'Each request stores the company, contact, chosen package and quoted amount. Mark it paid once the transfer clears.', 'eagleeye' ); ?></p>
	</div>
	<?php
}

/**
 * Request inbox columns.
 *
 * @param array $columns Columns.
 * @return array
 */
function eagleeye_request_columns( $columns ) {
	return array(
		'cb'            => $columns['cb'],
		'title'         => __( 'Company', 'eagleeye' ),
		'ee_contact'    => __( 'Contact', 'eagleeye' ),
		'ee_package'    => __( 'Package', 'eagleeye' ),
		'ee_amount'     => __( 'Quoted', 'eagleeye' ),
		'ee_status'     => __( 'Status', 'eagleeye' ),
		'ee_reference'  => __( 'Reference', 'eagleeye' ),
		'date'          => __( 'Received', 'eagleeye' ),
	);
}
add_filter( 'manage_ee_request_posts_columns', 'eagleeye_request_columns' );

/**
 * Request inbox values.
 *
 * @param string $column  Column.
 * @param int    $post_id Post.
 */
function eagleeye_request_column_content( $column, $post_id ) {
	switch ( $column ) {
		case 'ee_contact':
			echo esc_html( get_post_meta( $post_id, '_ee_name', true ) );
			echo '<br><a href="mailto:' . esc_attr( get_post_meta( $post_id, '_ee_email', true ) ) . '">' . esc_html( get_post_meta( $post_id, '_ee_email', true ) ) . '</a>';
			$phone = get_post_meta( $post_id, '_ee_phone', true );
			if ( $phone ) {
				echo '<br>' . esc_html( $phone );
			}
			break;

		case 'ee_package':
			echo esc_html( get_post_meta( $post_id, '_ee_package_label', true ) ?: '—' );
			break;

		case 'ee_amount':
			$amount = (int) get_post_meta( $post_id, '_ee_amount', true );
			echo $amount ? esc_html( eagleeye_naira( $amount ) ) : '—';
			break;

		case 'ee_status':
			$status = get_post_meta( $post_id, '_ee_status', true );
			$labels = array(
				'new'       => array( __( 'New', 'eagleeye' ), '#a15c00' ),
				'invoiced'  => array( __( 'Invoice sent', 'eagleeye' ), '#1c4f9c' ),
				'quoted'    => array( __( 'Quoted', 'eagleeye' ), '#1c4f9c' ),
				'claimed'   => array( __( 'Payment claimed', 'eagleeye' ), '#8a4b00' ),
				'paid'      => array( __( 'Paid', 'eagleeye' ), '#0a7d33' ),
				'completed' => array( __( 'Completed', 'eagleeye' ), '#0a7d33' ),
			);
			$meta = $labels[ $status ] ?? array( ucfirst( $status ), '#6b665c' );
			printf( '<strong style="color:%s">%s</strong>', esc_attr( $meta[1] ), esc_html( $meta[0] ) );
			break;

		case 'ee_reference':
			echo esc_html( get_post_meta( $post_id, '_ee_reference', true ) ?: '—' );
			break;
	}
}
add_action( 'manage_ee_request_posts_custom_column', 'eagleeye_request_column_content', 10, 2 );

/**
 * Confirm a payment once and send the client the next steps.
 *
 * @param int $post_id Request ID.
 * @return bool True when this call changed the status.
 */
function eagleeye_confirm_request_payment( $post_id ) {
	if ( 'paid' === get_post_meta( $post_id, '_ee_status', true ) ) {
		return false;
	}

	eagleeye_set_request_status( $post_id, 'paid', __( 'Payment confirmed by the newsroom.', 'eagleeye' ) );

	$name      = (string) get_post_meta( $post_id, '_ee_company', true );
	$contact   = (string) get_post_meta( $post_id, '_ee_name', true );
	$email     = (string) get_post_meta( $post_id, '_ee_email', true );
	$reference = (string) get_post_meta( $post_id, '_ee_reference', true );
	$amount    = (int) get_post_meta( $post_id, '_ee_amount', true );
	$package   = (string) get_post_meta( $post_id, '_ee_package_label', true );
	$start     = (string) get_post_meta( $post_id, '_ee_start', true );

	if ( $email ) {
		eagleeye_send_email(
			$email,
			sprintf( __( 'Payment confirmed — %s', 'eagleeye' ), $reference ),
			eagleeye_email_template(
				__( 'You are booked with Eagle Eye', 'eagleeye' ),
				'<p>Dear ' . esc_html( $contact ) . ',</p>'
				. '<p>We have confirmed <strong>' . esc_html( eagleeye_naira( $amount ) ) . '</strong> for <strong>' . esc_html( $name ) . '</strong>. Your booking (<strong>' . esc_html( $reference ) . '</strong>) is now paid and reserved.</p>'
				. '<table cellpadding="7" cellspacing="0" style="font-size:15px;margin:16px 0">'
				. '<tr><td style="color:#6b665c">Placement</td><td><strong>' . esc_html( $package ) . '</strong></td></tr>'
				. '<tr><td style="color:#6b665c">Preferred start</td><td><strong>' . esc_html( $start ? $start : __( 'To be agreed', 'eagleeye' ) ) . '</strong></td></tr>'
				. '</table>'
				. '<p><strong>Next step:</strong> reply with your banner artwork (PNG or JPG, no animation) and the exact start date. Our team will check the specification, schedule the placement and send you a live link when it is on the site.</p>'
				. '<p>If you have any questions, reply to this email or call ' . esc_html( get_theme_mod( 'eagleeye_phone', '07017138501' ) ) . '.</p>'
				. '<p>— The Eagle Eye advertising team</p>'
			)
		);
	}

	return true;
}

/**
 * Booking and payment panel on a request.
 *
 * @param WP_Post $post Request.
 */
function eagleeye_request_payment_box( $post ) {
	wp_nonce_field( 'eagleeye_request_payment', 'eagleeye_request_payment_nonce' );

	$status = (string) get_post_meta( $post->ID, '_ee_status', true );
	$status = $status ? $status : 'new';
	$labels = array(
		'new'       => __( 'New request', 'eagleeye' ),
		'invoiced'  => __( 'Invoice sent', 'eagleeye' ),
		'claimed'   => __( 'Client says paid', 'eagleeye' ),
		'paid'      => __( 'Paid — booking confirmed', 'eagleeye' ),
		'completed' => __( 'Campaign completed', 'eagleeye' ),
	);
	$claim  = eagleeye_request_claim_url( $post->ID );
	$amount = (int) get_post_meta( $post->ID, '_ee_amount', true );
	$ref    = (string) get_post_meta( $post->ID, '_ee_reference', true );
	?>
	<p><label for="ee_request_status"><strong><?php esc_html_e( 'Booking status', 'eagleeye' ); ?></strong></label></p>
	<?php if ( current_user_can( 'manage_eagleeye_ad_approval' ) ) : ?>
		<select name="ee_request_status" id="ee_request_status" class="widefat">
			<?php foreach ( $labels as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status, $key ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<p class="description"><?php esc_html_e( 'Choosing “Paid” emails the client a confirmation and the artwork instructions, once.', 'eagleeye' ); ?></p>
	<?php else : ?>
		<p><strong><?php echo esc_html( $labels[ $status ] ?? $labels['new'] ); ?></strong></p>
		<p class="description"><?php esc_html_e( 'An authorised administrator confirms payment and sends the client’s receipt.', 'eagleeye' ); ?></p>
	<?php endif; ?>

	<p style="margin:14px 0 0"><strong><?php echo esc_html( eagleeye_naira( $amount ) ); ?></strong><br>
		<span class="description"><?php echo esc_html( $ref ? $ref : __( 'No reference', 'eagleeye' ) ); ?></span></p>

	<?php if ( $claim ) : ?>
		<p><a href="<?php echo esc_url( $claim ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open the client payment link', 'eagleeye' ); ?></a></p>
	<?php endif; ?>

	<?php if ( current_user_can( 'manage_eagleeye_ad_approval' ) && 'paid' !== $status ) : ?>
		<p><a class="button button-primary" style="width:100%;text-align:center" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=eagleeye_mark_request_paid&request=' . $post->ID ), 'eagleeye_mark_paid_' . $post->ID ) ); ?>"><?php esc_html_e( 'Mark payment received', 'eagleeye' ); ?></a></p>
	<?php endif; ?>

	<?php if ( current_user_can( 'manage_eagleeye_ad_approval' ) ) : ?>
		<p><a class="button" style="width:100%;text-align:center" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=eagleeye_resend_invoice&request=' . $post->ID ), 'eagleeye_resend_invoice_' . $post->ID ) ); ?>"><?php esc_html_e( 'Email the invoice again', 'eagleeye' ); ?></a></p>
	<?php endif; ?>

	<?php
	$log = (array) get_post_meta( $post->ID, '_ee_log', true );
	if ( $log ) :
		?>
		<details style="margin-top:12px"><summary><?php esc_html_e( 'History', 'eagleeye' ); ?></summary>
			<ul style="font-size:12px;color:#5a554b;margin:8px 0 0 16px;list-style:disc">
				<?php foreach ( array_reverse( $log ) as $line ) : ?>
					<li><?php echo esc_html( $line ); ?></li>
				<?php endforeach; ?>
			</ul>
		</details>
		<?php
	endif;
}

/**
 * Register the booking panel.
 */
function eagleeye_add_request_payment_box() {
	add_meta_box( 'ee_request_payment', __( 'Booking & payment', 'eagleeye' ), 'eagleeye_request_payment_box', 'ee_request', 'side', 'high' );
}
add_action( 'add_meta_boxes', 'eagleeye_add_request_payment_box' );

/**
 * Save the booking status from the request screen.
 *
 * @param int $post_id Request ID.
 */
function eagleeye_save_request_payment( $post_id ) {
	if ( ! isset( $_POST['eagleeye_request_payment_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['eagleeye_request_payment_nonce'] ) ), 'eagleeye_request_payment' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_eagleeye_ad_approval' ) ) {
		return;
	}

	$new = isset( $_POST['ee_request_status'] ) ? sanitize_key( wp_unslash( $_POST['ee_request_status'] ) ) : '';
	$old = (string) get_post_meta( $post_id, '_ee_status', true );

	if ( ! $new || $new === $old ) {
		return;
	}

	if ( 'paid' === $new ) {
		eagleeye_confirm_request_payment( $post_id );
		return;
	}

	eagleeye_set_request_status( $post_id, $new, __( 'Status changed in admin.', 'eagleeye' ) );
}
add_action( 'save_post_ee_request', 'eagleeye_save_request_payment' );

/**
 * “Mark payment received” row action.
 *
 * @param array   $actions Actions.
 * @param WP_Post $post    Post.
 * @return array
 */
function eagleeye_request_row_actions( $actions, $post ) {
	if ( 'ee_request' !== $post->post_type || ! current_user_can( 'manage_eagleeye_ad_approval' ) || 'paid' === get_post_meta( $post->ID, '_ee_status', true ) ) {
		return $actions;
	}

	$actions['ee_mark_paid'] = '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=eagleeye_mark_request_paid&request=' . $post->ID ), 'eagleeye_mark_paid_' . $post->ID ) ) . '">' . esc_html__( 'Mark paid', 'eagleeye' ) . '</a>';

	return $actions;
}
add_filter( 'post_row_actions', 'eagleeye_request_row_actions', 10, 2 );

/**
 * Handle the row action.
 */
function eagleeye_handle_mark_request_paid() {
	$request_id = isset( $_GET['request'] ) ? absint( $_GET['request'] ) : 0;

	if ( ! $request_id || ! current_user_can( 'manage_eagleeye_ad_approval' ) || ! current_user_can( 'edit_post', $request_id ) ) {
		wp_die( esc_html__( 'Only an authorised newsroom administrator can confirm advertiser payment.', 'eagleeye' ) );
	}

	check_admin_referer( 'eagleeye_mark_paid_' . $request_id );
	eagleeye_confirm_request_payment( $request_id );

	wp_safe_redirect( admin_url( 'edit.php?post_type=ee_request&paid=1' ) );
	exit;
}
add_action( 'admin_post_eagleeye_mark_request_paid', 'eagleeye_handle_mark_request_paid' );

/**
 * Resend the invoice to the client.
 */
function eagleeye_handle_resend_invoice() {
	$request_id = isset( $_GET['request'] ) ? absint( $_GET['request'] ) : 0;

	if ( ! $request_id || ! current_user_can( 'manage_eagleeye_ad_approval' ) || ! current_user_can( 'edit_post', $request_id ) ) {
		wp_die( esc_html__( 'Only an authorised newsroom administrator can resend advertiser invoices.', 'eagleeye' ) );
	}

	check_admin_referer( 'eagleeye_resend_invoice_' . $request_id );

	$email     = (string) get_post_meta( $request_id, '_ee_email', true );
	$reference = (string) get_post_meta( $request_id, '_ee_reference', true );
	$sent      = false;

	if ( $email ) {
		$sent = eagleeye_send_email(
			$email,
			sprintf( __( 'Invoice %s — Eagle Eye advertising', 'eagleeye' ), $reference ),
			eagleeye_email_template( __( 'Your invoice', 'eagleeye' ), eagleeye_invoice_body( $request_id ) ),
			eagleeye_admin_email()
		);
	}

	wp_safe_redirect( add_query_arg( 'invoice', $sent ? 'resent' : 'failed', admin_url( 'post.php?post=' . $request_id . '&action=edit' ) ) );
	exit;
}
add_action( 'admin_post_eagleeye_resend_invoice', 'eagleeye_handle_resend_invoice' );

/**
 * Show the client's brief and contact details beside the booking panel.
 *
 * @param WP_Post $post Request.
 */
function eagleeye_request_details_box( $post ) {
	$fields = array(
		__( 'Company', 'eagleeye' )       => get_post_meta( $post->ID, '_ee_company', true ),
		__( 'Contact', 'eagleeye' )       => get_post_meta( $post->ID, '_ee_name', true ),
		__( 'Email', 'eagleeye' )         => get_post_meta( $post->ID, '_ee_email', true ),
		__( 'Phone', 'eagleeye' )         => get_post_meta( $post->ID, '_ee_phone', true ),
		__( 'Placement', 'eagleeye' )     => get_post_meta( $post->ID, '_ee_package_label', true ),
		__( 'Amount', 'eagleeye' )        => eagleeye_naira( (int) get_post_meta( $post->ID, '_ee_amount', true ) ),
		__( 'Preferred start', 'eagleeye' ) => get_post_meta( $post->ID, '_ee_start', true ),
		__( 'Budget note', 'eagleeye' )   => get_post_meta( $post->ID, '_ee_budget', true ),
		__( 'Reference', 'eagleeye' )     => get_post_meta( $post->ID, '_ee_reference', true ),
	);
	?>
	<table class="widefat striped" style="border:0;max-width:760px">
		<tbody>
			<?php foreach ( $fields as $label => $value ) : ?>
				<tr>
					<th style="width:170px"><?php echo esc_html( $label ); ?></th>
					<td><?php echo esc_html( $value ? $value : '—' ); ?></td>
				</tr>
			<?php endforeach; ?>
			<tr>
				<th><?php esc_html_e( 'Campaign brief', 'eagleeye' ); ?></th>
				<td><?php echo nl2br( esc_html( (string) get_post_meta( $post->ID, '_ee_message', true ) ) ); ?></td>
			</tr>
		</tbody>
	</table>
	<?php
}

/**
 * Register the details panel.
 */
function eagleeye_add_request_details_box() {
	add_meta_box( 'ee_request_details', __( 'Client & brief', 'eagleeye' ), 'eagleeye_request_details_box', 'ee_request', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'eagleeye_add_request_details_box' );

/**
 * Confirmation notices for invoice actions.
 */
function eagleeye_request_admin_notice() {
	if ( ! isset( $_GET['invoice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}

	$state = sanitize_key( wp_unslash( $_GET['invoice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$text  = 'resent' === $state ? __( 'Invoice emailed to the client.', 'eagleeye' ) : __( 'The invoice could not be sent. Check Email settings and the client address.', 'eagleeye' );

	printf( '<div class="notice %s is-dismissible"><p>%s</p></div>', 'resent' === $state ? 'notice-success' : 'notice-error', esc_html( $text ) );
}
add_action( 'admin_notices', 'eagleeye_request_admin_notice' );

/**
 * Find a media-kit request by its private invoice token.
 *
 * @param string $token Invoice token.
 * @return WP_Post|null
 */
function eagleeye_request_from_token( $token ) {
	$token = sanitize_text_field( (string) $token );

	if ( ! $token ) {
		return null;
	}

	$found = get_posts( array(
		'post_type'      => 'ee_request',
		'post_status'    => 'private',
		'numberposts'    => 1,
		'meta_key'       => '_ee_token',
		'meta_value'     => $token,
	) );

	return $found ? $found[0] : null;
}

/**
 * Render an on-screen invoice so the booking flow works before email delivery
 * is configured, and remains printable afterwards.
 *
 * @param int $post_id Request ID.
 */
function eagleeye_render_invoice( $post_id ) {
	$post_id   = (int) $post_id;
	$name      = (string) get_post_meta( $post_id, '_ee_name', true );
	$company   = (string) get_post_meta( $post_id, '_ee_company', true );
	$email     = (string) get_post_meta( $post_id, '_ee_email', true );
	$reference = (string) get_post_meta( $post_id, '_ee_reference', true );
	$amount    = (int) get_post_meta( $post_id, '_ee_amount', true );
	$label     = (string) get_post_meta( $post_id, '_ee_package_label', true );
	$period    = (string) get_post_meta( $post_id, '_ee_period', true );
	$token     = (string) get_post_meta( $post_id, '_ee_token', true );
	$status    = (string) get_post_meta( $post_id, '_ee_status', true );
	$claim_url = $token ? eagleeye_request_claim_url( $post_id ) : '';
	$logo      = eagleeye_brand_mark();
	?>
	<div class="invoice-card" id="invoice">
		<header class="invoice-card__head">
			<div>
				<p class="invoice-card__kicker"><?php esc_html_e( 'Advertising invoice', 'eagleeye' ); ?></p>
				<h2><?php echo esc_html( $company ); ?></h2>
				<p class="invoice-card__meta">
					<?php echo esc_html( sprintf( __( 'Prepared for %s', 'eagleeye' ), $name ) ); ?>
					<?php if ( $email ) : ?> · <?php echo esc_html( $email ); ?><?php endif; ?>
				</p>
			</div>
			<?php if ( $logo ) : ?>
				<img class="invoice-card__logo" src="<?php echo esc_url( $logo['url'] ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" width="<?php echo esc_attr( $logo['width'] ); ?>" height="<?php echo esc_attr( $logo['height'] ); ?>">
			<?php endif; ?>
		</header>

		<div class="invoice-card__body">
			<div class="invoice-card__facts">
				<div><span><?php esc_html_e( 'Reference', 'eagleeye' ); ?></span><strong><?php echo esc_html( $reference ); ?></strong></div>
				<div><span><?php esc_html_e( 'Placement', 'eagleeye' ); ?></span><strong><?php echo esc_html( $label ); ?></strong><?php if ( $period ) : ?><em><?php echo esc_html( $period ); ?></em><?php endif; ?></div>
				<div><span><?php esc_html_e( 'Amount due', 'eagleeye' ); ?></span><strong><?php echo esc_html( eagleeye_naira( $amount ) ); ?></strong></div>
				<div><span><?php esc_html_e( 'Status', 'eagleeye' ); ?></span><strong><?php echo esc_html( 'claimed' === $status ? __( 'Payment reported', 'eagleeye' ) : __( 'Awaiting payment', 'eagleeye' ) ); ?></strong></div>
			</div>

			<div class="invoice-card__pay">
				<h3><?php esc_html_e( 'Pay by bank transfer', 'eagleeye' ); ?></h3>
				<?php echo wp_kses_post( eagleeye_bank_block() ); ?>
				<p class="invoice-card__note"><?php esc_html_e( 'Use the invoice reference as the transfer narration. Keep this page for your records.', 'eagleeye' ); ?></p>
				<div class="invoice-card__actions">
					<button class="btn btn--outline" type="button" data-print-page><?php esc_html_e( 'Print invoice', 'eagleeye' ); ?></button>
					<?php if ( $claim_url && 'claimed' !== $status && 'paid' !== $status && 'completed' !== $status ) : ?>
						<a class="btn btn--solid" href="<?php echo esc_url( $claim_url ); ?>"><?php esc_html_e( 'I have paid — notify the newsroom', 'eagleeye' ); ?></a>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
	<?php
}
