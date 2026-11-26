<?php
/**
 * Paid advertising inventory.
 *
 * Every ad is a record with a placement, an advertiser, a price and a schedule.
 * Only records explicitly marked "Paid" and inside their booked dates are
 * rendered on the site; everything else falls back to the house promotion.
 *
 * @package Eagle Eye
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the advertisement post type.
 */
function eagleeye_register_ad_type() {
	register_post_type( 'ee_ad', array(
		'labels' => array(
			'name'               => __( 'Advertisements', 'eagleeye' ),
			'singular_name'      => __( 'Advertisement', 'eagleeye' ),
			'add_new'            => __( 'Add advertisement', 'eagleeye' ),
			'add_new_item'       => __( 'Add advertisement', 'eagleeye' ),
			'edit_item'          => __( 'Edit advertisement', 'eagleeye' ),
			'new_item'           => __( 'New advertisement', 'eagleeye' ),
			'view_item'          => __( 'View advertisement', 'eagleeye' ),
			'search_items'       => __( 'Search advertisements', 'eagleeye' ),
			'not_found'          => __( 'No advertisements yet.', 'eagleeye' ),
			'not_found_in_trash' => __( 'No advertisements in trash.', 'eagleeye' ),
			'menu_name'          => __( 'Advertisements', 'eagleeye' ),
		),
		'public'             => false,
		'show_ui'            => true,
		'show_in_menu'       => 'eagleeye',
		'capability_type'    => 'post',
		'map_meta_cap'       => true,
		'supports'           => array( 'title', 'thumbnail' ),
		'menu_icon'          => 'dashicons-megaphone',
		'rewrite'            => false,
		'query_var'          => false,
	) );
}
add_action( 'init', 'eagleeye_register_ad_type' );

/**
 * Placements available to advertisers.
 *
 * @return array
 */
function eagleeye_ad_slots() {
	return array(
		'header'    => __( 'Header leaderboard (970 × 250)', 'eagleeye' ),
		'sidebar'   => __( 'Sidebar / rail (300 × 250)', 'eagleeye' ),
		'inarticle' => __( 'In-article (728 × 90)', 'eagleeye' ),
		'category'  => __( 'Category banner (970 × 250)', 'eagleeye' ),
		'footer'    => __( 'Footer strip (970 × 90)', 'eagleeye' ),
	);
}

/**
 * Advertisement statuses.
 *
 * @return array
 */
function eagleeye_ad_statuses() {
	return array(
		'pending' => __( 'Awaiting payment', 'eagleeye' ),
		'paid'    => __( 'Paid — live when scheduled', 'eagleeye' ),
		'paused'  => __( 'Paused', 'eagleeye' ),
		'expired' => __( 'Finished', 'eagleeye' ),
	);
}

/**
 * Field defaults.
 *
 * @return array
 */
function eagleeye_ad_defaults() {
	return array(
		'_ee_slot'       => 'header',
		'_ee_status'     => 'pending',
		'_ee_advertiser' => '',
		'_ee_url'        => '',
		'_ee_start'      => current_time( 'Y-m-d' ),
		'_ee_end'        => gmdate( 'Y-m-d', strtotime( '+30 days', current_time( 'timestamp' ) ) ),
		'_ee_amount'     => '',
		'_ee_notes'      => '',
		'_ee_html'       => '',
	);
}

/**
 * Meta box.
 */
