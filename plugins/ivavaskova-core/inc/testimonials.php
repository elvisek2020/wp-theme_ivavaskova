<?php
/**
 * EKC module: testimonials
 * Version: 1.0.0
 * Requires: common
 * Replaces: strong-testimonials, testimonial-free, wp-testimonials
 * Summary: Recenze klientů jako jednoduchý typ obsahu v administraci, přiřazení k nabídce, pořadí a výpis shortcodem nebo funkcí pro šablonu.
 */

defined( 'ABSPATH' ) || exit;

/*
 * - Typ obsahu ekc_testimonial (menu Recenze, jen pro editory a správce): titulek = jméno, jak se
 *   má zobrazit („Jana, Praha“), obsah = text recenze v prostém textovém poli (bez blokového
 *   editoru, bez TinyMCE a médií), meta _ekc_context = ke které nabídce recenze patří (volné pole
 *   s nabídkou dosud použitých hodnot), pořadí = pole Pořadí (menu_order, vzestupně).
 * - Typ není veřejný: žádné adresy, archiv, hledání, sitemapa, REST ani náhled. Recenze se na web
 *   dostanou jen funkcí ekc_testimonials() nebo shortcodem.
 * - Přehled v administraci: sloupce Jméno, Kontext (odkaz = filtr), Text (úryvek), Pořadí, filtr
 *   podle kontextu, výchozí řazení podle pořadí. Když na webu běží modul post-order, přidá se typ
 *   do ekc_order_types a pořadí jde měnit přetažením.
 * - Shortcode [ekc_recenze kontext="…" pocet="3" razeni="poradi|nahodne"]: kontext = přesná shoda
 *   celé hodnoty (velikost písmen podle kolace databáze, obvykle nerozhoduje), prázdný = všechny;
 *   pocet výchozí 10, 0 = všechny. Náhodné řazení je za cache hostingu stejné až do jejího
 *   vypršení – střídání recenzí patří do šablony (JS nad celým výpisem).
 *
 * Markup (výchozí, filtr ekc_testimonials_html( $html, $items, $args )):
 *   div.ekc-reviews
 *     figure.ekc-review
 *       blockquote.ekc-review__text            text, zalomení jako <br>
 *       figcaption.ekc-review__name             jméno (+ span.ekc-review__context, jen ve výpisu bez kontextu)
 *   Bez recenzí = prázdný řetězec (filtr se volá i tak).
 *
 * Veřejné funkce:
 *   ekc_testimonials( array $args = [] ): array – [ ['id' => int, 'name' => string, 'text' => string,
 *     'context' => string], … ]; args context (''), limit (10, 0 = všechny), orderby (menu_order|rand).
 *     name a context jsou prostý text (escapuj), text je hotové bezpečné HTML (escapovaný text, jen <br>).
 *   ekc_testimonials_render( array $args = [] ): string – výchozí markup (totéž co shortcode).
 *   ekc_testimonials_contexts( bool $drafts = false ): string[] – použité kontexty (abecedně).
 * Filtry: ekc_testimonials_html( $html, $items, $args ), ekc_testimonials_suggestions( $contexts )
 *   – další návrhy kontextu v editoru (např. názvy nabídek z jiného modulu).
 */

/* -------------------------------------------------------------------------
 * Typ obsahu
 * ---------------------------------------------------------------------- */

