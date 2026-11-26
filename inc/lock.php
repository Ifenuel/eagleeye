<?php
/**
 * Newsroom screen lock.
 *
 * When the dashboard sits idle, the session is locked on the server. The lock
 * is recorded as a transient keyed to the user's session token, so a reload or
 * a direct URL cannot get past it: no dashboard markup is sent until the
 * password is entered again.
 *
 * @package Eagle Eye
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * How long the dashboard may sit idle before it locks, in seconds.
 *
 * @return int
 */
function eagleeye_lock_timeout() {
	$minutes = (int) apply_filters( 'eagleeye_lock_minutes', 60 );

	if ( $minutes < 1 ) {
		$minutes = 1;
	}

	return $minutes * MINUTE_IN_SECONDS;
}

/**
 * Transient key for the current user + session.
 *
 * @return string
 */
function eagleeye_lock_key() {
	$token = wp_get_session_token();

	return 'ee_lock_' . get_current_user_id() . '_' . substr( md5( (string) $token ), 0, 16 );
}

/**
 * Is the current session locked?
 *
 * @return bool
 */
function eagleeye_is_locked() {
	return (bool) get_transient( eagleeye_lock_key() );
}

/**
 * Should this request be allowed through?
 *
 * @return bool
 */
function eagleeye_lock_applies() {
	if ( ! is_user_logged_in() || wp_doing_ajax() || wp_doing_cron() ) {
		return false;
	}

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		return false;
	}

	if ( ! current_user_can( 'edit_posts' ) ) {
		return false;
	}

	return true;
}

/**
 * Block the dashboard while locked.
 */
function eagleeye_enforce_lock() {
	if ( ! eagleeye_lock_applies() || ! eagleeye_is_locked() ) {
		return;
	}

	nocache_headers();
	eagleeye_render_lock_screen();
	exit;
}
add_action( 'admin_init', 'eagleeye_enforce_lock', 1 );

/**
 * The lock screen itself.
 */
