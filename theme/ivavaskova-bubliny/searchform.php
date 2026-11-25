<?php
/**
 * Formulář hledání.
 */

defined( 'ABSPATH' ) || exit;

$ivb_id = wp_unique_id( 'search-' );
?>
<form role="search" method="get" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="screen-reader-text" for="<?php echo esc_attr( $ivb_id ); ?>">Hledat</label>
	<input class="search-form__input" type="search" id="<?php echo esc_attr( $ivb_id ); ?>" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="Hledat na webu…">
	<button class="search-form__btn" type="submit">Hledat</button>
</form>