add_action(
	'init',
	static function () {
		register_post_type(
			'ekc_testimonial',
			array(
				'labels'              => array(
					'name'               => 'Recenze',
					'singular_name'      => 'Recenze',
					'menu_name'          => 'Recenze',
					'all_items'          => 'Všechny recenze',
					'add_new'            => 'Přidat recenzi',
					'add_new_item'       => 'Přidat recenzi',
					'new_item'           => 'Nová recenze',
					'edit_item'          => 'Upravit recenzi',
					'search_items'       => 'Hledat v recenzích',
					'not_found'          => 'Žádné recenze.',
					'not_found_in_trash' => 'V koši nejsou žádné recenze.',
					'attributes'         => 'Pořadí',
				),
				'description'         => 'Recenze klientů pro výpis na webu.',
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_nav_menus'   => false,
				'show_in_rest'        => false, // zároveň vypíná blokový editor
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'menu_position'       => 22,
				'menu_icon'           => 'dashicons-format-quote',
				'supports'            => array( 'title', 'editor', 'page-attributes' ),
				'capability_type'     => 'page', // editoři a správci
				'map_meta_cap'        => true,
				'delete_with_user'    => false,
			)
		);
	}
);

add_filter(
	'post_updated_messages',
	static function ( array $messages ): array {
		$messages['ekc_testimonial'] = array(
			0  => '',
			1  => 'Recenze byla aktualizována.',
			2  => 'Uloženo.',
			3  => 'Uloženo.',
			4  => 'Recenze byla aktualizována.',
			5  => 'Recenze byla obnovena z revize.',
			6  => 'Recenze byla zveřejněna.',
			7  => 'Recenze byla uložena.',
			8  => 'Recenze byla odeslána ke schválení.',
			9  => 'Zveřejnění recenze je naplánované.',
			10 => 'Koncept recenze byl uložen.',
		);
		return $messages;
	}
);

// Pořadí přetažením, když na webu běží modul post-order.
add_filter(
	'ekc_order_types',
	static fn( $types ): array => array_values( array_unique( array_merge( (array) $types, array( 'ekc_testimonial' ) ) ) )
);

/* -------------------------------------------------------------------------
 * Editor
 * ---------------------------------------------------------------------- */

/**
 * Editujeme recenzi? (obrazovka úprav nebo přehledu)
 *
 * @return bool
 */
function ekc_testimonials_screen(): bool {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	return $screen && 'ekc_testimonial' === $screen->post_type;
}

add_filter(
	'enter_title_here',
	static fn( $text, $post = null ): string => ( $post instanceof WP_Post && 'ekc_testimonial' === $post->post_type )
		? 'Jméno, jak se má zobrazit (např. Jana, Praha)'
		: (string) $text,
	10,
	2
);

// Text recenze = prosté textové pole (bez TinyMCE, tlačítek a médií).
add_filter(
	'wp_editor_settings',
	static function ( $settings, $editor_id ) {
		if ( 'content' !== $editor_id || ! ekc_testimonials_screen() ) {
			return $settings;
		}
		return array_merge(
			(array) $settings,
			array(
				'tinymce'          => false,
				'quicktags'        => false,
				'media_buttons'    => false,
				'drag_drop_upload' => false,
				'editor_height'    => 220,
			)
		);
	},
	10,
	2
);

/**
 * Očištění kontextu (prostý text, nejvýš 100 znaků).
 *
 * @param mixed $value Hodnota.
 * @return string
 */
function ekc_testimonials_clean_context( $value ): string {
	$value = sanitize_text_field( html_entity_decode( (string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 100 ) : substr( $value, 0, 100 );
}

/**
 * Použité kontexty recenzí, abecedně.
 *
 * @param bool $drafts Včetně konceptů a čekajících (pro editor); jinak jen zveřejněné.
 * @return string[]
 */
function ekc_testimonials_contexts( bool $drafts = false ): array {
	global $wpdb;
	$statuses = $drafts ? array( 'publish', 'future', 'draft', 'pending', 'private' ) : array( 'publish' );
	$in       = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
	// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $in jsou jen zástupné %s
	$values = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT DISTINCT m.meta_value FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id
			WHERE m.meta_key = '_ekc_context' AND m.meta_value <> '' AND p.post_type = 'ekc_testimonial' AND p.post_status IN ({$in})
			LIMIT 200",
			$statuses
		)
	);
	// phpcs:enable
	$values = array_values( array_unique( array_map( 'strval', (array) $values ) ) );
	natcasesort( $values );
	return array_values( $values );
}

