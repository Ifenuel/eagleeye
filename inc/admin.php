<?php
/**
 * Newsroom admin experience.
 *
 * @package Eagle Eye
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Give trusted newsroom staff the editorial and operational access they need
 * without granting WordPress configuration, theme, plugin, or user management.
 */
function eagleeye_register_newsroom_role() {
	if ( '1.0.1' === get_option( 'eagleeye_newsroom_role_version' ) ) {
		return;
	}

	$capabilities = array(
		'read'                    => true,
		'edit_posts'              => true,
		'edit_others_posts'       => true,
		'edit_published_posts'    => true,
		'edit_private_posts'      => true,
		'publish_posts'           => true,
		'upload_files'            => true,
		'manage_categories'       => true,
		'moderate_comments'       => true,
		'read_private_posts'      => true,
		'edit_pages'              => true,
		'edit_others_pages'       => true,
		'edit_published_pages'    => true,
		'publish_pages'           => true,
		'manage_eagleeye_newsletter' => true,
		'manage_eagleeye_ad_approval' => false,
	);

	$role = get_role( 'eagleeye_newsroom' );

	if ( ! $role ) {
		$role = add_role( 'eagleeye_newsroom', __( 'Eagle Eye Newsroom', 'eagleeye' ), $capabilities );
	}

	if ( $role ) {
		foreach ( $capabilities as $capability => $grant ) {
			if ( $grant ) {
				$role->add_cap( $capability );
			}
		}
	}

	$administrator = get_role( 'administrator' );
	if ( $administrator ) {
		$administrator->add_cap( 'manage_eagleeye_newsletter' );
		$administrator->add_cap( 'manage_eagleeye_ad_approval' );
	}

	update_option( 'eagleeye_newsroom_role_version', '1.0.1', false );
}
add_action( 'init', 'eagleeye_register_newsroom_role', 2 );

/**
 * Top-level Eagle Eye menu and newsroom tools.
 */
function eagleeye_admin_menu() {
	add_menu_page(
		__( 'Eagle Eye Newsroom', 'eagleeye' ),
		__( 'Newsroom', 'eagleeye' ),
		'edit_posts',
		'eagleeye',
		'eagleeye_newsroom_page',
		'dashicons-awards',
		3
	);

	add_submenu_page(
		'eagleeye',
		__( 'Newsroom overview', 'eagleeye' ),
		__( 'Overview', 'eagleeye' ),
		'edit_posts',
		'eagleeye',
		'eagleeye_newsroom_page'
	);

	add_submenu_page(
		'eagleeye',
		__( 'Newsletter', 'eagleeye' ),
		__( 'Newsletter', 'eagleeye' ),
		'manage_eagleeye_newsletter',
		'eagleeye-newsletter',
		'eagleeye_newsletter_admin_page'
	);

	add_submenu_page(
		'eagleeye',
		__( 'Analytics', 'eagleeye' ),
		__( 'Analytics', 'eagleeye' ),
		'edit_posts',
		'eagleeye-analytics',
		'eagleeye_analytics_dashboard_redirect'
	);

	add_submenu_page(
		'eagleeye',
		__( 'Rate card', 'eagleeye' ),
		__( 'Rate card', 'eagleeye' ),
		'manage_options',
		'eagleeye-rates',
		'eagleeye_rate_card_page'
	);

	add_submenu_page(
		'eagleeye',
		__( 'Email & notifications', 'eagleeye' ),
		__( 'Email settings', 'eagleeye' ),
		'manage_options',
		'eagleeye-email',
		'eagleeye_mail_settings_page'
	);
}
add_action( 'admin_menu', 'eagleeye_admin_menu' );

/**
 * Send the Analytics submenu to Site Kit before any admin output is sent.
 *
 * The submenu callback runs after the admin header is printed, where a
 * redirect can no longer be sent. This runs on admin_init instead.
 */
