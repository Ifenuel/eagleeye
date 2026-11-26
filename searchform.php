<?php
/**
 * Search form.
 *
 * @package Eagle Eye
 */

$field_id = 'search-' . wp_unique_id();
?>
<form role="search" method="get" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="screen-reader-text" for="<?php echo esc_attr( $field_id ); ?>"><?php esc_html_e( 'Search Eagle Eye', 'eagleeye' ); ?></label>
	<input type="search" id="<?php echo esc_attr( $field_id ); ?>" class="search-form__field" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="<?php esc_attr_e( 'Search stories…', 'eagleeye' ); ?>">
	<button type="submit" class="search-form__submit"><?php esc_html_e( 'Search', 'eagleeye' ); ?></button>
</form>
