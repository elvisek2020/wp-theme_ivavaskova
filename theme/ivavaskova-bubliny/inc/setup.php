<?php
/**
 * Projektový modul šablony: menu, velikosti obrázků, content_width, styly a skript šablony,
 * písma, titulka jen se statickou úvodní stránkou, volby v Přizpůsobit (včetně loga).
 * Společnou podporu (title-tag, thumbnails, html5, editor-styles…) a úklid <head> řeší inc/ekt/common.php.
 *
 * Logo je vlastní pole Přizpůsobit → Obecné → Logo (ekt_site_brand), ne jádrové custom-logo:
 * custom_logo a globální volbu site_logo sdílí WordPress se starou šablonou „Hvězdná noc“,
 * takže nové logo by se po návratu na ni ukázalo i tam (a její ilustrace zase tady).
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'after_setup_theme',
	static function () {
		load_theme_textdomain( EKT_SLUG, EKT_DIR . '/languages' );
		add_theme_support( 'align-wide' );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'disable-custom-gradients' );
		add_theme_support(
			'editor-color-palette',
			array(
				array( 'name' => 'Olivová', 'slug' => 'olive', 'color' => '#4A4E2C' ),
				array( 'name' => 'Khaki', 'slug' => 'khaki', 'color' => '#C8CCA0' ),
				array( 'name' => 'Světlá khaki', 'slug' => 'khaki-light', 'color' => '#E6E8D3' ),
				array( 'name' => 'Zlatá', 'slug' => 'gold', 'color' => '#C9A54A' ),
				array( 'name' => 'Noc', 'slug' => 'night', 'color' => '#1B2559' ),
				array( 'name' => 'Hvězdy', 'slug' => 'star', 'color' => '#F5D76E' ),
				array( 'name' => 'Bílá', 'slug' => 'white', 'color' => '#FFFFFF' ),
				array( 'name' => 'Text', 'slug' => 'ink', 'color' => '#24261B' ),
			)
		);
		add_editor_style( array( 'assets/css/fonts.css', 'assets/css/main.css', 'assets/css/editor.css' ) );
		add_post_type_support( 'page', 'excerpt' );
		register_nav_menus(
			array(
				'primary' => 'Hlavní menu',
				'footer'  => 'Odkazy v patičce',
			)
		);
	}
);

// Šířka obsahu pro vložená videa a obrázky.
$GLOBALS['content_width'] = 760;

// Titulka (front-page.php) jen se statickou úvodní stránkou (Nastavení → Čtení). V režimu
// „Poslední příspěvky“ by front-page.php překryla výpis článků na „/“ i na /page/2/ a dál;
// bez ní WordPress použije index.php se stránkováním.
add_filter(
	'frontpage_template_hierarchy',
	static fn( array $templates ): array => 'page' === get_option( 'show_on_front' ) ? $templates : array()
);

// Barvy palety jako třídy (editor je ukládá jako has-<slug>-color / has-<slug>-background-color).
add_action(
	'wp_enqueue_scripts',
	static function () {
		foreach ( array( 'fonts', 'main' ) as $name ) {
			$rel = '/assets/css/' . $name . '.css';
			$ver = is_file( EKT_DIR . $rel ) ? (string) filemtime( EKT_DIR . $rel ) : EKT_VERSION;
			wp_enqueue_style( EKT_SLUG . '-' . $name, EKT_URI . $rel, 'main' === $name ? array( EKT_SLUG . '-fonts' ) : array(), $ver );
		}
		$rel = '/assets/js/theme.js';
		if ( is_file( EKT_DIR . $rel ) ) {
			wp_enqueue_script( EKT_SLUG . '-theme', EKT_URI . $rel, array(), (string) filemtime( EKT_DIR . $rel ), array( 'strategy' => 'defer', 'in_footer' => true ) );
		}
		// Blok knihovny (wp-block-library) potřebuje jen obsah s bloky; jinak jeho styl nechává common.
	}
);

// Předem načíst hlavní řezy písma (latin), ať text a nadpisy neposkočí.
add_filter(
	'ekt_preload_fonts',
	static fn( array $fonts ): array => array_merge(
		$fonts,
		array(
			'assets/fonts/karla-normal-400-600-latin.woff2',
			'assets/fonts/bricolage-grotesque-normal-400-700-latin.woff2',
		)
	)
);

// Osobní web: vydavatel v JSON-LD je osoba. Obrázek osoby = logo šablony (site_brand); jádrové
// custom_logo, které kanonický modul seo bere, tu může být jen převzaté ze staré šablony.
add_filter(
	'ekt_seo_publisher',
	static function ( array $entity ): array {
		unset( $entity['logo'] );
		$brand = (int) ekt_opt( 'site_brand' );
		$src   = ( $brand && function_exists( 'ekt_seo_image' ) ) ? ekt_seo_image( $brand, 'full' ) : array();
		if ( $src ) {
			$entity['logo'] = array_merge( array( '@type' => 'ImageObject' ), $src );
		}
		return array_merge( $entity, array( '@type' => 'Person' ) );
	}
);

// Úryvek: kratší a bez „[…]“.
add_filter( 'excerpt_length', static fn(): int => 26, 20 );
add_filter( 'excerpt_more', static fn(): string => '…', 20 );

/* -------------------------------------------------------------------------
 * Přizpůsobit → Iva Vašková – Mluvící bubliny
 * ---------------------------------------------------------------------- */