function eagleeye_analytics_dashboard_early_redirect() {
	if ( empty( $_GET['page'] ) || 'eagleeye-analytics' !== $_GET['page'] ) {
		return;
	}

	eagleeye_analytics_dashboard_redirect();
}
add_action( 'admin_init', 'eagleeye_analytics_dashboard_early_redirect' );

/**
 * Open the Site Kit analytics dashboard without exposing its settings pages.
 */
function eagleeye_analytics_dashboard_redirect() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html__( 'You do not have access to newsroom analytics.', 'eagleeye' ) );
	}

	wp_safe_redirect( admin_url( 'admin.php?page=googlesitekit-dashboard' ) );
	exit;
}

/**
 * Active paid bookings.
 *
 * @return int
 */
function eagleeye_active_ad_count() {
	$query = new WP_Query( array(
		'post_type'      => 'ee_ad',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => array(
			array(
				'key'   => '_ee_status',
				'value' => 'paid',
			),
		),
	) );

	$count = 0;

	foreach ( $query->posts as $id ) {
		if ( eagleeye_ad_is_live( $id ) ) {
			$count++;
		}
	}

	return $count;
}

/**
 * Booked advertising value.
 *
 * @return int
 */
function eagleeye_booked_value() {
	$query = new WP_Query( array(
		'post_type'      => 'ee_ad',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	) );

	$total = 0;

	foreach ( $query->posts as $id ) {
		$total += (int) get_post_meta( $id, '_ee_amount', true );
	}

	return $total;
}

/**
 * Newsroom dashboard.
 */
