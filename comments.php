<?php
/**
 * Comments.
 *
 * @package Eagle Eye
 */

if ( post_password_required() ) {
	return;
}
?>
<section id="comments" class="comments">
	<?php if ( have_comments() ) : ?>
		<h2 class="comments__title">
			<?php
			$count = get_comments_number();
			printf(
				/* translators: %s: comment count. */
				esc_html( _n( '%s response', '%s responses', $count, 'eagleeye' ) ),
				esc_html( number_format_i18n( $count ) )
			);
			?>
		</h2>

		<ol class="comment-list">
			<?php
			wp_list_comments( array(
				'style'       => 'ol',
				'short_ping'  => true,
				'avatar_size' => 44,
			) );
			?>
		</ol>

		<?php
		the_comments_pagination( array(
			'prev_text' => esc_html__( 'Previous', 'eagleeye' ),
			'next_text' => esc_html__( 'Next', 'eagleeye' ),
		) );
		?>
	<?php endif; ?>

	<?php if ( ! comments_open() && get_comments_number() ) : ?>
		<p class="comments__closed"><?php esc_html_e( 'Comments are closed for this story.', 'eagleeye' ); ?></p>
	<?php endif; ?>

	<?php
	comment_form( array(
		'title_reply'        => __( 'Join the conversation', 'eagleeye' ),
		'title_reply_before' => '<h2 id="reply-title" class="comments__title comment-reply-title">',
		'title_reply_after'  => '</h2>',
		'title_reply_to'     => __( 'Reply to %s', 'eagleeye' ),
		'comment_notes_before' => '<p class="comments__notes">' . esc_html__( 'Your email address will not be published. Keep the discussion civil and on the record.', 'eagleeye' ) . '</p>',
		'class_submit'       => 'btn btn--solid',
		'label_submit'       => __( 'Post comment', 'eagleeye' ),
	) );
	?>
</section>