ekt_setting_register(
	'site_brand',
	array(
		'type'        => 'image',
		'default'     => 0,
		'label'       => 'Logo (zlaté sluníčko)',
		'description' => 'Prázdné = zástupné sluníčko. Vedle loga se vždy ukáže název webu.',
		'section'     => 'general',
		'priority'    => 5,
	)
);

ekt_customizer_section( 'home', 'Titulka', 12 );

ekt_setting_register(
	'site_slogan',
	array(
		'type'     => 'text',
		'default'  => 'O krok blíž k sobě díky vnitřnímu tanci životní síly',
		'label'    => 'Slogan (velký nadpis úvodu)',
		'section'  => 'home',
		'priority' => 10,
	)
);
ekt_setting_register(
	'site_perex',
	array(
		'type'     => 'textarea',
		'default'  => 'Vytvořím Ti prostor pro vlastní prožitek, ve kterém se skrze hudbu, vědomý dotek a práci s životní energií a moudrostí těla dostaneš hlouběji k sobě, ke svým emocím a vnitřním procesům. To vše vleže, se zavřenýma očima, bez úsilí a tlaku.',
		'label'    => 'Text v bublině pod sloganem',
		'section'  => 'home',
		'priority' => 20,
	)
);
foreach ( array( 1, 2, 3 ) as $ivb_i ) {
	ekt_setting_register(
		'site_hero_' . $ivb_i,
		array(
			'type'        => 'image',
			'default'     => 0,
			'label'       => 'Fotka úvodu ' . $ivb_i,
			'description' => 1 === $ivb_i ? 'Fotky se v oblázku plynule střídají. Nejlépe na výšku (4 : 5), obličej v horní třetině.' : '',
			'section'     => 'home',
			'priority'    => 30 + $ivb_i,
		)
	);
}
unset( $ivb_i );
ekt_setting_register(
	'site_hero_small',
	array(
		'type'        => 'image',
		'default'     => 0,
		'label'       => 'Malá fotka v rohu úvodu',
		'description' => 'Příroda, detail – na mobilu se neukazuje.',
		'section'     => 'home',
		'priority'    => 40,
	)
);
ekt_setting_register(
	'site_strip',
	array(
		'type'        => 'text',
		'default'     => '',
		'label'       => 'Pruh nad hlavičkou',
		'description' => 'Krátké oznámení na všech stránkách, např. „Skupinové lekce v lednu – přihlášky otevřené“. Prázdné = bez pruhu.',
		'section'     => 'home',
		'priority'    => 50,
	)
);
ekt_setting_register(
	'site_strip_url',
	array(
		'type'     => 'url',
		'default'  => '',
		'label'    => 'Odkaz pruhu',
		'section'  => 'home',
		'priority' => 51,
	)
);

ekt_setting_register(
	'site_email',
	array(
		'type'     => 'email',
		'default'  => '',
		'label'    => 'Kontaktní e-mail',
		'section'  => 'footer',
		'priority' => 5,
	)
);
ekt_setting_register(
	'site_phone',
	array(
		'type'     => 'text',
		'default'  => '',
		'label'    => 'Kontaktní telefon',
		'section'  => 'footer',
		'priority' => 6,
	)
);
ekt_setting_register(
	'site_avatar',
	array(
		'type'        => 'image',
		'default'     => 0,
		'label'       => 'Fotka do rozhovoru (avatar)',
		'description' => 'Malé kolečko u otázek v rezervaci a u kontaktu. Čtvercový výřez obličeje.',
		'section'     => 'footer',
		'priority'    => 7,
	)
);
ekt_setting_register(
	'site_news_url',
	array(
		'type'        => 'url',
		'default'     => '',
		'label'       => 'Odběr novinek: odkaz',
		'description' => 'Adresa přihlašovacího formuláře newsletteru (Ecomail, Mailchimp…). Prázdné = box se v patičce nezobrazí.',
		'section'     => 'footer',
		'priority'    => 8,
	)
);
ekt_setting_register(
	'site_news_text',
	array(
		'type'     => 'text',
		'default'  => 'Termíny skupinových lekcí a nové články.',
		'label'    => 'Odběr novinek: text',
		'section'  => 'footer',
		'priority' => 9,
	)
);

/* -------------------------------------------------------------------------
 * Nastavení stránky
 * ---------------------------------------------------------------------- */

ekt_post_setting_register(
	'site_chips',
	array(
		'type'       => 'text',
		'label'      => 'Štítky',
		'help'       => 'Krátké štítky oddělené čárkou, např. „skupinově, individuálně“ nebo „e-book“. Ukazují se u nabídky a knihy.',
		'post_types' => array( 'page' ),
		'priority'   => 10,
	)
);
ekt_post_setting_register(
	'site_focus',
	array(
		'type'       => 'select',
		'label'      => 'Výřez náhledového obrázku',
		'help'       => 'Která část fotky zůstane vidět v oblázku.',
		'post_types' => array( 'page', 'post' ),
		'priority'   => 20,
		'choices'    => array(
			''       => 'Horní třetina (obličej)',
			'top'    => 'Horní okraj',
			'center' => 'Střed',
			'bottom' => 'Spodní část',
		),
	)
);
