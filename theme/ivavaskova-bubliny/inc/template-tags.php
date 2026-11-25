<?php
/**
 * Projektový modul šablony: pomocné funkce pro šablonové soubory (stránky podle šablony,
 * odkazy na rezervaci a blog, oblázkové fotky, štítky, sluníčko, hvězdy).
 */

defined( 'ABSPATH' ) || exit;

/**
 * Publikované stránky s danou šablonou stránky, seřazené podle pořadí v administraci.
 *
 * @param string $template Soubor šablony stránky (page-nabidka.php).
 * @return WP_Post[]
 */
function ivb_pages_by_template( string $template ): array {
	static $cache = array();
	if ( ! isset( $cache[ $template ] ) ) {
		$cache[ $template ] = get_posts(
			array(
				'post_type'        => 'page',
				'post_status'      => 'publish',
				'posts_per_page'   => 12,
				'meta_key'         => '_wp_page_template', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'       => $template, // phpcs:ignore WordPress.DB.SlowDBQuery
				'orderby'          => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
				'suppress_filters' => false,
			)
		);
	}
	return $cache[ $template ];
}

/**
 * Nabídky = stránky se šablonou „Nabídka“.
 *
 * @return WP_Post[]
 */
function ivb_offer_pages(): array {
	return ivb_pages_by_template( 'page-nabidka.php' );
}

/**
 * Stránka „Moje dětské knihy“ = první stránka se šablonou „Svět knih“.
 */
function ivb_books_page(): ?WP_Post {
	$pages = ivb_pages_by_template( 'page-knihy.php' );
	return $pages[0] ?? null;
}

/**
 * Stránka podle adresy (slugu), jen publikovaná.
 *
 * @param string $path Cesta stránky, např. o-mne.
 */
function ivb_page_by_path( string $path ): ?WP_Post {
	$page = get_page_by_path( $path );
	return ( $page instanceof WP_Post && 'publish' === $page->post_status ) ? $page : null;
}

/**
 * Stránka „O mně“ (filtr ivb_about_path, výchozí o-mne).
 */
function ivb_about_page(): ?WP_Post {
	return ivb_page_by_path( (string) apply_filters( 'ivb_about_path', 'o-mne' ) );
}

/**
 * Podstránky (publikované) seřazené podle pořadí.
 *
 * @param int $parent ID nadřazené stránky.
 * @return WP_Post[]
 */
function ivb_child_pages( int $parent ): array {
	return get_pages(
		array(
			'parent'      => $parent,
			'sort_column' => 'menu_order,post_title',
			'post_status' => 'publish',
		)
	);
}

/**
 * Název stránky tak, jak je uložený (bez wptexturize a HTML entit): pro porovnání s kontextem
 * recenzí a s názvy služeb rezervací. Escapuje se až při výstupu.
 *
 * @param WP_Post|int $post Stránka.
 */
