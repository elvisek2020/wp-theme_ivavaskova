<?php
/**
 * Úpravy obsahu článků přes WP_HTML_Tag_Processor (žádné regexy nad celým HTML):
 *  - externí odkazy do nového okna (nahrazuje open-external-links-in-a-new-window)
 *  - odkazy na obrázky otevře lightbox (nahrazuje wp-jquery-lightbox)
 *  - vložená videa (iframe YouTube/Vimeo) se přizpůsobí šířce
 */

defined( 'ABSPATH' ) || exit;

/** Dokud běží původní plugin, šablona danou úpravu nedělá (žádná dvojí náhrada). */
function iv_lightbox_enabled(): bool {
	return ! defined( 'LIGHTPRESS_VERSION' );
}
function iv_external_links_enabled(): bool {
	return ! function_exists( 'external_links_in_new_windows_client' );
}

add_filter( 'the_content', function ( $html ) {
	if ( is_admin() || '' === trim( (string) $html ) ) {
		return $html;
	}
	$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	$host = preg_replace( '/^www\./', '', $host );
	$p    = new WP_HTML_Tag_Processor( $html );
	while ( $p->next_tag() ) {
		$tag = $p->get_tag();
		if ( 'A' === $tag ) {
			$href = (string) $p->get_attribute( 'href' );
			if ( '' === $href ) {
				continue;
			}
			if ( iv_lightbox_enabled() && preg_match( '/\.(jpe?g|png|webp|gif|avif)(\?.*)?$/i', $href ) ) {
				$p->set_attribute( 'data-iv-lightbox', 'post' );
				continue;
			}
			$h = preg_replace( '/^www\./', '', (string) wp_parse_url( $href, PHP_URL_HOST ) );
			if ( iv_external_links_enabled() && $h && $h !== $host && preg_match( '#^https?://#i', $href ) ) {
				$p->set_attribute( 'target', '_blank' );
				$rel = trim( (string) $p->get_attribute( 'rel' ) . ' noopener' );
				$p->set_attribute( 'rel', implode( ' ', array_unique( preg_split( '/\s+/', $rel ) ) ) );
			}
		} elseif ( 'IFRAME' === $tag ) {
			$src = (string) $p->get_attribute( 'src' );
			if ( preg_match( '#(youtube\.com|youtube-nocookie\.com|youtu\.be|vimeo\.com)#i', $src ) ) {
				$p->add_class( 'iv-video' );
				$p->remove_attribute( 'width' );
				$p->remove_attribute( 'height' );
				$p->remove_attribute( 'frameborder' );
				if ( ! $p->get_attribute( 'loading' ) ) {
					$p->set_attribute( 'loading', 'lazy' );
				}
				if ( ! $p->get_attribute( 'title' ) ) {
					$p->set_attribute( 'title', 'Video' );
				}
			}
		}
	}
	return $p->get_updated_html();
}, 20 );

/*
 * YouTube odkaz s playlistem (…watch?v=X&list=Y) → přehrávač celého playlistu bez dotazu na oEmbed.
 * Když první video playlistu zmizí, YouTube oEmbed vrátí 404 a místo videa by zůstal jen odkaz.
 */
add_filter( 'pre_oembed_result', function ( $result, $url ) {
	if ( null !== $result || ! preg_match( '#^https?://(www\.|m\.)?youtube\.com/(watch|playlist)\?#i', (string) $url ) ) {
		return $result;
	}
	parse_str( (string) wp_parse_url( html_entity_decode( (string) $url ), PHP_URL_QUERY ), $q );
	if ( empty( $q['list'] ) || ! preg_match( '/^[A-Za-z0-9_-]+$/', $q['list'] ) ) {
		return $result;
	}
	return sprintf(
		'<iframe class="iv-video" src="%s" title="YouTube playlist" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>',
		esc_url( 'https://www.youtube-nocookie.com/embed/videoseries?list=' . $q['list'] )
	);
}, 10, 2 );

// Klasické galerie [gallery]: odkazy na soubor (lightbox), jinak na stránku přílohy.
add_filter( 'shortcode_atts_gallery', function ( $out ) {
	if ( iv_lightbox_enabled() && empty( $out['link'] ) ) {
		$out['link'] = 'file';
	}
	// Výchozí miniatury 150 px jsou na dnešních displejích rozmazané → větší ořez.
	if ( 'thumbnail' === ( $out['size'] ?? 'thumbnail' ) ) {
		$out['size'] = 'iv-card';
	}
	return $out;
} );
add_filter( 'use_default_gallery_style', '__return_false' );

// Obrázky v galerii a blocích s odkazem na soubor dostanou lightbox i přes render_block.
add_filter( 'render_block_core/gallery', function ( $html ) {
	if ( ! iv_lightbox_enabled() ) {
		return $html;
	}
	$p = new WP_HTML_Tag_Processor( $html );
	while ( $p->next_tag( 'a' ) ) {
		if ( preg_match( '/\.(jpe?g|png|webp|gif|avif)$/i', (string) $p->get_attribute( 'href' ) ) ) {
			$p->set_attribute( 'data-iv-lightbox', 'post' );
		}
	}
	return $p->get_updated_html();
} );
