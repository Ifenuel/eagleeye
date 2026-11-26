<?php
/**
 * Citizen report editorial workflow.
 *
 * Turns the private report inbox into a review queue. A report is a lead, not a
 * story: it moves through New, Verifying, Needs information, Approved, Published
 * or Rejected, with an assignee, internal notes, contact history, and a one-click
 * draft that an editor still has to verify and publish.
 *
 * @package Eagle Eye
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Review statuses, in workflow order.
 *
 * @return array<string,string>
 */
function eagleeye_report_statuses() {
	return array(
		'new'          => __( 'New', 'eagleeye' ),
		'verifying'    => __( 'Verifying', 'eagleeye' ),
		'needs_info'   => __( 'Needs information', 'eagleeye' ),
		'approved'     => __( 'Approved', 'eagleeye' ),
		'published'    => __( 'Published', 'eagleeye' ),
		'rejected'     => __( 'Rejected', 'eagleeye' ),
	);
}

/**
 * Current status for a report.
 *
 * @param int $post_id Report ID.
 * @return string
 */
function eagleeye_report_status( $post_id ) {
	$status  = (string) get_post_meta( $post_id, '_ee_review_status', true );
	$allowed = eagleeye_report_statuses();

	return isset( $allowed[ $status ] ) ? $status : 'new';
}

/**
 * Human label for a status.
 *
 * @param string $status Status key.
 * @return string
 */
function eagleeye_report_status_label( $status ) {
	$statuses = eagleeye_report_statuses();

	return isset( $statuses[ $status ] ) ? $statuses[ $status ] : $statuses['new'];
}

/**
 * Move a report to a new status and keep an audit trail.
 *
 * @param int    $post_id Report ID.
 * @param string $status  Status key.
 * @param string $note    Optional internal note for the log.
 * @return bool
 */
function eagleeye_report_set_status( $post_id, $status, $note = '' ) {
	$statuses = eagleeye_report_statuses();

	if ( ! isset( $statuses[ $status ] ) ) {
		return false;
	}

	$previous = eagleeye_report_status( $post_id );

	if ( $previous === $status ) {
		return true;
	}

	update_post_meta( $post_id, '_ee_review_status', $status );

	if ( 'new' !== $status && ! get_post_meta( $post_id, '_ee_reviewed_at', true ) ) {
		update_post_meta( $post_id, '_ee_reviewed_at', current_time( 'mysql' ) );
	}

	$log   = get_post_meta( $post_id, '_ee_status_log', true );
	$log   = is_array( $log ) ? $log : array();
	$log[] = array(
		'status' => $status,
		'from'   => $previous,
		'user'   => get_current_user_id(),
		'time'   => current_time( 'mysql' ),
		'note'   => $note,
	);

	update_post_meta( $post_id, '_ee_status_log', $log );

	if ( 'new' === $status ) {
		delete_post_meta( $post_id, '_ee_reviewed_at' );
	}

	return true;
}

/**
 * Assignee user ID, or 0 when unassigned.
 *
 * @param int $post_id Report ID.
 * @return int
 */
function eagleeye_report_assignee( $post_id ) {
	return absint( get_post_meta( $post_id, '_ee_assignee', true ) );
}

/**
 * Users who can be assigned a report.
 *
 * @return WP_User[]
 */
function eagleeye_report_reviewers() {
	return get_users(
		array(
			'capability' => 'edit_posts',
			'orderby'    => 'display_name',
			'number'     => 100,
		)
	);
}

/**
 * Internal notes for a report.
 *
 * @param int $post_id Report ID.
 * @return string
 */
function eagleeye_report_notes( $post_id ) {
	return (string) get_post_meta( $post_id, '_ee_internal_notes', true );
}

/**
 * Contact history entries.
 *
 * @param int $post_id Report ID.
 * @return array<int,array<string,string>>
 */
function eagleeye_report_contact_log( $post_id ) {
	$log = get_post_meta( $post_id, '_ee_contact_log', true );

	return is_array( $log ) ? $log : array();
}

/**
 * Channels available for contact history.
 *
 * @return array<string,string>
 */
function eagleeye_report_contact_channels() {
	return array(
		'call'     => __( 'Phone call', 'eagleeye' ),
		'whatsapp' => __( 'WhatsApp', 'eagleeye' ),
		'sms'      => __( 'SMS', 'eagleeye' ),
		'email'    => __( 'Email', 'eagleeye' ),
		'visit'    => __( 'In person', 'eagleeye' ),
		'other'    => __( 'Other', 'eagleeye' ),
	);
}

/**
 * Report status counts, including reports that predate the workflow.
 *
 * @return array<string,int>
 */
