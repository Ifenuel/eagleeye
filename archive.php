<?php
/**
 * Generic archive: tags, authors, dates.
 *
 * @package Eagle Eye
 */

get_header();
?>

<header class="archive-head">
	<div class="wrap">
		<?php eagleeye_breadcrumbs(); ?>
		<span class="eyebrow"><?php esc_html_e( 'Archive', 'eagleeye' ); ?></span>
		<h1 class="archive-head__title"><?php the_archive_title(); ?></h1>
		<?php if ( get_the_archive_description() ) : ?>
			<div class="archive-head__dek"><?php the_archive_description(); ?></div>
		<?php endif; ?>
		<p class="archive-head__count">
			<?php
			printf(
				/* translators: %s: number of stories. */
				esc_html( _n( '%s story', '%s stories', (int) $GLOBALS['wp_query']->found_posts, 'eagleeye' ) ),
				esc_html( number_format_i18n( (int) $GLOBALS['wp_query']->found_posts ) )
			);
			?>
		</p>
	</div>
</header>

<div class="wrap archive-body">
	<?php if ( have_posts() ) : ?>
		<div class="archive-grid archive-grid--uniform">
			<?php
			while ( have_posts() ) :
				the_post();
				get_template_part( 'template-parts/card', null, array( 'variant' => 'tile' ) );
			endwhile;
			?>
		</div>

		<?php eagleeye_pagination(); ?>
	<?php else : ?>
		<p class="empty-note"><?php esc_html_e( 'Nothing has been published here yet.', 'eagleeye' ); ?></p>
	<?php endif; ?>
</div>

<?php
get_footer();
