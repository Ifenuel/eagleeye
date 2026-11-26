<?php
/**
 * Author archive: who wrote this, and everything they wrote.
 *
 * The stock archive lists posts next to a name and nothing else. A byline is a
 * promise to the reader, so this page introduces the person first — photo,
 * bio, how much they have published — and then their work.
 *
 * @package Eagle Eye
 */

get_header();

$eagleeye_author = get_queried_object();
$eagleeye_photo  = ( $eagleeye_author instanceof WP_User ) ? eagleeye_user_photo_url( $eagleeye_author->ID, array( 160, 160 ) ) : '';
$eagleeye_stories = (int) count_user_posts( $eagleeye_author->ID, 'post', true );
?>

<header class="archive-head author-head">
	<div class="wrap">
		<?php eagleeye_breadcrumbs(); ?>

		<div class="author-head__identity">
			<div class="author-head__photo">
				<?php if ( $eagleeye_photo ) : ?>
					<img src="<?php echo esc_url( $eagleeye_photo ); ?>" alt="" width="112" height="112">
				<?php else : ?>
					<?php eagleeye_avatar( $eagleeye_author, 'xl' ); ?>
				<?php endif; ?>
			</div>

			<div class="author-head__copy">
				<span class="eyebrow"><?php esc_html_e( 'Byline', 'eagleeye' ); ?></span>
				<h1 class="archive-head__title"><?php echo esc_html( $eagleeye_author->display_name ); ?></h1>

				<?php if ( $eagleeye_author->description ) : ?>
					<p class="author-head__bio"><?php echo esc_html( $eagleeye_author->description ); ?></p>
				<?php endif; ?>

				<p class="archive-head__count">
					<?php
					printf(
						/* translators: %s: number of stories. */
						esc_html( _n( '%s story published', '%s stories published', $eagleeye_stories, 'eagleeye' ) ),
						esc_html( number_format_i18n( $eagleeye_stories ) )
					);
					?>
					<span class="dot" aria-hidden="true"></span>
					<?php
					printf(
						/* translators: %s: human readable date. */
						esc_html__( 'Writing here since %s', 'eagleeye' ),
						esc_html( date_i18n( 'F Y', strtotime( $eagleeye_author->user_registered ) ) )
					);
					?>
				</p>
			</div>
		</div>
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
		<p class="empty-note"><?php esc_html_e( 'Nothing has been published under this byline yet.', 'eagleeye' ); ?></p>
	<?php endif; ?>
</div>

<?php
get_footer();
