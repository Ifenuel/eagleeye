<?php
/**
 * Single article.
 *
 * @package Eagle Eye
 */

get_header();

while ( have_posts() ) :
	the_post();

	$term   = eagleeye_primary_category();
	$source = (string) get_post_meta( get_the_ID(), '_eagleeye_source', true );
	$credit = eagleeye_credit();
	?>
	<article id="post-<?php the_ID(); ?>" <?php post_class( 'article' ); ?>>
		<div class="wrap">
			<?php eagleeye_breadcrumbs(); ?>
		</div>

		<header class="article__header wrap">
			<div class="article__kicker-row">
				<?php eagleeye_kicker(); ?>
				<?php if ( eagleeye_flag( '_eagleeye_breaking' ) ) : ?>
					<span class="badge badge--live"><?php esc_html_e( 'Breaking', 'eagleeye' ); ?></span>
				<?php endif; ?>
			</div>

			<h1 class="article__title"><?php the_title(); ?></h1>

			<?php if ( eagleeye_dek() ) : ?>
				<p class="article__dek"><?php echo esc_html( eagleeye_dek() ); ?></p>
			<?php endif; ?>

			<div class="article__byline">
				<?php eagleeye_byline( array( 'avatar' => true ) ); ?>
				<span class="dot" aria-hidden="true"></span>
				<span><?php echo esc_html( get_the_date( 'j F Y, H:i' ) ); ?></span>
				<span class="dot" aria-hidden="true"></span>
				<span><?php printf( esc_html__( '%d min read', 'eagleeye' ), (int) eagleeye_reading_time() ); ?></span>
				<?php if ( $term ) : ?>
					<span class="dot" aria-hidden="true"></span>
					<a class="article__desk" href="<?php echo esc_url( get_category_link( $term ) ); ?>"><?php echo esc_html( $term->name ); ?></a>
				<?php endif; ?>
			</div>
		</header>

		<?php if ( has_post_thumbnail() ) : ?>
			<figure class="article__figure wrap">
				<?php the_post_thumbnail( 'eagleeye-lead', array( 'class' => 'article__image' ) ); ?>
				<?php if ( wp_get_attachment_caption( get_post_thumbnail_id() ) || get_post_meta( get_the_ID(), '_ee_photo_credit', true ) ) : ?>
					<figcaption>
						<?php echo esc_html( wp_get_attachment_caption( get_post_thumbnail_id() ) ); ?>
						<?php eagleeye_photo_credit(); ?>
					</figcaption>
				<?php endif; ?>
			</figure>
		<?php endif; ?>

		<div class="wrap article__layout">
			<div class="article__main">
				<div class="entry-content">
					<?php the_content(); ?>
					<?php
					wp_link_pages( array(
						'before' => '<nav class="page-links">' . esc_html__( 'Pages:', 'eagleeye' ),
						'after'  => '</nav>',
					) );
					?>
				</div>

				<?php if ( $source ) : ?>
					<p class="article__source"><strong><?php esc_html_e( 'Source:', 'eagleeye' ); ?></strong> <?php echo esc_html( $source ); ?></p>
				<?php endif; ?>

				<?php if ( has_tag() ) : ?>
					<div class="article__tags">
						<span class="article__tags-label"><?php esc_html_e( 'Filed under', 'eagleeye' ); ?></span>
						<?php the_tags( '', '', '' ); ?>
					</div>
				<?php endif; ?>

				<?php eagleeye_share_links(); ?>

				<div class="article__author">
					<div>
						<span class="eyebrow"><?php esc_html_e( 'Published by', 'eagleeye' ); ?></span>
						<h2 class="article__author-name"><?php
							if ( $credit ) {
								printf( '<a href="%1$s">%2$s</a>', esc_url( eagleeye_credit_url( $credit ) ), esc_html( $credit ) );
							} else {
								esc_html_e( 'Eagle Eye', 'eagleeye' );
							}
						?></h2>
						<p class="article__author-bio"><?php
							echo esc_html(
								$credit
									? sprintf( __( 'Eagle Eye is an independent Nigerian digital news publication. This story was reported and published by %s.', 'eagleeye' ), $credit )
									: __( 'Eagle Eye is an independent Nigerian digital news publication. This story was edited and published by the Eagle Eye newsroom.', 'eagleeye' )
							);
						?></p>
					</div>
				</div>

				<?php
				$related = new WP_Query( eagleeye_query_args( array(
					'posts_per_page' => 3,
					'post__not_in'   => array( get_the_ID() ),
					'category__in'   => $term ? array( $term->term_id ) : array(),
				) ) );

				if ( $related->have_posts() ) :
					?>
					<section class="related">
						<?php eagleeye_section_head( __( 'Related coverage', 'eagleeye' ), $term ? get_category_link( $term ) : '', __( 'More from this section', 'eagleeye' ) ); ?>
						<div class="related__grid">
							<?php
							while ( $related->have_posts() ) :
								$related->the_post();
								get_template_part( 'template-parts/card', null, array( 'variant' => 'tile' ) );
							endwhile;
							wp_reset_postdata();
							?>
						</div>
					</section>
					<?php
				endif;
				?>
			</div>

			<aside class="article__rail">
				<?php
				$side_q = eagleeye_trending_query( 4, array( get_the_ID() ) );
				if ( $side_q->have_posts() ) :
					?>
					<div class="rail-block rail-block--sticky">
						<h2 class="rail-block__title"><?php esc_html_e( 'Most Read', 'eagleeye' ); ?></h2>
						<ol class="rank-list">
							<?php
							$rank = 0;
							while ( $side_q->have_posts() ) :
								$side_q->the_post();
								$rank++;
								?>
								<li class="rank-list__item">
									<span class="rank-list__num" aria-hidden="true"><?php echo esc_html( $rank ); ?></span>
									<div>
										<?php eagleeye_kicker(); ?>
										<a class="rank-list__title" href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
									</div>
								</li>
							<?php endwhile; ?>
						</ol>
					</div>
					<?php
					wp_reset_postdata();
				endif;

				eagleeye_ad( 'sidebar' );

				if ( is_active_sidebar( 'sidebar-article' ) ) {
					dynamic_sidebar( 'sidebar-article' );
				}
				?>
			</aside>
		</div>

		<?php
		if ( comments_open() || get_comments_number() ) {
			echo '<div class="wrap article__comments">';
			comments_template();
			echo '</div>';
		}
		?>
	</article>
	<?php
endwhile;

get_footer();
