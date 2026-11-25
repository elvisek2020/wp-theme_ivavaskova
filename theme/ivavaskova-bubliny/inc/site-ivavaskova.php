<?php
/**
 * Výchozí hodnoty Přizpůsobit pro www.ivavaskova.cz (GA, sítě, patička, popis webu) a název
 * doprovodného pluginu. Výchozí hodnoty platí, dokud správce v Přizpůsobit neuloží vlastní;
 * při přepnutí ze šablony „Hvězdná noc“ převezme common uložené volby ekt_* (GA ID, sítě, patička).
 * Logo je vlastní pole šablony (inc/setup.php), jádrové custom_logo ani site_logo se tu nemění.
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'ekt_defaults',
	static fn( array $defaults ): array => array_merge(
		$defaults,
		array(
			'ga_id'            => 'G-C9Y6LRWHZ7',
			'social_instagram' => 'https://www.instagram.com/madeofstarsandlove/',
			'social_facebook'  => 'https://www.facebook.com/madeofstarsandlove/',
			'social_youtube'   => 'https://www.youtube.com/channel/UCOatukXNveP4NX2HTgU5NkA',
			'copyright_name'   => 'Iva Vašková',
			'credit_name'      => 'ElvisEK',
			'credit_url'       => 'https://www.elvisek.cz',
			'meta_desc'        => 'Iva Vašková – prožitkové lekce Soma Synergy – Kundalini aktivace, Innerdance a Energetický obraz, dětské knihy a blog.',
		)
	)
);

// Doprovodný plugin je ivavaskova-core (sdílený se starou šablonou), ne EKT_SLUG-core: podle toho
// common v upozornění pozná, že je nainstalovaný, a nabídne správný ZIP.
add_filter( 'ekt_core_plugin', static fn(): string => 'ivavaskova-core' );
