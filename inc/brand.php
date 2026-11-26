<?php
/**
 * Brand assets: the publisher's logo, favicon and login screen.
 *
 * @package Eagle Eye
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Locate a brand image dropped into /brand/.
 *
 * @param string $variant dark (header) or light (footer).
 * @return array{url:string,width:int,height:int,path:string}|array
 */
function eagleeye_brand_image( $variant = 'dark' ) {
	$names = 'light' === $variant
		? array( 'eagleeye-logo-light.png', 'eagleeye-logo-light.svg' )
		: array( 'eagleeye-logo-trim.png', 'eagleeye-logo.png', 'eagleeye-logo.jpg', 'eagleeye-logo.jpeg', 'eagleeye-logo.webp', 'eagleeye-logo.svg' );

	foreach ( $names as $name ) {
		$path = EAGLEEYE_DIR . '/brand/' . $name;

		if ( ! file_exists( $path ) ) {
			continue;
		}

		$size = @getimagesize( $path );

		return array(
			'url'    => add_query_arg( 'ver', (string) filemtime( $path ), EAGLEEYE_URI . '/brand/' . $name ),
			'width'  => $size ? (int) $size[0] : 0,
			'height' => $size ? (int) $size[1] : 0,
			'path'   => $path,
		);
	}

	return array();
}

/**
 * The publisher's primary logo used on light surfaces.
 *
 * @return array{url:string,width:int,height:int,path:string}|array
 */
function eagleeye_brand_mark() {
	return eagleeye_brand_image( 'dark' );
}

/**
 * Square brand symbol for the site icon, app icon and circular seal.
 *
 * @return array{url:string,width:int,height:int,path:string}|array
 */
function eagleeye_brand_symbol() {
	$path = EAGLEEYE_DIR . '/brand/eagleeye-brand-icon-512.png';

	if ( ! file_exists( $path ) ) {
		return array();
	}

	$size = @getimagesize( $path );

	return array(
		'url'    => add_query_arg( 'ver', (string) filemtime( $path ), EAGLEEYE_URI . '/brand/eagleeye-brand-icon-512.png' ),
		'width'  => $size ? (int) $size[0] : 512,
		'height' => $size ? (int) $size[1] : 512,
		'path'   => $path,
	);
}

/**
 * URL for the new mark.
 *
 * @return string
 */
function eagleeye_brand_mark_url() {
	$mark = eagleeye_brand_mark();

	return $mark ? $mark['url'] : '';
}

/**
 * Print the lockup used in the header, drawer and footer.
 *
 * @param string $variant dark|light.
 * @param string $class   Extra classes.
 */
function eagleeye_brand_identity( $variant = 'dark', $class = '' ) {
	$logo = eagleeye_brand_image( $variant );

	if ( ! $logo ) {
		return;
	}

	$classes = 'ee-brand ee-brand--identity';

	if ( 'light' === $variant ) {
		$classes .= ' ee-brand--foot';
	}

	if ( $class ) {
		$classes .= ' ' . $class;
	}

	?>
	<span class="<?php echo esc_attr( $classes ); ?>">
		<img class="ee-brand__img" src="<?php echo esc_url( $logo['url'] ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" width="<?php echo esc_attr( $logo['width'] ); ?>" height="<?php echo esc_attr( $logo['height'] ); ?>" decoding="async">
	</span>
	<?php
}

/**
 * Print the publisher logo, falling back to the typeset wordmark.
 *
 * @param string $variant dark|light.
 * @param string $class   Extra classes.
 */
function eagleeye_logo( $variant = 'dark', $class = '' ) {
	$logo = eagleeye_brand_image( $variant );

	if ( $logo ) {
		printf(
			'<img class="ee-brand__img %1$s" src="%2$s" alt="%3$s"%4$s%5$s decoding="async">',
			esc_attr( $class ),
			esc_url( $logo['url'] ),
			esc_attr( get_bloginfo( 'name' ) ),
			$logo['width'] ? ' width="' . esc_attr( (string) $logo['width'] ) . '"' : '',
			$logo['height'] ? ' height="' . esc_attr( (string) $logo['height'] ) . '"' : ''
		);

		return;
	}

	printf(
		'<span class="ee-brand__word %1$s">Eagle <span class="ee-brand__eye">Eye</span></span>',
		esc_attr( $class )
	);
}

/**
 * Print the light footer logo, generating one from the dark artwork if needed.
 *
 * @param string $class Extra classes.
 */
function eagleeye_logo_light( $class = '' ) {
	$light = eagleeye_brand_image( 'light' );

	if ( ! $light ) {
		$dark = eagleeye_brand_image( 'dark' );

		if ( $dark ) {
			printf(
				'<span class="ee-brand__plate"><img class="ee-brand__img %1$s" src="%2$s" alt="%3$s" decoding="async"></span>',
				esc_attr( $class ),
				esc_url( $dark['url'] ),
				esc_attr( get_bloginfo( 'name' ) )
			);

			return;
		}
	}

	eagleeye_logo( 'light', $class );
}

/**
 * 512px brand icon for the site icon and social cards.
 *
 * @return string Absolute path or empty string.
 */
