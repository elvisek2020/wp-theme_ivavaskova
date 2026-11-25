<?php
/**
 * Projektové úpravy webu ivavaskova.cz (napojení na kanonické moduly jen filtry).
 */

defined( 'ABSPATH' ) || exit;

/*
 * /llms.txt: úvod z dřívější verze pluginu, dokud správce v Nastavení → Iva Vašková Core
 * nevyplní vlastní (pole „Úvod do /llms.txt“). Web nemá popis v Nastavení → Obecné.
 */
add_filter(
	'ekc_llms_lines',
	static function ( array $lines ): array {
		if ( '' !== trim( (string) ekc_opt( 'llms_intro' ) ) || '' !== trim( (string) get_bloginfo( 'description' ) ) ) {
			return $lines;
		}
		array_splice(
			$lines,
			2,
			0,
			array(
				'> Osobní blog Ivy Vaškové',
				'',
				'Osobní blog (česky): krása, styl, tělo a duše, děti, cestování a deník.',
				'',
			)
		);
		return $lines;
	}
);

/*
 * Dvoufázové ověření je na tomto webu volitelné i pro správce (rozhodnutí 2026-11-25). Kanon
 * two-factor od 1.2.0 ho ve výchozím stavu u správců vyžaduje; dokud volbu „Vyžadovat u správců“
 * nikdo neuloží, platí tady „nevyžadovat“. Uložená volba v Nastavení → Iva Vašková Core má přednost.
 */
$ivc_2fa_optional = static function ( $settings ) {
	if ( ! is_array( $settings ) ) {
		$settings = array();
	}
	if ( ! array_key_exists( 'two_factor_required', $settings ) ) {
		$settings['two_factor_required'] = false;
	}
	return $settings;
};
add_filter( 'option_ekc_settings', $ivc_2fa_optional );
add_filter( 'default_option_ekc_settings', $ivc_2fa_optional );
unset( $ivc_2fa_optional );
