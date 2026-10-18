<?php
/**
 * Šablona Iva Vašková — načtení modulů z inc/.
 */

defined( 'ABSPATH' ) || exit;

define( 'IV_VERSION', wp_get_theme( get_template() )->get( 'Version' ) );
define( 'IV_DIR', get_template_directory() );
define( 'IV_URI', get_template_directory_uri() );

// Moduly: jeden soubor = jedna oblast. Zakomentováním řádku se modul vypne.
$iv_modules = array(
	'setup',             // podpora šablony, menu, logo, úklid <head>, převzetí nastavení z Dollah
	'assets',            // CSS/JS, písma, verze podle filemtime
	'template-tags',     // ikony, meta článku, stránkování
	'customizer',        // Vzhled → Přizpůsobit: sociální sítě, patička, GA4
	'content',           // externí odkazy, responzivní videa, lightbox k obrázkům
	'seo',               // meta description, Open Graph, JSON-LD
	'analytics-consent', // GA4 + cookie lišta (nahrazuje MonsterInsights a CookieYes)
	'comments-antispam', // honeypot v komentářích
	'updater',           // aktualizace šablony z GitHub Releases
);
foreach ( $iv_modules as $iv_module ) {
	require_once IV_DIR . '/inc/' . $iv_module . '.php';
}
unset( $iv_modules, $iv_module );
