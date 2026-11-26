<?php
/**
 * Editorial front page.
 *
 * @package Eagle Eye
 */

get_header();

$used = array();

/* -----------------------------------------------------------------
 * Lead package
 * --------------------------------------------------------------- */
$lead_q = new WP_Query( eagleeye_query_args( array(
	'posts_per_page' => 1,
	'meta_key'       => '_eagleeye_featured', // phpcs:ignore WordPress.DB.SlowDBQuery
	'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery
) ) );

if ( ! $lead_q->have_posts() ) {
	$lead_q = new WP_Query( eagleeye_query_args( array( 'posts_per_page' => 1 ) ) );
}

if ( $lead_q->have_posts() ) {
	while ( $lead_q->have_posts() ) {
		$lead_q->the_post();
		$used[] = get_the_ID();
	}
	wp_reset_postdata();
}

$side_q = new WP_Query( eagleeye_query_args( array(
	'posts_per_page' => 5,
	'post__not_in'   => $used,
) ) );

$side_ids = wp_list_pluck( $side_q->posts, 'ID' );
$used     = array_merge( $used, $side_ids );

$brief_q = new WP_Query( eagleeye_query_args( array(
	'posts_per_page' => 3,
	'post__not_in'   => $used,
) ) );

$used = array_merge( $used, wp_list_pluck( $brief_q->posts, 'ID' ) );
?>

<?php if ( ! is_paged() ) : ?>
<div class="wrap ad-strip">
	<?php eagleeye_ad( 'header' ); ?>
</div>
<?php endif; ?>

<section class="hero" aria-label="<?php esc_attr_e( 'Top stories', 'eagleeye' ); ?>">
	<div class="wrap hero__grid">
		<div class="hero__lead">
			<?php
			if ( $lead_q->have_posts() ) :
				while ( $lead_q->have_posts() ) :
					$lead_q->the_post();
					get_template_part( 'template-parts/card', null, array( 'variant' => 'lead' ) );
				endwhile;
				wp_reset_postdata();
			else :
				?>
				<p class="empty-note"><?php esc_html_e( 'Publish your first story to fill the front page.', 'eagleeye' ); ?></p>
			<?php endif; ?>

			<?php if ( $side_ids ) : ?>
				<div class="hero__also">
					<p class="hero__also-label"><?php esc_html_e( 'Also on the front page', 'eagleeye' ); ?></p>
					<ol class="hero__list">
						<?php
						$rank = 1;
						$n    = 0;
						foreach ( $side_ids as $post_id ) :
							$n++;
							if ( $n <= 2 ) {
								// The first two run as feature cards in the rail.
								continue;
							}
							$post_id = (int) $post_id;
							?>
							<li>
								<span class="hero__rank" aria-hidden="true"><?php echo esc_html( str_pad( (string) $rank, 2, '0', STR_PAD_LEFT ) ); ?></span>
								<a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"><?php echo esc_html( get_the_title( $post_id ) ); ?></a>
								<span class="hero__time"><?php echo esc_html( get_the_date( 'H:i', $post_id ) ); ?></span>
							</li>
							<?php
							$rank++;
						endforeach;
						?>
					</ol>
				</div>
			<?php endif; ?>
		</div>

		<div class="hero__side">
			<div class="hero__features">
				<?php
				$side_index = 0;
				if ( $side_q->have_posts() ) :
					while ( $side_q->have_posts() ) :
						$side_q->the_post();
						if ( $side_index < 2 ) {
							get_template_part( 'template-parts/card', null, array( 'variant' => 'feature' ) );
						}
						$side_index++;
					endwhile;
					wp_reset_postdata();
				endif;
				?>
			</div>
		</div>
	</div>
</section>

<div class="brief">
	<div class="wrap brief__inner">
		<p class="brief__label"><?php esc_html_e( 'The Brief', 'eagleeye' ); ?></p>
		<div class="brief__items">
			<?php
			if ( $brief_q->have_posts() ) :
				$brief_i = 0;
				while ( $brief_q->have_posts() ) :
					$brief_q->the_post();
					$brief_i++;
					?>
					<article class="brief__item">
						<span class="brief__count" aria-hidden="true"><?php echo esc_html( $brief_i ); ?></span>
						<?php eagleeye_kicker(); ?>
						<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
						<span class="brief__time"><?php echo esc_html( eagleeye_short_time() ); ?></span>
					</article>
					<?php
				endwhile;
				wp_reset_postdata();
			endif;
			?>
		</div>
	</div>
