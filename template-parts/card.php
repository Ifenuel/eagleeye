<?php
/**
 * Story card.
 *
 * @package Eagle Eye
 */

$variant = isset( $args['variant'] ) ? $args['variant'] : 'feature';
$index   = isset( $args['index'] ) ? (int) $args['index'] : 0;
$classes = array( 'card', 'card--' . $variant );
?>
<article <?php post_class( $classes ); ?>>
	<?php if ( in_array( $variant, array( 'lead', 'feature', 'tile', 'wide' ), true ) ) : ?>
		<a class="card__media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
			<?php eagleeye_thumbnail( 'lead' === $variant ? 'eagleeye-lead' : ( 'wide' === $variant ? 'eagleeye-wide' : 'eagleeye-card' ) ); ?>
		</a>
	<?php endif; ?>

	<div class="card__body">
		<?php if ( 'numbered' === $variant && $index ) : ?>
			<span class="card__rank" aria-hidden="true"><?php echo esc_html( str_pad( (string) $index, 2, '0', STR_PAD_LEFT ) ); ?></span>
		<?php endif; ?>

		<?php eagleeye_kicker(); ?>

		<h3 class="card__title">
			<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
		</h3>

		<?php if ( in_array( $variant, array( 'lead', 'feature', 'wide', 'list' ), true ) ) : ?>
			<p class="card__dek"><?php echo esc_html( wp_trim_words( eagleeye_dek(), 'list' === $variant ? 20 : 24, '…' ) ); ?></p>
		<?php endif; ?>

		<?php if ( in_array( $variant, array( 'lead', 'wide' ), true ) ) : ?>
			<div class="card__meta">
				<?php eagleeye_byline( array( 'avatar' => 'lead' === $variant ) ); ?>
				<span class="dot" aria-hidden="true"></span>
				<?php eagleeye_posted_on(); ?>
				<span class="dot" aria-hidden="true"></span>
				<span><?php printf( esc_html__( '%d min read', 'eagleeye' ), (int) eagleeye_reading_time() ); ?></span>
			</div>
		<?php else : ?>
			<div class="card__meta">
				<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( eagleeye_short_time() ); ?></time>
				<?php if ( 'opinion' === $variant ) : ?>
					<span class="dot" aria-hidden="true"></span>
					<span><?php esc_html_e( 'Eagle Eye', 'eagleeye' ); ?></span>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>

	<?php if ( 'list' === $variant ) : ?>
		<a class="card__thumb" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
			<?php eagleeye_thumbnail( 'eagleeye-card' ); ?>
		</a>
	<?php endif; ?>
</article>
