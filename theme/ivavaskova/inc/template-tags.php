<?php
/**
 * Pomocné funkce pro šablony: ikony, meta článku, rubriky, stránkování, logo.
 */

defined( 'ABSPATH' ) || exit;

/** Inline SVG ikona (stroke = currentColor). */
function iv_icon( string $name, int $size = 20 ): string {
	$paths = array(
		'menu'      => '<path d="M4 7h16M4 12h16M4 17h16"/>',
		'close'     => '<path d="M6 6l12 12M18 6L6 18"/>',
		'moon'      => '<path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z"/>',
		'sun'       => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
		'search'    => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>',
		'chevron'   => '<path d="M6 9l6 6 6-6"/>',
		'arrow'     => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		'arrow-l'   => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
		'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r=".8" fill="currentColor"/>',
		'facebook'  => '<path d="M15 3h-2.5A4.5 4.5 0 0 0 8 7.5V10H5.5v4H8v7h4v-7h3l.5-4H12V8a1 1 0 0 1 1-1h2z"/>',
		'youtube'   => '<rect x="2.5" y="5" width="19" height="14" rx="4"/><path d="M10 9.5v5l4.5-2.5z" fill="currentColor"/>',
		'star'      => '<path d="M12 3l2.6 5.6 6 .7-4.5 4.1 1.2 6L12 16.4 6.7 19.4l1.2-6L3.4 9.3l6-.7z"/>',
	);
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}
	return sprintf(
		'<svg class="iv-icon iv-icon-%1$s" width="%2$d" height="%2$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%3$s</svg>',
		esc_attr( $name ),
		$size,
		$paths[ $name ]
	);
}

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

/** Odkazy na sociální sítě z Přizpůsobit. */
function iv_social_links( string $class = 'iv-social' ): void {
	$links = array();
	foreach ( array( 'instagram' => 'Instagram', 'facebook' => 'Facebook', 'youtube' => 'YouTube' ) as $key => $label ) {
		$url = get_theme_mod( 'iv_social_' . $key );
		if ( $url ) {
			$links[] = sprintf( '<a href="%s" target="_blank" rel="noopener me" aria-label="%s">%s</a>', esc_url( $url ), esc_attr( $label ), iv_icon( $key, 22 ) );
		}
	}
	if ( $links ) {
		echo '<div class="' . esc_attr( $class ) . '">' . implode( '', $links ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escapováno výše
	}
}

/** Stránkování výpisu. */
function iv_pagination(): void {
	the_posts_pagination( array(
		'mid_size'           => 1,
		'prev_text'          => iv_icon( 'arrow-l', 18 ) . '<span class="screen-reader-text">Novější</span>',
		'next_text'          => '<span class="screen-reader-text">Starší</span>' . iv_icon( 'arrow', 18 ),
		'screen_reader_text' => 'Stránkování',
	) );
}
