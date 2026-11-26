<?php
/**
 * Template Name: About Eagle Eye
 *
 * Brand page for the publication.
 *
 * @package Eagle Eye
 */

get_header();

while ( have_posts() ) :
	the_post();
	$email = eagleeye_option( 'eagleeye_email', '' );
	$phone = eagleeye_option( 'eagleeye_phone', '' );
	?>
	<article <?php post_class( 'about-page' ); ?>>
		<header class="about-hero">
			<div class="wrap about-hero__grid">
				<div class="about-hero__copy">
					<?php eagleeye_breadcrumbs(); ?>
					<p class="about-kicker about-kicker--gold"><?php esc_html_e( 'The newsroom', 'eagleeye' ); ?></p>
					<h1 class="about-hero__title"><?php esc_html_e( 'About', 'eagleeye' ); ?> <em><?php esc_html_e( 'Eagle Eye', 'eagleeye' ); ?></em></h1>
					<?php if ( has_excerpt() ) : ?>
						<p class="about-hero__dek"><?php echo esc_html( get_the_excerpt() ); ?></p>
					<?php endif; ?>
					<div class="about-hero__stats">
						<div>
							<strong>07</strong>
							<span><?php esc_html_e( 'core values', 'eagleeye' ); ?></span>
						</div>
						<div>
							<strong>05</strong>
							<span><?php esc_html_e( 'public standards', 'eagleeye' ); ?></span>
						</div>
						<div>
							<strong>01</strong>
							<span><?php esc_html_e( 'editorial standard', 'eagleeye' ); ?></span>
						</div>
					</div>
				</div>

				<div class="about-hero__seal" aria-hidden="true">
					<div class="about-seal">
						<?php $seal_logo = eagleeye_brand_image( 'dark' ); ?>
						<?php if ( $seal_logo ) : ?>
							<img src="<?php echo esc_url( $seal_logo['url'] ); ?>" alt="<?php esc_attr_e( 'Eagle Eye', 'eagleeye' ); ?>" width="<?php echo esc_attr( $seal_logo['width'] ); ?>" height="<?php echo esc_attr( $seal_logo['height'] ); ?>" loading="eager" decoding="async">
						<?php endif; ?>
					</div>
					<p><?php esc_html_e( 'Seeing Beyond the Headlines', 'eagleeye' ); ?></p>
				</div>
			</div>
		</header>

		<div class="wrap about-body">
			<div class="about-prose">
				<?php the_content(); ?>
			</div>

			<?php if ( $email || $phone ) : ?>
				<div class="about-contact-line">
					<span><?php esc_html_e( 'Newsroom contact', 'eagleeye' ); ?></span>
					<?php if ( $email ) : ?>
						<a href="mailto:<?php echo esc_attr( antispambot( $email ) ); ?>"><?php echo esc_html( antispambot( $email ) ); ?></a>
					<?php endif; ?>
					<?php if ( $phone ) : ?>
						<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a>
					<?php endif; ?>
					<a href="<?php echo esc_url( home_url( '/contact-us/' ) ); ?>"><?php esc_html_e( 'Send a message', 'eagleeye' ); ?> <span aria-hidden="true">&rarr;</span></a>
				</div>
			<?php endif; ?>
		</div>
	</article>
	<?php
endwhile;

get_footer();
