<?php
/**
 * Projektový modul šablony: menu, logo, velikosti obrázků, písma, styly a skripty šablony, výpis článků.
 * Společnou podporu (title-tag, thumbnails, html5, editor-styles…), úklid <head>, převzetí menu a loga
 * ze staré šablony a upozornění na chybějící plugin řeší inc/ekt/common.php.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'after_setup_theme',
	static function () {
		load_theme_textdomain( EKT_SLUG, EKT_DIR . '/languages' );
		add_theme_support(
			'custom-logo',
			array(
				'height'      => 264,
				'width'       => 695,
				'flex-height' => true,
				'flex-width'  => true,
			)
		);
		add_theme_support( 'align-wide' );
		add_editor_style( array( 'assets/css/fonts.css', 'assets/css/editor.css' ) );
		register_nav_menus(
			array(
				'primary' => 'Hlavní menu',
				'footer'  => 'Menu v patičce',
			)
		);
		set_post_thumbnail_size( 720, 480, true );
		add_image_size( 'iv-card', 720, 480, true );
		add_image_size( 'iv-wide', 1440, 810, false );
	}
);

// Šířka obsahu pro vložená videa a obrázky.
$GLOBALS['content_width'] = 720;

/** Verze souboru šablony podle filemtime (cache prohlížeče i hostingu se obnoví sama). */
function iv_asset_ver( string $rel ): string {
	$file = EKT_DIR . '/' . $rel;
	return is_file( $file ) ? (string) filemtime( $file ) : EKT_VERSION;
}

// Styly a skript šablony (vzhled). Funkční CSS a JS kanonických modulů načítají moduly samy z assets/ekt/.
add_action(
	'wp_enqueue_scripts',
	static function () {
		wp_enqueue_style( 'iv-fonts', EKT_URI . '/assets/css/fonts.css', array(), iv_asset_ver( 'assets/css/fonts.css' ) );
		wp_enqueue_style( 'iv-main', EKT_URI . '/assets/css/main.css', array( 'iv-fonts' ), iv_asset_ver( 'assets/css/main.css' ) );
		wp_enqueue_style( 'iv-print', EKT_URI . '/assets/css/print.css', array( 'iv-main' ), iv_asset_ver( 'assets/css/print.css' ), 'print' );
		wp_enqueue_script(
			'iv-theme',
			EKT_URI . '/assets/js/theme.js',
			array(),
			iv_asset_ver( 'assets/js/theme.js' ),
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);
		if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
			wp_enqueue_script( 'comment-reply' );
		}
	}
);

// Předběžné načtení hlavních písem (nadpisy a text), ať stránka neproblikne.
add_filter(
	'ekt_preload_fonts',
	static fn( array $fonts ): array => array_merge( $fonts, array( 'assets/fonts/Comfortaa-normal-latin.woff2', 'assets/fonts/OpenSans-normal-latin.woff2' ) )
);

// Barva lišty prohlížeče: pruh menu „noční modrá“ (světlý i tmavý režim).
add_filter(
	'ekt_color_scheme_theme_color',
	static fn(): array => array(
		'light' => '#1b2559',
		'dark'  => '#0b102a',
	)
);

// Osobní web: vydavatel v JSON-LD je osoba, ne organizace.
add_filter( 'ekt_seo_publisher', static fn( array $entity ): array => array_merge( $entity, array( '@type' => 'Person' ) ) );

// Výpis článků: 9 na stránku (mřížka 3 × 3).
add_action(
	'pre_get_posts',
	static function ( WP_Query $q ) {
		if ( ! is_admin() && $q->is_main_query() && ( $q->is_home() || $q->is_archive() || $q->is_search() ) ) {
			$q->set( 'posts_per_page', 9 );
		}
	}
);

// Výňatek: krátký a bez „[…]“.
add_filter( 'excerpt_length', static fn(): int => 32 );
add_filter( 'excerpt_more', static fn(): string => '…' );

// Klasické galerie [gallery]: výchozí miniatury 150 px jsou na dnešních displejích rozmazané → větší ořez;
// vlastní styl galerie v main.css. Odkaz na soubor (lightbox) doplní modul lightbox.
add_filter(
	'shortcode_atts_gallery',
	static function ( array $out ): array {
		if ( 'thumbnail' === ( $out['size'] ?? 'thumbnail' ) ) {
			$out['size'] = 'iv-card';
		}
		return $out;
	}
);
add_filter( 'use_default_gallery_style', '__return_false' );
