<?php
/**
 * Template Name: Advertise
 *
 * @package Eagle Eye
 */

get_header();

$rates   = eagleeye_rate_card();
$message = eagleeye_media_kit_message();
$phone   = eagleeye_option( 'eagleeye_phone', '07017138501' );
$email   = eagleeye_option( 'eagleeye_email', '' );
$invoice_token   = isset( $_GET['invoice'] ) ? sanitize_text_field( wp_unslash( $_GET['invoice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$invoice_request = $invoice_token ? eagleeye_request_from_token( $invoice_token ) : null;
?>

<header class="ad-hero">
	<div class="wrap ad-hero__grid">
		<div class="ad-hero__copy">
			<span class="eyebrow eyebrow--light"><?php esc_html_e( 'Media partnerships', 'eagleeye' ); ?></span>
			<h1 class="ad-hero__title"><?php esc_html_e( 'Your brand,', 'eagleeye' ); ?><br><em><?php esc_html_e( 'in good company.', 'eagleeye' ); ?></em></h1>
			<p class="ad-hero__dek"><?php esc_html_e( 'Put your brand beside reporting that people trust. Eagle Eye reaches engaged readers across Nigeria with news, analysis and opinion published for a national audience.', 'eagleeye' ); ?></p>
			<div class="ad-hero__actions">
				<a class="btn btn--solid" href="#media-kit"><?php esc_html_e( 'Request the media kit', 'eagleeye' ); ?> <span aria-hidden="true">&rarr;</span></a>
				<a class="btn btn--outline" href="#rates"><?php esc_html_e( 'View rates', 'eagleeye' ); ?></a>
			</div>
		</div>

		<aside class="ad-hero__panel">
			<?php eagleeye_brand_identity( 'dark' ); ?>
			<p class="ad-hero__panel-kicker"><?php esc_html_e( 'Talk to the newsroom', 'eagleeye' ); ?></p>
			<p class="ad-hero__panel-title"><?php esc_html_e( 'A direct line to our advertising team.', 'eagleeye' ); ?></p>
			<p><?php esc_html_e( 'Tell us what you want to achieve. We will send a written quote, confirm availability and handle the placement directly — no agency runaround.', 'eagleeye' ); ?></p>
			<?php if ( $email ) : ?>
				<p><a href="mailto:<?php echo esc_attr( antispambot( $email ) ); ?>"><?php echo esc_html( antispambot( $email ) ); ?></a></p>
			<?php endif; ?>
			<?php if ( $phone ) : ?>
				<p><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a></p>
			<?php endif; ?>
			<span class="ad-hero__note"><?php esc_html_e( 'Every booking is confirmed by the newsroom before publication.', 'eagleeye' ); ?></span>
		</aside>
	</div>
</header>

<?php if ( $invoice_request ) : ?>
	<section class="section invoice-section" id="invoice">
		<div class="wrap">
			<?php eagleeye_render_invoice( $invoice_request->ID ); ?>
		</div>
	</section>
<?php endif; ?>

<section class="section ad-value">
	<div class="wrap ad-value__grid">
		<div>
			<span class="eyebrow"><?php esc_html_e( 'Why Eagle Eye', 'eagleeye' ); ?></span>
			<h2 class="ad-value__title"><?php esc_html_e( 'Reach readers who stay with the story.', 'eagleeye' ); ?></h2>
			<p class="ad-value__copy"><?php esc_html_e( 'Our readers come for clear reporting, useful context and a newsroom that takes Nigeria seriously. Advertising here is simple, transparent and built around the way people actually read.', 'eagleeye' ); ?></p>
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
		</div>

		<ol class="ad-proof" aria-label="<?php esc_attr_e( 'Why advertisers choose Eagle Eye', 'eagleeye' ); ?>">
			<li>
				<span class="ad-proof__num">01</span>
				<div>
					<strong><?php esc_html_e( 'A trusted editorial environment', 'eagleeye' ); ?></strong>
					<span><?php esc_html_e( 'Your message sits beside independent reporting, clearly labelled and kept separate from editorial judgement.', 'eagleeye' ); ?></span>
				</div>
			</li>
			<li>
				<span class="ad-proof__num">02</span>
				<div>
					<strong><?php esc_html_e( 'A national reading habit', 'eagleeye' ); ?></strong>
					<span><?php esc_html_e( 'News, politics, business, sport, culture and community reporting for readers in Nigeria and the diaspora.', 'eagleeye' ); ?></span>
				</div>
			</li>
			<li>
				<span class="ad-proof__num">03</span>
				<div>
					<strong><?php esc_html_e( 'Booked directly with the newsroom', 'eagleeye' ); ?></strong>
					<span><?php esc_html_e( 'A written invoice, a named contact and a team that confirms every detail before the placement goes live.', 'eagleeye' ); ?></span>
				</div>
			</li>
		</ol>
	</div>
</section>

<section class="section" id="rates">
	<div class="wrap">
		<?php
		eagleeye_section_head(
			__( 'Placements & rates', 'eagleeye' ),
			'',
			'',
			__( 'Straightforward pricing. Choose a placement and we will confirm availability before you pay.', 'eagleeye' )
		);
		?>

		<div class="ad-rate-grid">
			<?php foreach ( $rates as $key => $package ) : ?>
				<article class="ad-rate-card" id="rate-<?php echo esc_attr( $key ); ?>">
					<span class="ad-rate-card__size"><?php echo esc_html( $package['size'] ); ?></span>
					<h3><?php echo esc_html( $package['label'] ); ?></h3>
					<p><?php echo esc_html( $package['detail'] ); ?></p>
					<div class="ad-rate-card__price">
						<strong><?php echo esc_html( eagleeye_naira( $package['price'] ) ); ?></strong>
						<span><?php echo esc_html( $package['period'] ); ?></span>
					</div>
					<a class="ad-rate-card__cta" href="#media-kit"><?php esc_html_e( 'Enquire about this placement', 'eagleeye' ); ?> <span aria-hidden="true">&rarr;</span></a>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<section class="section section--tint" id="media-kit">
	<div class="wrap ad-booking">
		<div class="ad-booking__intro">
			<span class="eyebrow"><?php esc_html_e( 'Start a conversation', 'eagleeye' ); ?></span>
			<h2><?php echo esc_html( $invoice_request ? __( 'Start another request', 'eagleeye' ) : __( 'Request the media kit.', 'eagleeye' ) ); ?></h2>
			<p><?php esc_html_e( 'Tell us about your brand and the placement you have in mind. We will send an invoice immediately, then confirm your dates, artwork requirements and go-live plan.', 'eagleeye' ); ?></p>

			<ol class="ad-steps">
				<li><span>01</span><div><?php esc_html_e( 'Choose a placement and send your details. Your invoice is generated immediately.', 'eagleeye' ); ?></div></li>
				<li><span>02</span><div><?php esc_html_e( 'Pay by bank transfer using your reference, then tell us in one click.', 'eagleeye' ); ?></div></li>
				<li><span>03</span><div><?php esc_html_e( 'We confirm payment, request your artwork and schedule the placement.', 'eagleeye' ); ?></div></li>
			</ol>

			<div class="ad-contact-strip">
				<span><?php esc_html_e( 'Prefer to talk first?', 'eagleeye' ); ?></span>
				<?php if ( $phone ) : ?>
					<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a>
				<?php endif; ?>
				<?php if ( $email ) : ?>
					<a href="mailto:<?php echo esc_attr( antispambot( $email ) ); ?>"><?php echo esc_html( antispambot( $email ) ); ?></a>
				<?php endif; ?>
			</div>
		</div>

		<div class="ad-form-panel">
			<?php if ( $message['text'] ) : ?>
				<div class="form-notice form-notice--<?php echo esc_attr( $message['type'] ); ?>" role="status"><?php echo esc_html( $message['text'] ); ?></div>
			<?php endif; ?>

			<h2 class="advertise-grid__heading"><?php esc_html_e( 'Tell us about the campaign.', 'eagleeye' ); ?></h2>
			<p class="field__note"><?php esc_html_e( 'All fields marked * are required. Your invoice is generated immediately, with the amount and a payment confirmation link.', 'eagleeye' ); ?></p>

			<form class="ee-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="eagleeye_media_kit">
				<?php wp_nonce_field( 'eagleeye_media_kit', 'eagleeye_kit_nonce' ); ?>

				<div class="field-row">
					<p class="field">
						<label for="ee_company"><?php esc_html_e( 'Company / brand', 'eagleeye' ); ?> <span aria-hidden="true">*</span></label>
						<input type="text" id="ee_company" name="ee_company" required autocomplete="organization">
					</p>
					<p class="field">
						<label for="ee_name"><?php esc_html_e( 'Contact name', 'eagleeye' ); ?> <span aria-hidden="true">*</span></label>
						<input type="text" id="ee_name" name="ee_name" required autocomplete="name">
					</p>
				</div>

				<div class="field-row">
					<p class="field">
						<label for="ee_email"><?php esc_html_e( 'Email', 'eagleeye' ); ?> <span aria-hidden="true">*</span></label>
						<input type="email" id="ee_email" name="ee_email" required autocomplete="email">
					</p>
					<p class="field">
						<label for="ee_phone"><?php esc_html_e( 'Phone', 'eagleeye' ); ?></label>
						<input type="tel" id="ee_phone" name="ee_phone" autocomplete="tel">
					</p>
				</div>

				<div class="field-row">
					<p class="field">
						<label for="ee_package"><?php esc_html_e( 'Package', 'eagleeye' ); ?></label>
						<select id="ee_package" name="ee_package">
							<?php foreach ( $rates as $key => $package ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>">
									<?php echo esc_html( $package['label'] . ' — ' . eagleeye_naira( $package['price'] ) . ' ' . $package['period'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</p>
					<p class="field">
						<label for="ee_start"><?php esc_html_e( 'Preferred start', 'eagleeye' ); ?></label>
						<input type="date" id="ee_start" name="ee_start">
					</p>
				</div>

				<p class="field">
					<label for="ee_budget"><?php esc_html_e( 'Budget note (optional)', 'eagleeye' ); ?></label>
					<input type="text" id="ee_budget" name="ee_budget" placeholder="<?php esc_attr_e( 'e.g. ₦400,000 for a six-week flight', 'eagleeye' ); ?>">
				</p>

				<p class="field">
					<label for="ee_message"><?php esc_html_e( 'Campaign brief', 'eagleeye' ); ?></label>
					<textarea id="ee_message" name="ee_message" rows="5" placeholder="<?php esc_attr_e( 'What are you promoting, and who should see it?', 'eagleeye' ); ?>"></textarea>
				</p>

				<label class="newsletter__honeypot" aria-hidden="true">Website<input type="text" name="ee_website" tabindex="-1" autocomplete="off"></label>

				<p class="form-actions">
					<button type="submit" class="btn btn--solid"><?php esc_html_e( 'Get my invoice', 'eagleeye' ); ?> <span aria-hidden="true">&rarr;</span></button>
				</p>
			</form>
		</div>
	</div>
</section>

<?php
get_footer();
