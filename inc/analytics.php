<?php
/**
 * Page analytics and consent.
 *
 * The project key is read from a constant, an environment variable, or the
 * private server secret file outside public_html, so it never ships inside the
 * theme. With no key configured the site loads no analytics script at all.
 *
 * PostHog is only loaded after the reader chooses "Accept analytics". Rejecting
 * stores the choice and leaves the PostHog script unloaded.
 *
 * @package Eagle Eye
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PostHog project API key, if configured.
 *
 * @return string
 */
function eagleeye_posthog_key() {
	if ( defined( 'EAGLEEYE_POSTHOG_KEY' ) && EAGLEEYE_POSTHOG_KEY ) {
		return (string) EAGLEEYE_POSTHOG_KEY;
	}

	$env = getenv( 'EAGLEEYE_POSTHOG_KEY' );
	if ( $env ) {
		return (string) $env;
	}

	$secret = eagleeye_secret( 'posthog_key' );
	if ( $secret ) {
		return $secret;
	}

	$stored = get_option( 'eagleeye_posthog_key' );

	return $stored ? (string) $stored : '';
}

/**
 * PostHog ingestion host.
 *
 * Projects created in the EU region use https://eu.i.posthog.com.
 *
 * @return string
 */
function eagleeye_posthog_host() {
	$host = '';

	if ( defined( 'EAGLEEYE_POSTHOG_HOST' ) && EAGLEEYE_POSTHOG_HOST ) {
		$host = (string) EAGLEEYE_POSTHOG_HOST;
	}

	if ( ! $host ) {
		$env = getenv( 'EAGLEEYE_POSTHOG_HOST' );
		if ( $env ) {
			$host = (string) $env;
		}
	}

	if ( ! $host ) {
		$host = (string) get_option( 'eagleeye_posthog_host', '' );
	}

	if ( ! $host ) {
		$host = eagleeye_secret( 'posthog_host' );
	}

	if ( ! $host ) {
		$host = 'https://us.i.posthog.com';
	}

	return (string) apply_filters( 'eagleeye_posthog_host', untrailingslashit( $host ) );
}

/**
 * Whether analytics may be recorded for this request.
 *
 * Staff activity is excluded by default so the numbers reflect readers rather
 * than the newsroom. Filter `eagleeye_track_logged_in` to include it.
 *
 * @return bool
 */
function eagleeye_analytics_can_track() {
	if ( is_admin() ) {
		return false;
	}

	if ( is_user_logged_in() && ! apply_filters( 'eagleeye_track_logged_in', false ) ) {
		return false;
	}

	return true;
}

/**
 * Print the consent-aware analytics configuration.
 *
 * The PostHog library is not requested until `eeAnalytics.load()` runs, which
 * only happens after an accepted choice. The banner markup lives in the footer.
 */