function eagleeye_newsroom_page() {
	$counts    = wp_count_posts( 'post' );
	$drafts    = isset( $counts->draft ) ? (int) $counts->draft : 0;
	$pub       = isset( $counts->publish ) ? (int) $counts->publish : 0;
	$scheduled = isset( $counts->future ) ? (int) $counts->future : 0;

	global $wpdb;
	$subs_table = eagleeye_subscribers_table();
	$subs       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$subs_table} WHERE status = 'subscribed'" ); // phpcs:ignore WordPress.DB
	$pending    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$subs_table} WHERE status = 'pending'" ); // phpcs:ignore WordPress.DB

	$requests = new WP_Query( array(
		'post_type'      => 'ee_request',
		'post_status'    => 'private',
		'posts_per_page' => 3,
	) );

	$request_total = (int) wp_count_posts( 'ee_request' )->private;
	$user          = wp_get_current_user();
	$recent        = get_posts( array( 'numberposts' => 6, 'post_status' => array( 'publish', 'draft', 'future' ) ) );
	$customize     = admin_url( 'customize.php' );
	?>
	<div class="wrap">
		<div class="ee-page-head">
			<div>
				<h1><?php esc_html_e( 'Newsroom', 'eagleeye' ); ?></h1>
				<p class="ee-page-head__sub"><?php esc_html_e( 'Everything you publish, sell and send, in one place.', 'eagleeye' ); ?></p>
			</div>
			<p class="ee-page-head__actions">
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=eagleeye-write' ) ); ?>"><?php esc_html_e( '＋ Write a story', 'eagleeye' ); ?></a>
				<a class="button" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View site', 'eagleeye' ); ?></a>
			</p>
		</div>
		<p class="ee-hello"><?php echo esc_html( sprintf( __( 'Good to see you, %s · %s', 'eagleeye' ), $user->display_name, date_i18n( 'l, j F Y' ) ) ); ?></p>

		<div class="ee-quick">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=eagleeye-write' ) ); ?>">
				<strong><?php esc_html_e( 'Write a story', 'eagleeye' ); ?></strong>
				<span><?php esc_html_e( 'Headline, section, standfirst, publish.', 'eagleeye' ); ?></span>
			</a>
			<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=ee_ad' ) ); ?>">
				<strong><?php esc_html_e( 'Book an advertisement', 'eagleeye' ); ?></strong>
				<span><?php esc_html_e( 'Only confirmed, paid bookings are published.', 'eagleeye' ); ?></span>
			</a>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=ee_request' ) ); ?>">
				<strong><?php esc_html_e( 'Media kit requests', 'eagleeye' ); ?></strong>
				<span><?php esc_html_e( 'Quote, reference and payment status.', 'eagleeye' ); ?></span>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=eagleeye-sections' ) ); ?>">
				<strong><?php esc_html_e( 'Sections', 'eagleeye' ); ?></strong>
				<span><?php esc_html_e( 'Every section and its story count.', 'eagleeye' ); ?></span>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=eagleeye-analytics' ) ); ?>">
				<strong><?php esc_html_e( 'Analytics', 'eagleeye' ); ?></strong>
				<span><?php esc_html_e( 'Audience, search and story performance.', 'eagleeye' ); ?></span>
			</a>
		</div>

		<div class="ee-dash">
			<div class="ee-dash__stat">
				<span><?php esc_html_e( 'Published', 'eagleeye' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( $pub ) ); ?></strong>
				<em><?php printf( esc_html__( '%s scheduled · %s drafts', 'eagleeye' ), esc_html( number_format_i18n( $scheduled ) ), esc_html( number_format_i18n( $drafts ) ) ); ?></em>
			</div>
			<div class="ee-dash__stat">
				<span><?php esc_html_e( 'Confirmed subscribers', 'eagleeye' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( $subs ) ); ?></strong>
				<em><?php printf( esc_html__( '%s awaiting confirmation', 'eagleeye' ), esc_html( number_format_i18n( $pending ) ) ); ?></em>
			</div>
			<div class="ee-dash__stat">
				<span><?php esc_html_e( 'Ads live now', 'eagleeye' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( eagleeye_active_ad_count() ) ); ?></strong>
				<em><?php echo esc_html( sprintf( __( '%s booked in total', 'eagleeye' ), eagleeye_naira( eagleeye_booked_value() ) ) ); ?></em>
			</div>
			<div class="ee-dash__stat">
				<span><?php esc_html_e( 'Media kit requests', 'eagleeye' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( $request_total ) ); ?></strong>
				<em><?php esc_html_e( 'Quotes, invoices and status', 'eagleeye' ); ?></em>
			</div>
		</div>

		<?php
		if ( function_exists( 'eagleeye_report_status_counts' ) ) :
			$report_counts = eagleeye_report_status_counts();
			$waiting       = (int) $report_counts['new'] + (int) $report_counts['verifying'] + (int) $report_counts['needs_info'];
			$response      = eagleeye_report_response_hours();
			?>
			<div class="card" style="max-width:none;margin:0 0 24px;padding:20px">
				<h2 style="margin-top:0"><?php esc_html_e( 'Citizen reports', 'eagleeye' ); ?></h2>
				<p style="margin-top:0">
					<strong><?php echo esc_html( number_format_i18n( $waiting ) ); ?></strong>
					<?php esc_html_e( 'leads still need a decision.', 'eagleeye' ); ?>
					<?php
					printf(
						esc_html__( '%1$d new · %2$d verifying · %3$d awaiting information · %4$d approved · %5$d published · %6$d rejected', 'eagleeye' ),
						(int) $report_counts['new'],
						(int) $report_counts['verifying'],
						(int) $report_counts['needs_info'],
						(int) $report_counts['approved'],
						(int) $report_counts['published'],
						(int) $report_counts['rejected']
					);
					?>
				</p>
				<p class="description">
					<?php
					if ( null === $response ) {
						esc_html_e( 'No report has been reviewed yet, so there is no response time to show.', 'eagleeye' );
					} else {
						printf( esc_html__( 'Average time from submission to first review: %s hours.', 'eagleeye' ), esc_html( number_format_i18n( $response, 1 ) ) );
					}
					?>
				</p>
				<p>
					<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=eagleeye_report' ) ); ?>"><?php esc_html_e( 'Open the report inbox', 'eagleeye' ); ?></a>
					<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=eagleeye_report&ee_status=new' ) ); ?>"><?php esc_html_e( 'Unreviewed reports', 'eagleeye' ); ?></a>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=eagleeye-coverage' ) ); ?>"><?php esc_html_e( 'Coverage plan', 'eagleeye' ); ?></a>
				</p>
			</div>
		<?php endif; ?>

		<div class="ee-guide">
			<div class="ee-guide__intro">
				<span><?php esc_html_e( 'Start here', 'eagleeye' ); ?></span>
				<h2><?php esc_html_e( 'Publishing takes three steps', 'eagleeye' ); ?></h2>
				<p><?php esc_html_e( 'You never need to touch the WordPress editor if you do not want to. The composer handles the headline, standfirst, section, byline, image, tags and schedule.', 'eagleeye' ); ?></p>
			</div>
			<ol class="ee-guide__steps">
				<li>
					<strong><?php esc_html_e( 'Write the story', 'eagleeye' ); ?></strong>
					<span><?php esc_html_e( 'Headline, standfirst, byline and body in the composer.', 'eagleeye' ); ?></span>
				</li>
				<li>
					<strong><?php esc_html_e( 'Choose the section', 'eagleeye' ); ?></strong>
					<span><?php esc_html_e( 'Politics, Business, Sports and the rest. This puts it in the right section.', 'eagleeye' ); ?></span>
				</li>
				<li>
					<strong><?php esc_html_e( 'Publish or schedule', 'eagleeye' ); ?></strong>
					<span><?php esc_html_e( 'Go live immediately, save a draft, or set a future time.', 'eagleeye' ); ?></span>
				</li>
			</ol>
			<p class="ee-guide__cta"><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=eagleeye-write' ) ); ?>"><?php esc_html_e( 'Open the composer', 'eagleeye' ); ?></a></p>
		</div>

		<div class="ee-dash__grid">
			<div class="card" style="max-width:none;margin:0;padding:20px">
				<h2 style="margin-top:0"><?php esc_html_e( 'Recent stories', 'eagleeye' ); ?></h2>
				<?php if ( $recent ) : ?>
					<table class="widefat striped" style="border:0">
						<tbody>
							<?php foreach ( $recent as $story ) : ?>
								<tr>
									<td><a href="<?php echo esc_url( get_edit_post_link( $story->ID ) ); ?>"><?php echo esc_html( wp_trim_words( $story->post_title, 10 ) ); ?></a></td>
									<td style="width:90px;color:#8d877b"><?php echo esc_html( ucfirst( $story->post_status ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p><?php esc_html_e( 'No stories yet.', 'eagleeye' ); ?></p>
				<?php endif; ?>
				<p><a class="button" href="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>"><?php esc_html_e( 'All stories', 'eagleeye' ); ?></a></p>
			</div>

			<div class="card" style="max-width:none;margin:0;padding:20px">
				<h2 style="margin-top:0"><?php esc_html_e( 'Latest media kit requests', 'eagleeye' ); ?></h2>
				<?php if ( $requests->have_posts() ) : ?>
					<table class="widefat striped" style="border:0">
						<tbody>
							<?php while ( $requests->have_posts() ) : $requests->the_post(); ?>
								<tr>
									<td><a href="<?php echo esc_url( get_edit_post_link() ); ?>"><?php echo esc_html( get_post_meta( get_the_ID(), '_ee_company', true ) ?: get_the_title() ); ?></a></td>
									<td><?php echo esc_html( get_post_meta( get_the_ID(), '_ee_package_label', true ) ?: '—' ); ?></td>
									<td style="white-space:nowrap"><?php echo esc_html( eagleeye_naira( (int) get_post_meta( get_the_ID(), '_ee_amount', true ) ) ); ?></td>
								</tr>
							<?php endwhile; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p><?php esc_html_e( 'No media kit requests yet. They arrive from the Advertise page.', 'eagleeye' ); ?></p>
				<?php endif; ?>
				<?php wp_reset_postdata(); ?>
				<p><a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=ee_request' ) ); ?>"><?php esc_html_e( 'Open requests', 'eagleeye' ); ?></a></p>
			</div>
		</div>

		<h2 style="margin-top:34px"><?php esc_html_e( 'Everything else', 'eagleeye' ); ?></h2>
		<p class="ee-shortcuts">
			<a class="button" href="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>"><?php esc_html_e( 'All stories', 'eagleeye' ); ?></a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=eagleeye-sections' ) ); ?>"><?php esc_html_e( 'Sections', 'eagleeye' ); ?></a>
			<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=ee_ad' ) ); ?>"><?php esc_html_e( 'Advertisements', 'eagleeye' ); ?></a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=eagleeye-newsletter' ) ); ?>"><?php esc_html_e( 'Newsletter', 'eagleeye' ); ?></a>
			<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=page' ) ); ?>"><?php esc_html_e( 'Pages & legal', 'eagleeye' ); ?></a>
			<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=eagleeye-rates' ) ); ?>"><?php esc_html_e( 'Rate card', 'eagleeye' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=eagleeye-email' ) ); ?>"><?php esc_html_e( 'Email settings', 'eagleeye' ); ?></a>
				<a class="button" href="<?php echo esc_url( $customize ); ?>"><?php esc_html_e( 'Design & homepage', 'eagleeye' ); ?></a>
			<?php endif; ?>
		</p>
		<p class="description"><?php esc_html_e( 'Approved rates and bank details live under Rate card. Edit the Privacy Policy, Terms and other legal pages under Pages & legal.', 'eagleeye' ); ?></p>
	</div>
	<?php
}

