<?php
/**
 * Sidebar.
 *
 * @package Eagle Eye
 */

if ( ! is_active_sidebar( 'sidebar-article' ) && ! is_active_sidebar( 'sidebar-home' ) ) {
	return;
}
?>
<aside class="sidebar" aria-label="<?php esc_attr_e( 'Sidebar', 'eagleeye' ); ?>">
	<?php
	if ( is_active_sidebar( 'sidebar-article' ) ) {
		dynamic_sidebar( 'sidebar-article' );
	}

	eagleeye_ad( 'sidebar' );
	?>
</aside>
