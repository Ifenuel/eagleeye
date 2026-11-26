<?php
/**
 * Standard page.
 *
 * Used for the standards library and any future information pages. Each page
 * gets its own hero, introduction and accent while sharing one reading system.
 *
 * @package Eagle Eye
 */

get_header();

while ( have_posts() ) :
	the_post();

	$slug    = get_post_field( 'post_name', get_the_ID() );
	$policies = eagleeye_policy_meta();
	$meta    = isset( $policies[ $slug ] ) ? $policies[ $slug ] : array();
	$library = $meta ? eagleeye_policy_library() : array();
	$raw     = apply_filters( 'the_content', get_the_content() );

	if ( $meta ) {
		$prepared = eagleeye_prepare_page_content( $raw, $slug );
		$summary  = $meta['intro'];
		$updated  = $prepared['updated'] ? $prepared['updated'] : get_the_modified_date( 'j F Y' );
		$read     = $prepared['read_time'];
	} else {
		$prepared = eagleeye_prepare_page_content( $raw, 'section' );
		$summary  = has_excerpt() ? get_the_excerpt() : eagleeye_page_summary( $raw );
		$updated  = $prepared['updated'] ? $prepared['updated'] : get_the_modified_date( 'j F Y' );
		$read     = $prepared['read_time'];
	}

	$logo = eagleeye_brand_mark();
	$email = eagleeye_option( 'eagleeye_email', '' );
	$accent = $meta ? $meta['accent'] : 'gold';
	$policy_count = count( $library );
	?>
	<div class="ee-progress" aria-hidden="true"><span></span></div>

	<article <?php post_class( 'brand-page policy-page policy-page--' . $accent ); ?>>
		<header class="brand-page-hero">
			<div class="wrap brand-page-hero__grid">
				<div class="brand-page-hero__copy">
					<?php eagleeye_breadcrumbs(); ?>

					<p class="brand-page-hero__kicker">
						<?php if ( $meta ) : ?>
							<span><?php echo esc_html( sprintf( __( 'Policy %1$s / %2$s', 'eagleeye' ), $meta['number'], str_pad( (string) $policy_count, 2, '0', STR_PAD_LEFT ) ) ); ?></span>
							<?php echo esc_html( $meta['label'] ); ?>
						<?php else : ?>
							<span><?php esc_html_e( 'Eagle Eye', 'eagleeye' ); ?></span>
							<?php esc_html_e( 'Information', 'eagleeye' ); ?>
						<?php endif; ?>
					</p>

					<h1 class="brand-page-hero__title"><?php the_title(); ?></h1>
					<p class="brand-page-hero__dek"><?php echo esc_html( $summary ); ?></p>

					<div class="brand-page-hero__meta">
						<span><?php echo esc_html( sprintf( __( 'Updated %s', 'eagleeye' ), $updated ) ); ?></span>
						<span><?php echo esc_html( sprintf( __( '%s min read', 'eagleeye' ), number_format_i18n( $read ) ) ); ?></span>
						<span><?php esc_html_e( 'Maintained by the newsroom', 'eagleeye' ); ?></span>
					</div>
				</div>

				<aside class="brand-page-hero__panel">
					<?php if ( $logo ) : ?>
						<a class="brand-page-hero__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
							<img src="<?php echo esc_url( $logo['url'] ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" width="<?php echo esc_attr( $logo['width'] ); ?>" height="<?php echo esc_attr( $logo['height'] ); ?>" loading="eager" decoding="async">
						</a>
					<?php endif; ?>

					<?php if ( $library ) : ?>
						<p class="brand-page-hero__panel-label"><?php esc_html_e( 'Standards & policies', 'eagleeye' ); ?></p>
						<ol class="policy-index">
							<?php foreach ( $library as $item ) : ?>
								<li<?php echo $item['slug'] === $slug ? ' class="is-current" aria-current="page"' : ''; ?>>
									<a href="<?php echo esc_url( $item['url'] ); ?>">
										<span><?php echo esc_html( $item['number'] ); ?></span>
										<?php echo esc_html( $item['label'] ); ?>
									</a>
								</li>
							<?php endforeach; ?>
						</ol>
					<?php else : ?>
						<p class="brand-page-hero__panel-label"><?php esc_html_e( 'The newsroom', 'eagleeye' ); ?></p>
						<p class="brand-page-hero__panel-note"><?php esc_html_e( 'Independent journalism, published by Eagle Eye.', 'eagleeye' ); ?></p>
						<a class="link-arrow link-arrow--light" href="<?php echo esc_url( home_url( '/contact-us/' ) ); ?>"><?php esc_html_e( 'Contact Eagle Eye', 'eagleeye' ); ?> <span aria-hidden="true">&rarr;</span></a>
					<?php endif; ?>
				</aside>
			</div>
		</header>

		<?php if ( has_post_thumbnail() ) : ?>
			<figure class="page__figure wrap">
				<?php the_post_thumbnail( 'eagleeye-wide', array( 'class' => 'page__image' ) ); ?>
			</figure>
		<?php endif; ?>

		<div class="wrap policy-layout">
			<aside class="policy-rail">
				<?php if ( $prepared['toc'] ) : ?>
					<details class="policy-toc" open>
						<summary><?php esc_html_e( 'On this page', 'eagleeye' ); ?></summary>
						<nav aria-label="<?php esc_attr_e( 'On this page', 'eagleeye' ); ?>">
							<ol>
								<?php foreach ( $prepared['toc'] as $index => $item ) : ?>
									<li>
										<a href="#<?php echo esc_attr( $item['id'] ); ?>">
											<span><?php echo esc_html( str_pad( (string) ( $index + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span>
											<?php echo esc_html( $item['title'] ); ?>
										</a>
									</li>
								<?php endforeach; ?>
							</ol>
						</nav>
					</details>
				<?php endif; ?>

				<div class="policy-rail__contact">
					<p class="policy-rail__label"><?php esc_html_e( 'Questions?', 'eagleeye' ); ?></p>
					<p><?php esc_html_e( 'Write to the newsroom and we will point you to the right person.', 'eagleeye' ); ?></p>
					<?php if ( $email ) : ?>
						<a href="mailto:<?php echo esc_attr( antispambot( $email ) ); ?>"><?php echo esc_html( antispambot( $email ) ); ?></a>
					<?php endif; ?>
					<a class="link-arrow" href="<?php echo esc_url( home_url( '/contact-us/' ) ); ?>"><?php esc_html_e( 'Contact page', 'eagleeye' ); ?> <span aria-hidden="true">&rarr;</span></a>
				</div>
			</aside>

			<div class="policy-main">
				<div class="policy-prose prose">
					<?php echo wp_kses_post( $prepared['content'] ); ?>
					<?php
					wp_link_pages( array(
						'before' => '<nav class="page-links">' . esc_html__( 'Pages:', 'eagleeye' ),
						'after'  => '</nav>',
					) );
					?>
				</div>

				<footer class="policy-end">
					<div>
						<p class="policy-end__label"><?php esc_html_e( 'Eagle Eye', 'eagleeye' ); ?></p>
						<p class="policy-end__note"><?php esc_html_e( 'Seeing beyond the headlines. Published by Eagle Eye.', 'eagleeye' ); ?></p>
					</div>
					<div class="policy-end__actions">
						<button class="btn btn--outline" type="button" data-print-page><?php esc_html_e( 'Print this page', 'eagleeye' ); ?></button>
						<?php if ( $meta ) : ?>
							<a class="btn btn--solid" href="<?php echo esc_url( home_url( '/contact-us/' ) ); ?>"><?php esc_html_e( 'Contact the newsroom', 'eagleeye' ); ?></a>
						<?php endif; ?>
					</div>
				</footer>

				<?php if ( $meta ) : ?>
					<p class="policy-disclaimer"><?php esc_html_e( 'This page describes Eagle Eye’s own practices. It is not legal advice.', 'eagleeye' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
	</article>
	<?php
endwhile;

get_footer();