// Kontext mezi titulkem a textem.
add_action(
	'edit_form_after_title',
	static function ( WP_Post $post ) {
		if ( 'ekc_testimonial' !== $post->post_type ) {
			return;
		}
		$value       = (string) get_post_meta( $post->ID, '_ekc_context', true );
		$suggestions = array_merge( ekc_testimonials_contexts( true ), (array) apply_filters( 'ekc_testimonials_suggestions', array() ) );
		$suggestions = array_values( array_unique( array_filter( array_map( 'ekc_testimonials_clean_context', $suggestions ) ) ) );
		natcasesort( $suggestions );

		wp_nonce_field( 'ekc_testimonial_save', 'ekc_testimonial_nonce' );
		echo '<p style="margin:16px 0 4px"><label for="ekc-context"><strong>Kontext</strong></label></p>';
		echo '<p style="margin:0"><input type="text" id="ekc-context" name="ekc_context" class="regular-text" maxlength="100" autocomplete="off" list="ekc-context-list" value="' . esc_attr( $value ) . '"></p>';
		echo '<datalist id="ekc-context-list">';
		foreach ( $suggestions as $suggestion ) {
			echo '<option value="' . esc_attr( $suggestion ) . '"></option>';
		}
		echo '</datalist>';
		echo '<p class="description" style="margin:4px 0 16px">Ke které nabídce recenze patří – stejný text u všech recenzí téže nabídky (vyberte z nabídky pole). '
			. 'Na stránce je vypíše shortcode <code>[ekc_recenze kontext="…"]</code>. Prázdné = obecná recenze, zobrazí se jen ve výpisu všech recenzí.</p>';
		echo '<p style="margin:0 0 4px"><strong>Text recenze</strong> <span class="description">– prostý text, nový řádek = zalomení.</span></p>';
	}
);

