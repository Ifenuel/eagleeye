<?php
/**
 * Fallback template.
 *
 * @package Eagle Eye
 */

get_header();
?>

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
		<p class="empty-note"><?php esc_html_e( 'Nothing found.', 'eagleeye' ); ?></p>
	<?php endif; ?>
</div>

<?php
get_footer();
