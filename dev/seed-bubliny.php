<?php
/**
 * Lokální obsah pro šablonu „Mluvící bubliny“ (jen dev, idempotentní):
 * stránky podle struktury nového webu se zástupnými texty, fotky z návrhu, menu, titulka
 * a stránka příspěvků, volby šablony a ukázkové recenze.
 *
 * Spuštění (fotky z návrhu do dev/wp/wp-content/seed-bubliny/, skript do dev/wp/ – volume ./wp):
 *   mkdir -p dev/wp/wp-content/seed-bubliny && cp _docs/varianty-2026-11/assets/* dev/wp/wp-content/seed-bubliny/
 *   cp dev/seed-bubliny.php dev/wp/ && cd dev && docker compose run --rm -q cli wp eval-file /var/www/html/seed-bubliny.php
 * Po aktivaci šablony spusť znovu: vzor „Jak lekce probíhá“ se do stránek vloží jen s aktivní šablonou.
 */

defined( 'ABSPATH' ) || exit;

require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

const IVB_SEED_DIR = WP_CONTENT_DIR . '/seed-bubliny/';
const IVB_PH       = 'Zástupný text – finální text připraví Iva.';

/**
 * Fotka z návrhu do knihovny médií (podle meta _ivb_seed jen jednou).
 */
function ivb_seed_img( string $file, string $alt ): int {
	$ids = get_posts(
		array(
			'post_type'   => 'attachment',
			'post_status' => 'any',
			'meta_key'    => '_ivb_seed',
			'meta_value'  => $file,
			'fields'      => 'ids',
			'numberposts' => 1,
		)
	);
	if ( $ids ) {
		return (int) $ids[0];
	}
	$tmp = wp_tempnam( $file );
	copy( IVB_SEED_DIR . $file, $tmp );
	$id = media_handle_sideload( array( 'name' => $file, 'tmp_name' => $tmp ), 0 );
	if ( is_wp_error( $id ) ) {
		WP_CLI::warning( $file . ': ' . $id->get_error_message() );
		return 0;
	}
	update_post_meta( $id, '_ivb_seed', $file );
	update_post_meta( $id, '_wp_attachment_image_alt', $alt );
	return (int) $id;
}

/**
 * Stránka podle cesty (vytvoří nebo aktualizuje).
 */
function ivb_seed_page( string $path, string $title, array $a = array() ): int {
	$page = get_page_by_path( $path );
	$data = array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => $title,
		'post_name'    => basename( $path ),
		'post_content' => $a['content'] ?? '',
		'post_excerpt' => $a['excerpt'] ?? '',
		'post_parent'  => $a['parent'] ?? 0,
		'menu_order'   => $a['order'] ?? 0,
	);
	if ( $page ) {
		$data['ID'] = $page->ID;
		$id         = wp_update_post( $data );
	} else {
		$id = wp_insert_post( $data );
	}
	update_post_meta( $id, '_wp_page_template', $a['template'] ?? 'default' );
	if ( ! empty( $a['thumb'] ) ) {
		set_post_thumbnail( $id, $a['thumb'] );
	}
	if ( isset( $a['chips'] ) ) {
		update_post_meta( $id, 'ekt_site_chips', $a['chips'] );
	}
	return (int) $id;
}

$img = array(
	'usmev'    => ivb_seed_img( 'iva-usmev.jpg', 'Iva Vašková s úsměvem v podzimním parku' ),
	'stan'     => ivb_seed_img( 'iva-boho-stan.jpg', 'Iva Vašková sedí v boho stanu' ),
	'kytice'   => ivb_seed_img( 'iva-louka-kytice.jpg', 'Iva Vašková s kyticí lučních květin' ),
	'lupiny'   => ivb_seed_img( 'priroda-lupiny.jpg', 'Kvetoucí lupiny' ),
	'saty'     => ivb_seed_img( 'iva-horcicove-saty.jpg', 'Iva Vašková v hořčicových šatech' ),
	'lehatko'  => ivb_seed_img( 'studio-lehatko.jpg', 'Místnost s lehátkem pro lekce' ),
	'motyl'    => ivb_seed_img( 'priroda-motyl.jpg', 'Motýl na kvetoucí rostlině' ),
	'kreslo'   => ivb_seed_img( 'iva-kreslo.jpg', 'Iva Vašková v křesle' ),
	'kocka'    => ivb_seed_img( 'iva-kocka.jpg', 'Iva Vašková s kočkou v náručí' ),
	'deti'     => ivb_seed_img( 'ilustrace-deti.png', 'Ilustrace: maminka s dětmi pod hvězdnou dekou' ),
	'radky'    => ivb_seed_img( 'kniha-radky-z-lasky.png', 'Kniha Řádky z lásky v rukou' ),
	'zrcadlo'  => ivb_seed_img( 'kniha-dite-jako-zrcadlo.png', 'E-book Dítě jako zrcadlo na tabletu a telefonu' ),
);