function ivb_raw_title( $post ): string {
	$title = (string) get_post_field( 'post_title', $post, 'raw' );
	return trim( html_entity_decode( wp_strip_all_tags( $title ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
}

/**
 * Adresa stránky s rezervací: šablona stránky „Kontakt a rezervace“, jinak stránka se shortcodem
 * [ekc_rezervace] (ID v transientu), jinak kotva rezervace na titulce.
 * Bez modulu rezervací (plugin neaktivní) '' – tlačítka Rezervovat se pak nevypíšou.
 */
function ivb_booking_url(): string {
	if ( ! function_exists( 'ekc_reservation_form' ) ) {
		return '';
	}
	$tpl = ivb_pages_by_template( 'page-rezervace.php' );
	if ( $tpl ) {
		return (string) get_permalink( $tpl[0] );
	}
	$id = get_transient( 'ivb_booking_page' );
	if ( false === $id ) {
		global $wpdb;
		$id = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish'
			AND post_content LIKE '%[ekc_rezervace%'
			ORDER BY menu_order, ID LIMIT 1"
		);
		set_transient( 'ivb_booking_page', $id, DAY_IN_SECONDS );
	}
	if ( $id ) {
		return (string) get_permalink( (int) $id );
	}
	return home_url( '/#rezervace' );
}
add_action(
	'save_post_page',
	static function () {
		delete_transient( 'ivb_booking_page' );
	}
);

/**
 * Adresa výpisu článků. Režim „Poslední příspěvky“ (Nastavení → Čtení): titulka, výpis je na ní.
 * Se statickou úvodní stránkou: stránka příspěvků, jinak archiv rubriky Archiv, jinak '' (výpis
 * článků nikde není a odkaz by vedl zpátky na titulku – „Archiv článků“ se pak nevypíše).
 */
function ivb_blog_url(): string {
	if ( 'page' !== get_option( 'show_on_front' ) ) {
		return home_url( '/' );
	}
	$page = (int) get_option( 'page_for_posts' );
	if ( $page ) {
		return (string) get_permalink( $page );
	}
	$cat = get_category_by_slug( 'archiv' );
	return $cat ? (string) get_category_link( $cat ) : '';
}

/**
 * Štítky stránky (Nastavení stránky → Štítky), HTML.
 *
 * @param WP_Post|int|null $post Stránka.
 */
function ivb_chips( $post = null ): string {
	$raw = function_exists( 'ekt_post_setting' ) ? (string) ekt_post_setting( 'site_chips', $post ) : '';
	$out = '';
	foreach ( array_filter( array_map( 'trim', explode( ',', $raw ) ) ) as $chip ) {
		$out .= '<span class="chip">' . esc_html( $chip ) . '</span>';
	}
	return $out ? '<div class="chips">' . $out . '</div>' : '';
}

/**
 * object-position podle volby Výřez náhledového obrázku.
 *
 * @param WP_Post|int|null $post Příspěvek.
 */
function ivb_focus( $post = null ): string {
	$map = array(
		''       => '50% 28%',
		'top'    => '50% 0%',
		'center' => '50% 50%',
		'bottom' => '50% 85%',
	);
	$val = function_exists( 'ekt_post_setting' ) ? (string) ekt_post_setting( 'site_focus', $post ) : '';
	return $map[ $val ] ?? $map[''];
}

/**
 * Obrázek v oblázku (wrapper .pebw > .peb > img).
 *
 * @param int    $attachment_id Příloha.
 * @param string $size          Velikost.
 * @param string $class         Třídy navíc na .pebw.
 * @param array  $attr          Atributy obrázku (sizes, style, alt, loading…).
 */
function ivb_pebble( int $attachment_id, string $size = 'large', string $class = '', array $attr = array() ): string {
	if ( ! $attachment_id ) {
		return '';
	}
	$img = wp_get_attachment_image( $attachment_id, $size, false, $attr );
	if ( ! $img ) {
		return '';
	}
	return '<div class="' . esc_attr( trim( 'pebw ' . $class ) ) . '"><div class="peb">' . $img . '</div></div>';
}

/**
 * Náhled stránky nebo článku v oblázku s výřezem podle nastavení.
 *
 * @param WP_Post $post  Příspěvek.
 * @param string  $class Třídy navíc.
 * @param string  $sizes Atribut sizes.
 */
function ivb_thumb_pebble( WP_Post $post, string $class = '', string $sizes = '(max-width: 980px) 84vw, 380px' ): string {
	return ivb_pebble(
		(int) get_post_thumbnail_id( $post ),
		'large',
		$class,
		array(
			'sizes' => $sizes,
			'style' => 'object-position:' . ivb_focus( $post ),
			'alt'   => trim( (string) get_post_meta( (int) get_post_thumbnail_id( $post ), '_wp_attachment_image_alt', true ) ),
		)
	);
}

/**
 * Krátký text stránky: ruční úryvek, jinak začátek obsahu. Stránka chráněná heslem nic neukáže.
 *
 * @param WP_Post $post  Příspěvek.
 * @param int     $words Počet slov, když úryvek chybí.
 */
function ivb_excerpt( WP_Post $post, int $words = 30 ): string {
	if ( post_password_required( $post ) ) {
		return '';
	}
	if ( has_excerpt( $post ) ) {
		return (string) get_the_excerpt( $post );
	}
	$text = wp_strip_all_tags( strip_shortcodes( excerpt_remove_blocks( (string) $post->post_content ) ) );
	return wp_trim_words( $text, $words, '…' );
}

/**
 * Sluníčko (zástupné logo) z SVG spritu v patičce.
 *
 * @param string $class Třída.
 */
function ivb_sun( string $class = '' ): string {
	return '<svg' . ( $class ? ' class="' . esc_attr( $class ) . '"' : '' ) . ' aria-hidden="true" focusable="false"><use href="#ivb-sun"/></svg>';
}

/**
 * Hvězdička nebo tečka z SVG spritu, absolutně umístěná.
 *
 * @param string $style Inline umístění (left, top, width, height).
 * @param bool   $dot   Tečka místo čtyřcípé hvězdy.
 */
function ivb_star( string $style, bool $dot = false ): string {
	return '<svg style="' . esc_attr( $style ) . '" aria-hidden="true" focusable="false"><use href="#' . ( $dot ? 'ivb-dot' : 'ivb-star' ) . '"/></svg>';
}

/**
 * Avatar pro rozhovor (Přizpůsobit → Patička → Fotka do rozhovoru), jinak sluníčko.
 *
 * @param string $class Třídy navíc.
 */
function ivb_avatar( string $class = '' ): string {
	$id  = function_exists( 'ekt_opt' ) ? (int) ekt_opt( 'site_avatar' ) : 0;
	$img = $id ? wp_get_attachment_image( $id, 'thumbnail', false, array( 'alt' => '', 'loading' => 'lazy' ) ) : '';
	if ( $img ) {
		return '<span class="' . esc_attr( trim( 'ava ' . $class ) ) . '">' . $img . '</span>';
	}
	return '<span class="' . esc_attr( trim( 'ava ava--sun ' . $class ) ) . '">' . ivb_sun() . '</span>';
}

/**
 * Obsah pruhu nad hlavičkou: text z Přizpůsobit, jinak nejbližší skupinový termín (modul rezervací).
 *
 * @return array{text:string,url:string}
 */
function ivb_strip(): array {
	$text = function_exists( 'ekt_opt' ) ? trim( (string) ekt_opt( 'site_strip' ) ) : '';
	$url  = function_exists( 'ekt_opt' ) ? (string) ekt_opt( 'site_strip_url' ) : '';
	if ( '' === $text && function_exists( 'ekc_reservation_events' ) ) {
		$events = ekc_reservation_events(
			array(
				'limit'     => 1,
				'available' => true,
			)
		);
		$event  = $events[0] ?? null;
		if ( is_array( $event ) && ! empty( $event['date'] ) ) {
			// date = „čt 15. 1. 2026, 18:00“, label = nabídka (jinak název termínu).
			$text = sprintf( 'Nejbližší skupinová lekce: %s · %s', (string) ( $event['label'] ?? '' ), (string) $event['date'] );
			$url  = ivb_booking_url();
			if ( $url && ! empty( $event['id'] ) ) {
				// add_query_arg() kotvu adresy zachová (titulka bez stránky rezervace má #rezervace).
				$url = add_query_arg( 'termin', (int) $event['id'], $url );
				if ( ! str_contains( $url, '#' ) ) {
					$url .= '#rezervace';
				}
			}
		}
	}
	// Mezery a „·“ na okrajích po znacích (trim() s maskou by bral bajty a rozbil UTF-8, např. « nebo NBSP).
	return array(
		'text' => (string) preg_replace( '/^[\s·]+|[\s·]+$/u', '', $text ),
		'url'  => $url,
	);
}

/**
 * Ikona (inline SVG, stroke currentColor) pro kontakt a ovládání recenzí.
 *
 * @param string $name mail|phone|prev|next|pause|play|chevron|menu|close.
 */
function ivb_icon( string $name ): string {
	$paths = array(
		'mail'    => '<rect x="3" y="5" width="18" height="14" rx="3"/><path d="M4 7l8 6 8-6"/>',
		'phone'   => '<path stroke-linejoin="round" d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a1 1 0 0 1-1 1A16 16 0 0 1 4 5a1 1 0 0 1 1-1z"/>',
		'prev'    => '<path stroke-linecap="round" d="M15 5l-7 7 7 7"/>',
		'next'    => '<path stroke-linecap="round" d="M9 5l7 7-7 7"/>',
		'menu'    => '<path stroke-linecap="round" d="M4 7h16M4 12h12M4 17h16"/>',
		'close'   => '<path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/>',
		'chevron' => '<path d="M6 9l6 6 6-6"/>',
	);
	if ( 'pause' === $name ) {
		return '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="7" y="5" width="3.5" height="14" rx="1.5"/><rect x="13.5" y="5" width="3.5" height="14" rx="1.5"/></svg>';
	}
	if ( 'play' === $name ) {
		return '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5l11 7-11 7z"/></svg>';
	}
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}
	return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
}

/**
 * Hlavní menu, dokud správce žádné nepřiřadí: stránky do dvou úrovní (Co nabízím → nabídky).
 */
function ivb_menu_fallback(): void {
	$items = wp_list_pages(
		array(
			'title_li'    => '',
			'depth'       => 2,
			'echo'        => false,
			'sort_column' => 'menu_order,post_title',
		)
	);
	if ( $items ) {
		echo '<ul class="nav__list">' . $items . '</ul>'; // phpcs:ignore WordPress.Security.EscapeOutput -- HTML z wp_list_pages
	}
}

/**
 * Odkazy v patičce, dokud správce nepřiřadí menu „Odkazy v patičce“.
 */
function ivb_footer_links_fallback(): void {
	$links = array(
		'https://soma-synergy.com/'                       => 'Soma Synergy',
		'https://kundaliniactivationtraining.com/en/'     => 'Kundalini Activation Training',
	);
	$booking = ivb_booking_url();
	if ( $booking ) {
		$links[ $booking ] = 'Kontakt a rezervace';
	}
	$blog = ivb_blog_url();
	if ( $blog ) {
		$links[ $blog ] = 'Archiv článků';
	}
	echo '<ul>';
	foreach ( $links as $url => $label ) {
		printf( '<li><a href="%s">%s</a></li>', esc_url( $url ), esc_html( $label ) );
	}
	echo '</ul>';
}
