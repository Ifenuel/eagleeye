<?php
/**
 * Site footer.
 *
 * @package Eagle Eye
 */

$socials   = eagleeye_socials();
$email     = eagleeye_option( 'eagleeye_email', '' );
$phone     = eagleeye_option( 'eagleeye_phone', '07017138501' );
$address   = eagleeye_option( 'eagleeye_address', '' );
$ad_page   = get_page_by_path( 'advertise-with-us' );

if ( ! is_singular() || is_front_page() ) {
	eagleeye_newsletter_form( 'band' );
}
?>
</main><!-- #main -->

<footer class="site-foot ee-foot">
	<div class="site-foot__glow" aria-hidden="true"></div>

	<div class="wrap ee-foot__top">
		<div class="ee-foot__brand">
			<a class="ee-brand--foot" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
				<?php eagleeye_brand_identity( 'light' ); ?>
			</a>
			<p class="ee-foot__tagline"><?php echo esc_html( get_bloginfo( 'description' ) ); ?></p>
			<p class="ee-foot__about"><?php esc_html_e( 'Eagle Eye is an independent digital news platform delivering accurate, timely, credible and balanced reporting - beyond the headlines.', 'eagleeye' ); ?></p>

			<div class="ee-foot__actions">
				<?php if ( $ad_page ) : ?>
					<a class="ee-foot__cta" href="<?php echo esc_url( get_permalink( $ad_page ) ); ?>">
						<span><?php esc_html_e( 'Advertise with us', 'eagleeye' ); ?></span>
						<span aria-hidden="true">&rarr;</span>
					</a>
				<?php endif; ?>

				<?php if ( $socials ) : ?>
					<ul class="socials socials--footer" aria-label="<?php esc_attr_e( 'Eagle Eye on social media', 'eagleeye' ); ?>">
						<?php foreach ( $socials as $key => $social ) : ?>
							<li><a href="<?php echo esc_url( $social['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $social['label'] ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>

		<div class="ee-foot__links">
			<div class="ee-foot__group">
				<h2 class="ee-foot__label"><?php esc_html_e( 'Sections', 'eagleeye' ); ?></h2>
				<nav class="ee-foot__nav" aria-label="<?php esc_attr_e( 'Footer sections', 'eagleeye' ); ?>">
					<?php
					wp_nav_menu( array(
						'theme_location' => 'footer',
						'container'      => false,
						'menu_class'     => 'ee-foot__list ee-foot__list--sections',
						'depth'          => 1,
						'fallback_cb'    => 'eagleeye_footer_sections_fallback',
					) );
					?>
				</nav>
			</div>

			<div class="ee-foot__group">
				<h2 class="ee-foot__label"><?php esc_html_e( 'Newsroom', 'eagleeye' ); ?></h2>
				<ul class="ee-foot__list">
					<?php if ( $address ) : ?>
						<li><?php echo esc_html( $address ); ?></li>
					<?php endif; ?>
					<?php if ( $email ) : ?>
						<li><a href="mailto:<?php echo esc_attr( antispambot( $email ) ); ?>"><?php echo esc_html( antispambot( $email ) ); ?></a></li>
					<?php endif; ?>
					<?php if ( $phone ) : ?>
						<li><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a></li>
					<?php endif; ?>
					<li><a href="<?php echo esc_url( home_url( '/contact-us/' ) ); ?>"><?php esc_html_e( 'Send a news tip', 'eagleeye' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/#newsletter' ) ); ?>"><?php esc_html_e( 'The Eagle Eye Morning Brief', 'eagleeye' ); ?></a></li>
				</ul>
			</div>

			<div class="ee-foot__group">
				<h2 class="ee-foot__label"><?php esc_html_e( 'Eagle Eye', 'eagleeye' ); ?></h2>
				<nav class="ee-foot__nav" aria-label="<?php esc_attr_e( 'Company links', 'eagleeye' ); ?>">
					<?php
					wp_nav_menu( array(
						'theme_location' => 'legal',
						'container'      => false,
						'menu_class'     => 'ee-foot__list',
						'depth'          => 1,
						'fallback_cb'    => 'eagleeye_footer_company_fallback',
					) );
					?>
					<?php if ( eagleeye_posthog_key() ) : ?>
						<ul class="ee-foot__list ee-foot__list--utility">
							<li><button type="button" class="ee-foot__cookie-settings" data-ee-consent-open><?php esc_html_e( 'Cookie settings', 'eagleeye' ); ?></button></li>
						</ul>
					<?php endif; ?>
				</nav>
			</div>
		</div>
	</div>

	<div class="wrap ee-foot__bottom">
		<p>&copy; <?php echo esc_html( date_i18n( 'Y' ) ); ?> <?php echo esc_html( get_bloginfo( 'name' ) ); ?>. <?php esc_html_e( 'All rights reserved.', 'eagleeye' ); ?></p>
		<p class="ee-foot__credit"><?php esc_html_e( 'Published by Eagle Eye', 'eagleeye' ); ?> &middot; <?php esc_html_e( 'Powered by Ifenuel Tech Hub', 'eagleeye' ); ?></p>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
