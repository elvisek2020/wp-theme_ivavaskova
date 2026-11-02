<?php
/**
 * Projektové pomocné funkce šablony: logo s názvem, datum, rubriky, meta článku, stránkování.
 * Ikony jsou z kanonu (ekt_icon()), sociální sítě z modulu social (ekt_social_links()).
 */

defined( 'ABSPATH' ) || exit;

/** Datum česky: 7. ledna 2021 (s nedělitelnou mezerou). */
function iv_date( $post = null ): string {
	return str_replace( '. ', ".\u{00A0}", get_the_date( 'j. F Y', $post ) );
}

/** Rubriky článku jako štítky (bez nadřazené „Kategorie“ a „Nezařazené“). */
function iv_post_cats( $post = null, int $max = 2 ): string {
	$out = array();
	foreach ( (array) get_the_category( $post ? get_post( $post )->ID : 0 ) as $cat ) {
		if ( in_array( $cat->slug, array( 'kategorie', 'nezarazene', 'uncategorized' ), true ) ) {
			continue;
		}
		$out[] = sprintf( '<a class="iv-cat" href="%s">%s</a>', esc_url( get_category_link( $cat ) ), esc_html( $cat->name ) );
		if ( count( $out ) >= $max ) {
			break;
		}
	}
	return implode( '', $out );
}

/** Řádek meta: rubriky + datum. */
function iv_post_meta( $post = null ): void {
	printf(
		'<div class="iv-meta">%s<time datetime="%s">%s</time></div>',
		iv_post_cats( $post ), // phpcs:ignore WordPress.Security.EscapeOutput -- escapováno v iv_post_cats
		esc_attr( get_the_date( 'c', $post ) ),
		esc_html( iv_date( $post ) )
	);
}

/** Logo (ilustrace) + název webu. */
function iv_branding(): void {
	$tag = ( is_front_page() && is_home() ) ? 'h1' : 'p';
	echo '<a class="iv-brand" href="' . esc_url( home_url( '/' ) ) . '" rel="home">';
	$logo = (int) get_theme_mod( 'custom_logo' );
	if ( $logo ) {
		echo wp_get_attachment_image( $logo, 'medium_large', false, array( 'class' => 'iv-brand-logo', 'alt' => '', 'fetchpriority' => 'high', 'loading' => 'eager', 'sizes' => '(max-width: 600px) 70vw, 300px' ) );
	}
	printf( '<%1$s class="iv-brand-name">%2$s</%1$s>', $tag, esc_html( get_bloginfo( 'name' ) ) );
	echo '</a>';
}

/** Stránkování výpisu. */
function iv_pagination(): void {
	the_posts_pagination( array(
		'mid_size'           => 1,
		'prev_text'          => ekt_icon( 'arrow-l', 18 ) . '<span class="screen-reader-text">Novější</span>',
		'next_text'          => '<span class="screen-reader-text">Starší</span>' . ekt_icon( 'arrow', 18 ),
		'screen_reader_text' => 'Stránkování',
	) );
}
