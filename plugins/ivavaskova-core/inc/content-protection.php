<?php
/**
 * EKC module: content-protection
 * Version: 1.0.0
 * Requires: common
 * Replaces: rcd-right-click-disabler, wp-content-copy-protector
 * Summary: Nepřihlášeným návštěvníkům zakáže pravé tlačítko, kopírování, výběr textu a přetahování obrázků.
 */

defined( 'ABSPATH' ) || exit;

/*
 * Volitelný modul. Odradí běžné kopírování, ne zkušeného uživatele: vývojářské nástroje (F12),
 * zdroj stránky ani vypnutý JavaScript neblokuje a blokovat nemá. Přihlášení uživatelé
 * a formulářová pole (input, textarea, select, contenteditable) omezení nemají.
 * Starou volbu iv_protect_content převádí migrace v common na klíč content_protection.
 */

ekc_settings_register(
	'content_protection',
	array(
		'type'    => 'bool',
		'default' => true,
		'label'   => 'Ochrana obsahu',
		'help'    => 'Nepřihlášeným návštěvníkům zakáže pravé tlačítko, kopírování, výběr textu a přetahování obrázků. Formulářová pole a přihlášené uživatele neomezuje. Odradí běžné kopírování, ne zkušeného uživatele (vývojářské nástroje F12 neblokuje).',
		'section' => 'Obsah',
	)
);

/**
 * Platí ochrana pro tento požadavek?
 *
 * @return bool
 */
function ekc_content_protection_on(): bool {
	return ekc_opt( 'content_protection' ) && ! is_user_logged_in() && ! is_feed() && ! is_embed();
}

/**
 * Styl a skript na frontendu (přes registr skriptů, aby fungovala CSP nonce a optimalizace).
 */
function ekc_content_protection_assets(): void {
	if ( ! ekc_content_protection_on() ) {
		return;
	}
	wp_register_style( 'ekc-protect', false, array(), EKC_VERSION );
	wp_enqueue_style( 'ekc-protect' );
	wp_add_inline_style( 'ekc-protect', 'body{-webkit-user-select:none;user-select:none;-webkit-touch-callout:none}input,textarea,select,[contenteditable],[contenteditable] *{-webkit-user-select:text;user-select:text}img{-webkit-user-drag:none}' );

	wp_register_script( 'ekc-protect', false, array(), EKC_VERSION, true );
	wp_enqueue_script( 'ekc-protect' );
	wp_add_inline_script(
		'ekc-protect',
		"(function(){var editable=function(el){return el&&el.closest&&el.closest('input,textarea,select,[contenteditable]');};['contextmenu','copy','cut','selectstart'].forEach(function(type){document.addEventListener(type,function(e){if(!editable(e.target)){e.preventDefault();}});});document.addEventListener('dragstart',function(e){if(e.target&&e.target.tagName==='IMG'){e.preventDefault();}});})();"
	);
}
add_action( 'wp_enqueue_scripts', 'ekc_content_protection_assets' );
