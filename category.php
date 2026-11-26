<?php
/**
 * Category archive.
 *
 * @package Eagle Eye
 */

get_header();

$term        = get_queried_object();
$description = term_description();
?>

<header class="archive-head">
	<div class="wrap">
		<?php eagleeye_breadcrumbs(); ?>
		<span class="eyebrow"><?php esc_html_e( 'Section', 'eagleeye' ); ?></span>
		<h1 class="archive-head__title"><?php single_cat_title(); ?></h1>
		<?php if ( $description ) : ?>
			<div class="archive-head__dek"><?php echo wp_kses_post( $description ); ?></div>
		<?php else : ?>
			<p class="archive-head__dek">
				<?php
				printf(
					/* translators: %s: category name. */
					esc_html__( 'Reporting, analysis and context from the %s section.', 'eagleeye' ),
					esc_html( single_cat_title( '', false ) )
				);
				?>
			</p>
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

<div class="wrap ad-strip">
	<?php eagleeye_ad( 'category' ); ?>
</div>

<div class="wrap archive-body">
	<?php if ( have_posts() ) : ?>
		<?php
		$i = 0;
		while ( have_posts() ) :
			the_post();
			if ( 0 === $i ) :
				?>
				<div class="archive-feature">
					<?php get_template_part( 'template-parts/card', null, array( 'variant' => 'wide' ) ); ?>
				</div>
				<div class="archive-grid">
			<?php else : ?>
				<?php get_template_part( 'template-parts/card', null, array( 'variant' => 'tile' ) ); ?>
			<?php endif; ?>
			<?php
			$i++;
		endwhile;
		?>
		</div>

		<?php eagleeye_pagination(); ?>
	<?php else : ?>
		<p class="empty-note"><?php esc_html_e( 'No stories have been published in this section yet.', 'eagleeye' ); ?></p>
	<?php endif; ?>
</div>

<?php
get_footer();
