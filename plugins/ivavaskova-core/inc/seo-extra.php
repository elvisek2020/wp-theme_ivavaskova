<?php
/**
 * Bezpečnostní hlavičky, sitemap (nahrazuje google-xml-sitemaps-v3-for-qtranslate) a /llms.txt.
 */

defined( 'ABSPATH' ) || exit;

/* Bezpečnostní hlavičky pro stránky generované WordPressem (web i admin). */
add_action( 'send_headers', function () {
	if ( headers_sent() ) {
		return;
	}
	header( 'X-Content-Type-Options: nosniff' );
	header( 'X-Frame-Options: SAMEORIGIN' );
	header( 'Referrer-Policy: strict-origin-when-cross-origin' );
	header( 'Permissions-Policy: camera=(), microphone=(), geolocation=(), interest-cohort=()' );
	if ( is_ssl() && 'local' !== wp_get_environment_type() ) {
		header( 'Strict-Transport-Security: max-age=31536000' ); // bez includeSubDomains – subdomény můžou jet i bez HTTPS
	}
} );

/* Sitemapa: bez seznamu uživatelů (prozrazuje přihlašovací jméno) + datum poslední změny. */
add_filter( 'wp_sitemaps_add_provider', function ( $provider, $name ) {
	return 'users' === $name ? false : $provider;
}, 10, 2 );

add_filter( 'wp_sitemaps_posts_entry', function ( $entry, $post ) {
	$entry['lastmod'] = wp_date( DATE_W3C, strtotime( $post->post_modified_gmt . ' UTC' ) );
	return $entry;
}, 10, 2 );

add_filter( 'wp_sitemaps_index_entry', function ( $entry, $object_type, $object_subtype ) {
	global $wpdb;
	if ( 'post' === $object_type && $object_subtype ) {
		$last = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(post_modified_gmt) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'", $object_subtype ) );
		if ( $last ) {
			$entry['lastmod'] = wp_date( DATE_W3C, strtotime( $last . ' UTC' ) );
		}
	}
	return $entry;
}, 10, 3 );

/* Autorské archivy nepoužíváme (prozrazují login) → na titulku. */
add_action( 'template_redirect', function () {
	if ( is_author() ) {
		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}
} );

add_action( 'parse_request', function () {
	$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );

	/* Staré adresy sitemapy z dřívějšího pluginu → core sitemapa (dokud plugin běží, nechat jemu;
	   statický soubor sitemap.xml v kořeni má přednost, dokud existuje). */
	if ( ! class_exists( 'GoogleSitemapGeneratorLoader' ) && in_array( $path, array( 'sitemap.xml', 'sitemap.xml.gz', 'sitemap_index.xml' ), true ) ) {
		wp_safe_redirect( home_url( '/wp-sitemap.xml' ), 301 );
		exit;
	}

	/* /llms.txt – stručný přehled webu pro AI (https://llmstxt.org). */
	if ( 'llms.txt' !== $path ) {
		return;
	}
	$out   = array();
	$out[] = '# ' . get_bloginfo( 'name' );
	$out[] = '';
	$out[] = '> ' . ( get_bloginfo( 'description' ) ?: 'Osobní blog Ivy Vaškové' );
	$out[] = '';
	$out[] = 'Osobní blog (česky): krása, styl, tělo a duše, děti, cestování a deník.';
	$out[] = '';
	$out[] = '## Témata';
	$out[] = '';
	foreach ( get_categories( array( 'orderby' => 'count', 'order' => 'DESC', 'hide_empty' => true ) ) as $cat ) {
		$n     = (int) $cat->count;
		$out[] = sprintf( '- [%s](%s): %d %s', html_entity_decode( $cat->name, ENT_QUOTES, 'UTF-8' ), get_category_link( $cat ), $n, 1 === $n ? 'článek' : ( $n >= 2 && $n <= 4 ? 'články' : 'článků' ) );
	}
	$out[] = '';
	$out[] = '## Nejnovější články';
	$out[] = '';
	foreach ( get_posts( array( 'numberposts' => 30, 'post_status' => 'publish' ) ) as $p ) {
		$excerpt = wp_trim_words( wp_strip_all_tags( get_the_excerpt( $p ) ), 22, '…' );
		$out[]   = sprintf( '- [%s](%s): %s', html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' ), get_permalink( $p ), $excerpt );
	}
	$out[] = '';
	$out[] = '## Další';
	$out[] = '';
	$out[] = '- [Sitemap](' . home_url( '/wp-sitemap.xml' ) . ')';
	$out[] = '- [RSS](' . get_feed_link() . ')';
	foreach ( array( 'about_me', 'contact' ) as $slug ) {
		$page = get_page_by_path( $slug );
		if ( $page ) {
			$out[] = '- [' . html_entity_decode( get_the_title( $page ), ENT_QUOTES, 'UTF-8' ) . '](' . get_permalink( $page ) . ')';
		}
	}
	status_header( 200 );
	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'Cache-Control: public, max-age=3600' );
	echo implode( "\n", $out ) . "\n";
	exit;
} );