function eagleeye_analytics_tag() {
	$key = eagleeye_posthog_key();

	if ( ! $key || is_admin() || is_customize_preview() ) {
		return;
	}

	$can_track = eagleeye_analytics_can_track();
	?>
	<script>
	(function () {
		var KEY = <?php echo wp_json_encode( $key ); ?>;
		var HOST = <?php echo wp_json_encode( eagleeye_posthog_host() ); ?>;
		var STORAGE_KEY = 'eagleeye_analytics_consent';
		var canTrack = <?php echo $can_track ? 'true' : 'false'; ?>;
		var consent = readConsent();
		var loaded = false;
		var banner = null;

		function readConsent() {
			try {
				var stored = window.localStorage.getItem( STORAGE_KEY );
				if ( stored === 'accepted' || stored === 'rejected' ) {
					return stored;
				}
			} catch ( e ) {}

			var match = document.cookie.match( /(?:^|;\s*)eagleeye_analytics_consent=(accepted|rejected)/ );
			return match ? match[1] : '';
		}

		function writeConsent( value ) {
			try {
				window.localStorage.setItem( STORAGE_KEY, value );
			} catch ( e ) {}

			document.cookie = STORAGE_KEY + '=' + value + '; Max-Age=15552000; Path=/; SameSite=Lax' + ( location.protocol === 'https:' ? '; Secure' : '' );
		}

		function allowed() {
			return canTrack && consent === 'accepted';
		}

		function load() {
			if ( ! allowed() || loaded ) {
				return;
			}

			loaded = true;

			!function(t,e){var o,n,p,r;e.__SV||(window.posthog=e,e._i=[],e.init=function(i,s,a){function g(t,e){var o=e.split(".");2==o.length&&(t=t[o[0]],e=o[1]),t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}}(p=t.createElement("script")).type="text/javascript",p.async=!0,p.crossOrigin="anonymous",p.src=s.api_host.replace(".i.posthog.com","-assets.i.posthog.com")+"/static/array.js",(r=t.getElementsByTagName("script")[0]).parentNode.insertBefore(p,r);var u=e;for(void 0!==a?u=e[a]=[]:a="posthog",u.people=u.people||[],u.toString=function(t){var e="posthog";return"posthog"!==a&&(e+="."+a),t||(e+=" (stub)"),e},u.people.toString=function(){return u.toString(1)+".people (stub)"},o="capture identify alias people.set people.set_once set_config register register_once unregister opt_out_capturing has_opted_out_capturing opt_in_capturing reset isFeatureEnabled onFeatureFlags getFeatureFlag getFeatureFlagPayload reloadFeatureFlags group updateEarlyAccessFeatureEnrollment getEarlyAccessFeatures getActiveMatchingSurveys getSurveys getSurveys getNextSurveyStep onSessionId setPersonProperties".split(" "),n=0;n<o.length;n++)g(u,o[n]);e._i.push([i,s,a])},e.__SV=1)}(document,window.posthog||[]);

			posthog.init( KEY, {
				api_host: HOST,
				cookieless_mode: 'always',
				person_profiles: 'never',
				capture_pageview: true,
				capture_pageleave: true
			} );
		}

		function show( moveFocus ) {
			banner = banner || document.getElementById( 'ee-consent' );

			if ( ! banner ) {
				return;
			}

			banner.hidden = false;

			if ( moveFocus ) {
				try {
					banner.focus( { preventScroll: true } );
				} catch ( e ) {
					banner.focus();
				}
			}
		}

		function hide() {
			banner = banner || document.getElementById( 'ee-consent' );

			if ( banner ) {
				banner.hidden = true;
			}
		}

		function accept() {
			consent = 'accepted';
			writeConsent( consent );

			load();
			hide();
		}

		function reject() {
			consent = 'rejected';
			writeConsent( consent );

			// cookieless_mode "always" ignores opt_out_capturing(), so a page
			// reload is the only reliable way to remove an already-loaded tracker.
			if ( loaded ) {
				window.location.reload();
				return;
			}

			hide();
		}

		function openSettings() {
			show( true );
		}

		function bind() {
			banner = document.getElementById( 'ee-consent' );

			if ( banner ) {
				banner.addEventListener( 'click', function ( event ) {
					var button = event.target.closest( '[data-ee-consent]' );

					if ( ! button ) {
						return;
					}

					if ( button.getAttribute( 'data-ee-consent' ) === 'accepted' ) {
						accept();
					} else {
						reject();
					}
				} );
			}

			document.addEventListener( 'click', function ( event ) {
				var opener = event.target.closest( '[data-ee-consent-open]' );

				if ( ! opener ) {
					return;
				}

				event.preventDefault();
				openSettings();
			} );

			if ( consent === 'accepted' ) {
				load();
				hide();
			} else if ( consent === 'rejected' ) {
				hide();
			} else {
				show( false );
			}
		}

		window.eeAnalytics = {
			allowed: allowed,
			load: load,
			open: openSettings,
			accept: accept,
			reject: reject,
			track: function ( name, props ) {
				if ( allowed() && window.posthog ) {
					window.posthog.capture( name, props || {} );
				}
			}
		};

		if ( consent === 'accepted' ) {
			load();
		}

		if ( document.readyState === 'loading' ) {
			document.addEventListener( 'DOMContentLoaded', bind );
		} else {
			bind();
		}
	})();
	</script>
	<?php
}
add_action( 'wp_head', 'eagleeye_analytics_tag', 30 );

/**
 * Print the analytics consent banner.
 */
function eagleeye_analytics_consent_banner() {
	$key = eagleeye_posthog_key();

	if ( ! $key || is_admin() || is_customize_preview() ) {
		return;
	}

	$can_track = eagleeye_analytics_can_track();
	?>
	<div class="ee-consent" id="ee-consent" role="region" aria-labelledby="ee-consent-title" tabindex="-1" hidden>
		<div class="ee-consent__inner">
			<div class="ee-consent__copy">
				<strong class="ee-consent__title" id="ee-consent-title"><?php esc_html_e( 'Your privacy choices', 'eagleeye' ); ?></strong>
				<p>
					<?php esc_html_e( 'We use PostHog analytics to understand which stories are read. Analytics stays off unless you accept.', 'eagleeye' ); ?>
					<a href="<?php echo esc_url( home_url( '/cookie-notice/' ) ); ?>"><?php esc_html_e( 'Cookie Notice', 'eagleeye' ); ?></a>
				</p>
				<?php if ( ! $can_track ) : ?>
					<span class="ee-consent__note"><?php esc_html_e( 'You are signed in, so your visit is not recorded.', 'eagleeye' ); ?></span>
				<?php endif; ?>
			</div>
			<div class="ee-consent__actions">
				<button type="button" class="ee-consent__btn ee-consent__btn--reject" data-ee-consent="rejected"><?php esc_html_e( 'Reject analytics', 'eagleeye' ); ?></button>
				<button type="button" class="ee-consent__btn ee-consent__btn--accept" data-ee-consent="accepted"><?php esc_html_e( 'Accept analytics', 'eagleeye' ); ?></button>
			</div>
		</div>
	</div>
	<?php
}
add_action( 'wp_footer', 'eagleeye_analytics_consent_banner', 20 );

/**
 * Report newsletter confirmation as a conversion.
 *
 * Fires once on the confirmation screen, so sign-ups can be measured against
 * homepage and article traffic instead of guessed at. It only records when the
 * reader has already accepted analytics.
 */
function eagleeye_analytics_conversion() {
	$key = eagleeye_posthog_key();

	if ( ! $key || ! isset( $_GET['ee_newsletter'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}

	$state = sanitize_key( wp_unslash( $_GET['ee_newsletter'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( ! in_array( $state, array( 'confirmed', 'subscribed' ), true ) ) {
		return;
	}
	?>
	<script>
	if ( window.eeAnalytics ) {
		window.eeAnalytics.track( 'newsletter_confirmed', { source: 'confirmation_page' } );
	}
	</script>
	<?php
}
add_action( 'wp_footer', 'eagleeye_analytics_conversion', 40 );