</div>

<?php
/* -----------------------------------------------------------------
 * Top stories strip
 * --------------------------------------------------------------- */
$top_q = new WP_Query( eagleeye_query_args( array(
	'posts_per_page' => 3,
	'post__not_in'   => $used,
) ) );

if ( $top_q->have_posts() ) :
	$used = array_merge( $used, wp_list_pluck( $top_q->posts, 'ID' ) );
	?>
	<section class="section top-stories">
		<div class="wrap">
			<?php
			eagleeye_section_head(
				__( 'Top Stories', 'eagleeye' ),
				'',
				'',
				__( 'What the newsroom is following right now', 'eagleeye' )
			);
			?>
			<div class="top-stories__grid">
				<?php
				while ( $top_q->have_posts() ) :
					$top_q->the_post();
					get_template_part( 'template-parts/card', null, array( 'variant' => 'tile' ) );
				endwhile;
				wp_reset_postdata();
				?>
			</div>
		</div>
	</section>
	<?php
endif;
?>

<section class="section audience-desks" aria-label="<?php esc_attr_e( 'Community and campus news', 'eagleeye' ); ?>">
	<div class="wrap audience-desks__head">
		<span class="eyebrow"><?php esc_html_e( 'Citizen reporting', 'eagleeye' ); ?></span>
		<h2><?php esc_html_e( 'Eagle Eye Citizen Reporter', 'eagleeye' ); ?></h2>
		<p><?php esc_html_e( 'Community and campus stories reported by the people closest to them.', 'eagleeye' ); ?></p>
	</div>
	<div class="wrap audience-desks__grid">
		<?php
		$audience_desks = array(
			'community-eye' => array(
				'title'       => __( 'Community Eye', 'eagleeye' ),
				'dek'         => __( 'Local stories, people and issues shaping everyday community life.', 'eagleeye' ),
				'link_label'  => __( 'Explore Community Eye', 'eagleeye' ),
			),
			'campus-eye' => array(
				'title'       => __( 'Campus Eye', 'eagleeye' ),
				'dek'         => __( 'Your Campus. Your Voice. Your Story.', 'eagleeye' ),
				'link_label'  => __( 'Explore Campus Eye', 'eagleeye' ),
			),
		);
		foreach ( $audience_desks as $slug => $desk ) :
			$desk_query = new WP_Query( eagleeye_query_args( array(
				'category_name'  => $slug,
				'posts_per_page' => 2,
			) ) );
			$desk_url = home_url( '/campus-eye/' );
			if ( 'community-eye' === $slug ) {
				$desk_url = home_url( '/community-eye/' );
			}
			?>
			<section class="audience-desk audience-desk--<?php echo esc_attr( $slug ); ?>">
				<span class="eyebrow"><?php esc_html_e( 'Citizen reporter desk', 'eagleeye' ); ?></span>
				<h2 class="audience-desk__title"><a href="<?php echo esc_url( $desk_url ); ?>"><?php echo esc_html( $desk['title'] ); ?></a></h2>
				<p class="audience-desk__dek"><?php echo esc_html( $desk['dek'] ); ?></p>
				<?php if ( $desk_query->have_posts() ) : ?>
					<ul class="audience-desk__stories">
						<?php while ( $desk_query->have_posts() ) : $desk_query->the_post(); ?>
							<li><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></li>
						<?php endwhile; ?>
					</ul>
					<?php wp_reset_postdata(); ?>
				<?php else : ?>
					<p class="audience-desk__empty"><?php esc_html_e( 'New stories coming soon.', 'eagleeye' ); ?></p>
				<?php endif; ?>
				<a class="audience-desk__link" href="<?php echo esc_url( $desk_url ); ?>"><?php echo esc_html( $desk['link_label'] ); ?> <span aria-hidden="true">&rarr;</span></a>
			</section>
		<?php endforeach; ?>
	</div>
</section>

