<?php
/**
 * Plugin Name:       Iva Vašková Core
 * Description:       Funkce webu Iva Vašková nezávislé na šabloně. Nahrazuje 6 pluginů.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Tested up to:      7.1
 * Requires PHP:      7.4
 * Author:            elvisek
 * License:           GPL-2.0-or-later
 * Update URI:        https://github.com/elvisek2020/wp-theme_ivavaskova
 * Text Domain:       ivavaskova-core
 */

defined( 'ABSPATH' ) || exit;

function iv_core_boot() {
	$data = get_file_data(
		__FILE__,
		array(
			'Version' => 'Version',
			'Tested'  => 'Tested up to',
		)
	);

	define( 'IV_CORE_FILE', __FILE__ );
	define( 'IV_CORE_BASENAME', plugin_basename( __FILE__ ) );
	define( 'IV_CORE_VERSION', $data['Version'] );
	define( 'IV_CORE_TESTED', $data['Tested'] );

	// Moduly: jeden soubor = jedna oblast. Zakomentováním řádku se modul vypne.
	$modules = array(
		'core',        // ochrana a log přihlášení, info o serveru, aktualizace, hardening, updater pluginu
		'seo-extra',   // bezpečnostní hlavičky, sitemap, /llms.txt
		'protection',  // ochrana obsahu proti kopírování
		'maintenance', // Nástroje → Údržba webu
		'health',      // widget Zdraví webu na Nástěnce
	);
	foreach ( $modules as $module ) {
		require_once __DIR__ . '/inc/' . $module . '.php';
	}
}
iv_core_boot();
