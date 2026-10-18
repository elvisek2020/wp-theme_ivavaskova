<?php
/**
 * Podpora šablony, menu, velikosti obrázků, úklid <head>, převzetí nastavení ze šablony Dollah.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'after_setup_theme', function () {
	load_theme_textdomain( 'ivavaskova', IV_DIR . '/languages' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
	add_theme_support( 'custom-logo', array( 'height' => 264, 'width' => 695, 'flex-height' => true, 'flex-width' => true ) );
	add_theme_support( 'editor-styles' );
	add_editor_style( array( 'assets/css/fonts.css', 'assets/css/editor.css' ) );
	add_theme_support( 'align-wide' );

	register_nav_menus( array(
		'primary' => 'Hlavní menu',
		'footer'  => 'Menu v patičce',
	) );

	set_post_thumbnail_size( 720, 480, true );
	add_image_size( 'iv-card', 720, 480, true );
	add_image_size( 'iv-wide', 1440, 810, false );
} );

// Šířka obsahu pro vložená videa a obrázky.
$GLOBALS['content_width'] = 720;

// Úklid <head>: verze WP, emoji, RSD, wlwmanifest, shortlink.
remove_action( 'wp_head', 'wp_generator' );
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wlwmanifest_link' );
remove_action( 'wp_head', 'wp_shortlink_wp_head' );
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
remove_action( 'admin_print_styles', 'print_emoji_styles' );

// Výpis článků: 9 na stránku (mřížka 3 × 3).
add_action( 'pre_get_posts', function ( $q ) {
	if ( ! is_admin() && $q->is_main_query() && ( $q->is_home() || $q->is_archive() || $q->is_search() ) ) {
		$q->set( 'posts_per_page', 9 );
	}
} );

// Výňatek: krátký a bez „[…]“.
add_filter( 'excerpt_length', function () {
	return 32;
} );
add_filter( 'excerpt_more', function () {
	return '…';
} );

/**
 * Při přepnutí ze šablony Dollah převezme menu a logo, aby web nezůstal bez navigace.
 */
add_action( 'after_switch_theme', function () {
	$old = get_option( 'theme_mods_dollah' );
	if ( ! is_array( $old ) ) {
		return;
	}
	$locations = get_theme_mod( 'nav_menu_locations', array() );
	if ( empty( $locations['primary'] ) ) {
		$from                 = (array) ( $old['nav_menu_locations'] ?? array() );
		$locations['primary'] = (int) ( $from['hoot-secondary'] ?? ( $from['hoot-primary'] ?? reset( $from ) ) );
	}
	// WordPress při přepnutí přiřadí stejné menu i do patičky — tam patří jen krátké menu, ne celé hlavní.
	if ( ! empty( $locations['footer'] ) && $locations['footer'] === $locations['primary'] ) {
		unset( $locations['footer'] );
	}
	if ( ! empty( $locations['primary'] ) ) {
		set_theme_mod( 'nav_menu_locations', $locations );
	}
	if ( ! get_theme_mod( 'custom_logo' ) && ! empty( $old['custom_logo'] ) ) {
		set_theme_mod( 'custom_logo', (int) $old['custom_logo'] );
	}
} );

// Upozornění v administraci, když chybí plugin Iva Vašková Core.
add_action( 'admin_notices', function () {
	if ( defined( 'IV_CORE_VERSION' ) || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-warning"><p><strong>Šablona Iva Vašková:</strong> není aktivní plugin <em>Iva Vašková Core</em> (ochrana přihlášení, aktualizace, sitemap, údržba). Nainstalujte ho z <a href="https://github.com/elvisek2020/wp-theme_ivavaskova/releases/latest" target="_blank" rel="noopener">GitHub Releases</a>.</p></div>';
} );
