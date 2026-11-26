<?php
/**
 * 404.
 *
 * @package Eagle Eye
 */

get_header();
?>

<section class="not-found">
	<div class="wrap not-found__inner">
		<p class="not-found__code" aria-hidden="true">404</p>
		<span class="eyebrow"><?php esc_html_e( '404', 'eagleeye' ); ?></span>
		<h1 class="not-found__title"><?php esc_html_e( 'Page not found.', 'eagleeye' ); ?></h1>
		<p class="not-found__text"><?php esc_html_e( 'The link may be broken, or the story may have moved. Search the newsroom or return to the front page.', 'eagleeye' ); ?></p>

		<form role="search" method="get" class="not-found__search" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<label class="screen-reader-text" for="not-found-search"><?php esc_html_e( 'Search Eagle Eye', 'eagleeye' ); ?></label>
			<input type="search" id="not-found-search" name="s" placeholder="<?php esc_attr_e( 'Search the newsroom', 'eagleeye' ); ?>">
			<button type="submit" class="btn btn--solid"><?php esc_html_e( 'Search', 'eagleeye' ); ?></button>
		</form>

		<p><a class="link-arrow" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to the front page', 'eagleeye' ); ?> <span aria-hidden="true">&rarr;</span></a></p>
	</div>
</section>

<?php
$latest = new WP_Query( eagleeye_query_args( array( 'posts_per_page' => 3 ) ) );

if ( $latest->have_posts() ) :
	?>
	<section class="section">
		<div class="wrap">
			<?php eagleeye_section_head( __( 'Latest reporting', 'eagleeye' ), home_url( '/latest/' ), __( 'All stories', 'eagleeye' ) ); ?>
			<div class="archive-grid archive-grid--uniform">
				<?php
				while ( $latest->have_posts() ) :
					$latest->the_post();
					get_template_part( 'template-parts/card', null, array( 'variant' => 'tile' ) );
				endwhile;
				wp_reset_postdata();
				?>
			</div>
		</div>
	</section>
	<?php
endif;

get_footer();