function eagleeye_report_status_counts() {
	$counts = array_fill_keys( array_keys( eagleeye_report_statuses() ), 0 );
	$total  = 0;

	$ids = get_posts(
		array(
			'post_type'      => 'eagleeye_report',
			'post_status'    => array( 'private', 'publish', 'draft', 'pending' ),
			'posts_per_page' => 500,
			'fields'         => 'ids',
		)
	);

	foreach ( $ids as $id ) {
		$total++;
		$counts[ eagleeye_report_status( $id ) ]++;
	}

	$counts['all'] = $total;

	return $counts;
}

/**
 * Average hours from submission to first editorial review.
 *
 * @return float|null
 */
function eagleeye_report_response_hours() {
	$ids = get_posts(
		array(
			'post_type'      => 'eagleeye_report',
			'post_status'    => array( 'private', 'publish', 'draft', 'pending' ),
			'posts_per_page' => 300,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);

	$total = 0.0;
	$count = 0;

	foreach ( $ids as $id ) {
		$submitted = (string) get_post_meta( $id, '_ee_submitted_at', true );
		$reviewed  = (string) get_post_meta( $id, '_ee_reviewed_at', true );

		if ( ! $submitted || ! $reviewed ) {
			continue;
		}

		$start = strtotime( $submitted );
		$end   = strtotime( $reviewed );

		if ( ! $start || ! $end || $end < $start ) {
			continue;
		}

		$total += ( $end - $start ) / HOUR_IN_SECONDS;
		$count++;
	}

	return $count ? round( $total / $count, 1 ) : null;
}

/* -------------------------------------------------------------------------
 * Admin list table: columns, filters, views and bulk actions.
 * ---------------------------------------------------------------------- */

/**
 * Add workflow columns.
 *
 * @param array $columns Existing columns.
 * @return array
 */
function eagleeye_report_workflow_columns( $columns ) {
	$new = array();

	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;

		if ( 'title' === $key ) {
			$new['ee_status'] = __( 'Status', 'eagleeye' );
		}
	}

	$new['ee_assignee'] = __( 'Assigned to', 'eagleeye' );
	$new['ee_flags']    = __( 'Flags', 'eagleeye' );

	return $new;
}
add_filter( 'manage_eagleeye_report_posts_columns', 'eagleeye_report_workflow_columns', 20 );

/**
 * Print workflow column values.
 *
 * @param string $column  Column key.
 * @param int    $post_id Report ID.
 */
