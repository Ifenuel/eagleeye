<?php
/**
 * Template Name: Contact
 *
 * @package Eagle Eye
 */

get_header();

$email   = eagleeye_option( 'eagleeye_email', '' );
$phone   = eagleeye_option( 'eagleeye_phone', '07017138501' );
$address = eagleeye_option( 'eagleeye_address', '' );
$socials = eagleeye_socials();
$logo    = eagleeye_brand_mark();
?>

<header class="page-hero page-hero--dark">
	<div class="wrap page-hero__inner">
		<div>
			<?php eagleeye_breadcrumbs(); ?>
			<span class="eyebrow eyebrow--light"><?php esc_html_e( 'Talk to the newsroom', 'eagleeye' ); ?></span>
			<h1 class="page-hero__title"><?php the_title(); ?></h1>
			<p class="page-hero__dek"><?php esc_html_e( 'Send a news tip, corrections request, editorial enquiry or partnership message. Every message is routed to the newsroom.', 'eagleeye' ); ?></p>
		</div>
		<div class="page-hero__aside">
			<?php if ( $logo ) : ?>
				<div class="page-hero__logo-plate">
					<img src="<?php echo esc_url( $logo['url'] ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" width="<?php echo esc_attr( $logo['width'] ); ?>" height="<?php echo esc_attr( $logo['height'] ); ?>">
				</div>
			<?php endif; ?>
			<p class="page-hero__aside-label"><?php esc_html_e( 'Direct contact', 'eagleeye' ); ?></p>
			<?php if ( $email ) : ?>
				<a href="mailto:<?php echo esc_attr( antispambot( $email ) ); ?>"><?php echo esc_html( antispambot( $email ) ); ?></a>
			<?php endif; ?>
			<?php if ( $phone ) : ?>
				<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a>
			<?php endif; ?>
		</div>
	</div>
</header>

<div class="wrap contact-layout">
	<div class="contact-layout__form">
		<?php
		while ( have_posts() ) :
			the_post();
			if ( trim( get_the_content() ) ) {
				echo '<div class="prose prose--tight">';
				the_content();
				echo '</div>';
			}
		endwhile;
		?>

		<h2 class="contact-layout__heading"><?php esc_html_e( 'Send a message', 'eagleeye' ); ?></h2>
		<?php
		eagleeye_contact_form( array(
			'button' => __( 'Send to the newsroom', 'eagleeye' ),
		) );
		?>
	</div>

	<aside class="contact-layout__aside">
		<div class="panel">
			<h2 class="panel__title"><?php esc_html_e( 'Newsroom', 'eagleeye' ); ?></h2>
			<ul class="contact-list">
				<?php if ( $address ) : ?>
					<li><span class="contact-list__label"><?php esc_html_e( 'Address', 'eagleeye' ); ?></span><?php echo esc_html( $address ); ?></li>
				<?php endif; ?>
				<?php if ( $email ) : ?>
					<li><span class="contact-list__label"><?php esc_html_e( 'Email', 'eagleeye' ); ?></span><a href="mailto:<?php echo esc_attr( antispambot( $email ) ); ?>"><?php echo esc_html( antispambot( $email ) ); ?></a></li>
				<?php endif; ?>
				<?php if ( $phone ) : ?>
					<li><span class="contact-list__label"><?php esc_html_e( 'Phone', 'eagleeye' ); ?></span><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a></li>
				<?php endif; ?>
				<li><span class="contact-list__label"><?php esc_html_e( 'Hours', 'eagleeye' ); ?></span><?php esc_html_e( 'Monday–Friday, 9am–5pm WAT', 'eagleeye' ); ?></li>
			</ul>

			<?php if ( $socials ) : ?>
				<ul class="socials socials--contact">
					<?php foreach ( $socials as $key => $social ) : ?>
						<li><a href="<?php echo esc_url( $social['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $social['label'] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>

		<div class="panel panel--tint">
			<h2 class="panel__title"><?php esc_html_e( 'Sending a tip?', 'eagleeye' ); ?></h2>
			<p><?php esc_html_e( 'Be specific about what you know, who is involved and how we can verify it. If the matter is sensitive, say so and we will arrange a safer channel before you share documents.', 'eagleeye' ); ?></p>
			<p class="small"><?php esc_html_e( 'Do not send passwords, bank details or material you are not entitled to share.', 'eagleeye' ); ?></p>
		</div>

		<?php eagleeye_ad( 'sidebar' ); ?>
	</aside>
</div>

<?php
get_footer();