/**
 * Dashboard widget for at-a-glance numbers.
 */
function eagleeye_dashboard_widget() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}

	wp_add_dashboard_widget( 'eagleeye_newsroom_widget', __( 'Eagle Eye newsroom', 'eagleeye' ), 'eagleeye_dashboard_widget_render' );
}
add_action( 'wp_dashboard_setup', 'eagleeye_dashboard_widget' );

/**
 * Dashboard widget content.
 */
function eagleeye_dashboard_widget_render() {
	global $wpdb;
	$table = eagleeye_subscribers_table();
	$subs  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'subscribed'" ); // phpcs:ignore WordPress.DB
	$pub   = (int) wp_count_posts( 'post' )->publish;
	?>
	<ul style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:0 0 14px">
		<li style="background:#f6f7f7;padding:12px"><strong style="font-size:22px;display:block"><?php echo esc_html( number_format_i18n( $pub ) ); ?></strong><?php esc_html_e( 'stories live', 'eagleeye' ); ?></li>
		<li style="background:#f6f7f7;padding:12px"><strong style="font-size:22px;display:block"><?php echo esc_html( number_format_i18n( $subs ) ); ?></strong><?php esc_html_e( 'subscribers', 'eagleeye' ); ?></li>
		<li style="background:#f6f7f7;padding:12px"><strong style="font-size:22px;display:block"><?php echo esc_html( number_format_i18n( eagleeye_active_ad_count() ) ); ?></strong><?php esc_html_e( 'ads live', 'eagleeye' ); ?></li>
		<li style="background:#f6f7f7;padding:12px"><strong style="font-size:22px;display:block"><?php echo esc_html( number_format_i18n( (int) wp_count_posts( 'ee_request' )->private ) ); ?></strong><?php esc_html_e( 'media kit requests', 'eagleeye' ); ?></li>
	</ul>
	<p style="margin:0">
		<a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php' ) ); ?>"><?php esc_html_e( 'Write a story', 'eagleeye' ); ?></a>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=eagleeye' ) ); ?>"><?php esc_html_e( 'Newsroom', 'eagleeye' ); ?></a>
	</p>
	<?php
}
