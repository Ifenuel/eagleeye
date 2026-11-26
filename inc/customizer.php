<?php
/**
 * Customizer: brand controls and the editorial settings the newsroom manages
 * without touching template code.
 *
 * @package Eagle Eye
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read a theme setting with a default.
 *
 * @param string $key     Setting key.
 * @param mixed  $default Default value.
 * @return mixed
 */
function eagleeye_option( $key, $default = '' ) {
	$value = get_theme_mod( $key, $default );

	return ( '' === $value || null === $value ) ? $default : $value;
}

/**
 * Sanitize a hex colour.
 *
 * @param string $value Raw value.
 * @return string
 */
function eagleeye_sanitize_hex( $value ) {
	$value = sanitize_hex_color( $value );

	return $value ? $value : '#d81f2a';
}

/**
 * Sanitize a checkbox.
 *
 * @param mixed $value Raw value.
 * @return bool
 */
function eagleeye_sanitize_bool( $value ) {
	return (bool) $value;
}

/**
 * Sanitize a URL, allowing empty values.
 *
 * @param string $value Raw value.
 * @return string
 */
function eagleeye_sanitize_url( $value ) {
	return $value ? esc_url_raw( $value ) : '';
}

/**
 * Register controls.
 *
 * @param WP_Customize_Manager $wp_customize Customizer instance.
 */
