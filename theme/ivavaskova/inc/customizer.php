<?php
/**
 * Vzhled → Přizpůsobit → Iva Vašková: sociální sítě, text v patičce, Google Analytics.
 */

defined( 'ABSPATH' ) || exit;

/** Výchozí hodnoty (převzaté z dřívějšího webu). */
function iv_mod_defaults(): array {
	return array(
		'iv_social_instagram' => 'https://www.instagram.com/madeofstarsandlove/',
		'iv_social_facebook'  => 'https://www.facebook.com/madeofstarsandlove/',
		'iv_social_youtube'   => 'https://www.youtube.com/channel/UCOatukXNveP4NX2HTgU5NkA',
		'iv_footer_text'      => '',
		'iv_ga4_id'           => 'G-C9Y6LRWHZ7',
	);
}

foreach ( iv_mod_defaults() as $iv_mod => $iv_default ) {
	add_filter( 'theme_mod_' . $iv_mod, function ( $value ) use ( $iv_default ) {
		return false === $value ? $iv_default : $value;
	} );
}
unset( $iv_mod, $iv_default );

add_action( 'customize_register', function ( WP_Customize_Manager $c ) {
	$c->add_section( 'iv_theme', array(
		'title'    => 'Iva Vašková',
		'priority' => 30,
	) );

	foreach ( array( 'instagram' => 'Instagram', 'facebook' => 'Facebook', 'youtube' => 'YouTube' ) as $key => $label ) {
		$c->add_setting( 'iv_social_' . $key, array( 'sanitize_callback' => 'esc_url_raw', 'default' => iv_mod_defaults()[ 'iv_social_' . $key ] ) );
		$c->add_control( 'iv_social_' . $key, array(
			'section'     => 'iv_theme',
			'label'       => $label,
			'type'        => 'url',
			'description' => 'Adresa profilu. Prázdné = ikona se nezobrazí.',
		) );
	}

	$c->add_setting( 'iv_footer_text', array( 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ) );
	$c->add_control( 'iv_footer_text', array(
		'section'     => 'iv_theme',
		'label'       => 'Text v patičce',
		'description' => 'Zobrazí se pod ikonami. Prázdné = © roky a název webu.',
	) );

	$c->add_setting( 'iv_ga4_id', array(
		'sanitize_callback' => function ( $v ) {
			$v = strtoupper( trim( (string) $v ) );
			return preg_match( '/^G-[A-Z0-9]{4,}$/', $v ) ? $v : '';
		},
		'default'           => iv_mod_defaults()['iv_ga4_id'],
	) );
	$c->add_control( 'iv_ga4_id', array(
		'section'     => 'iv_theme',
		'label'       => 'Google Analytics 4 (ID měření)',
		'description' => 'Např. G-XXXXXXX. Načte se jen se souhlasem návštěvníka. Prázdné = žádná analytika ani cookie lišta.',
	) );
} );