<section class="wrap section split">
	<div class="split__main">
		<?php
		$latest_q = new WP_Query( eagleeye_query_args( array(
			'posts_per_page' => 5,
			'post__not_in'   => $used,
		) ) );
		$used     = array_merge( $used, wp_list_pluck( $latest_q->posts, 'ID' ) );

		eagleeye_section_head(
			__( 'Latest News', 'eagleeye' ),
			home_url( '/latest/' ),
			__( 'All stories', 'eagleeye' ),
			__( 'Reported and edited by the Eagle Eye newsroom', 'eagleeye' )
		);
		?>
		<div class="stack">
			<?php
			if ( $latest_q->have_posts() ) :
				while ( $latest_q->have_posts() ) :
					$latest_q->the_post();
					get_template_part( 'template-parts/card', null, array( 'variant' => 'list' ) );
				endwhile;
				wp_reset_postdata();
			endif;
			?>
		</div>
	</div>

	<aside class="split__rail" aria-label="<?php esc_attr_e( 'Most read and advertising', 'eagleeye' ); ?>">
		<?php
		$trending_q = eagleeye_trending_query( 5, $used );
		if ( $trending_q->have_posts() ) :
			?>
			<div class="rail-block">
				<h2 class="rail-block__title"><?php esc_html_e( 'Most Read', 'eagleeye' ); ?></h2>
				<ol class="rank-list">
					<?php
					$rank = 0;
					while ( $trending_q->have_posts() ) :
						$trending_q->the_post();
						$rank++;
						?>
						<li class="rank-list__item">
							<span class="rank-list__num" aria-hidden="true"><?php echo esc_html( $rank ); ?></span>
							<div>
								<?php eagleeye_kicker(); ?>
								<a class="rank-list__title" href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
								<span class="rank-list__meta"><?php printf( esc_html__( '%s reads', 'eagleeye' ), esc_html( eagleeye_views_label() ) ); ?></span>
							</div>
						</li>
					<?php endwhile; ?>
				</ol>
			</div>
			<?php
			wp_reset_postdata();
		endif;

		eagleeye_ad( 'sidebar' );

		if ( is_active_sidebar( 'sidebar-home' ) ) {
			dynamic_sidebar( 'sidebar-home' );
		}
		?>
	</aside>
</section>

<?php
/* -----------------------------------------------------------------
 * Desk band: Politics
 * --------------------------------------------------------------- */
$politics_q = eagleeye_desk_query( 'politics', 4, $used );
if ( $politics_q->have_posts() ) :
	$used = array_merge( $used, wp_list_pluck( $politics_q->posts, 'ID' ) );
	?>
	<section class="section desk-band">
		<div class="wrap">
			<?php
			eagleeye_section_head(
				__( 'Politics', 'eagleeye' ),
				eagleeye_category_url( 'politics' ),
				__( 'All politics', 'eagleeye' )
			);
			?>
			<div class="desk-band__grid">
				<?php
				$i = 0;
				while ( $politics_q->have_posts() ) :
					$politics_q->the_post();
					if ( 0 === $i ) {
						get_template_part( 'template-parts/card', null, array( 'variant' => 'feature' ) );
					}
					$i++;
				endwhile;
				?>
				<div class="desk-band__list">
					<?php
					$politics_q->rewind_posts();
					$i = 0;
					while ( $politics_q->have_posts() ) :
						$politics_q->the_post();
						if ( $i > 0 ) {
							get_template_part( 'template-parts/card', null, array( 'variant' => 'compact' ) );
						}
						$i++;
					endwhile;
					wp_reset_postdata();
					?>
				</div>
			</div>
		</div>
	</section>
	<?php
endif;

/* -----------------------------------------------------------------
 * The Big Read
 * --------------------------------------------------------------- */
$bigread_q = new WP_Query( eagleeye_query_args( array(
	'posts_per_page' => 1,
	'tag'            => 'the-big-read',
	'post__not_in'   => $used,
) ) );

if ( ! $bigread_q->have_posts() ) {
	$bigread_q = eagleeye_desk_query( 'international', 1, $used );
}

if ( $bigread_q->have_posts() ) :
	$used = array_merge( $used, wp_list_pluck( $bigread_q->posts, 'ID' ) );
	?>
	<section class="bigread">
		<div class="wrap bigread__inner">
			<?php
			while ( $bigread_q->have_posts() ) :
				$bigread_q->the_post();
				?>
				<a class="bigread__media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
					<?php eagleeye_thumbnail( 'eagleeye-wide' ); ?>
				</a>
				<div class="bigread__panel">
					<span class="eyebrow eyebrow--light"><?php esc_html_e( 'The Big Read', 'eagleeye' ); ?></span>
					<?php eagleeye_kicker(); ?>
					<h2 class="bigread__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
					<p class="bigread__dek"><?php echo esc_html( wp_trim_words( eagleeye_dek(), 30, '…' ) ); ?></p>
					<div class="bigread__meta">
						<?php eagleeye_byline(); ?>
						<span class="dot" aria-hidden="true"></span>
						<span><?php printf( esc_html__( '%d min read', 'eagleeye' ), (int) eagleeye_reading_time() ); ?></span>
					</div>
					<a class="link-arrow" href="<?php the_permalink(); ?>"><?php esc_html_e( 'Read the full story', 'eagleeye' ); ?> <span aria-hidden="true">&rarr;</span></a>
				</div>
			<?php endwhile; wp_reset_postdata(); ?>
		</div>
	</section>
	<?php
