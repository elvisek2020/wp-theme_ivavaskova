<?php
/**
 * SEO: meta description, Open Graph, Twitter karta a JSON-LD (BlogPosting / WebSite).
 * Nevypisuje nic, když běží SEO plugin (Yoast, Rank Math, SEOPress, AIOSEO) — žádné dvojí meta tagy.
 */

defined( 'ABSPATH' ) || exit;

function iv_seo_plugin_active(): bool {
	return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || defined( 'AIOSEO_VERSION' );
}

/** Krátký popis aktuální stránky (max. ~160 znaků). */
function iv_seo_description(): string {
	$text = '';
	if ( is_singular() ) {
		$post = get_queried_object();
		$text = has_excerpt( $post ) ? $post->post_excerpt : wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
	} elseif ( is_category() || is_tag() ) {
		$text = term_description();
		if ( ! trim( wp_strip_all_tags( (string) $text ) ) ) {
			$text = sprintf( 'Články v rubrice %s na blogu %s.', single_term_title( '', false ), get_bloginfo( 'name' ) );
		}
	} else {
		$text = get_bloginfo( 'description' ) ?: 'Osobní blog Ivy Vaškové o kráse, stylu, dětech, těle a duši.';
	}
	$text = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES, 'UTF-8' ) ) );
	if ( mb_strlen( $text ) > 160 ) {
		$text = rtrim( mb_substr( $text, 0, 157 ), " ,.;:-" ) . '…';
	}
	return $text;
}

/** Obrázek pro sdílení: náhled článku, jinak logo. */
function iv_seo_image(): array {
	$id = is_singular() && has_post_thumbnail() ? get_post_thumbnail_id() : (int) get_theme_mod( 'custom_logo' );
	if ( ! $id ) {
		return array();
	}
	$img = wp_get_attachment_image_src( $id, 'large' );
	return $img ? array( 'url' => $img[0], 'w' => (int) $img[1], 'h' => (int) $img[2] ) : array();
}

add_action( 'wp_head', function () {
	if ( iv_seo_plugin_active() || is_404() ) {
		return;
	}
	$desc  = iv_seo_description();
	$title = wp_get_document_title();
	$url   = is_singular() ? get_permalink() : home_url( add_query_arg( array(), $GLOBALS['wp']->request ? '/' . $GLOBALS['wp']->request . '/' : '/' ) );
	$img   = iv_seo_image();
	$meta  = array(
		array( 'name', 'description', $desc ),
		array( 'property', 'og:locale', 'cs_CZ' ),
		array( 'property', 'og:site_name', get_bloginfo( 'name' ) ),
		array( 'property', 'og:type', is_single() ? 'article' : 'website' ),
		array( 'property', 'og:title', is_singular() ? single_post_title( '', false ) : $title ),
		array( 'property', 'og:description', $desc ),
		array( 'property', 'og:url', $url ),
		array( 'name', 'twitter:card', $img ? 'summary_large_image' : 'summary' ),
	);
	if ( $img ) {
		$meta[] = array( 'property', 'og:image', $img['url'] );
		$meta[] = array( 'property', 'og:image:width', (string) $img['w'] );
		$meta[] = array( 'property', 'og:image:height', (string) $img['h'] );
	}
	if ( is_single() ) {
		$meta[] = array( 'property', 'article:published_time', get_the_date( 'c' ) );
		$meta[] = array( 'property', 'article:modified_time', get_the_modified_date( 'c' ) );
	}
	foreach ( $meta as $m ) {
		if ( '' !== (string) $m[2] ) {
			printf( '<meta %s="%s" content="%s">' . "\n", $m[0], esc_attr( $m[1] ), esc_attr( $m[2] ) );
		}
	}

	// JSON-LD
	$author = array( '@type' => 'Person', 'name' => 'Iva Vašková', 'url' => home_url( '/' ) );
	if ( is_single() ) {
		$data = array(
			'@context'         => 'https://schema.org',
			'@type'            => 'BlogPosting',
			'headline'         => wp_strip_all_tags( get_the_title() ),
			'description'      => $desc,
			'datePublished'    => get_the_date( 'c' ),
			'dateModified'     => get_the_modified_date( 'c' ),
			'mainEntityOfPage' => get_permalink(),
			'author'           => $author,
			'publisher'        => $author,
			'inLanguage'       => 'cs',
		);
		if ( $img ) {
			$data['image'] = $img['url'];
		}
	} elseif ( is_front_page() ) {
		$data = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'WebSite',
			'name'       => get_bloginfo( 'name' ),
			'url'        => home_url( '/' ),
			'inLanguage' => 'cs',
			'author'     => $author,
		);
	}
	if ( ! empty( $data ) ) {
		echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}
}, 5 );

// Vyhledávání a stránkované archivy neindexovat.
add_filter( 'wp_robots', function ( $robots ) {
	if ( is_search() || ( is_paged() && ! is_home() ) ) {
		$robots['noindex'] = true;
		$robots['follow']  = true;
	}
	return $robots;
} );
