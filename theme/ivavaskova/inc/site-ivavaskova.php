<?php
/**
 * Výchozí hodnoty Přizpůsobit ze starého webu (MonsterInsights, Sticky Side Buttons, patička Dollah).
 * Platí, dokud správce v Přizpůsobit neuloží vlastní; uložené hodnoty iv_* převádí migrace common.
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
			'copyright_name'   => 'Ivuš',
			'credit_name'      => 'ElvisEK',
			'credit_url'       => 'https://www.elvisek.cz',
			'meta_desc'        => 'Osobní blog Ivy Vaškové o kráse, stylu, dětech, těle a duši.',
		)
	)
);
