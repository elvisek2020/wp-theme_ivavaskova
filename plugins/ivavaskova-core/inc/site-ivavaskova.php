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
