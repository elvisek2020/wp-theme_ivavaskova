<?php
/**
 * CSS a JS šablony. Verze souborů podle filemtime, aby se po nahrání obnovila cache prohlížeče i hostingu.
 */

defined( 'ABSPATH' ) || exit;

function iv_asset_ver( string $rel ): string {
	$file = IV_DIR . '/' . $rel;
	return is_file( $file ) ? (string) filemtime( $file ) : IV_VERSION;
}

add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style( 'iv-fonts', IV_URI . '/assets/css/fonts.css', array(), iv_asset_ver( 'assets/css/fonts.css' ) );
	wp_enqueue_style( 'iv-main', IV_URI . '/assets/css/main.css', array( 'iv-fonts' ), iv_asset_ver( 'assets/css/main.css' ) );
	wp_enqueue_style( 'iv-print', IV_URI . '/assets/css/print.css', array( 'iv-main' ), iv_asset_ver( 'assets/css/print.css' ), 'print' );
	wp_enqueue_script( 'iv-theme', IV_URI . '/assets/js/theme.js', array(), iv_asset_ver( 'assets/js/theme.js' ), array( 'strategy' => 'defer', 'in_footer' => true ) );

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
} );

// Předběžné načtení hlavních písem (nadpisy a text), ať stránka neproblikne.
add_action( 'wp_head', function () {
	foreach ( array( 'Comfortaa-normal-latin', 'OpenSans-normal-latin' ) as $font ) {
		printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( IV_URI . '/assets/fonts/' . $font . '.woff2' ) );
	}
}, 2 );

/**
 * Světlý/tmavý režim: volba z localStorage se nastaví dřív, než se vykreslí stránka (žádné probliknutí).
 */
add_action( 'wp_head', function () {
	echo "<script>(function(){try{var t=localStorage.getItem('iv-theme');if(t==='dark'||t==='light'){document.documentElement.setAttribute('data-theme',t);}}catch(e){}})();</script>\n";
	echo '<meta name="theme-color" content="#1b2559">' . "\n";
}, 0 );