function eagleeye_brand_icon_path() {
	foreach ( array( 'eagleeye-brand-icon-512.png', 'eagleeye-icon.png', 'eagleeye-logo-trim.png', 'eagleeye-logo.png' ) as $name ) {
		$path = EAGLEEYE_DIR . '/brand/' . $name;

		if ( file_exists( $path ) ) {
			return $path;
		}
	}

	return '';
}

/**
 * Branded login screen.
 */
function eagleeye_login_branding() {
	$logo = eagleeye_brand_image( 'light' );
	$logo_url = $logo ? $logo['url'] : eagleeye_brand_mark_url();
	?>
	<style>
		body.login{background:#08080a;min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;padding:36px 16px 22px;box-sizing:border-box}
		body.login::before{content:"";position:fixed;inset:0;background:radial-gradient(ellipse 70% 45% at 50% 0%,rgba(246,194,28,.16),transparent 65%),radial-gradient(ellipse 50% 40% at 100% 100%,rgba(224,27,47,.16),transparent 60%);pointer-events:none}
		#login{position:relative;width:100%;max-width:430px;padding:0}
		#login h1{margin:0 0 6px}
		#login h1 a{display:block;width:100%;max-width:330px;height:156px;margin:0 auto;background:transparent url("<?php echo esc_url( $logo_url ); ?>") center/contain no-repeat;border:0;box-shadow:none;font-size:0;color:transparent;text-indent:-9999px;overflow:hidden}
		#login h1::after{content:"SEEING BEYOND THE HEADLINES";display:block;margin:12px auto 26px;text-align:center;font-size:9px;font-weight:700;letter-spacing:.24em;color:#f6c21c;line-height:1.5}
		#login form,#login .message,#login .success,#login .notice,#login #login_error{background:#fff;border:0;border-top:4px solid #f6c21c;border-radius:2px;box-shadow:0 34px 70px -34px rgba(0,0,0,.9);padding:30px 30px 26px;margin-top:0}
		#login form label{display:block;margin-bottom:7px;color:#1c1c20;font-size:12.5px;font-weight:700;letter-spacing:.04em}
		#login form input[type=text],#login form input[type=password],#login form input[type=email]{width:100%;height:48px;padding:0 13px;background:#fff;border:1px solid #b9b2a4;border-radius:2px;color:#08080a;font-size:16px;box-shadow:none}
		#login form input[type=text]:focus,#login form input[type=password]:focus,#login form input[type=email]:focus{border-color:#08080a;box-shadow:0 0 0 3px rgba(246,194,28,.55);outline:none}
		#login form .input,#login form input{color:#08080a}
		.login .forgetmenot{margin-top:6px}
		.login .forgetmenot label{color:#3a3a40;font-size:13px;font-weight:500}
		#wp-submit,.wp-core-ui #wp-submit{float:right;height:46px;padding:0 22px;background:#e01b2f;border:1px solid #e01b2f;border-radius:2px;color:#fff;font-size:11.5px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;text-shadow:none;box-shadow:none}
		#wp-submit:hover,.wp-core-ui #wp-submit:hover{background:#08080a;border-color:#08080a;color:#f6c21c}
		.login #nav,.login #backtoblog{text-align:center;padding:0;margin:18px 0 0;font-size:13px}
		.login #nav a,.login #backtoblog a{color:#f6c21c !important;text-decoration:none;border-bottom:1px solid rgba(246,194,28,.55);padding-bottom:2px;font-weight:600}
		.login #nav a:hover,.login #backtoblog a:hover{color:#fff;border-bottom-color:#fff}
		.login .privacy-policy-page-link{margin:22px 0 0}
		.login .privacy-policy-page-link a{color:#8d877b;font-size:11px;letter-spacing:.08em;text-transform:uppercase;text-decoration:none}
		.login .message,.login .success{border-left:4px solid #0a7d33;color:#1c1c20;font-size:14px}
		#login #login_error{border-left:4px solid #e01b2f;color:#1c1c20;font-size:14px}
		.login .button.wp-hide-pw{color:#3a3a40}
		.login .wp-pwd{position:relative}
		.login #language-switcher{margin-top:16px}
		.login #language-switcher select{background:#fff;color:#08080a;border-color:#b9b2a4}
		@media (max-width:480px){#login{max-width:100%}#login form{padding:24px 20px}#login h1 a{height:118px;background-size:80% auto}#wp-submit{width:100%;float:none;margin-top:14px}.login .forgetmenot{float:none;margin-bottom:6px}}
	</style>
	<?php
}
add_action( 'login_enqueue_scripts', 'eagleeye_login_branding' );

/**
 * Accessible login heading and page title.
 *
 * @return string
 */
function eagleeye_login_headertext() {
	return __( 'Eagle Eye Newsroom', 'eagleeye' );
}
add_filter( 'login_headertext', 'eagleeye_login_headertext' );

/**
 * Login screen footer credit.
 */
function eagleeye_login_footer_credit() {
	echo '<p style="position:relative;margin:26px 0 0;text-align:center;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Arial,sans-serif;font-size:10px;letter-spacing:.14em;text-transform:uppercase;color:#6f6a60">Eagle Eye · Seeing beyond the headlines</p>';
}
add_action( 'login_footer', 'eagleeye_login_footer_credit' );

/**
 * Point the login logo at the site.
 *
 * @return string
 */
function eagleeye_login_url() {
	return home_url( '/' );
}
add_filter( 'login_headerurl', 'eagleeye_login_url' );