add_action(
	'save_post_ekc_testimonial',
	static function ( int $post_id ) {
		if ( wp_is_post_revision( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return;
		}
		// Rychlé úpravy a WP-CLI pole nemají – kontext se nemění.
		$nonce = isset( $_POST['ekc_testimonial_nonce'] ) ? sanitize_key( wp_unslash( $_POST['ekc_testimonial_nonce'] ) ) : '';
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'ekc_testimonial_save' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$value = ekc_testimonials_clean_context( isset( $_POST['ekc_context'] ) ? wp_unslash( $_POST['ekc_context'] ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- očištění v ekc_testimonials_clean_context
		if ( '' === $value ) {
			delete_post_meta( $post_id, '_ekc_context' );
		} else {
			update_post_meta( $post_id, '_ekc_context', wp_slash( $value ) );
		}
	}
);

/* -------------------------------------------------------------------------
 * Přehled v administraci
 * ---------------------------------------------------------------------- */

add_filter(
	'manage_ekc_testimonial_posts_columns',
	static fn(): array => array(
		'cb'          => '<input type="checkbox">',
		'title'       => 'Jméno',
		'ekc_context' => 'Kontext',
		'ekc_text'    => 'Text',
		'ekc_order'   => 'Pořadí',
		'date'        => 'Datum',
	)
);

add_filter(
	'manage_edit-ekc_testimonial_sortable_columns',
	static function ( array $columns ): array {
		$columns['ekc_order'] = 'menu_order';
		return $columns;
	}
);

add_action(
	'manage_ekc_testimonial_posts_custom_column',
	static function ( string $column, int $post_id ) {
		if ( 'ekc_context' === $column ) {
			$context = (string) get_post_meta( $post_id, '_ekc_context', true );
			echo '' === $context
				? '<span aria-hidden="true">—</span>'
				: '<a href="' . esc_url( add_query_arg( array( 'post_type' => 'ekc_testimonial', 'ekc_context' => rawurlencode( $context ) ), admin_url( 'edit.php' ) ) ) . '">' . esc_html( $context ) . '</a>';
		} elseif ( 'ekc_text' === $column ) {
			// Totéž, co uvidí návštěvník (bez shortcodů a HTML), zalomení jako mezera.
			$text = str_replace( '<br>', ' ', ekc_testimonials_text( (string) get_post_field( 'post_content', $post_id ) ) );
			echo esc_html( wp_trim_words( html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), 25, '…' ) );
		} elseif ( 'ekc_order' === $column ) {
			echo (int) get_post_field( 'menu_order', $post_id );
		}
	},
	10,
	2
);

add_action(
	'admin_head-edit.php',
	static function () {
		if ( ekc_testimonials_screen() ) {
			echo '<style>.fixed .column-ekc_context{width:16%}.fixed .column-ekc_order{width:5em}.fixed .column-ekc_text{width:40%}</style>';
		}
	}
);

// Filtr podle kontextu nad přehledem.
add_action(
	'restrict_manage_posts',
	static function ( $post_type ) {
		if ( 'ekc_testimonial' !== $post_type ) {
			return;
		}
		$contexts = ekc_testimonials_contexts( true );
		if ( ! $contexts ) {
			return;
		}
		$current = isset( $_GET['ekc_context'] ) ? ekc_testimonials_clean_context( wp_unslash( $_GET['ekc_context'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- jen filtr přehledu
		echo '<label class="screen-reader-text" for="ekc-context-filter">Filtrovat podle kontextu</label>';
		echo '<select name="ekc_context" id="ekc-context-filter"><option value="">Všechny kontexty</option>';
		foreach ( $contexts as $context ) {
			echo '<option value="' . esc_attr( $context ) . '"' . selected( $current, $context, false ) . '>' . esc_html( $context ) . '</option>';
		}
		echo '</select>';
	}
);

// Výchozí řazení podle pořadí a filtr podle kontextu.
add_action(
	'pre_get_posts',
	static function ( $query ) {
		global $pagenow;
		if ( ! is_admin() || 'edit.php' !== $pagenow || ! $query instanceof WP_Query || ! $query->is_main_query() || 'ekc_testimonial' !== $query->get( 'post_type' ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- jen zobrazení přehledu
		if ( ! isset( $_GET['orderby'] ) ) {
			$query->set(
				'orderby',
				array(
					'menu_order' => 'ASC',
					'date'       => 'DESC',
				)
			);
		}
		$context = isset( $_GET['ekc_context'] ) ? ekc_testimonials_clean_context( wp_unslash( $_GET['ekc_context'] ) ) : '';
		// phpcs:enable
		if ( '' !== $context ) {
			$query->set(
				'meta_query',
				array(
					array(
						'key'   => '_ekc_context',
						'value' => $context,
					),
				)
			);
		}
	}
);

/* -------------------------------------------------------------------------
 * Data a výpis
 * ---------------------------------------------------------------------- */

/**
 * Normalizované argumenty výpisu.
 *
 * @param array $args context, limit, orderby.
 * @return array{context:string,limit:int,orderby:string}
 */
function ekc_testimonials_args( array $args ): array {
	$args = wp_parse_args(
		$args,
		array(
			'context' => '',
			'limit'   => 10,
			'orderby' => 'menu_order',
		)
	);
	return array(
		'context' => ekc_testimonials_clean_context( $args['context'] ),
		'limit'   => max( 0, (int) $args['limit'] ),
		'orderby' => 'rand' === $args['orderby'] ? 'rand' : 'menu_order',
	);
}

/**
 * Text recenze jako bezpečné HTML: bez značek a shortcodů, zalomení řádků jako <br>,
 * nejvýš jeden prázdný řádek za sebou.
 *
 * @param string $content Obsah příspěvku.
 * @return string
 */
function ekc_testimonials_text( string $content ): string {
	$content = strip_shortcodes( $content );
	$content = (string) preg_replace( '#<br\s*/?>#i', "\n", $content );
	$content = (string) preg_replace( '#</(p|div|li|h[1-6]|blockquote)>#i', "\n\n", $content );
	$content = html_entity_decode( wp_strip_all_tags( $content ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$lines   = array_map( 'trim', explode( "\n", str_replace( array( "\r\n", "\r" ), "\n", $content ) ) );
	$text    = trim( (string) preg_replace( "/\n{3,}/", "\n\n", implode( "\n", $lines ) ) );
	return '' === $text ? '' : implode( '<br>', array_map( 'esc_html', explode( "\n", $text ) ) );
}

/**
 * Zveřejněné recenze.
 *
 * @param array $args context (přesná shoda, '' = všechny), limit (10, 0 = všechny), orderby (menu_order|rand).
 * @return array<int,array{id:int,name:string,text:string,context:string}>
 */
function ekc_testimonials( array $args = array() ): array {
	$args  = ekc_testimonials_args( $args );
	$query = array(
		'post_type'              => 'ekc_testimonial',
		'post_status'            => 'publish',
		'has_password'           => false,
		'posts_per_page'         => $args['limit'] ? $args['limit'] : -1,
		'orderby'                => 'rand' === $args['orderby'] ? 'rand' : array(
			'menu_order' => 'ASC',
			'date'       => 'DESC',
			'ID'         => 'DESC',
		),
		'no_found_rows'          => true,
		'ignore_sticky_posts'    => true,
		'update_post_term_cache' => false,
	);
	if ( '' !== $args['context'] ) {
		$query['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery
			array(
				'key'   => '_ekc_context',
				'value' => $args['context'],
			),
		);
	}

	$items = array();
	foreach ( ( new WP_Query( $query ) )->posts as $post ) {
		$text = ekc_testimonials_text( (string) $post->post_content );
		if ( '' === $text ) {
			continue;
		}
		$items[] = array(
			'id'      => (int) $post->ID,
			'name'    => trim( html_entity_decode( wp_strip_all_tags( (string) $post->post_title ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ),
			'text'    => $text,
			'context' => (string) get_post_meta( $post->ID, '_ekc_context', true ),
		);
	}
	return $items;
}

/**
 * Výchozí markup výpisu (filtr ekc_testimonials_html).
 *
 * @param array $args Jako ekc_testimonials().
 * @return string
 */
function ekc_testimonials_render( array $args = array() ): string {
	$args  = ekc_testimonials_args( $args );
	$items = ekc_testimonials( $args );
	$html  = '';
	if ( $items ) {
		$html = '<div class="ekc-reviews">';
		foreach ( $items as $item ) {
			// Kontext jen ve výpisu všech recenzí – ve výpisu jedné nabídky by se opakoval.
			$context = '' === $args['context'] && '' !== $item['context']
				? ' <span class="ekc-review__context">' . esc_html( $item['context'] ) . '</span>'
				: '';
			$caption = '' !== $item['name'] || '' !== $context
				? '<figcaption class="ekc-review__name">' . esc_html( $item['name'] ) . $context . '</figcaption>'
				: '';
			$html   .= '<figure class="ekc-review"><blockquote class="ekc-review__text">' . $item['text'] . '</blockquote>' . $caption . '</figure>';
		}
		$html .= '</div>';
	}
	return (string) apply_filters( 'ekc_testimonials_html', $html, $items, $args );
}

add_shortcode(
	'ekc_recenze',
	static function ( $atts ): string {
		$atts = shortcode_atts(
			array(
				'kontext' => '',
				'pocet'   => 10,
				'razeni'  => 'poradi',
			),
			is_array( $atts ) ? $atts : array(),
			'ekc_recenze'
		);
		return ekc_testimonials_render(
			array(
				'context' => (string) $atts['kontext'],
				'limit'   => (int) $atts['pocet'],
				'orderby' => 'nahodne' === $atts['razeni'] ? 'rand' : 'menu_order',
			)
		);
	}
);