function eagleeye_report_workflow_column_content( $column, $post_id ) {
	if ( 'ee_status' === $column ) {
		$status = eagleeye_report_status( $post_id );
		$colors = array(
			'new'        => '#1d2327',
			'verifying'  => '#8a6100',
			'needs_info' => '#8a4b00',
			'approved'   => '#116b2f',
			'published'  => '#0a4baf',
			'rejected'   => '#8a2424',
		);
		$color  = isset( $colors[ $status ] ) ? $colors[ $status ] : '#1d2327';

		printf(
			'<strong style="color:%1$s">%2$s</strong>',
			esc_attr( $color ),
			esc_html( eagleeye_report_status_label( $status ) )
		);
	}

	if ( 'ee_assignee' === $column ) {
		$user_id = eagleeye_report_assignee( $post_id );
		$user    = $user_id ? get_userdata( $user_id ) : false;

		echo $user ? esc_html( $user->display_name ) : '<span style="color:#777">' . esc_html__( 'Unassigned', 'eagleeye' ) . '</span>';
	}

	if ( 'ee_flags' === $column ) {
		$flags = array();

		$duplicate = absint( get_post_meta( $post_id, '_ee_duplicate_of', true ) );
		if ( $duplicate ) {
			$flags[] = sprintf(
				'<a href="%1$s" style="color:#8a4b00">%2$s</a>',
				esc_url( get_edit_post_link( $duplicate ) ),
				esc_html__( 'Possible duplicate', 'eagleeye' )
			);
		}

		$repeat = absint( get_post_meta( $post_id, '_ee_repeat_reports', true ) );
		if ( $repeat > 1 ) {
			$flags[] = esc_html( sprintf( __( '%d reports from this number', 'eagleeye' ), $repeat ) );
		}

		$score = absint( get_post_meta( $post_id, '_ee_abuse_score', true ) );
		if ( $score >= 30 ) {
			$flags[] = esc_html( sprintf( __( 'Abuse score %d', 'eagleeye' ), $score ) );
		}

		echo $flags ? implode( '<br>', $flags ) : '&mdash;'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
add_action( 'manage_eagleeye_report_posts_custom_column', 'eagleeye_report_workflow_column_content', 20, 2 );

/**
 * Status and assignee filters above the list.
 */
function eagleeye_report_list_filters() {
	global $typenow;

	if ( 'eagleeye_report' !== $typenow ) {
		return;
	}

	$status = isset( $_GET['ee_status'] ) ? sanitize_key( wp_unslash( $_GET['ee_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$owner  = isset( $_GET['ee_assignee'] ) ? absint( $_GET['ee_assignee'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	echo '<select name="ee_status"><option value="">' . esc_html__( 'All statuses', 'eagleeye' ) . '</option>';

	foreach ( eagleeye_report_statuses() as $key => $label ) {
		printf(
			'<option value="%1$s" %2$s>%3$s</option>',
			esc_attr( $key ),
			selected( $status, $key, false ),
			esc_html( $label )
		);
	}

	echo '</select>';

	echo '<select name="ee_assignee"><option value="">' . esc_html__( 'Anyone', 'eagleeye' ) . '</option>';

	foreach ( eagleeye_report_reviewers() as $user ) {
		printf(
			'<option value="%1$d" %2$s>%3$s</option>',
			(int) $user->ID,
			selected( $owner, (int) $user->ID, false ),
			esc_html( $user->display_name )
		);
	}

	echo '</select>';
}
add_action( 'restrict_manage_posts', 'eagleeye_report_list_filters' );

/**
 * Apply the list filters and support the retention view.
 *
 * @param WP_Query $query Query.
 */
function eagleeye_report_list_query( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() ) {
		return;
	}

	if ( 'eagleeye_report' !== $query->get( 'post_type' ) ) {
		return;
	}

	$meta_query = array();

	$status = isset( $_GET['ee_status'] ) ? sanitize_key( wp_unslash( $_GET['ee_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( $status && isset( eagleeye_report_statuses()[ $status ] ) ) {
		if ( 'new' === $status ) {
			$meta_query[] = array(
				'relation' => 'OR',
				array(
					'key'     => '_ee_review_status',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'   => '_ee_review_status',
					'value' => 'new',
				),
			);
		} else {
			$meta_query[] = array(
				'key'   => '_ee_review_status',
				'value' => $status,
			);
		}
	}

	$owner = isset( $_GET['ee_assignee'] ) ? absint( $_GET['ee_assignee'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( $owner ) {
		$meta_query[] = array(
			'key'   => '_ee_assignee',
			'value' => $owner,
		);
	}

	if ( $meta_query ) {
		$query->set( 'meta_query', $meta_query ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
	}

	if ( isset( $_GET['ee_retention'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$days = eagleeye_report_retention_days();

		$query->set(
			'date_query',
			array(
				array(
					'before'    => gmdate( 'Y-m-d H:i:s', strtotime( '-' . $days . ' days' ) ),
					'inclusive' => true,
					'column'    => 'post_date_gmt',
				),
			)
		);
	}
}
add_action( 'pre_get_posts', 'eagleeye_report_list_query' );

/**
 * Retention period in days. Reports are never deleted automatically.
 *
 * @return int
 */
function eagleeye_report_retention_days() {
	$days = (int) apply_filters( 'eagleeye_report_retention_days', 180 );

	return $days > 0 ? $days : 180;
}

/**
 * Workflow views above the report list.
 *
 * @param array $views Existing views.
 * @return array
 */
function eagleeye_report_list_views( $views ) {
	$counts     = eagleeye_report_status_counts();
	$base       = admin_url( 'edit.php?post_type=eagleeye_report' );
	$mine       = get_current_user_id();
	$mine_count = 0;

	$assigned = get_posts(
		array(
			'post_type'      => 'eagleeye_report',
			'post_status'    => array( 'private', 'publish', 'draft', 'pending' ),
			'posts_per_page' => 500,
			'fields'         => 'ids',
			'meta_key'       => '_ee_assignee', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => $mine, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);
	$mine_count = count( $assigned );

	$views['ee_new'] = sprintf(
		'<a href="%1$s">%2$s <span class="count">(%3$d)</span></a>',
		esc_url( add_query_arg( 'ee_status', 'new', $base ) ),
		esc_html__( 'New', 'eagleeye' ),
		(int) $counts['new']
	);

	$views['ee_verifying'] = sprintf(
		'<a href="%1$s">%2$s <span class="count">(%3$d)</span></a>',
		esc_url( add_query_arg( 'ee_status', 'verifying', $base ) ),
		esc_html__( 'Verifying', 'eagleeye' ),
		(int) $counts['verifying']
	);

	$views['ee_needs_info'] = sprintf(
		'<a href="%1$s">%2$s <span class="count">(%3$d)</span></a>',
		esc_url( add_query_arg( 'ee_status', 'needs_info', $base ) ),
		esc_html__( 'Needs information', 'eagleeye' ),
		(int) $counts['needs_info']
	);

	$views['ee_mine'] = sprintf(
		'<a href="%1$s">%2$s <span class="count">(%3$d)</span></a>',
		esc_url( add_query_arg( 'ee_assignee', $mine, $base ) ),
		esc_html__( 'Assigned to me', 'eagleeye' ),
		(int) $mine_count
	);

	$older = new WP_Query(
		array(
			'post_type'      => 'eagleeye_report',
			'post_status'    => array( 'private', 'publish', 'draft', 'pending' ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'date_query'     => array(
				array(
					'before' => gmdate( 'Y-m-d H:i:s', strtotime( '-' . eagleeye_report_retention_days() . ' days' ) ),
					'column' => 'post_date_gmt',
				),
			),
		)
	);

	if ( $older->found_posts ) {
		$views['ee_retention'] = sprintf(
			'<a href="%1$s">%2$s <span class="count">(%3$d)</span></a>',
			esc_url( add_query_arg( 'ee_retention', '1', $base ) ),
			esc_html__( 'Past retention', 'eagleeye' ),
			(int) $older->found_posts
		);
	}

	return $views;
}
add_filter( 'views_edit-eagleeye_report', 'eagleeye_report_list_views' );

/**
 * Row actions for quick status changes.
 *
 * @param array   $actions Existing actions.
 * @param WP_Post $post    Report.
 * @return array
 */
function eagleeye_report_row_actions( $actions, $post ) {
	if ( 'eagleeye_report' !== $post->post_type || ! current_user_can( 'edit_post', $post->ID ) ) {
		return $actions;
	}

	$steps = array(
		'verifying'  => __( 'Start verifying', 'eagleeye' ),
		'needs_info' => __( 'Needs information', 'eagleeye' ),
		'approved'   => __( 'Approve', 'eagleeye' ),
		'rejected'   => __( 'Reject', 'eagleeye' ),
	);

	$current = eagleeye_report_status( $post->ID );

	foreach ( $steps as $status => $label ) {
		if ( $status === $current ) {
			continue;
		}

		$actions[ 'ee_' . $status ] = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url(
				wp_nonce_url(
					add_query_arg(
						array(
							'action' => 'eagleeye_report_status',
							'report' => $post->ID,
							'status' => $status,
						),
						admin_url( 'admin-post.php' )
					),
					'eagleeye_report_status_' . $post->ID
				)
			),
			esc_html( $label )
		);
	}

	return $actions;
}
add_filter( 'post_row_actions', 'eagleeye_report_row_actions', 10, 2 );

/**
 * Handle a quick status change.
 */
function eagleeye_report_handle_status_change() {
	$post_id = isset( $_GET['report'] ) ? absint( $_GET['report'] ) : 0;
	$status  = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';

	if ( ! $post_id || 'eagleeye_report' !== get_post_type( $post_id ) ) {
		wp_die( esc_html__( 'Report not found.', 'eagleeye' ), 404 );
	}

	check_admin_referer( 'eagleeye_report_status_' . $post_id );

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		wp_die( esc_html__( 'You are not allowed to review this report.', 'eagleeye' ), 403 );
	}

	eagleeye_report_set_status( $post_id, $status );

	wp_safe_redirect(
		add_query_arg(
			'ee_notice',
			'status',
			wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=eagleeye_report' )
		)
	);
	exit;
}
add_action( 'admin_post_eagleeye_report_status', 'eagleeye_report_handle_status_change' );

/**
 * Admin notices for workflow actions.
 */
function eagleeye_report_admin_notices() {
	if ( empty( $_GET['ee_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}

	$notice = sanitize_key( wp_unslash( $_GET['ee_notice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$text   = array(
		'status'      => __( 'Report status updated.', 'eagleeye' ),
		'contact'     => __( 'Contact history updated.', 'eagleeye' ),
		'draft'       => __( 'Story draft created. Verify every fact before publishing.', 'eagleeye' ),
		'draft_exists' => __( 'This report already has a linked draft.', 'eagleeye' ),
		'erased'      => __( 'Report and its private media were permanently deleted.', 'eagleeye' ),
	);

	if ( ! isset( $text[ $notice ] ) ) {
		return;
	}

	printf(
		'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
		esc_html( $text[ $notice ] )
	);
}
add_action( 'admin_notices', 'eagleeye_report_admin_notices' );

/* -------------------------------------------------------------------------
 * Review panel.
 * ---------------------------------------------------------------------- */

/**
 * Register the review panel.
 */
function eagleeye_report_review_box() {
	add_meta_box(
		'eagleeye_report_review',
		__( 'Editorial review', 'eagleeye' ),
		'eagleeye_report_review_box_html',
		'eagleeye_report',
		'normal',
		'high'
	);

	add_meta_box(
		'eagleeye_report_source',
		__( 'Source citizen report', 'eagleeye' ),
		'eagleeye_report_source_box_html',
		'post',
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes', 'eagleeye_report_review_box' );

/**
 * Render the review panel.
 *
 * @param WP_Post $post Report.
 */
function eagleeye_report_review_box_html( $post ) {
	$status    = eagleeye_report_status( $post->ID );
	$assignee  = eagleeye_report_assignee( $post->ID );
	$notes     = eagleeye_report_notes( $post->ID );
	$log       = eagleeye_report_contact_log( $post->ID );
	$channels  = eagleeye_report_contact_channels();
	$draft_id  = absint( get_post_meta( $post->ID, '_ee_draft_id', true ) );
	$duplicate = absint( get_post_meta( $post->ID, '_ee_duplicate_of', true ) );
	$abuse     = absint( get_post_meta( $post->ID, '_ee_abuse_score', true ) );
	$history   = get_post_meta( $post->ID, '_ee_status_log', true );
	$history   = is_array( $history ) ? $history : array();

	wp_nonce_field( 'eagleeye_report_review', 'eagleeye_report_review_nonce' );
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="ee-review-status"><?php esc_html_e( 'Status', 'eagleeye' ); ?></label></th>
			<td>
				<select name="ee_review_status" id="ee-review-status">
					<?php foreach ( eagleeye_report_statuses() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status, $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'A report is a lead until an editor verifies it. This status never publishes anything by itself.', 'eagleeye' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="ee-review-assignee"><?php esc_html_e( 'Assigned to', 'eagleeye' ); ?></label></th>
			<td>
				<select name="ee_review_assignee" id="ee-review-assignee">
					<option value="0"><?php esc_html_e( 'Unassigned', 'eagleeye' ); ?></option>
					<?php foreach ( eagleeye_report_reviewers() as $user ) : ?>
						<option value="<?php echo esc_attr( (int) $user->ID ); ?>" <?php selected( $assignee, (int) $user->ID ); ?>><?php echo esc_html( $user->display_name ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="ee-review-notes"><?php esc_html_e( 'Internal notes', 'eagleeye' ); ?></label></th>
			<td>
				<textarea name="ee_internal_notes" id="ee-review-notes" rows="5" class="large-text"><?php echo esc_textarea( $notes ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Visible to editors only. Never shown to the reporter or published.', 'eagleeye' ); ?></p>
			</td>
		</tr>
	</table>

	<?php if ( $duplicate ) : ?>
		<p style="padding:10px 12px;background:#fcf3e3;border-left:4px solid #c07a00">
			<?php
			printf(
				/* translators: %s: link to the earlier report. */
				esc_html__( 'This may repeat an earlier report: %s', 'eagleeye' ),
				'<a href="' . esc_url( (string) get_edit_post_link( $duplicate ) ) . '">' . esc_html( get_the_title( $duplicate ) ) . '</a>'
			);
			?>
		</p>
	<?php endif; ?>

	<?php if ( $abuse >= 30 ) : ?>
		<p style="padding:10px 12px;background:#fbecec;border-left:4px solid #b32d2e">
			<?php echo esc_html( sprintf( __( 'Automatic abuse score: %d out of 100. Review carefully; do not click links from the report.', 'eagleeye' ), $abuse ) ); ?>
		</p>
	<?php endif; ?>

	<h3><?php esc_html_e( 'Contact history', 'eagleeye' ); ?></h3>

	<?php if ( $log ) : ?>
		<table class="widefat striped" style="margin-bottom:12px">
			<thead>
				<tr>
					<th style="width:120px"><?php esc_html_e( 'Date', 'eagleeye' ); ?></th>
					<th style="width:130px"><?php esc_html_e( 'Method', 'eagleeye' ); ?></th>
					<th><?php esc_html_e( 'What was discussed', 'eagleeye' ); ?></th>
					<th style="width:110px"><?php esc_html_e( 'Logged by', 'eagleeye' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( array_reverse( $log ) as $entry ) : ?>
					<tr>
						<td><?php echo esc_html( $entry['date'] ?? '' ); ?></td>
						<td><?php echo esc_html( $channels[ $entry['channel'] ?? 'other' ] ?? __( 'Other', 'eagleeye' ) ); ?></td>
						<td><?php echo nl2br( esc_html( $entry['summary'] ?? '' ) ); ?></td>
						<td>
							<?php
							$user = ! empty( $entry['user'] ) ? get_userdata( (int) $entry['user'] ) : false;
							echo esc_html( $user ? $user->display_name : __( 'Unknown', 'eagleeye' ) );
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php else : ?>
		<p><?php esc_html_e( 'No contact recorded yet.', 'eagleeye' ); ?></p>
	<?php endif; ?>

	<div style="padding:12px;border:1px solid #dcdcde;background:#f6f7f7;margin-bottom:16px">
		<p style="margin-top:0"><strong><?php esc_html_e( 'Log a contact attempt', 'eagleeye' ); ?></strong></p>
		<p>
			<label for="ee-contact-date"><?php esc_html_e( 'Date', 'eagleeye' ); ?></label>
			<input type="date" id="ee-contact-date" name="ee_contact_date" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>">
			<label for="ee-contact-channel" style="margin-left:12px"><?php esc_html_e( 'Method', 'eagleeye' ); ?></label>
			<select id="ee-contact-channel" name="ee_contact_channel">
				<?php foreach ( $channels as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="ee-contact-summary" class="screen-reader-text"><?php esc_html_e( 'What was discussed', 'eagleeye' ); ?></label>
			<textarea name="ee_contact_summary" id="ee-contact-summary" rows="3" class="large-text" placeholder="<?php esc_attr_e( 'e.g. Called reporter; asked for the date and location of the incident.', 'eagleeye' ); ?>"></textarea>
		</p>
		<p class="description" style="margin-top:0"><?php esc_html_e( 'This is added to the contact history when you update the report. Do not record more personal detail than verification needs.', 'eagleeye' ); ?></p>
	</div>

	<h3><?php esc_html_e( 'Next step', 'eagleeye' ); ?></h3>

	<?php if ( $draft_id && get_post( $draft_id ) ) : ?>
		<p>
			<?php
			printf(
				/* translators: %s: draft link. */
				esc_html__( 'Linked draft: %s', 'eagleeye' ),
				'<a href="' . esc_url( (string) get_edit_post_link( $draft_id ) ) . '">' . esc_html( get_the_title( $draft_id ) ) . '</a>'
			);
			?>
		</p>
	<?php endif; ?>

	<p>
		<a class="button button-primary" href="
			<?php
			echo esc_url(
				wp_nonce_url(
					add_query_arg(
						array(
							'action' => 'eagleeye_report_create_draft',
							'report' => $post->ID,
						),
						admin_url( 'admin-post.php' )
					),
					'eagleeye_report_create_draft_' . $post->ID
				)
			);
			?>
		"><?php esc_html_e( 'Create story draft from this report', 'eagleeye' ); ?></a>
		<a class="button" href="
			<?php
			echo esc_url(
				wp_nonce_url(
					add_query_arg(
						array(
							'action' => 'eagleeye_report_export',
							'report' => $post->ID,
						),
						admin_url( 'admin-post.php' )
					),
					'eagleeye_report_export_' . $post->ID
				)
			);
			?>
		"><?php esc_html_e( 'Export report', 'eagleeye' ); ?></a>
	</p>

	<?php if ( $history ) : ?>
		<details style="margin-top:14px">
			<summary><?php esc_html_e( 'Review history', 'eagleeye' ); ?></summary>
			<ul style="margin-left:18px;list-style:disc">
				<?php foreach ( array_reverse( $history ) as $entry ) : ?>
					<li>
						<?php
						$user = ! empty( $entry['user'] ) ? get_userdata( (int) $entry['user'] ) : false;
						printf(
							/* translators: 1: status label, 2: date, 3: user name. */
							esc_html__( '%1$s · %2$s · %3$s', 'eagleeye' ),
							esc_html( eagleeye_report_status_label( (string) ( $entry['status'] ?? 'new' ) ) ),
							esc_html( (string) ( $entry['time'] ?? '' ) ),
							esc_html( $user ? $user->display_name : __( 'Unknown', 'eagleeye' ) )
						);
						?>
					</li>
				<?php endforeach; ?>
			</ul>
		</details>
	<?php endif; ?>

	<p class="description"><?php esc_html_e( 'Erasure is permanent: it deletes the report and its private media. Export first if the newsroom needs a record.', 'eagleeye' ); ?></p>

	<p>
		<a class="button button-link-delete" href="
			<?php
			echo esc_url(
				wp_nonce_url(
					add_query_arg(
						array(
							'action' => 'eagleeye_report_erase',
							'report' => $post->ID,
						),
						admin_url( 'admin-post.php' )
					),
					'eagleeye_report_erase_' . $post->ID
				)
			);
			?>
		" onclick="return window.confirm( '<?php echo esc_js( __( 'Permanently delete this report and its uploaded media? This cannot be undone.', 'eagleeye' ) ); ?>' );"><?php esc_html_e( 'Erase report and media', 'eagleeye' ); ?></a>
	</p>
	<?php
}

/**
 * Show the source report on a story draft.
 *
 * @param WP_Post $post Story.
 */
function eagleeye_report_source_box_html( $post ) {
	$report_id = absint( get_post_meta( $post->ID, '_ee_source_report', true ) );

	if ( ! $report_id || ! get_post( $report_id ) ) {
		return;
	}

	printf(
		'<p><a href="%1$s">%2$s</a></p>',
		esc_url( (string) get_edit_post_link( $report_id ) ),
		esc_html( get_the_title( $report_id ) )
	);
	echo '<p class="description">' . esc_html__( 'Prepared from a citizen report. Verify the facts, attribution and media rights before publishing.', 'eagleeye' ) . '</p>';
}

/**
 * Save status, assignee and notes.
 *
 * @param int $post_id Report ID.
 */
function eagleeye_report_save_review( $post_id ) {
	if ( ! isset( $_POST['eagleeye_report_review_nonce'] ) ) {
		return;
	}

	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['eagleeye_report_review_nonce'] ) ), 'eagleeye_report_review' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( 'eagleeye_report' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( isset( $_POST['ee_review_status'] ) ) {
		eagleeye_report_set_status( $post_id, sanitize_key( wp_unslash( $_POST['ee_review_status'] ) ) );
	}

	if ( isset( $_POST['ee_review_assignee'] ) ) {
		$assignee = absint( $_POST['ee_review_assignee'] );

		if ( $assignee ) {
			$user = get_userdata( $assignee );

			if ( $user && user_can( $user, 'edit_posts' ) ) {
				update_post_meta( $post_id, '_ee_assignee', $assignee );
			}
		} else {
			delete_post_meta( $post_id, '_ee_assignee' );
		}
	}

	if ( isset( $_POST['ee_internal_notes'] ) ) {
		update_post_meta( $post_id, '_ee_internal_notes', sanitize_textarea_field( wp_unslash( $_POST['ee_internal_notes'] ) ) );
	}

	$summary = isset( $_POST['ee_contact_summary'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ee_contact_summary'] ) ) : '';

	if ( $summary ) {
		$channels = eagleeye_report_contact_channels();
		$channel  = isset( $_POST['ee_contact_channel'] ) ? sanitize_key( wp_unslash( $_POST['ee_contact_channel'] ) ) : 'other';
		$date     = isset( $_POST['ee_contact_date'] ) ? sanitize_text_field( wp_unslash( $_POST['ee_contact_date'] ) ) : '';
		$log      = eagleeye_report_contact_log( $post_id );

		$log[] = array(
			'date'    => $date ? $date : current_time( 'Y-m-d' ),
			'channel' => isset( $channels[ $channel ] ) ? $channel : 'other',
			'summary' => $summary,
			'user'    => get_current_user_id(),
		);

		update_post_meta( $post_id, '_ee_contact_log', $log );

		if ( 'new' === eagleeye_report_status( $post_id ) ) {
			eagleeye_report_set_status( $post_id, 'verifying', __( 'Contact logged.', 'eagleeye' ) );
		}
	}
}
add_action( 'save_post', 'eagleeye_report_save_review' );

/**
 * Create a story draft from a report.
 */
function eagleeye_report_handle_create_draft() {
	$post_id = isset( $_GET['report'] ) ? absint( $_GET['report'] ) : 0;

	if ( ! $post_id || 'eagleeye_report' !== get_post_type( $post_id ) ) {
		wp_die( esc_html__( 'Report not found.', 'eagleeye' ), 404 );
	}

	check_admin_referer( 'eagleeye_report_create_draft_' . $post_id );

	if ( ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html__( 'You are not allowed to create a draft from this report.', 'eagleeye' ), 403 );
	}

	$existing = absint( get_post_meta( $post_id, '_ee_draft_id', true ) );

	if ( $existing && get_post( $existing ) ) {
		wp_safe_redirect( add_query_arg( 'ee_notice', 'draft_exists', (string) get_edit_post_link( $post_id, 'raw' ) ) );
		exit;
	}

	$report  = get_post( $post_id );
	$content = (string) $report->post_content;
	$content = '<p><em>' . esc_html__( 'Draft prepared from a citizen report. Verify every fact, name, date and media right before publishing.', 'eagleeye' ) . '</em></p>' . "\n\n" . $content;

	$draft_id = wp_insert_post(
		array(
			'post_type'    => 'post',
			'post_status'  => 'draft',
			'post_title'   => $report->post_title,
			'post_content' => $content,
			'post_author'  => get_current_user_id(),
		),
		true
	);

	if ( is_wp_error( $draft_id ) || ! $draft_id ) {
		wp_die( esc_html__( 'The draft could not be created.', 'eagleeye' ), 500 );
	}

	update_post_meta( $draft_id, '_ee_source_report', $post_id );
	update_post_meta( $post_id, '_ee_draft_id', $draft_id );

	$log   = get_post_meta( $post_id, '_ee_status_log', true );
	$log   = is_array( $log ) ? $log : array();
	$log[] = array(
		'status' => eagleeye_report_status( $post_id ),
		'from'   => eagleeye_report_status( $post_id ),
		'user'   => get_current_user_id(),
		'time'   => current_time( 'mysql' ),
		'note'   => __( 'Story draft created.', 'eagleeye' ),
	);

	update_post_meta( $post_id, '_ee_status_log', $log );

	wp_safe_redirect(
		add_query_arg(
			'ee_notice',
			'draft',
			add_query_arg(
				array(
					'post'   => $draft_id,
					'action' => 'edit',
				),
				admin_url( 'post.php' )
			)
		)
	);
	exit;
}
add_action( 'admin_post_eagleeye_report_create_draft', 'eagleeye_report_handle_create_draft' );

/**
 * Export one report as JSON for the newsroom record.
 */
function eagleeye_report_handle_export() {
	$post_id = isset( $_GET['report'] ) ? absint( $_GET['report'] ) : 0;

	if ( ! $post_id || 'eagleeye_report' !== get_post_type( $post_id ) ) {
		wp_die( esc_html__( 'Report not found.', 'eagleeye' ), 404 );
	}

	check_admin_referer( 'eagleeye_report_export_' . $post_id );

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		wp_die( esc_html__( 'You are not allowed to export this report.', 'eagleeye' ), 403 );
	}

	$files = get_post_meta( $post_id, '_ee_files', true );
	$names = array();

	if ( is_array( $files ) ) {
		foreach ( $files as $file ) {
			$names[] = $file['name'] ?? '';
		}
	}

	$data = array(
		'report_id'   => $post_id,
		'title'       => get_the_title( $post_id ),
		'status'      => eagleeye_report_status( $post_id ),
		'assignee'    => eagleeye_report_assignee( $post_id ),
		'submitted'   => get_post_meta( $post_id, '_ee_submitted_at', true ),
		'section'     => get_post_meta( $post_id, '_ee_report_type', true ),
		'name'        => get_post_meta( $post_id, '_ee_name', true ),
		'phone'       => get_post_meta( $post_id, '_ee_phone', true ),
		'community'   => get_post_meta( $post_id, '_ee_community', true ),
		'address'     => get_post_meta( $post_id, '_ee_address', true ),
		'lga'         => get_post_meta( $post_id, '_ee_lga', true ),
		'school'      => get_post_meta( $post_id, '_ee_school', true ),
		'description' => get_post_field( 'post_content', $post_id, 'raw' ),
		'suggested'   => get_post_meta( $post_id, '_ee_action', true ),
		'notes'       => eagleeye_report_notes( $post_id ),
		'contacts'    => eagleeye_report_contact_log( $post_id ),
		'files'       => $names,
		'exported_at' => current_time( 'mysql' ),
	);

	nocache_headers();
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="report-' . $post_id . '.json"' );
	header( 'X-Content-Type-Options: nosniff' );
	echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	exit;
}
add_action( 'admin_post_eagleeye_report_export', 'eagleeye_report_handle_export' );

/**
 * Permanently erase a report and its private media.
 */
function eagleeye_report_handle_erase() {
	$post_id = isset( $_GET['report'] ) ? absint( $_GET['report'] ) : 0;

	if ( ! $post_id || 'eagleeye_report' !== get_post_type( $post_id ) ) {
		wp_die( esc_html__( 'Report not found.', 'eagleeye' ), 404 );
	}

	check_admin_referer( 'eagleeye_report_erase_' . $post_id );

	if ( ! current_user_can( 'delete_post', $post_id ) ) {
		wp_die( esc_html__( 'Only an administrator can erase a report.', 'eagleeye' ), 403 );
	}

	eagleeye_citizen_report_delete_files( $post_id );
	wp_delete_post( $post_id, true );

	wp_safe_redirect( add_query_arg( 'ee_notice', 'erased', admin_url( 'edit.php?post_type=eagleeye_report' ) ) );
	exit;
}
add_action( 'admin_post_eagleeye_report_erase', 'eagleeye_report_handle_erase' );

/**
 * Mark the source report as published when its draft goes live.
 *
 * @param string  $new_status New status.
 * @param string  $old_status Old status.
 * @param WP_Post $post       Post.
 */
function eagleeye_report_mark_published( $new_status, $old_status, $post ) {
	if ( 'publish' !== $new_status || 'publish' === $old_status || 'post' !== $post->post_type ) {
		return;
	}

	$report_id = absint( get_post_meta( $post->ID, '_ee_source_report', true ) );

	if ( ! $report_id || 'eagleeye_report' !== get_post_type( $report_id ) ) {
		return;
	}

	update_post_meta( $report_id, '_ee_published_url', get_permalink( $post->ID ) );
	eagleeye_report_set_status( $report_id, 'published', __( 'Linked story published.', 'eagleeye' ) );
}
add_action( 'transition_post_status', 'eagleeye_report_mark_published', 10, 3 );