function eagleeye_customize_register( $wp_customize ) {
	$wp_customize->get_setting( 'blogdescription' )->transport = 'refresh';

	/* ---------------------------------------------------------------
	 * Identity
	 * ------------------------------------------------------------- */
	$wp_customize->add_section( 'eagleeye_identity', array(
		'title'       => __( 'Eagle Eye Identity', 'eagleeye' ),
		'priority'    => 25,
		'description' => __( 'The accent colour drives links, kickers and editorial accents across the site.', 'eagleeye' ),
	) );

	$wp_customize->add_setting( 'eagleeye_accent', array(
		'default'           => '#d81f2a',
		'sanitize_callback' => 'eagleeye_sanitize_hex',
		'transport'         => 'refresh',
	) );
	$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'eagleeye_accent', array(
		'label'   => __( 'Accent colour', 'eagleeye' ),
		'section' => 'eagleeye_identity',
	) ) );

	$wp_customize->add_setting( 'eagleeye_masthead_note', array(
		'default'           => __( 'Independent journalism for a changing nation', 'eagleeye' ),
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'eagleeye_masthead_note', array(
		'label'   => __( 'Masthead line', 'eagleeye' ),
		'section' => 'eagleeye_identity',
		'type'    => 'text',
	) );

	/* ---------------------------------------------------------------
	 * Breaking news
	 * ------------------------------------------------------------- */
	$wp_customize->add_section( 'eagleeye_ticker', array(
		'title'    => __( 'Breaking News Ticker', 'eagleeye' ),
		'priority' => 30,
	) );

	$wp_customize->add_setting( 'eagleeye_ticker_enabled', array(
		'default'           => true,
		'sanitize_callback' => 'eagleeye_sanitize_bool',
	) );
	$wp_customize->add_control( 'eagleeye_ticker_enabled', array(
		'label'   => __( 'Show the breaking-news ticker', 'eagleeye' ),
		'section' => 'eagleeye_ticker',
		'type'    => 'checkbox',
	) );

	$wp_customize->add_setting( 'eagleeye_ticker_label', array(
		'default'           => __( 'Breaking', 'eagleeye' ),
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'eagleeye_ticker_label', array(
		'label'       => __( 'Ticker label', 'eagleeye' ),
		'description' => __( 'Mark stories as breaking from the post editor to place them here.', 'eagleeye' ),
		'section'     => 'eagleeye_ticker',
		'type'        => 'text',
	) );

	/* ---------------------------------------------------------------
	 * Newsletter
	 * ------------------------------------------------------------- */
	$wp_customize->add_section( 'eagleeye_newsletter_settings', array(
		'title'    => __( 'Newsletter', 'eagleeye' ),
		'priority' => 32,
	) );

	$wp_customize->add_setting( 'eagleeye_newsletter_title', array(
		'default'           => __( 'The Eagle Eye Morning Brief', 'eagleeye' ),
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'eagleeye_newsletter_title', array(
		'label'   => __( 'Newsletter title', 'eagleeye' ),
		'section' => 'eagleeye_newsletter_settings',
		'type'    => 'text',
	) );

	$wp_customize->add_setting( 'eagleeye_newsletter_text', array(
		'default'           => __( 'The stories shaping Nigeria and the world, selected by our editors and delivered every morning at 7am.', 'eagleeye' ),
		'sanitize_callback' => 'sanitize_textarea_field',
	) );
	$wp_customize->add_control( 'eagleeye_newsletter_text', array(
		'label'   => __( 'Newsletter description', 'eagleeye' ),
		'section' => 'eagleeye_newsletter_settings',
		'type'    => 'textarea',
	) );

	$wp_customize->add_setting( 'eagleeye_newsletter_note', array(
		'default'           => __( 'Free. One briefing every morning at 7am. Unsubscribe anytime.', 'eagleeye' ),
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'eagleeye_newsletter_note', array(
		'label'   => __( 'Small print', 'eagleeye' ),
		'section' => 'eagleeye_newsletter_settings',
		'type'    => 'text',
	) );

	/* ---------------------------------------------------------------
	 * Contact and social
	 * ------------------------------------------------------------- */
	$wp_customize->add_section( 'eagleeye_contact', array(
		'title'    => __( 'Contact & Social', 'eagleeye' ),
		'priority' => 34,
	) );

	$contact_fields = array(
		'eagleeye_publisher' => array( __( 'Publisher', 'eagleeye' ), 'sanitize_text_field', 'Eagle Eye' ),
		'eagleeye_email'     => array( __( 'Newsroom email', 'eagleeye' ), 'sanitize_email', '' ),
		'eagleeye_phone'     => array( __( 'Phone', 'eagleeye' ), 'sanitize_text_field', '07017138501' ),
		'eagleeye_address'   => array( __( 'Address', 'eagleeye' ), 'sanitize_text_field', '' ),
	);

	foreach ( $contact_fields as $key => $field ) {
		$wp_customize->add_setting( $key, array(
			'default'           => $field[2],
			'sanitize_callback' => $field[1],
		) );
		$wp_customize->add_control( $key, array(
			'label'   => $field[0],
			'section' => 'eagleeye_contact',
			'type'    => 'text',
		) );
	}

	$socials = array(
		'eagleeye_social_x'        => __( 'X / Twitter URL', 'eagleeye' ),
		'eagleeye_social_facebook' => __( 'Facebook URL', 'eagleeye' ),
		'eagleeye_social_instagram'=> __( 'Instagram URL', 'eagleeye' ),
		'eagleeye_social_youtube'  => __( 'YouTube URL', 'eagleeye' ),
		'eagleeye_social_whatsapp' => __( 'WhatsApp channel URL', 'eagleeye' ),
		'eagleeye_social_linkedin' => __( 'LinkedIn URL', 'eagleeye' ),
	);

	foreach ( $socials as $key => $label ) {
		$wp_customize->add_setting( $key, array(
			'default'           => '',
			'sanitize_callback' => 'eagleeye_sanitize_url',
		) );
		$wp_customize->add_control( $key, array(
			'label'   => $label,
			'section' => 'eagleeye_contact',
			'type'    => 'url',
		) );
	}

	/* ---------------------------------------------------------------
	 * Advertising
	 * ------------------------------------------------------------- */
	$wp_customize->add_section( 'eagleeye_ads', array(
		'title'       => __( 'Advertising Placements', 'eagleeye' ),
		'priority'    => 36,
		'description' => __( 'Upload a banner image and destination for each placement. Empty placements show a house promotion linking to your Advertise page. Google AdSense can be added later inside the same slots.', 'eagleeye' ),
	) );

	$slots = array(
		'header'    => __( 'Homepage / header leaderboard', 'eagleeye' ),
		'sidebar'   => __( 'Article and homepage sidebar', 'eagleeye' ),
		'inarticle' => __( 'In-article rectangle', 'eagleeye' ),
		'category'  => __( 'Category archive banner', 'eagleeye' ),
	);

	foreach ( $slots as $slot => $label ) {
		$wp_customize->add_setting( "eagleeye_ad_{$slot}_image", array(
			'default'           => '',
			'sanitize_callback' => 'esc_url_raw',
		) );
		$wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, "eagleeye_ad_{$slot}_image", array(
			'label'   => sprintf( '%s — image', $label ),
			'section' => 'eagleeye_ads',
		) ) );

		$wp_customize->add_setting( "eagleeye_ad_{$slot}_url", array(
			'default'           => '',
			'sanitize_callback' => 'eagleeye_sanitize_url',
		) );
		$wp_customize->add_control( "eagleeye_ad_{$slot}_url", array(
			'label'   => sprintf( '%s — link', $label ),
			'section' => 'eagleeye_ads',
			'type'    => 'url',
		) );

		$wp_customize->add_setting( "eagleeye_ad_{$slot}_label", array(
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
		) );
		$wp_customize->add_control( "eagleeye_ad_{$slot}_label", array(
			'label'   => sprintf( '%s — image alt text', $label ),
			'section' => 'eagleeye_ads',
			'type'    => 'text',
		) );
	}
}
add_action( 'customize_register', 'eagleeye_customize_register' );

/**
 * Social profiles that have been configured.
 *
 * @return array
 */
function eagleeye_socials() {
	$map = array(
		'x'         => array( 'eagleeye_social_x', 'X' ),
		'facebook'  => array( 'eagleeye_social_facebook', 'Facebook' ),
		'instagram' => array( 'eagleeye_social_instagram', 'Instagram' ),
		'youtube'   => array( 'eagleeye_social_youtube', 'YouTube' ),
		'whatsapp'  => array( 'eagleeye_social_whatsapp', 'WhatsApp' ),
		'linkedin'  => array( 'eagleeye_social_linkedin', 'LinkedIn' ),
	);

	$socials = array();

	foreach ( $map as $key => $config ) {
		$url = get_theme_mod( $config[0], '' );
		if ( $url ) {
			$socials[ $key ] = array(
				'label' => $config[1],
				'url'   => $url,
			);
		}
	}

	return $socials;
}
