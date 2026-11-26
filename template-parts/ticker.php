<?php
/**
 * Breaking-news ticker.
 *
 * @package Eagle Eye
 */

if ( ! eagleeye_option( 'eagleeye_ticker_enabled', true ) ) {
	return;
}

$breaking = eagleeye_breaking_query( 5 );

if ( ! $breaking->have_posts() ) {
	return;
}

$label = eagleeye_option( 'eagleeye_ticker_label', __( 'Breaking', 'eagleeye' ) );
$items = array();

while ( $breaking->have_posts() ) {
	$breaking->the_post();
	$items[] = array(
		'title' => get_the_title(),
		'url'   => get_permalink(),
		'time'  => eagleeye_short_time(),
	);
}
wp_reset_postdata();
?>
<div class="ticker" data-ticker>
	<div class="wrap ticker__inner">
		<span class="ticker__label">
			<span class="ticker__pulse" aria-hidden="true"></span>
			<?php echo esc_html( $label ); ?>
		</span>

		<div class="ticker__viewport">
			<div class="ticker__track" data-ticker-track>
				<?php for ( $pass = 0; $pass < 2; $pass++ ) : ?>
					<div class="ticker__set" <?php echo 1 === $pass ? 'aria-hidden="true"' : ''; ?>>
						<?php foreach ( $items as $item ) : ?>
							<a class="ticker__item" href="<?php echo esc_url( $item['url'] ); ?>" <?php echo 1 === $pass ? 'tabindex="-1"' : ''; ?>>
								<span class="ticker__headline"><?php echo esc_html( $item['title'] ); ?></span>
								<span class="ticker__time"><?php echo esc_html( $item['time'] ); ?></span>
							</a>
							<span class="ticker__sep" aria-hidden="true"></span>
						<?php endforeach; ?>
					</div>
				<?php endfor; ?>
			</div>
		</div>
	</div>
</div>
