<?php
/**
 * Latest stories index (posts page).
 *
 * @package Eagle Eye
 */

get_header();
?>

<header class="archive-head">
	<div class="wrap">
		<?php eagleeye_breadcrumbs(); ?>
		<span class="eyebrow"><?php esc_html_e( 'The newsroom', 'eagleeye' ); ?></span>
		<h1 class="archive-head__title"><?php esc_html_e( 'Latest Stories', 'eagleeye' ); ?></h1>
		<p class="archive-head__dek"><?php esc_html_e( 'Every report, analysis and dispatch, newest first.', 'eagleeye' ); ?></p>
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
		<p class="empty-note"><?php esc_html_e( 'No stories published yet.', 'eagleeye' ); ?></p>
	<?php endif; ?>
</div>

<?php
get_footer();
