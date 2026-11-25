<?php
/**
 * EKC module: auto-updates
 * Version: 1.1.0
 * Requires: common
 * Replaces: stops-core-theme-and-plugin-updates
 * Summary: Automatické aktualizace WordPressu, pluginů a šablon; vlastní plugin a šablony webu jen ručně.
 */

defined( 'ABSPATH' ) || exit;

/*
 * Vlastní plugin (EKC_BASENAME) a vlastní šablony webu se automaticky neaktualizují nikdy:
 * nabídnou se v Nástěnka → Aktualizace (z GitHub Releases) a instalují se kliknutím.
 * Vlastní šablona = EKC_THEME a každá šablona, jejíž hlavička Update URI míří na stejné repo
 * jako plugin (EKC_REPO), např. nová šablona vydávaná vedle staré (ekc_is_own_theme()).
 * Na lokálním vývoji (WP_ENVIRONMENT_TYPE local) jsou automatické aktualizace vypnuté.
 */

ekc_settings_register(
	'core_updates',
	array(
		'type'    => 'select',
		'default' => 'all',
		'label'   => 'WordPress',
		'help'    => 'Automatické aktualizace jádra WordPressu.',
		'section' => 'Aktualizace',
		'options' => array(
			'all'   => 'Všechny verze automaticky',
			'minor' => 'Jen opravné verze (výchozí WordPress)',
			'off'   => 'Vypnuto',
		),
	)
);
ekc_settings_register(
	'plugin_updates',
	array(
		'type'    => 'select',
		'default' => 'all',
		'label'   => 'Pluginy a šablony',
		'help'    => 'Plugin ' . EKC_NAME . ' Core a vlastní šablony webu (i šablony vydávané ze stejného repozitáře) se automaticky neaktualizují nikdy – nabídnou se v Nástěnka → Aktualizace a instalují se kliknutím.',
		'section' => 'Aktualizace',
		'options' => array(
			'all'    => 'Všechny automaticky',
			'manual' => 'Podle nastavení u jednotlivých pluginů a šablon',
		),
	)
);

/**
 * Pluginy: vlastní plugin nikdy, ostatní podle nastavení.
 *
 * @param bool|null $update Aktualizovat?
 * @param object    $item   Plugin.
 * @return bool|null
 */
function ekc_auto_update_plugin( $update, $item ) {
	if ( isset( $item->plugin ) && EKC_BASENAME === $item->plugin ) {
		return false;
	}
	return 'all' === ekc_opt( 'plugin_updates' ) ? true : $update;
}
add_filter( 'auto_update_plugin', 'ekc_auto_update_plugin', 10, 2 );

/**
 * Je šablona vlastní šablonou webu? EKC_THEME, nebo hlavička Update URI na repo pluginu (EKC_REPO).
 *
 * @param string $stylesheet Slug (složka) šablony.
 * @return bool
 */
function ekc_is_own_theme( string $stylesheet ): bool {
	if ( '' === $stylesheet ) {
		return false;
	}
	if ( '' !== EKC_THEME && EKC_THEME === $stylesheet ) {
		return true;
	}
	if ( '' === EKC_REPO ) {
		return false;
	}
	$theme = wp_get_theme( $stylesheet );
	if ( ! $theme->exists() ) {
		return false;
	}
	$repo = trim( (string) preg_replace( '#^https?://github\.com/#i', '', (string) $theme->get( 'UpdateURI' ) ), '/' );
	return '' !== $repo && 0 === strcasecmp( $repo, EKC_REPO );
}

/**
 * Šablony: vlastní šablony webu (ekc_is_own_theme) nikdy, ostatní podle nastavení.
 *
 * @param bool|null $update Aktualizovat?
 * @param object    $item   Šablona.
 * @return bool|null
 */
function ekc_auto_update_theme( $update, $item ) {
	if ( isset( $item->theme ) && is_string( $item->theme ) && ekc_is_own_theme( $item->theme ) ) {
		return false;
	}
	return 'all' === ekc_opt( 'plugin_updates' ) ? true : $update;
}
add_filter( 'auto_update_theme', 'ekc_auto_update_theme', 10, 2 );

/**
 * Hlavní verze WordPressu automaticky jen při volbě „všechny“.
 *
 * @param bool $allow Povolit?
 * @return bool
 */
function ekc_auto_update_core_major( $allow ) {
	return 'all' === ekc_opt( 'core_updates' ) ? true : $allow;
}
add_filter( 'allow_major_auto_core_updates', 'ekc_auto_update_core_major' );

/**
 * Opravné verze WordPressu (výchozí chování, při „všechny“ výslovně zapnuté).
 *
 * @param bool $allow Povolit?
 * @return bool
 */
function ekc_auto_update_core_minor( $allow ) {
	return 'all' === ekc_opt( 'core_updates' ) ? true : $allow;
}
add_filter( 'allow_minor_auto_core_updates', 'ekc_auto_update_core_minor' );

/**
 * Volba „vypnuto“: jádro se automaticky neaktualizuje vůbec.
 *
 * @param bool|null $update Aktualizovat?
 * @return bool|null
 */
function ekc_auto_update_core( $update ) {
	return 'off' === ekc_opt( 'core_updates' ) ? false : $update;
}
add_filter( 'auto_update_core', 'ekc_auto_update_core' );

// Lokální vývoj: žádné automatické aktualizace.
if ( 'local' === wp_get_environment_type() ) {
	add_filter( 'automatic_updater_disabled', '__return_true' );
}
