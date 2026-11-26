<?php
/**
 * Advertising placements.
 *
 * Paid inventory lives in the Advertisements post type (inc/ads-manager.php).
 * The Customizer image remains as a manual fallback, and the house promotion
 * fills any slot with no live booking.
 *
 * @package Eagle Eye
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render an advertisement slot.
 *
 * @param string $slot  header|rail|inarticle|category|footer.
 * @param string $class Extra class names.
 */
function eagleeye_ad( $slot, $class = '' ) {
	$booking = function_exists( 'eagleeye_get_paid_ad' ) ? eagleeye_get_paid_ad( $slot ) : null;

	if ( $booking ) {
		$html  = get_post_meta( $booking->ID, '_ee_html', true );
		$url   = get_post_meta( $booking->ID, '_ee_url', true );
		$brand = get_post_meta( $booking->ID, '_ee_advertiser', true );
		$brand = $brand ? $brand : get_the_title( $booking );

		echo '<aside class="ad ad--' . esc_attr( $slot ) . ' ' . esc_attr( $class ) . '" aria-label="' . esc_attr__( 'Advertisement', 'eagleeye' ) . '">';
		echo '<span class="ad__tag">' . esc_html__( 'Advertisement', 'eagleeye' ) . '</span>';

		if ( $html ) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted ad code entered by an administrator.
		} elseif ( has_post_thumbnail( $booking ) ) {
			$image = get_the_post_thumbnail( $booking, 'full', array(
				'loading'  => 'lazy',
				'decoding' => 'async',
				'alt'      => $brand,
			) );

			if ( $url ) {
				printf(
					'<a class="ad__link" href="%1$s" rel="nofollow sponsored noopener" target="_blank">%2$s</a>',
					esc_url( $url ),
					$image // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core-generated markup.
				);
			} else {
				echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core-generated markup.
			}
		}

		echo '<span class="ad__credit">' . esc_html( sprintf( /* translators: %s: advertiser. */ __( 'Sponsored by %s', 'eagleeye' ), $brand ) ) . '</span>';
		echo '</aside>';

		return;
	}

	$image = get_theme_mod( "eagleeye_ad_{$slot}_image", '' );
	$url   = get_theme_mod( "eagleeye_ad_{$slot}_url", '' );
	$label = get_theme_mod( "eagleeye_ad_{$slot}_label", '' );
	$label = $label ? $label : __( 'Advertisement', 'eagleeye' );

	echo '<aside class="ad ad--' . esc_attr( $slot ) . ' ' . esc_attr( $class ) . '" aria-label="' . esc_attr__( 'Advertisement', 'eagleeye' ) . '">';
	echo '<span class="ad__tag">' . esc_html__( 'Advertisement', 'eagleeye' ) . '</span>';

	/**
	 * Inject a third-party ad unit (for example AdSense) into a slot.
	 */
	$code = apply_filters( 'eagleeye_ad_code', '', $slot );

	if ( $code ) {
		echo $code; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted ad integration.
	} elseif ( $image ) {
		$target = $url ? $url : '#';
		printf(
			'<a class="ad__link" href="%1$s"%2$s><img src="%3$s" alt="%4$s" loading="lazy" decoding="async"></a>',
			esc_url( $target ),
			$url ? ' rel="nofollow sponsored noopener" target="_blank"' : '',
			esc_url( $image ),
			esc_attr( $label )
		);
	} else {
		$advertise = get_page_by_path( 'advertise-with-us' );
		$link      = $advertise ? get_permalink( $advertise ) : home_url( '/' );
		?>
		<a class="ad__house" href="<?php echo esc_url( $link ); ?>">
			<span class="ad__house-kicker"><?php esc_html_e( 'Partner with Eagle Eye', 'eagleeye' ); ?></span>
			<span class="ad__house-title"><?php esc_html_e( 'Introduce your brand to Eagle Eye readers.', 'eagleeye' ); ?></span>
			<span class="ad__house-link"><?php esc_html_e( 'Request our media kit', 'eagleeye' ); ?> <span aria-hidden="true">&rarr;</span></span>
		</a>
		<?php
	}

	echo '</aside>';
}

/**
 * Place the in-article placement after the opening paragraphs.
 *
 * @param string $content Post content.
 * @return string
 */
function eagleeye_inject_inarticle_ad( $content ) {
	if ( is_admin() || ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}

	if ( false !== strpos( $content, 'ad--inarticle' ) ) {
		return $content;
	}

	ob_start();
	eagleeye_ad( 'inarticle' );
	$ad = (string) ob_get_clean();

	$paragraphs = 0;

	return (string) preg_replace_callback(
		'/<\/p>/i',
		function ( $matches ) use ( &$paragraphs, $ad ) {
			$paragraphs++;

			return 2 === $paragraphs ? '</p>' . $ad : $matches[0];
		},
		$content,
		2
	);
}
add_filter( 'the_content', 'eagleeye_inject_inarticle_ad', 12 );