$arc = '<!-- wp:pattern {"slug":"ivb/jak-lekce-probiha"} /-->';
$pat = WP_Block_Patterns_Registry::get_instance()->get_registered( 'ivb/jak-lekce-probiha' );
if ( $pat ) {
	$arc = $pat['content'];
}
$intro = '<!-- wp:paragraph --><p>' . IVB_PH . ' Sem přijde úvod o tom, co lekce je a pro koho je vhodná.</p><!-- /wp:paragraph -->';

$home    = ivb_seed_page( 'domu', 'Domů' );
$about   = ivb_seed_page(
	'o-mne',
	'O mně',
	array(
		'excerpt' => 'Sem přijde krátké představení Ivy – kdo je a jak se dostala k práci s tělem a životní energií. Druhá věta řekne, co je pro ni na lekcích nejdůležitější. ' . IVB_PH,
		'content' => '<!-- wp:paragraph --><p>' . IVB_PH . '</p><!-- /wp:paragraph -->',
		'thumb'   => $img['kocka'],
		'order'   => 1,
	)
);
$offers  = ivb_seed_page(
	'co-nabizim',
	'Co nabízím',
	array(
		'excerpt' => 'Tři prožitkové lekce. Skupinové i individuální – Energetický obraz na individuální domluvu.',
		'order'   => 2,
	)
);
ivb_seed_page( 'co-nabizim/soma-synergy-kundalini-aktivace', 'Soma Synergy – Kundalini aktivace', array( 'parent' => $offers, 'order' => 1, 'template' => 'page-nabidka.php', 'thumb' => $img['saty'], 'chips' => 'skupinově, individuálně', 'excerpt' => IVB_PH, 'content' => $intro . $arc ) );
ivb_seed_page( 'co-nabizim/innerdance', 'Innerdance', array( 'parent' => $offers, 'order' => 2, 'template' => 'page-nabidka.php', 'thumb' => $img['lehatko'], 'chips' => 'skupinově, individuálně', 'excerpt' => IVB_PH, 'content' => $intro . $arc ) );
ivb_seed_page( 'co-nabizim/energeticky-obraz', 'Energetický obraz', array( 'parent' => $offers, 'order' => 3, 'template' => 'page-nabidka.php', 'thumb' => $img['motyl'], 'chips' => 'individuální domluva', 'excerpt' => IVB_PH, 'content' => $intro ) );
$booking = ivb_seed_page(
	'kontakt-a-rezervace',
	'Kontakt a rezervace',
	array(
		'template' => 'page-rezervace.php',
		'excerpt'  => 'Krátký rozhovor místo dlouhého formuláře.',
		'content'  => '<!-- wp:shortcode -->[ekc_rezervace]<!-- /wp:shortcode -->',
		'order'    => 3,
	)
);
$books   = ivb_seed_page(
	'moje-detske-knihy',
	'Moje dětské knihy',
	array(
		'template' => 'page-knihy.php',
		'thumb'    => $img['deti'],
		'excerpt'  => 'Krátký úvod ke knihám připravuje Iva.',
		'order'    => 4,
	)
);
ivb_seed_page( 'moje-detske-knihy/radky-z-lasky', 'Řádky z lásky', array( 'parent' => $books, 'order' => 1, 'thumb' => $img['radky'], 'chips' => 'dětská kniha', 'excerpt' => 'Popis knihy připravuje Iva.' ) );
ivb_seed_page( 'moje-detske-knihy/dite-jako-zrcadlo', 'Dítě jako zrcadlo', array( 'parent' => $books, 'order' => 2, 'thumb' => $img['zrcadlo'], 'chips' => 'e-book', 'excerpt' => 'Popis e-booku připravuje Iva.' ) );
$blog    = ivb_seed_page( 'blog', 'Blog', array( 'order' => 5 ) );