endif;
?>

<?php
/* -----------------------------------------------------------------
 * Two-desk band
 * --------------------------------------------------------------- */
$desk_pair = array(
	'business'   => __( 'Business', 'eagleeye' ),
	'technology' => __( 'Technology', 'eagleeye' ),
);
?>
<section class="section section--tint">
	<div class="wrap twin-desks">
		<?php foreach ( $desk_pair as $slug => $label ) : ?>
			<?php
			$q = eagleeye_desk_query( $slug, 4, $used );
			if ( ! $q->have_posts() ) {
				continue;
			}
			$used = array_merge( $used, wp_list_pluck( $q->posts, 'ID' ) );
			?>
			<div class="twin-desks__col">
				<?php eagleeye_section_head( $label, eagleeye_category_url( $slug ), __( 'See all', 'eagleeye' ) ); ?>
				<?php
				$i = 0;
				while ( $q->have_posts() ) :
					$q->the_post();
					get_template_part( 'template-parts/card', null, array( 'variant' => 0 === $i ? 'tile' : 'compact' ) );
					$i++;
				endwhile;
				wp_reset_postdata();
				?>
			</div>
		<?php endforeach; ?>
	</div>
</section>

<?php
/* -----------------------------------------------------------------
 * Three desk columns
 * --------------------------------------------------------------- */
$desk_trio = array(
	'entertainment' => __( 'Entertainment', 'eagleeye' ),
	'sports'        => __( 'Sports', 'eagleeye' ),
	'lifestyle'     => __( 'Lifestyle', 'eagleeye' ),
);
?>
<section class="section">
	<div class="wrap trio">
		<?php foreach ( $desk_trio as $slug => $label ) : ?>
			<?php
			$q = eagleeye_desk_query( $slug, 4, $used );
			if ( ! $q->have_posts() ) {
				continue;
			}
			$used = array_merge( $used, wp_list_pluck( $q->posts, 'ID' ) );
			?>
			<div class="trio__col">
				<?php eagleeye_section_head( $label, eagleeye_category_url( $slug ) ); ?>
				<?php
				$i = 0;
				while ( $q->have_posts() ) :
					$q->the_post();
					get_template_part( 'template-parts/card', null, array( 'variant' => 0 === $i ? 'tile' : 'compact' ) );
					$i++;
				endwhile;
				wp_reset_postdata();
				?>
			</div>
		<?php endforeach; ?>
	</div>
</section>

<?php
/* -----------------------------------------------------------------
 * Nation & learning desks
 * --------------------------------------------------------------- */
$desk_nation = array(
	'education' => __( 'Education', 'eagleeye' ),
	'health'    => __( 'Health', 'eagleeye' ),
	'metro'     => __( 'Nigeria News', 'eagleeye' ),
);
?>
<section class="section section--rule">
	<div class="wrap nation">
		<div class="nation__intro">
			<span class="eyebrow"><?php esc_html_e( 'Across the nation', 'eagleeye' ); ?></span>
			<h2 class="nation__title"><?php esc_html_e( 'The sections closest to daily life.', 'eagleeye' ); ?></h2>
		</div>
		<div class="nation__cols">
			<?php foreach ( $desk_nation as $slug => $label ) : ?>
				<?php
				$q = eagleeye_desk_query( $slug, 3, $used );
				if ( ! $q->have_posts() ) {
					continue;
				}
				$used = array_merge( $used, wp_list_pluck( $q->posts, 'ID' ) );
				?>
				<div class="nation__col">
					<h3 class="nation__head"><a href="<?php echo esc_url( eagleeye_category_url( $slug ) ); ?>"><?php echo esc_html( $label ); ?></a></h3>
					<?php
					while ( $q->have_posts() ) :
						$q->the_post();
						get_template_part( 'template-parts/card', null, array( 'variant' => 'compact' ) );
					endwhile;
					wp_reset_postdata();
					?>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<?php
