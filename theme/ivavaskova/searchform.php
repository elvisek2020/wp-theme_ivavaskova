<?php
/**
 * Vyhledávací formulář.
 */

defined( 'ABSPATH' ) || exit;

$iv_id = wp_unique_id( 'iv-search-' );
?>
<form role="search" method="get" class="iv-search" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="screen-reader-text" for="<?php echo esc_attr( $iv_id ); ?>">Hledat</label>
	<input type="search" id="<?php echo esc_attr( $iv_id ); ?>" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="Hledat na blogu…">
	<button type="submit" class="iv-btn"><?php echo iv_icon( 'search', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span class="screen-reader-text">Hledat</span></button>
</form>
