<?php
/**
 * Video handling: poster-first player with a guaranteed YouTube fallback.
 *
 * @package Eagle Eye
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Replace raw YouTube iframes with a poster facade.
 *
 * YouTube rejects some embedded contexts outright (player error 153), which
 * leaves a blank box on the page. Showing the story image as a poster with an
 * explicit "Watch on YouTube" link means the reader always has a working path
 * to the video.
 *
 * @param string $content Post content.
 * @return string
 */
function eagleeye_video_facade( $content ) {
	if ( ! is_singular() || false === strpos( $content, 'youtube' ) ) {
		return $content;
	}

	$poster = '';
	if ( has_post_thumbnail() ) {
		$poster = (string) get_the_post_thumbnail_url( get_the_ID(), 'eagleeye-wide' );
	}

	$content = preg_replace_callback(
		'#<iframe[^>]+src=["\']https?://(?:www\.)?youtube(?:-nocookie)?\.com/embed/([A-Za-z0-9_-]{11})[^>]*>\s*</iframe>#i',
		function ( $matches ) use ( $poster ) {
			$id    = $matches[1];
			$watch = 'https://www.youtube.com/watch?v=' . $id;
			$image = $poster ? $poster : 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg';

			return sprintf(
				'<div class="ee-video" data-video-id="%1$s">'
				. '<button type="button" class="ee-video__play" aria-label="%2$s">'
				. '<img src="%3$s" alt="" width="1200" height="675" loading="lazy" decoding="async">'
				. '<span class="ee-video__btn" aria-hidden="true"></span>'
				. '<span class="ee-video__caption">%2$s</span>'
				. '</button>'
				. '<p class="ee-video__fallback"><a href="%4$s" target="_blank" rel="noopener">%5$s <span aria-hidden="true">&rarr;</span></a></p>'
				. '</div>',
				esc_attr( $id ),
				esc_attr__( 'Play video', 'eagleeye' ),
				esc_url( $image ),
				esc_url( $watch ),
				esc_html__( 'If the player does not load, watch on YouTube', 'eagleeye' )
			);
		},
		$content
	);

	return $content;
}
add_filter( 'the_content', 'eagleeye_video_facade', 20 );

/**
 * Swap the facade for a real player when the reader presses play.
 */
function eagleeye_video_script() {
	if ( ! is_singular( 'post' ) ) {
		return;
	}
	?>
	<script>
	document.addEventListener('click', function (event) {
		var button = event.target.closest('.ee-video__play');
		if (!button) {
			return;
		}
		var box = button.closest('.ee-video');
		var frame = document.createElement('iframe');
		frame.className = 'ee-video__frame';
		frame.src = 'https://www.youtube.com/embed/' + box.dataset.videoId + '?autoplay=1&rel=0';
		frame.title = button.getAttribute('aria-label') || 'Video';
		frame.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share');
		frame.setAttribute('allowfullscreen', '');
		frame.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
		box.insertBefore(frame, button);
		button.remove();
	});
	</script>
	<?php
}
add_action( 'wp_footer', 'eagleeye_video_script' );
