<?php
/**
 * Search results.
 *
 * @package Eagle Eye
 */

get_header();
?>

<header class="archive-head">
	<div class="wrap">
		<?php eagleeye_breadcrumbs(); ?>
		<span class="eyebrow"><?php esc_html_e( 'Search', 'eagleeye' ); ?></span>
		<h1 class="archive-head__title">
			<?php
			printf(
				/* translators: %s: search query. */
				esc_html__( 'Results for “%s”', 'eagleeye' ),
				esc_html( get_search_query() )
			);
			?>
		</h1>
		<p class="archive-head__count">
			<?php
			printf(
				/* translators: %s: result count. */
				esc_html( _n( '%s story found', '%s stories found', (int) $GLOBALS['wp_query']->found_posts, 'eagleeye' ) ),
				esc_html( number_format_i18n( (int) $GLOBALS['wp_query']->found_posts ) )
			);
			?>
		</p>

		<form role="search" method="get" class="archive-search" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<label class="screen-reader-text" for="archive-search-field"><?php esc_html_e( 'Search again', 'eagleeye' ); ?></label>
			<input type="search" id="archive-search-field" name="s" value="<?php echo esc_attr( get_search_query() ); ?>">
			<button type="submit" class="btn btn--outline"><?php esc_html_e( 'Search', 'eagleeye' ); ?></button>
		</form>
	</div>
</header>

<div class="wrap archive-body">
	<?php if ( have_posts() ) : ?>
		<div class="feed">
			<?php
			while ( have_posts() ) :
				the_post();
				get_template_part( 'template-parts/card', null, array( 'variant' => 'list' ) );
			endwhile;
			?>
		</div>

		<?php eagleeye_pagination(); ?>
	<?php else : ?>
		<div class="empty-state">
			<h2><?php esc_html_e( 'We could not find that story.', 'eagleeye' ); ?></h2>
			<p><?php esc_html_e( 'Try a broader phrase, a section name, or browse the latest reporting.', 'eagleeye' ); ?></p>
			<p><a class="btn btn--solid" href="<?php echo esc_url( home_url( '/latest/' ) ); ?>"><?php esc_html_e( 'Browse latest stories', 'eagleeye' ); ?></a></p>
		</div>
	<?php endif; ?>
</div>

<?php
get_footer();