/* -----------------------------------------------------------------
 * World News
 * --------------------------------------------------------------- */
$world_q = eagleeye_desk_query( 'international', 3 );
if ( $world_q->have_posts() ) :
	$used = array_merge( $used, wp_list_pluck( $world_q->posts, 'ID' ) );
	?>
	<section class="section section--tint">
		<div class="wrap">
			<?php
			eagleeye_section_head(
				__( 'World News', 'eagleeye' ),
				eagleeye_category_url( 'international' ),
				__( 'World coverage', 'eagleeye' ),
				__( 'How the world changes what happens at home', 'eagleeye' )
			);
			?>
			<div class="world">
				<?php
				$i = 0;
				while ( $world_q->have_posts() ) :
					$world_q->the_post();
					get_template_part( 'template-parts/card', null, array( 'variant' => 0 === $i ? 'wide' : 'compact' ) );
					$i++;
				endwhile;
				wp_reset_postdata();
				?>
			</div>
		</div>
	</section>
	<?php
endif;

/* -----------------------------------------------------------------
 * Opinion
 * --------------------------------------------------------------- */
$opinion_q = eagleeye_desk_query( 'opinion', 3, $used );
if ( $opinion_q->have_posts() ) :
	$used = array_merge( $used, wp_list_pluck( $opinion_q->posts, 'ID' ) );
	?>
	<section class="opinion-band">
		<div class="wrap">
			<div class="opinion-band__head">
				<div>
					<span class="eyebrow eyebrow--light"><?php esc_html_e( 'Opinion & Analysis', 'eagleeye' ); ?></span>
					<h2 class="opinion-band__title"><?php esc_html_e( 'Argument, evidence, and the case for better.', 'eagleeye' ); ?></h2>
				</div>
				<a class="link-arrow link-arrow--light" href="<?php echo esc_url( eagleeye_category_url( 'opinion' ) ); ?>"><?php esc_html_e( 'All opinion', 'eagleeye' ); ?> <span aria-hidden="true">&rarr;</span></a>
			</div>

			<div class="opinion-band__grid">
				<?php
				while ( $opinion_q->have_posts() ) :
					$opinion_q->the_post();
					?>
					<article class="opinion-card">
						<span class="opinion-card__mark" aria-hidden="true">&ldquo;</span>
						<h3 class="opinion-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
						<p class="opinion-card__dek"><?php echo esc_html( wp_trim_words( eagleeye_dek(), 20, '…' ) ); ?></p>
						<div class="opinion-card__author">
							<div>
								<span class="opinion-card__name"><?php esc_html_e( 'Eagle Eye', 'eagleeye' ); ?></span>
								<span class="opinion-card__role"><?php echo esc_html( get_the_date( 'j F Y' ) ); ?></span>
							</div>
						</div>
					</article>
				<?php endwhile; wp_reset_postdata(); ?>
			</div>
		</div>
	</section>
	<?php
endif;

/* -----------------------------------------------------------------
 * Video band
 * --------------------------------------------------------------- */
$video_q = new WP_Query( eagleeye_query_args( array(
	'posts_per_page' => 3,
	'meta_key'       => '_ee_video', // phpcs:ignore WordPress.DB.SlowDBQuery
	'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery
	'post__not_in'   => $used,
) ) );

if ( $video_q->have_posts() ) :
	?>
	<section class="video-band" aria-label="<?php esc_attr_e( 'Video reports', 'eagleeye' ); ?>">
		<div class="wrap">
			<div class="video-band__head">
				<div>
					<span class="eyebrow eyebrow--light"><?php esc_html_e( 'Watch', 'eagleeye' ); ?></span>
					<h2 class="video-band__title"><?php esc_html_e( 'Video reports', 'eagleeye' ); ?></h2>
				</div>
			</div>
			<div class="video-band__grid">
				<?php
				while ( $video_q->have_posts() ) :
					$video_q->the_post();
					?>
					<article <?php post_class( 'video-card' ); ?>>
						<a class="video-card__media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
							<?php eagleeye_thumbnail( 'eagleeye-card' ); ?>
							<span class="video-card__play" aria-hidden="true"></span>
						</a>
						<?php eagleeye_kicker(); ?>
						<h3 class="video-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
					</article>
				<?php
				endwhile;
				wp_reset_postdata();
				?>
			</div>
		</div>
	</section>
	<?php
endif;

get_footer();