function eagleeye_render_lock_screen() {
	$user  = wp_get_current_user();
	$photo = eagleeye_user_photo_url( $user->ID, array( 128, 128 ) );
	$photo = $photo ? $photo : get_avatar_url( $user->ID, array( 'size' => 128 ) );
	$name  = $user->display_name ? $user->display_name : $user->user_login;
	$mark  = eagleeye_brand_image( 'light' );
	$icon  = $mark ? $mark['url'] : get_template_directory_uri() . '/assets/img/logo-mark.png';
	?>
	<!DOCTYPE html>
	<html <?php language_attributes(); ?>>
	<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="robots" content="noindex, nofollow">
		<title><?php esc_html_e( 'Newsroom locked', 'eagleeye' ); ?> — <?php bloginfo( 'name' ); ?></title>
		<style>
			*{box-sizing:border-box}
			html,body{height:100%}
			body{
				margin:0;
				display:flex;
				align-items:center;
				justify-content:center;
				padding:24px;
				background:#08080a;
				background-image:radial-gradient(circle at 20% 10%, rgba(224,27,47,.22), transparent 45%),radial-gradient(circle at 85% 90%, rgba(246,194,28,.14), transparent 50%);
				font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;
				color:#f2eee5;
			}
			.lock{
				width:100%;
				max-width:420px;
				background:rgba(18,18,21,.92);
				border:1px solid #26262c;
				border-top:4px solid #f6c21c;
				padding:38px 34px 30px;
				text-align:center;
			}
			.lock__mark{display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:26px}
			.lock__mark img{width:auto;height:28px;display:block}
			.lock__mark span{font-size:11px;font-weight:700;letter-spacing:.24em;color:#f6c21c;text-transform:uppercase}
			.lock__photo{
				width:96px;height:96px;border-radius:50%;object-fit:cover;
				border:2px solid #f6c21c;display:block;margin:0 auto 16px;
				filter:grayscale(.15);
			}
			.lock h1{margin:0 0 6px;font-family:Georgia,serif;font-size:25px;line-height:1.2;color:#fff;letter-spacing:-.01em}
			.lock p{margin:0 0 22px;font-size:13.5px;line-height:1.6;color:#a8a294}
			.lock label{display:block;text-align:left;font-size:10.5px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:#cfc9bc;margin-bottom:8px}
			.lock input[type=password]{
				width:100%;padding:13px 14px;background:#0c0c0e;color:#fff;
				border:1px solid #3a3a42;border-radius:2px;font-size:15px;
				font-family:inherit;
			}
			.lock input[type=password]:focus{outline:none;border-color:#f6c21c;box-shadow:0 0 0 2px rgba(246,194,28,.28)}
			.lock button{
				width:100%;margin-top:14px;padding:14px 18px;border:0;border-radius:2px;
				background:#e01b2f;color:#fff;font-size:12px;font-weight:700;
				letter-spacing:.14em;text-transform:uppercase;cursor:pointer;font-family:inherit;
			}
			.lock button:hover{background:#fff;color:#08080a}
			.lock button:disabled{background:#4a4a52;color:#9a9aa2;cursor:not-allowed}
			.lock__error{display:none;margin:14px 0 0;padding:11px 13px;background:rgba(224,27,47,.14);border-left:3px solid #e01b2f;color:#ffb3bb;font-size:13px;text-align:left}
			.lock__error.is-on{display:block}
			.lock__foot{margin-top:24px;padding-top:18px;border-top:1px solid #26262c;font-size:12px;color:#8d877b}
			.lock__foot a{color:#cfc9bc;text-decoration:none;border-bottom:1px solid #3a3a42}
			.lock__foot a:hover{color:#f6c21c}
		</style>
	</head>
	<body>
		<main class="lock">
			<div class="lock__mark">
				<img src="<?php echo esc_url( $icon ); ?>" alt="">
				<span><?php esc_html_e( 'Eagle Eye Newsroom', 'eagleeye' ); ?></span>
			</div>

			<img class="lock__photo" src="<?php echo esc_attr( $photo ); ?>" alt="">

			<h1><?php esc_html_e( 'Locked for your security', 'eagleeye' ); ?></h1>
			<p><?php echo esc_html( sprintf( /* translators: %s: display name. */ __( 'Welcome back, %s. Enter your password to carry on where you stopped.', 'eagleeye' ), $name ) ); ?></p>

			<form id="ee-lock-form" autocomplete="off">
				<label for="ee-lock-pass"><?php esc_html_e( 'Password', 'eagleeye' ); ?></label>
				<input type="password" id="ee-lock-pass" name="password" autocomplete="current-password" required>
				<div class="lock__error" id="ee-lock-error" role="alert"></div>
				<button type="submit" id="ee-lock-submit"><?php esc_html_e( 'Unlock newsroom', 'eagleeye' ); ?></button>
			</form>

			<div class="lock__foot">
				<a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Sign out instead', 'eagleeye' ); ?></a>
			</div>
		</main>

		<script>
			(function () {
				var form = document.getElementById('ee-lock-form');
				var input = document.getElementById('ee-lock-pass');
				var errorBox = document.getElementById('ee-lock-error');
				var button = document.getElementById('ee-lock-submit');

				input.focus();

				form.addEventListener('submit', function (event) {
					event.preventDefault();
					button.disabled = true;
					errorBox.classList.remove('is-on');

					var body = new URLSearchParams();
					body.append('action', 'eagleeye_unlock');
					body.append('nonce', '<?php echo esc_js( wp_create_nonce( 'eagleeye_unlock' ) ); ?>');
					body.append('password', input.value);

					fetch('<?php echo esc_url_raw( admin_url( 'admin-ajax.php' ) ); ?>', {
						method: 'POST',
						credentials: 'same-origin',
						body: body
					})
						.then(function (r) { return r.json(); })
						.then(function (data) {
							if (data && data.success) {
								window.location.reload();
								return;
							}

							errorBox.textContent = (data && data.data && data.data.message) ? data.data.message : 'That password did not work.';
							errorBox.classList.add('is-on');
							input.value = '';
							input.focus();
							button.disabled = false;
						})
						.catch(function () {
							errorBox.textContent = 'Could not reach the server. Check your connection and try again.';
							errorBox.classList.add('is-on');
							button.disabled = false;
						});
				});
			})();
		</script>
	</body>
	</html>
	<?php
}

/**
 * Lock the current session (AJAX).
 */
function eagleeye_ajax_lock() {
	check_ajax_referer( 'eagleeye_lock', 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => __( 'Your session has expired. Please sign in again.', 'eagleeye' ) ), 401 );
	}

	set_transient( eagleeye_lock_key(), time(), 30 * MINUTE_IN_SECONDS );
	delete_transient( eagleeye_lock_attempts_key() );

	wp_send_json_success( array( 'locked' => true ) );
}
add_action( 'wp_ajax_eagleeye_lock', 'eagleeye_ajax_lock' );

/**
 * Failed unlock attempts key.
 *
 * @return string
 */
function eagleeye_lock_attempts_key() {
	return 'ee_lock_fails_' . get_current_user_id();
}

/**
 * Unlock the current session (AJAX).
 */
function eagleeye_ajax_unlock() {
	check_ajax_referer( 'eagleeye_unlock', 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => __( 'Your session has expired. Please sign in again.', 'eagleeye' ) ), 401 );
	}

	$attempts = (int) get_transient( eagleeye_lock_attempts_key() );

	if ( $attempts >= 5 ) {
		wp_send_json_error( array( 'message' => __( 'Too many attempts. Wait five minutes, or sign out and sign back in.', 'eagleeye' ) ), 429 );
	}

	$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
	$user     = wp_get_current_user();

	if ( '' === $password || ! wp_check_password( $password, $user->user_pass, $user->ID ) ) {
		set_transient( eagleeye_lock_attempts_key(), $attempts + 1, 5 * MINUTE_IN_SECONDS );
		wp_send_json_error( array( 'message' => __( 'That password did not work. Try again.', 'eagleeye' ) ), 403 );
	}

	delete_transient( eagleeye_lock_key() );
	delete_transient( eagleeye_lock_attempts_key() );

	wp_send_json_success( array( 'unlocked' => true ) );
}
add_action( 'wp_ajax_eagleeye_unlock', 'eagleeye_ajax_unlock' );

/**
 * "Lock now" link in the admin bar.
 *
 * @param WP_Admin_Bar $bar Admin bar.
 */
function eagleeye_lock_bar_node( $bar ) {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}

	$bar->add_node( array(
		'id'     => 'eagleeye-lock',
		'parent' => 'top-secondary',
		'title'  => __( 'Lock', 'eagleeye' ),
		'href'   => '#',
		'meta'   => array(
			'class' => 'ee-lock-now',
			'title' => __( 'Lock the newsroom now', 'eagleeye' ),
		),
	) );
}
add_action( 'admin_bar_menu', 'eagleeye_lock_bar_node', 90 );

/**
 * Idle timer.
 */
function eagleeye_lock_script() {
	if ( ! eagleeye_lock_applies() ) {
		return;
	}

	$timeout = eagleeye_lock_timeout();
	?>
	<script>
		(function () {
			var idleFor = 0;
			var limit = <?php echo (int) $timeout * 1000; ?>;
			var step = 5000;
			var busy = false;

			function lockNow() {
				if (busy) {
					return;
				}

				busy = true;

				var body = new URLSearchParams();
				body.append('action', 'eagleeye_lock');
				body.append('nonce', '<?php echo esc_js( wp_create_nonce( 'eagleeye_lock' ) ); ?>');

				fetch('<?php echo esc_url_raw( admin_url( 'admin-ajax.php' ) ); ?>', {
					method: 'POST',
					credentials: 'same-origin',
					body: body
				}).then(function () {
					window.location.reload();
				}).catch(function () {
					busy = false;
				});
			}

			setInterval(function () {
				if (busy) {
					return;
				}

				idleFor += step;

				if (idleFor >= limit) {
					lockNow();
				}
			}, step);

			function reset() {
				idleFor = 0;
			}

			['mousemove', 'mousedown', 'keydown', 'wheel', 'touchstart', 'scroll'].forEach(function (name) {
				window.addEventListener(name, reset, { passive: true });
			});

			document.addEventListener('visibilitychange', function () {
				if (!document.hidden) {
					reset();
				}
			});

			document.addEventListener('click', function (event) {
				var link = event.target.closest('.ee-lock-now');

				if (link) {
					event.preventDefault();
					lockNow();
				}
			});
		})();
	</script>
	<?php
}
add_action( 'admin_footer', 'eagleeye_lock_script', 99 );