update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $home );
update_option( 'page_for_posts', $blog );

// Menu
$menu_name = 'Hlavní menu (bubliny)';
$menu      = wp_get_nav_menu_object( $menu_name );
$menu_id   = $menu ? (int) $menu->term_id : (int) wp_create_nav_menu( $menu_name );
foreach ( (array) wp_get_nav_menu_items( $menu_id ) as $item ) {
	wp_delete_post( $item->ID, true );
}
$add = static function ( int $page, int $parent = 0 ) use ( $menu_id ): int {
	return (int) wp_update_nav_menu_item(
		$menu_id,
		0,
		array(
			'menu-item-object-id' => $page,
			'menu-item-object'    => 'page',
			'menu-item-type'      => 'post_type',
			'menu-item-status'    => 'publish',
			'menu-item-parent-id' => $parent,
		)
	);
};
$add( $home );
$add( $about );
$parent = $add( $offers );
foreach ( get_pages( array( 'parent' => $offers, 'sort_column' => 'menu_order' ) ) as $child ) {
	$add( $child->ID, $parent );
}
$add( $booking );
$add( $books );
$add( $blog );

// Volby šablony (theme_mods ivavaskova-bubliny, i když zrovna není aktivní)
$mods = (array) get_option( 'theme_mods_ivavaskova-bubliny', array() );
$mods = array_merge(
	$mods,
	array(
		'nav_menu_locations'    => array_merge( (array) ( $mods['nav_menu_locations'] ?? array() ), array( 'primary' => $menu_id ) ),
		'ekt_site_hero_1'       => $img['usmev'],
		'ekt_site_hero_2'       => $img['stan'],
		'ekt_site_hero_3'       => $img['kytice'],
		'ekt_site_hero_small'   => $img['lupiny'],
		'ekt_site_avatar'       => $img['usmev'],
		'ekt_site_email'        => 'test@example.com',
		'ekt_site_phone'        => '+420 000 000 000',
	)
);
update_option( 'theme_mods_ivavaskova-bubliny', $mods );

// Ukázkové recenze (modul testimonials)
if ( post_type_exists( 'ekc_testimonial' ) ) {
	$samples = array(
		array( 'Ukázka – Jana', 'Sem přijde recenze klientky nebo klienta… třeba o tom, jak lekci prožila a co jí přinesla.', 'Innerdance' ),
		array( 'Ukázka – Petr', 'Sem přijde druhá recenze klientky nebo klienta… Iva vybere krátké citace, které nejlépe popíšou zážitek z lekce.', 'Soma Synergy – Kundalini aktivace' ),
		array( 'Ukázka – Lucie', 'Sem přijde třetí recenze klientky nebo klienta… ideálně s křestním jménem a souhlasem se zveřejněním.', 'Innerdance' ),
	);
	foreach ( $samples as $i => $s ) {
		$exists = get_posts( array( 'post_type' => 'ekc_testimonial', 'title' => $s[0], 'numberposts' => 1, 'fields' => 'ids', 'post_status' => 'any' ) );
		$id     = $exists ? (int) $exists[0] : (int) wp_insert_post( array( 'post_type' => 'ekc_testimonial', 'post_status' => 'publish', 'post_title' => $s[0], 'post_content' => $s[1], 'menu_order' => $i + 1 ) );
		update_post_meta( $id, '_ekc_context', $s[2] );
	}
}

delete_transient( 'ivb_booking_page' );
WP_CLI::success( 'Obsah pro šablonu Mluvící bubliny je připravený (titulka ' . $home . ', blog ' . $blog . ', menu ' . $menu_id . ').' );