function eagleeye_ad_metabox() {
	add_meta_box(
		'eagleeye_ad_booking',
		__( 'Booking & payment', 'eagleeye' ),
		'eagleeye_ad_metabox_render',
		'ee_ad',
		'normal',
		'high'
	);

	add_meta_box(
		'eagleeye_ad_creative',
		__( 'Creative', 'eagleeye' ),
		'eagleeye_ad_creative_render',
		'ee_ad',
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes', 'eagleeye_ad_metabox' );

/**
 * Booking fields.
 *
 * @param WP_Post $post Post.
 */
function eagleeye_ad_metabox_render( $post ) {
	wp_nonce_field( 'eagleeye_ad_save', 'eagleeye_ad_nonce' );

	$values = array();

	foreach ( eagleeye_ad_defaults() as $key => $default ) {
		$values[ $key ] = get_post_meta( $post->ID, $key, true );
		$values[ $key ] = '' === $values[ $key ] ? $default : $values[ $key ];
	}
	?>
	<style>
		.ee-ad-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px 20px}
		.ee-ad-grid p{margin:0}
		.ee-ad-grid label{display:block;font-weight:600;margin-bottom:5px}
		.ee-ad-grid input,.ee-ad-grid select,.ee-ad-grid textarea{width:100%}
		.ee-ad-note{grid-column:1/-1;background:#fff8e1;border-left:4px solid #f6c21c;padding:10px 14px;margin:0}
	</style>
	<div class="ee-ad-grid">
		<p class="ee-ad-note">
			<strong><?php esc_html_e( 'Only paid bookings go live.', 'eagleeye' ); ?></strong>
			<?php esc_html_e( 'The site renders this creative only when the status is “Paid” and today falls between the start and end dates. Everything else shows the house promotion.', 'eagleeye' ); ?>
		</p>

		<p>
			<label for="ee_slot"><?php esc_html_e( 'Placement', 'eagleeye' ); ?></label>
			<select id="ee_slot" name="ee_slot">
				<?php foreach ( eagleeye_ad_slots() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $values['_ee_slot'], $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>

		<p>
			<label for="ee_status"><?php esc_html_e( 'Payment status', 'eagleeye' ); ?></label>
			<?php if ( current_user_can( 'manage_eagleeye_ad_approval' ) ) : ?>
				<select id="ee_status" name="ee_status">
					<?php foreach ( eagleeye_ad_statuses() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $values['_ee_status'], $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php else : ?>
				<strong><?php echo esc_html( eagleeye_ad_statuses()[ $values['_ee_status'] ] ?? __( 'Awaiting payment', 'eagleeye' ) ); ?></strong>
				<p class="description"><?php esc_html_e( 'Only an authorised administrator can approve a booking as paid or change its live status.', 'eagleeye' ); ?></p>
			<?php endif; ?>
		</p>

		<p>
			<label for="ee_advertiser"><?php esc_html_e( 'Advertiser', 'eagleeye' ); ?></label>
			<input type="text" id="ee_advertiser" name="ee_advertiser" value="<?php echo esc_attr( $values['_ee_advertiser'] ); ?>" placeholder="<?php esc_attr_e( 'Brand or agency name', 'eagleeye' ); ?>">
		</p>

		<p>
			<label for="ee_amount"><?php esc_html_e( 'Amount paid (₦)', 'eagleeye' ); ?></label>
			<input type="number" min="0" step="1000" id="ee_amount" name="ee_amount" value="<?php echo esc_attr( $values['_ee_amount'] ); ?>" placeholder="450000">
		</p>

		<p>
			<label for="ee_start"><?php esc_html_e( 'Start date', 'eagleeye' ); ?></label>
			<input type="date" id="ee_start" name="ee_start" value="<?php echo esc_attr( $values['_ee_start'] ); ?>">
		</p>

		<p>
			<label for="ee_end"><?php esc_html_e( 'End date', 'eagleeye' ); ?></label>
			<input type="date" id="ee_end" name="ee_end" value="<?php echo esc_attr( $values['_ee_end'] ); ?>">
		</p>

		<p style="grid-column:1/-1">
			<label for="ee_url"><?php esc_html_e( 'Destination URL', 'eagleeye' ); ?></label>
			<input type="url" id="ee_url" name="ee_url" value="<?php echo esc_attr( $values['_ee_url'] ); ?>" placeholder="https://advertiser.example/offer">
		</p>

		<p style="grid-column:1/-1">
			<label for="ee_notes"><?php esc_html_e( 'Internal notes', 'eagleeye' ); ?></label>
			<textarea id="ee_notes" name="ee_notes" rows="3" placeholder="<?php esc_attr_e( 'Invoice number, contact person, flight details…', 'eagleeye' ); ?>"><?php echo esc_textarea( $values['_ee_notes'] ); ?></textarea>
		</p>
	</div>
	<?php
}

/**
 * Creative panel.
 *
 * @param WP_Post $post Post.
 */
function eagleeye_ad_creative_render( $post ) {
	$html = get_post_meta( $post->ID, '_ee_html', true );
	?>
	<p><?php esc_html_e( 'Upload the banner as the featured image (970 × 250 for leaderboards, 300 × 250 for the rail).', 'eagleeye' ); ?></p>
	<p>
		<label for="ee_html"><strong><?php esc_html_e( 'Or paste ad code', 'eagleeye' ); ?></strong></label>
		<textarea id="ee_html" name="ee_html" rows="6" style="width:100%;font-family:monospace" placeholder="&lt;script&gt;…AdSense or network tag…&lt;/script&gt;"><?php echo esc_textarea( $html ); ?></textarea>
	</p>
	<p class="description"><?php esc_html_e( 'Ad code replaces the featured image. It is output only for paid, scheduled bookings and is never escaped — paste trusted network code only.', 'eagleeye' ); ?></p>
	<?php
}

/**
 * Persist booking fields.
 *
 * @param int $post_id Post ID.
 */
function eagleeye_save_ad( $post_id ) {
	if ( ! isset( $_POST['eagleeye_ad_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['eagleeye_ad_nonce'] ) ), 'eagleeye_ad_save' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$fields = array(
		'ee_slot'       => '_ee_slot',
		'ee_status'     => '_ee_status',
		'ee_advertiser' => '_ee_advertiser',
		'ee_url'        => '_ee_url',
		'ee_start'      => '_ee_start',
		'ee_end'        => '_ee_end',
		'ee_amount'     => '_ee_amount',
		'ee_notes'      => '_ee_notes',
	);

	foreach ( $fields as $field => $meta ) {
		if ( ! isset( $_POST[ $field ] ) ) {
			continue;
		}

		$raw = wp_unslash( $_POST[ $field ] );

		switch ( $meta ) {
			case '_ee_url':
				$value = esc_url_raw( $raw );
				break;
			case '_ee_amount':
				$value = (string) absint( $raw );
				break;
			case '_ee_start':
			case '_ee_end':
				$value = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $raw ) ? $raw : '';
				break;
			case '_ee_slot':
				$value = array_key_exists( $raw, eagleeye_ad_slots() ) ? $raw : 'header';
				break;
			case '_ee_status':
				if ( ! current_user_can( 'manage_eagleeye_ad_approval' ) && 'pending' !== $raw ) {
					continue 2;
				}
				$value = array_key_exists( $raw, eagleeye_ad_statuses() ) ? $raw : 'pending';
				break;
			default:
				$value = sanitize_text_field( $raw );
		}

		update_post_meta( $post_id, $meta, $value );
	}

	if ( isset( $_POST['ee_html'] ) ) {
		update_post_meta( $post_id, '_ee_html', wp_unslash( $_POST['ee_html'] ) );
	}
}
add_action( 'save_post_ee_ad', 'eagleeye_save_ad' );

/**
 * Admin columns.
 *
 * @param array $columns Columns.
 * @return array
 */
function eagleeye_ad_columns( $columns ) {
	return array(
		'cb'              => $columns['cb'],
		'title'           => __( 'Campaign', 'eagleeye' ),
		'ee_slot'         => __( 'Placement', 'eagleeye' ),
		'ee_advertiser'   => __( 'Advertiser', 'eagleeye' ),
		'ee_status'       => __( 'Payment', 'eagleeye' ),
		'ee_schedule'     => __( 'Flight', 'eagleeye' ),
		'ee_amount'       => __( 'Amount', 'eagleeye' ),
	);
}
add_filter( 'manage_ee_ad_posts_columns', 'eagleeye_ad_columns' );

/**
 * Render columns.
 *
 * @param string $column  Column key.
 * @param int    $post_id Post ID.
 */
function eagleeye_ad_column_content( $column, $post_id ) {
	switch ( $column ) {
		case 'ee_slot':
			$slots = eagleeye_ad_slots();
			$slot  = get_post_meta( $post_id, '_ee_slot', true );
			echo esc_html( $slots[ $slot ] ?? '—' );
			break;

		case 'ee_advertiser':
			echo esc_html( get_post_meta( $post_id, '_ee_advertiser', true ) ?: '—' );
			break;

		case 'ee_status':
			$status = get_post_meta( $post_id, '_ee_status', true );
			$colors = array(
				'paid'    => '#0a7d33',
				'pending' => '#a15c00',
				'paused'  => '#6b665c',
				'expired' => '#8d877b',
			);
			$labels = eagleeye_ad_statuses();
			printf(
				'<strong style="color:%1$s">%2$s</strong>',
				esc_attr( $colors[ $status ] ?? '#6b665c' ),
				esc_html( $labels[ $status ] ?? ucfirst( (string) $status ) )
			);
			if ( eagleeye_ad_is_live( $post_id ) ) {
				echo '<br><span style="color:#0a7d33">● ' . esc_html__( 'Live now', 'eagleeye' ) . '</span>';
			}
			break;

		case 'ee_schedule':
			$start = get_post_meta( $post_id, '_ee_start', true );
			$end   = get_post_meta( $post_id, '_ee_end', true );
			echo esc_html( trim( $start . ' → ' . $end, ' →' ) ?: '—' );
			break;

		case 'ee_amount':
			$amount = (int) get_post_meta( $post_id, '_ee_amount', true );
			echo $amount ? esc_html( '₦' . number_format_i18n( $amount ) ) : '—';
			break;
	}
}
add_action( 'manage_ee_ad_posts_custom_column', 'eagleeye_ad_column_content', 10, 2 );

/**
 * Is this booking currently live?
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function eagleeye_ad_is_live( $post_id ) {
	if ( 'paid' !== get_post_meta( $post_id, '_ee_status', true ) ) {
		return false;
	}

	$today = current_time( 'Y-m-d' );
	$start = get_post_meta( $post_id, '_ee_start', true );
	$end   = get_post_meta( $post_id, '_ee_end', true );

	if ( $start && $today < $start ) {
		return false;
	}

	if ( $end && $today > $end ) {
		return false;
	}

	return true;
}

/**
 * Find the paid booking for a placement.
 *
 * @param string $slot Placement key.
 * @return WP_Post|null
 */
function eagleeye_get_paid_ad( $slot ) {
	$query = new WP_Query( array(
		'post_type'      => 'ee_ad',
		'post_status'    => 'publish',
		'posts_per_page' => 5,
		'no_found_rows'  => true,
		'meta_query'     => array(
			array(
				'key'   => '_ee_slot',
				'value' => $slot,
			),
			array(
				'key'   => '_ee_status',
				'value' => 'paid',
			),
		),
		'orderby'        => 'date',
		'order'          => 'DESC',
	) );

	foreach ( $query->posts as $post ) {
		if ( eagleeye_ad_is_live( $post->ID ) ) {
			return $post;
		}
	}

	return null;
}

/**
 * Mark a booking paid from the list table.
 */
function eagleeye_ad_quick_pay() {
	$post_id = isset( $_GET['ad'] ) ? absint( $_GET['ad'] ) : 0;

	if ( ! $post_id || ! current_user_can( 'manage_eagleeye_ad_approval' ) || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_die( esc_html__( 'Only an authorised newsroom administrator can approve an advertisement as paid.', 'eagleeye' ) );
	}

	check_admin_referer( 'eagleeye_ad_pay_' . $post_id );

	update_post_meta( $post_id, '_ee_status', 'paid' );

	wp_safe_redirect( admin_url( 'edit.php?post_type=ee_ad&paid=1' ) );
	exit;
}
add_action( 'admin_post_eagleeye_ad_pay', 'eagleeye_ad_quick_pay' );

/**
 * Row action: mark paid.
 *
 * @param array $actions Actions.
 * @param WP_Post $post Post.
 * @return array
 */
function eagleeye_ad_row_actions( $actions, $post ) {
	if ( 'ee_ad' !== $post->post_type ) {
		return $actions;
	}

	if ( current_user_can( 'manage_eagleeye_ad_approval' ) && 'paid' !== get_post_meta( $post->ID, '_ee_status', true ) ) {
		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=eagleeye_ad_pay&ad=' . $post->ID ),
			'eagleeye_ad_pay_' . $post->ID
		);
		$actions['ee_pay'] = '<a href="' . esc_url( $url ) . '" style="color:#0a7d33;font-weight:600">' . esc_html__( 'Mark paid', 'eagleeye' ) . '</a>';
	}

	return $actions;
}
add_filter( 'post_row_actions', 'eagleeye_ad_row_actions', 10, 2 );

/**
 * Admin notice after a quick payment.
 */
function eagleeye_ad_paid_notice() {
	if ( isset( $_GET['paid'] ) && '1' === $_GET['paid'] ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Booking marked paid. It will appear on the site during its scheduled dates.', 'eagleeye' ) . '</p></div>';
	}
}
add_action( 'admin_notices', 'eagleeye_ad_paid_notice' );

/**
 * Renewal reminder.
 *
 * A placement that ends quietly is the cheapest revenue a newsroom ever
 * loses. Once a day the desk is emailed every paid booking closing within
 * the week, with the record one click away. The advertiser is not emailed:
 * renewing is a conversation, and the reminder exists to start it.
 */
function eagleeye_ad_renewal_reminder() {
	$slots    = eagleeye_ad_slots();
	$today    = current_time( 'Y-m-d' );
	$closing  = gmdate( 'Y-m-d', strtotime( '+7 days', current_time( 'timestamp' ) ) );
	$rows     = array();

	$ads = get_posts( array(
		'post_type'      => 'ee_ad',
		'post_status'    => array( 'publish', 'private' ),
		'posts_per_page' => 100,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_query'     => array(
			array(
				'key'   => '_ee_status',
				'value' => 'paid',
			),
			array(
				'key'     => '_ee_end',
				'value'   => array( $today, $closing ),
				'compare' => 'BETWEEN',
				'type'    => 'DATE',
			),
		),
	) );

	foreach ( $ads as $ad_id ) {
		$end = (string) get_post_meta( $ad_id, '_ee_end', true );

		// One reminder per end date: extending the flight re-arms it.
		if ( ! $end || $end === (string) get_post_meta( $ad_id, '_ee_renewal_notified', true ) ) {
			continue;
		}

		$slot = (string) get_post_meta( $ad_id, '_ee_slot', true );

		$rows[] = '<tr>'
			. '<td style="padding:8px;border-bottom:1px solid #e3ddd0">' . esc_html( get_post_meta( $ad_id, '_ee_advertiser', true ) ?: get_the_title( $ad_id ) ) . '</td>'
			. '<td style="padding:8px;border-bottom:1px solid #e3ddd0">' . esc_html( $slots[ $slot ] ?? $slot ) . '</td>'
			. '<td style="padding:8px;border-bottom:1px solid #e3ddd0">' . esc_html( mysql2date( 'j M Y', $end ) ) . '</td>'
			. '<td style="padding:8px;border-bottom:1px solid #e3ddd0"><a href="' . esc_url( admin_url( 'post.php?post=' . $ad_id . '&action=edit' ) ) . '">Open</a></td>'
			. '</tr>';

		update_post_meta( $ad_id, '_ee_renewal_notified', $end );
	}

	if ( ! $rows ) {
		return;
	}

	$body = '<p>These paid placements close within seven days. Extending one before it expires is the cheapest sale available all month.</p>'
		. '<table cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:15px;margin-top:14px">'
		. '<tr><th align="left" style="padding:8px;border-bottom:2px solid #08080a">Advertiser</th>'
		. '<th align="left" style="padding:8px;border-bottom:2px solid #08080a">Slot</th>'
		. '<th align="left" style="padding:8px;border-bottom:2px solid #08080a">Ends</th>'
		. '<th style="border-bottom:2px solid #08080a"></th></tr>'
		. implode( '', $rows )
		. '</table>'
		. '<p style="margin-top:18px;font-size:13px;color:#6b665c">Mark the booking paid again only after the renewal transfer clears. The reminder repeats only if the end date changes.</p>';

	foreach ( array_filter( array_unique( array( eagleeye_admin_email(), get_option( 'admin_email' ) ) ) ) as $admin ) {
		eagleeye_send_email(
			$admin,
			sprintf( __( '%d placement(s) ending this week', 'eagleeye' ), count( $rows ) ),
			eagleeye_email_template( __( 'Placements ending', 'eagleeye' ), $body )
		);
	}
}
add_action( 'eagleeye_ad_renewal_check', 'eagleeye_ad_renewal_reminder' );

/**
 * Schedule the daily renewal check once.
 */
function eagleeye_ad_renewal_schedule() {
	if ( ! wp_next_scheduled( 'eagleeye_ad_renewal_check' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'eagleeye_ad_renewal_check' );
	}
}
add_action( 'init', 'eagleeye_ad_renewal_schedule' );
