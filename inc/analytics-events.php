<?php
/**
 * GA4 reader action events.
 *
 * Page views alone say how many people arrived, not whether the site did its
 * job. This records the four reader actions that matter on a news site:
 * newsletter sign-ups, WhatsApp clicks, advertisement clicks and outbound
 * links. Events go through the Google tag that Site Kit already prints, so no
 * second tracking library is loaded and nothing is sent for signed-in staff.
 *
 * @package Eagle Eye
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Print the reader action listeners.
 *
 * Skipped for admin screens and for signed-in users, matching the rest of the
 * site's analytics behaviour.
 */
function eagleeye_ga_events() {
	if ( is_admin() || is_customize_preview() ) {
		return;
	}

	if ( is_user_logged_in() && ! apply_filters( 'eagleeye_track_logged_in', false ) ) {
		return;
	}

	?>
	<script>
	(function () {
		function send(name, params) {
			if (typeof window.gtag === 'function') {
				window.gtag('event', name, params || {});
			}
		}

		function text(el) {
			return (el && el.textContent ? el.textContent : '').replace(/\s+/g, ' ').trim().slice(0, 100);
		}

		document.addEventListener('click', function (event) {
			var link = event.target && event.target.closest ? event.target.closest('a[href]') : null;

			if (!link) {
				return;
			}

			var href = link.getAttribute('href') || '';

			if (!href || href.charAt(0) === '#' || /^(mailto:|tel:|javascript:)/i.test(href)) {
				return;
			}

			if (/wa\.me|api\.whatsapp\.com|whatsapp\.com/i.test(href)) {
				send('whatsapp_click', { link_url: link.href, link_text: text(link) });
				return;
			}

			var ad = link.closest('.ad');

			if (ad) {
				var match = (ad.className || '').match(/ad--([a-z0-9_-]+)/i);
				send('ad_click', {
					ad_slot: match ? match[1] : 'unknown',
					link_url: link.href,
					link_text: text(link)
				});
				return;
			}

			try {
				var url = new URL(link.href, window.location.href);

				if (url.hostname && url.hostname !== window.location.hostname) {
					send('outbound_click', {
						link_url: url.href,
						link_domain: url.hostname,
						link_text: text(link)
					});
				}
			} catch (e) {}
		}, true);

		document.addEventListener('submit', function (event) {
			var form = event.target;

			if (form && form.classList && form.classList.contains('newsletter__form')) {
				send('newsletter_submit', { form_context: form.className });
			}
		}, true);

		try {
			var state = new URLSearchParams(window.location.search).get('ee_newsletter');

			if (state === 'confirmed' || state === 'subscribed') {
				send('newsletter_signup', { source: 'confirmation_page' });
			}
		} catch (e) {}
	})();
	</script>
	<?php
}
add_action( 'wp_footer', 'eagleeye_ga_events', 50 );
