<?php
/**
 * EKC module: contact-form
 * Version: 1.0.1
 * Requires: common
 * Replaces: forminator, contact-form-7, wpforms-lite
 * Summary: Kontaktní formulář bez cizích služeb: antispam, e-mail správci, zprávy v administraci a převod záznamů z Forminatoru.
 */

defined( 'ABSPATH' ) || exit;

/*
 * - Shortcode [ekc_contact_form subject="…" button="…"]. Když Forminator neběží, převezme i jeho
 *   shortcode [forminator_form id="…"] (uložený v bloku forminator/forms), takže obsah stránek
 *   se po vypnutí Forminatoru nemusí přepisovat; předmět je název formuláře Forminatoru.
 * - Funguje bez JS: formulář se odešle na stejnou adresu, chyby se vypíšou u formuláře
 *   s vyplněnými hodnotami, po úspěchu přesměrování 303 na ?ekc_sent=1#kotva (žádné dvojí odeslání).
 * - Bez nonce (stránky bývají v cache hostingu): podepsané časové razítko (wp_hash), skryté pole,
 *   nejkratší doba vyplnění 3 s, nejvýš 5 odeslání za hodinu z jedné IP, nejvýš 3 odkazy ve zprávě.
 *   Spam dostane stejnou odpověď jako úspěch, ale nic se neuloží ani neodešle.
 * - E-mail na adresy z nastavení (prázdné = e-mail správce), Reply-To = odesílatel (jméno bez
 *   čárek a středníků, jinak by z něj wp_mail udělal další adresu). Kopie odesílateli se neposílá
 *   (formulář by šel zneužít k rozesílání).
 * - Zprávy jsou typ obsahu ekc_message (Zprávy z webu v administraci, jen pro manage_options),
 *   nepřečtené mají meta _ekc_unread a počet u menu. Metadata: _ekc_name, _ekc_email, _ekc_phone,
 *   _ekc_subject, _ekc_page, _ekc_ip, _ekc_mail_failed, _ekc_frmt_entry (převod).
 * - Převod z Forminatoru: krok migrace contact-form/forminator a tlačítko v Údržbě webu
 *   (idempotentní podle _ekc_frmt_entry); příjemce převezme z notifikace, když nastavení je prázdné.
 *
 * Markup (výchozí, filtr ekc_contact_form_html( $html, $args )):
 *   div.ekc-form#ekc-contact-N
 *     p.ekc-form__notice.ekc-form__notice--ok | --error (role=status / alert)
 *     form.ekc-form__form[method=post][novalidate? ne – HTML5 validace zůstává]
 *       div.ekc-form__grid > p.ekc-form__field.ekc-form__field--name|email|phone
 *         label.ekc-form__label (+ span.ekc-form__req) + input.ekc-form__input + span.ekc-form__error
 *       p.ekc-form__field.ekc-form__field--message > label + textarea.ekc-form__input
 *       p.ekc-form__hp (skryté pole, inline styl)
 *       p.ekc-form__consent
 *       p.ekc-form__actions > button.ekc-form__submit.wp-element-button
 *
 * Veřejné funkce: ekc_contact_form( array $args ): string, ekc_contact_unread(): int,
 * ekc_contact_import_forminator(): array, ekc_contact_form_migrated(): bool.
 * Filtry: ekc_contact_form_html, ekc_contact_form_texts( $texts ), ekc_contact_mail( $mail, $data ),
 * ekc_contact_is_spam( $spam, $data ). Akce: ekc_contact_received( $post_id, $data ).
 */

ekc_settings_register(
	'contact_to',
	array(
		'type'    => 'text',
		'default' => '',
		'label'   => 'Kontaktní formulář: příjemci',
		'help'    => 'E-maily oddělené čárkou. Prázdné = e-mail správce webu.',
		'section' => 'Obsah',
	)
);
ekc_settings_register(
	'contact_subject',
	array(
		'type'    => 'text',
		'default' => 'Zpráva z webu',
		'label'   => 'Kontaktní formulář: předmět e-mailu',
		'section' => 'Obsah',
	)
);
ekc_settings_register(
	'contact_phone',
	array(
		'type'    => 'select',
		'default' => 'optional',
		'label'   => 'Kontaktní formulář: telefon',
		'options' => array(
			'optional' => 'Nepovinný',
			'required' => 'Povinný',
			'off'      => 'Nezobrazovat',
		),
		'section' => 'Obsah',
	)
);
ekc_settings_register(
	'contact_success',
	array(
		'type'    => 'text',
		'default' => 'Děkujeme, zprávu jsme dostali. Ozveme se co nejdříve.',
		'label'   => 'Kontaktní formulář: poděkování',
		'section' => 'Obsah',
	)
);
ekc_settings_register(
	'contact_consent',
	array(
		'type'    => 'textarea',
		'default' => 'Odesláním souhlasíte se zpracováním uvedených údajů za účelem odpovědi na váš dotaz.',
		'label'   => 'Kontaktní formulář: text pod formulářem',
		'help'    => 'Prázdné = nic. Když je zveřejněná stránka Zásady ochrany osobních údajů, připojí se odkaz.',
		'section' => 'Obsah',
	)
);

const EKC_CONTACT_MIN_SECONDS = 3;
const EKC_CONTACT_RATE        = 5; // odeslání za hodinu z jedné IP

/**
 * Texty formuláře (filtr ekc_contact_form_texts).
 *
 * @return array<string,string>
 */
function ekc_contact_texts(): array {
	$texts = array(
		'name'        => 'Jméno a příjmení',
		'email'       => 'E-mail',
		'phone'       => 'Telefon',
		'message'     => 'Zpráva',
		'button'      => 'Odeslat zprávu',
		'required'    => 'povinné',
		'err_name'    => 'Vyplňte prosím jméno.',
		'err_email'   => 'Zadejte prosím platný e-mail.',
		'err_phone'   => 'Zadejte prosím platné telefonní číslo.',
		'err_message' => 'Napište prosím zprávu (aspoň pár slov).',
		'err_general' => 'Zprávu se nepodařilo odeslat, zkontrolujte prosím označená pole.',
		'err_rate'    => 'Odeslali jste příliš mnoho zpráv, zkuste to prosím později nebo zavolejte.',
		'privacy'     => 'Zásady ochrany osobních údajů',
	);
	return array_map( 'strval', array_merge( $texts, (array) apply_filters( 'ekc_contact_form_texts', $texts ) ) );
}

/* -------------------------------------------------------------------------
 * Zprávy v administraci
 * ---------------------------------------------------------------------- */

add_action(
	'init',
	static function () {
		register_post_type(
			'ekc_message',
			array(
				'labels'          => array(
					'name'          => 'Zprávy z webu',
					'singular_name' => 'Zpráva z webu',
					'menu_name'     => 'Zprávy z webu',
					'all_items'     => 'Všechny zprávy',
					'edit_item'     => 'Zpráva z webu',
					'search_items'  => 'Hledat ve zprávách',
					'not_found'     => 'Žádné zprávy.',
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'show_in_rest'    => false,
				'menu_position'   => 26,
				'menu_icon'       => 'dashicons-email-alt',
				'supports'        => array( 'title', 'editor' ),
				'capability_type' => 'post',
				'capabilities'    => array(
					'create_posts'       => 'do_not_allow',
					'edit_posts'         => 'manage_options',
					'edit_others_posts'  => 'manage_options',
					'edit_post'          => 'manage_options',
					'read_post'          => 'manage_options',
					'delete_post'        => 'manage_options',
					'delete_posts'       => 'manage_options',
					'delete_others_posts' => 'manage_options',
					'publish_posts'      => 'manage_options',
				),
				'map_meta_cap'    => false,
			)
		);
	}
);

/**
 * Počet nepřečtených zpráv.
 *
 * @return int
 */
function ekc_contact_unread(): int {
	$q = new WP_Query(
		array(
			'post_type'      => 'ekc_message',
			'post_status'    => 'publish',
			'meta_key'       => '_ekc_unread', // phpcs:ignore WordPress.DB.SlowDBQuery
			'fields'         => 'ids',
			'posts_per_page' => 1,
			'no_found_rows'  => false,
		)
	);
	return (int) $q->found_posts;
}

// Počet nepřečtených u položky menu.
add_action(
	'admin_menu',
	static function () {
		global $menu;
		$count = ekc_contact_unread();
		if ( ! $count || ! is_array( $menu ) ) {
			return;
		}
		foreach ( $menu as $i => $item ) {
			if ( 'edit.php?post_type=ekc_message' === ( $item[2] ?? '' ) ) {
				$menu[ $i ][0] .= ' <span class="awaiting-mod"><span class="pending-count">' . (int) $count . '</span></span>'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			}
		}
	},
	99
);

// Otevřená zpráva = přečtená.
add_action(
	'load-post.php',
	static function () {
		$id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
		if ( $id && 'ekc_message' === get_post_type( $id ) ) {
			delete_post_meta( $id, '_ekc_unread' );
		}
	}
);

add_filter(
	'manage_ekc_message_posts_columns',
	static fn(): array => array(
		'cb'          => '<input type="checkbox">',
		'title'       => 'Předmět',
		'ekc_from'    => 'Od',
		'ekc_message' => 'Zpráva',
		'date'        => 'Přijato',
	)
);

add_action(
	'manage_ekc_message_posts_custom_column',
	static function ( string $column, int $post_id ) {
		if ( 'ekc_from' === $column ) {
			$name  = (string) get_post_meta( $post_id, '_ekc_name', true );
			$email = (string) get_post_meta( $post_id, '_ekc_email', true );
			$phone = (string) get_post_meta( $post_id, '_ekc_phone', true );
			echo '<strong>' . esc_html( $name ) . '</strong>';
			if ( $email ) {
				echo '<br><a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>';
			}
			if ( $phone ) {
				echo '<br><a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ) . '">' . esc_html( $phone ) . '</a>';
			}
		} elseif ( 'ekc_message' === $column ) {
			echo esc_html( wp_trim_words( (string) get_post_field( 'post_content', $post_id ), 30, '…' ) );
			if ( get_post_meta( $post_id, '_ekc_mail_failed', true ) ) {
				echo '<br><span style="color:#b32d2e">E-mail se nepodařilo odeslat.</span>';
			}
		}
	},
	10,
	2
);

// Nepřečtené tučně v přehledu.
add_filter(
	'post_class',
	static function ( array $classes, $class, int $post_id ): array {
		if ( is_admin() && 'ekc_message' === get_post_type( $post_id ) && get_post_meta( $post_id, '_ekc_unread', true ) ) {
			$classes[] = 'ekc-unread';
		}
		return $classes;
	},
	10,
	3
);
add_action(
	'admin_head-edit.php',
	static function () {
		if ( 'ekc_message' === ( $GLOBALS['typenow'] ?? '' ) ) {
			echo '<style>.ekc-unread .row-title,.ekc-unread td{font-weight:600}</style>';
		}
	}
);

// Detail zprávy: odesílatel nad textem.
add_action(
	'edit_form_after_title',
	static function ( WP_Post $post ) {
		if ( 'ekc_message' !== $post->post_type ) {
			return;
		}
		$rows = array(
			'Jméno'   => esc_html( (string) get_post_meta( $post->ID, '_ekc_name', true ) ),
			'E-mail'  => ( static function ( string $e ): string {
				return $e ? '<a href="mailto:' . esc_attr( $e ) . '">' . esc_html( $e ) . '</a>' : '';
			} )( (string) get_post_meta( $post->ID, '_ekc_email', true ) ),
			'Telefon' => esc_html( (string) get_post_meta( $post->ID, '_ekc_phone', true ) ),
			'Stránka' => esc_html( (string) get_post_meta( $post->ID, '_ekc_page', true ) ),
			'IP'      => esc_html( (string) get_post_meta( $post->ID, '_ekc_ip', true ) ),
		);
		echo '<table class="widefat striped" style="margin:12px 0"><tbody>';
		foreach ( array_filter( $rows ) as $label => $html ) {
			echo '<tr><th style="width:120px">' . esc_html( $label ) . '</th><td>' . $html . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</tbody></table>';
	}
);

/* -------------------------------------------------------------------------
 * Formulář
 * ---------------------------------------------------------------------- */

/**
 * Podpis časového razítka a předmětu.
 *
 * @param int    $ts      Čas vykreslení.
 * @param string $subject Předmět.
 * @return string
 */
function ekc_contact_sign( int $ts, string $subject ): string {
	return substr( wp_hash( 'ekc_contact|' . $ts . '|' . $subject, 'nonce' ), 0, 20 );
}

/**
 * Adresa, na kterou se formulář odesílá (aktuální stránka bez parametrů formuláře).
 *
 * @return string
 */
function ekc_contact_action_url(): string {
	$url = is_singular() ? (string) get_permalink() : home_url( add_query_arg( array() ) );
	return remove_query_arg( 'ekc_sent', $url );
}

/**
 * Stav posledního odeslání v tomto požadavku (chyby a hodnoty).
 *
 * @param array|null $set Nový stav.
 * @return array{errors:array<string,string>,values:array<string,string>,general:string}
 */
function ekc_contact_state( ?array $set = null ): array {
	static $state = array(
		'errors'  => array(),
		'values'  => array(),
		'general' => '',
	);
	if ( null !== $set ) {
		$state = array_merge( $state, $set );
	}
	return $state;
}

/**
 * HTML formuláře.
 *
 * @param array $args subject (předmět zprávy), button (text tlačítka).
 * @return string
 */
function ekc_contact_form( array $args = array() ): string {
	static $n = 0;
	++$n;
	$t       = ekc_contact_texts();
	$args    = wp_parse_args(
		$args,
		array(
			'subject' => (string) ekc_opt( 'contact_subject' ),
			'button'  => $t['button'],
		)
	);
	$subject = sanitize_text_field( (string) $args['subject'] );
	$anchor  = 'ekc-contact-' . $n;
	$state   = ekc_contact_state();
	$sent    = isset( $_GET['ekc_sent'] ) && $n === (int) $_GET['ekc_sent']; // phpcs:ignore WordPress.Security.NonceVerification
	$posted  = isset( $_POST['ekc_cf'] ) && $n === (int) $_POST['ekc_cf']; // phpcs:ignore WordPress.Security.NonceVerification
	$errors  = $posted ? $state['errors'] : array();
	$values  = $posted ? $state['values'] : array();
	$phone   = (string) ekc_opt( 'contact_phone' );
	$ts      = time();

	$html = '<div class="ekc-form" id="' . esc_attr( $anchor ) . '">';
	if ( $sent ) {
		$html .= '<p class="ekc-form__notice ekc-form__notice--ok" role="status">' . esc_html( (string) ekc_opt( 'contact_success' ) ) . '</p>';
	} elseif ( $posted && ( $errors || $state['general'] ) ) {
		$html .= '<p class="ekc-form__notice ekc-form__notice--error" role="alert">' . esc_html( $state['general'] ?: $t['err_general'] ) . '</p>';
	}

	$field = static function ( string $key, string $type, bool $required, string $autocomplete ) use ( $t, $errors, $values, $n ): string {
		$id   = 'ekc-cf-' . $n . '-' . $key;
		$err  = $errors[ $key ] ?? '';
		$attr = ' id="' . esc_attr( $id ) . '" name="ekc_cf_' . esc_attr( $key ) . '" class="ekc-form__input" autocomplete="' . esc_attr( $autocomplete ) . '"'
			. ( $required ? ' required' : '' ) . ( $err ? ' aria-invalid="true" aria-describedby="' . esc_attr( $id ) . '-err"' : '' );
		$val  = (string) ( $values[ $key ] ?? '' );
		$out  = '<p class="ekc-form__field ekc-form__field--' . esc_attr( $key ) . '"><label class="ekc-form__label" for="' . esc_attr( $id ) . '">' . esc_html( $t[ $key ] )
			. ( $required ? ' <span class="ekc-form__req" aria-hidden="true">*</span>' : '' ) . '</label>';
		if ( 'textarea' === $type ) {
			$out .= '<textarea' . $attr . ' rows="6" maxlength="5000">' . esc_textarea( $val ) . '</textarea>';
		} else {
			$out .= '<input type="' . esc_attr( $type ) . '"' . $attr . ' value="' . esc_attr( $val ) . '" maxlength="' . ( 'tel' === $type ? 30 : 120 ) . '">';
		}
		if ( $err ) {
			$out .= '<span class="ekc-form__error" id="' . esc_attr( $id ) . '-err">' . esc_html( $err ) . '</span>';
		}
		return $out . '</p>';
	};

	if ( ! $sent ) {
		$html .= '<form class="ekc-form__form" method="post" action="' . esc_url( ekc_contact_action_url() . '#' . $anchor ) . '">';
		$html .= '<div class="ekc-form__grid">';
		$html .= $field( 'name', 'text', true, 'name' );
		$html .= $field( 'email', 'email', true, 'email' );
		if ( 'off' !== $phone ) {
			$html .= $field( 'phone', 'tel', 'required' === $phone, 'tel' );
		}
		$html .= '</div>';
		$html .= $field( 'message', 'textarea', true, 'off' );
		$html .= '<p class="ekc-form__hp" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden"><label>Web <input type="text" name="ekc_cf_web" tabindex="-1" autocomplete="off" value=""></label></p>';
		$html .= '<input type="hidden" name="ekc_cf" value="' . (int) $n . '">';
		$html .= '<input type="hidden" name="ekc_cf_ts" value="' . (int) $ts . '">';
		$html .= '<input type="hidden" name="ekc_cf_subject" value="' . esc_attr( $subject ) . '">';
		$html .= '<input type="hidden" name="ekc_cf_sig" value="' . esc_attr( ekc_contact_sign( $ts, $subject ) ) . '">';

		$consent = trim( (string) ekc_opt( 'contact_consent' ) );
		if ( '' !== $consent ) {
			$privacy = (int) get_option( 'wp_page_for_privacy_policy' );
			$link    = ( $privacy && 'publish' === get_post_status( $privacy ) )
				? ' <a href="' . esc_url( (string) get_permalink( $privacy ) ) . '">' . esc_html( $t['privacy'] ) . '</a>'
				: '';
			$html   .= '<p class="ekc-form__consent">' . esc_html( $consent ) . $link . '</p>';
		}
		$html .= '<p class="ekc-form__actions"><button type="submit" class="ekc-form__submit wp-element-button">' . esc_html( (string) $args['button'] ) . '</button></p>';
		$html .= '</form>';
	}
	$html .= '</div>';

	return (string) apply_filters( 'ekc_contact_form_html', $html, $args );
}

add_shortcode(
	'ekc_contact_form',
	static fn( $atts ): string => ekc_contact_form( is_array( $atts ) ? $atts : array() )
);

// Shortcode Forminatoru (uložený v bloku forminator/forms), když Forminator neběží.
add_action(
	'init',
	static function () {
		if ( shortcode_exists( 'forminator_form' ) ) {
			return;
		}
		add_shortcode(
			'forminator_form',
			static function ( $atts ): string {
				$id    = is_array( $atts ) ? (int) ( $atts['id'] ?? 0 ) : 0;
				$title = ekc_contact_frmt_title( $id );
				return ekc_contact_form( '' !== $title ? array( 'subject' => $title ) : array() );
			}
		);
	},
	99
);

/* -------------------------------------------------------------------------
 * Zpracování
 * ---------------------------------------------------------------------- */

add_action( 'template_redirect', 'ekc_contact_handle', 5 );

/**
 * Zpracuje odeslaný formulář.
 */
function ekc_contact_handle(): void {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! isset( $_POST['ekc_cf'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	// phpcs:disable WordPress.Security.NonceVerification -- veřejný formulář v cache, ochrana podpisem razítka
	$t      = ekc_contact_texts();
	$n      = (int) $_POST['ekc_cf'];
	$get    = static fn( string $k ): string => isset( $_POST[ 'ekc_cf_' . $k ] ) ? trim( (string) wp_unslash( $_POST[ 'ekc_cf_' . $k ] ) ) : '';
	$values = array(
		'name'    => sanitize_text_field( $get( 'name' ) ),
		'email'   => sanitize_email( $get( 'email' ) ),
		'phone'   => sanitize_text_field( $get( 'phone' ) ),
		'message' => sanitize_textarea_field( $get( 'message' ) ),
	);
	$subject = sanitize_text_field( $get( 'subject' ) );
	$ts      = (int) $get( 'ts' );
	// phpcs:enable

	$url  = ekc_contact_action_url();
	$done = static function () use ( $url, $n ): void {
		wp_safe_redirect( add_query_arg( 'ekc_sent', $n, $url ) . '#ekc-contact-' . $n, 303 );
		exit;
	};

	// Spam: tváří se jako úspěch, nic se neuloží ani neodešle.
	$spam = '' !== $get( 'web' )
		|| ! hash_equals( ekc_contact_sign( $ts, $subject ), $get( 'sig' ) )
		|| time() - $ts < EKC_CONTACT_MIN_SECONDS
		|| preg_match_all( '#https?://|www\.#i', $values['message'] ) > 3;
	if ( apply_filters( 'ekc_contact_is_spam', $spam, $values ) ) {
		$done();
	}

	$errors = array();
	$length = static fn( string $s ): int => function_exists( 'mb_strlen' ) ? mb_strlen( $s ) : strlen( $s );
	if ( $length( $values['name'] ) < 2 ) {
		$errors['name'] = $t['err_name'];
	}
	if ( ! is_email( $values['email'] ) ) {
		$errors['email'] = $t['err_email'];
	}
	$phone_mode = (string) ekc_opt( 'contact_phone' );
	if ( 'off' === $phone_mode ) {
		$values['phone'] = '';
	} elseif ( ( '' !== $values['phone'] || 'required' === $phone_mode ) && ! preg_match( '/^[0-9 +()\/.-]{6,30}$/', $values['phone'] ) ) {
		$errors['phone'] = $t['err_phone'];
	}
	if ( $length( $values['message'] ) < 5 ) {
		$errors['message'] = $t['err_message'];
	}
	if ( $errors ) {
		ekc_contact_state(
			array(
				'errors' => $errors,
				'values' => $values,
			)
		);
		return; // stránka se vykreslí s chybami a vyplněnými hodnotami
	}

	$ip   = ekc_client_ip();
	$rate = 'ekc_cf_rate_' . md5( $ip );
	$sent = (int) get_transient( $rate );
	if ( $sent >= EKC_CONTACT_RATE ) {
		ekc_contact_state(
			array(
				'values'  => $values,
				'general' => $t['err_rate'],
			)
		);
		return;
	}
	set_transient( $rate, $sent + 1, HOUR_IN_SECONDS );

	$values['subject'] = '' !== $subject ? $subject : (string) ekc_opt( 'contact_subject' );
	$values['page']    = $url;
	$values['ip']      = $ip;
	$post_id           = ekc_contact_store( $values );

	$ok = ekc_contact_send( $values );
	if ( $post_id && ! $ok ) {
		update_post_meta( $post_id, '_ekc_mail_failed', 1 );
	}
	do_action( 'ekc_contact_received', $post_id, $values );
	$done();
}

/**
 * Uloží zprávu.
 *
 * @param array $data name, email, phone, message, subject, page, ip; volitelně date (Y-m-d H:i:s), unread, frmt_entry.
 * @return int ID zprávy, 0 = chyba.
 */
function ekc_contact_store( array $data ): int {
	$args = array(
		'post_type'    => 'ekc_message',
		'post_status'  => 'publish',
		'post_title'   => wp_strip_all_tags( (string) ( $data['subject'] ?? '' ) . ' – ' . (string) ( $data['name'] ?? '' ) ),
		'post_content' => (string) ( $data['message'] ?? '' ),
		'meta_input'   => array(
			'_ekc_name'    => (string) ( $data['name'] ?? '' ),
			'_ekc_email'   => (string) ( $data['email'] ?? '' ),
			'_ekc_phone'   => (string) ( $data['phone'] ?? '' ),
			'_ekc_subject' => (string) ( $data['subject'] ?? '' ),
			'_ekc_page'    => (string) ( $data['page'] ?? '' ),
			'_ekc_ip'      => (string) ( $data['ip'] ?? '' ),
		),
	);
	if ( ! empty( $data['date'] ) ) {
		$args['post_date']     = (string) $data['date'];
		$args['post_date_gmt'] = get_gmt_from_date( (string) $data['date'] );
	}
	if ( $data['unread'] ?? true ) {
		$args['meta_input']['_ekc_unread'] = 1;
	}
	if ( ! empty( $data['frmt_entry'] ) ) {
		$args['meta_input']['_ekc_frmt_entry'] = (int) $data['frmt_entry'];
	}
	// Bez kontroly oprávnění a bez kses na prostém textu.
	kses_remove_filters();
	$id = wp_insert_post( wp_slash( $args ), true );
	kses_init_filters();
	return is_wp_error( $id ) ? 0 : (int) $id;
}

/**
 * Příjemci z nastavení, jinak e-mail správce.
 *
 * @return string[]
 */
function ekc_contact_recipients(): array {
	$list = array_filter( array_map( 'trim', explode( ',', (string) ekc_opt( 'contact_to' ) ) ), 'is_email' );
	return $list ? array_values( $list ) : array( (string) get_option( 'admin_email' ) );
}

/**
 * Pošle zprávu e-mailem (prostý text, Reply-To odesílatel).
 *
 * @param array $data Data zprávy.
 * @return bool
 */
function ekc_contact_send( array $data ): bool {
	$site = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
	$body = 'Nová zpráva z kontaktního formuláře na webu ' . $site . "\n\n"
		. 'Jméno: ' . $data['name'] . "\n"
		. 'E-mail: ' . $data['email'] . "\n"
		. ( '' !== $data['phone'] ? 'Telefon: ' . $data['phone'] . "\n" : '' )
		. "\n" . $data['message'] . "\n\n"
		. "-- \n"
		. 'Odesláno ' . wp_date( 'j. n. Y H:i' ) . ' ze stránky ' . $data['page'] . "\n"
		. 'Všechny zprávy: ' . admin_url( 'edit.php?post_type=ekc_message' ) . "\n";
	$reply = trim( (string) preg_replace( '/\s+/', ' ', str_replace( array( "\r", "\n", '"', '<', '>', ',', ';' ), ' ', (string) $data['name'] ) ) ); // wp_mail dělí Reply-To podle čárek, čárka ve jménu by přidala další adresu
	$mail  = array(
		'to'      => ekc_contact_recipients(),
		'subject' => '[' . $site . '] ' . $data['subject'] . ' – ' . $data['name'],
		'body'    => $body,
		'headers' => array(
			'Content-Type: text/plain; charset=UTF-8',
			'Reply-To: ' . ( '' !== $reply ? $reply . ' ' : '' ) . '<' . $data['email'] . '>',
		),
	);
	$mail = (array) apply_filters( 'ekc_contact_mail', $mail, $data );
	return (bool) wp_mail( $mail['to'], $mail['subject'], $mail['body'], $mail['headers'] );
}

/* -------------------------------------------------------------------------
 * Převod z Forminatoru
 * ---------------------------------------------------------------------- */

/**
 * Název formuláře Forminatoru jako předmět; názvy ve tvaru slugu (kontaktujte-nas) = '' (výchozí předmět).
 *
 * @param int $form_id ID formuláře.
 * @return string
 */
function ekc_contact_frmt_title( int $form_id ): string {
	$title = $form_id ? trim( (string) get_the_title( $form_id ) ) : '';
	return preg_match( '/^[a-z0-9_-]*$/', $title ) ? '' : $title;
}

/**
 * Tabulky Forminatoru existují?
 *
 * @return bool
 */
function ekc_contact_frmt_tables(): bool {
	global $wpdb;
	$table = $wpdb->prefix . 'frmt_form_entry';
	return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

/**
 * Záznamy Forminatoru, které ještě nejsou převedené.
 *
 * @return int
 */
function ekc_contact_frmt_pending(): int {
	global $wpdb;
	if ( ! ekc_contact_frmt_tables() ) {
		return 0;
	}
	// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$wpdb->prefix}frmt_form_entry e WHERE e.entry_type = 'custom-forms' AND NOT EXISTS (
			SELECT 1 FROM {$wpdb->postmeta} m WHERE m.meta_key = '_ekc_frmt_entry' AND m.meta_value = e.entry_id )"
	);
	// phpcs:enable
}

/**
 * Převedení zpráv z Forminatoru (idempotentní) a převzetí příjemce z notifikace.
 *
 * @return array{imported:int,pending:int}
 */
function ekc_contact_import_forminator(): array {
	global $wpdb;
	$imported = 0;
	if ( ekc_contact_frmt_tables() ) {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$entries = $wpdb->get_results(
			"SELECT e.entry_id, e.form_id, e.date_created FROM {$wpdb->prefix}frmt_form_entry e WHERE e.entry_type = 'custom-forms' AND NOT EXISTS (
				SELECT 1 FROM {$wpdb->postmeta} m WHERE m.meta_key = '_ekc_frmt_entry' AND m.meta_value = e.entry_id ) ORDER BY e.entry_id LIMIT 500"
		);
		foreach ( (array) $entries as $entry ) {
			$meta = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->prefix}frmt_form_entry_meta WHERE entry_id = %d", (int) $entry->entry_id ) );
			// phpcs:enable
			$data = array(
				'name'    => '',
				'email'   => '',
				'phone'   => '',
				'message' => '',
				'ip'      => '',
			);
			$extra = array();
			foreach ( (array) $meta as $row ) {
				$value = maybe_unserialize( $row->meta_value );
				$text  = is_array( $value ) ? implode( ', ', array_filter( array_map( 'strval', array_filter( $value, 'is_scalar' ) ) ) ) : (string) $value;
				$base  = (string) preg_replace( '/-\d+$/', '', (string) $row->meta_key );
				if ( '_forminator_user_ip' === $row->meta_key ) {
					$data['ip'] = $text;
				} elseif ( 'textarea' === $base ) {
					$data['message'] = trim( $data['message'] . "\n" . $text );
				} elseif ( in_array( $base, array( 'name', 'email', 'phone' ), true ) && '' === $data[ $base ] ) {
					$data[ $base ] = $text;
				} elseif ( '' !== $text && '_' !== substr( (string) $row->meta_key, 0, 1 ) ) {
					$extra[] = $row->meta_key . ': ' . $text;
				}
			}
			if ( $extra ) {
				$data['message'] .= "\n\n" . implode( "\n", $extra );
			}
			$title           = ekc_contact_frmt_title( (int) $entry->form_id );
			$data['subject'] = '' !== $title ? $title : (string) ekc_opt( 'contact_subject' );
			$data['page']    = '';
			$data['date']    = (string) $entry->date_created;
			$data['unread']  = false;
			$data['frmt_entry'] = (int) $entry->entry_id;
			if ( ekc_contact_store( $data ) ) {
				++$imported;
			}
		}
	}

	// Příjemce z notifikací Forminatoru, když nastavení je prázdné.
	if ( '' === trim( (string) ekc_opt( 'contact_to' ) ) ) {
		$found = array();
		foreach ( get_posts( array( 'post_type' => 'forminator_forms', 'post_status' => 'any', 'numberposts' => 20, 'fields' => 'ids' ) ) as $form_id ) {
			$meta = get_post_meta( (int) $form_id, 'forminator_form_meta', true );
			foreach ( (array) ( $meta['notifications'] ?? array() ) as $notification ) {
				foreach ( explode( ',', (string) ( $notification['recipients'] ?? '' ) ) as $email ) {
					$email = trim( $email );
					if ( is_email( $email ) ) {
						$found[ strtolower( $email ) ] = $email;
					}
				}
			}
		}
		if ( $found ) {
			ekc_opt_set( 'contact_to', implode( ', ', $found ) );
		}
	}

	return array(
		'imported' => $imported,
		'pending'  => ekc_contact_frmt_pending(),
	);
}

/**
 * Jsou všechny zprávy z Forminatoru převedené? (Pozůstatky Forminatoru se do té doby nenabízejí.)
 *
 * @return bool
 */
function ekc_contact_form_migrated(): bool {
	return 0 === ekc_contact_frmt_pending();
}

add_filter(
	'ekc_migrations',
	static function ( array $steps ): array {
		$steps['contact-form/forminator'] = static function (): bool {
			$result = ekc_contact_import_forminator();
			return 0 === $result['pending'];
		};
		return $steps;
	}
);

/* -------------------------------------------------------------------------
 * Údržba webu a Zdraví webu
 * ---------------------------------------------------------------------- */

ekc_maintenance_section(
	'contact',
	array(
		'title'  => 'Kontaktní formulář',
		'order'  => 45,
		'render' => 'ekc_contact_maint_render',
		'tasks'  => array(
			'import' => static function (): array {
				$r = ekc_contact_import_forminator();
				return array(
					'message' => sprintf( 'Převedeno zpráv z Forminatoru: %d. Zbývá: %d.', $r['imported'], $r['pending'] ),
					'type'    => $r['pending'] ? 'warning' : 'success',
				);
			},
		),
	)
);

/**
 * Sekce v Údržbě webu.
 *
 * @param string $id ID sekce.
 */
function ekc_contact_maint_render( string $id ): void {
	echo '<p>Zprávy: <a href="' . esc_url( admin_url( 'edit.php?post_type=ekc_message' ) ) . '">Zprávy z webu</a>'
		. ' (nepřečtené: ' . (int) ekc_contact_unread() . '). Příjemci: ' . esc_html( implode( ', ', ekc_contact_recipients() ) ) . '.</p>';
	$pending = ekc_contact_frmt_pending();
	if ( $pending ) {
		echo '<p>' . esc_html( ekc_plural( $pending, 'Z Forminatoru zbývá převést %d zprávu.', 'Z Forminatoru zbývá převést %d zprávy.', 'Z Forminatoru zbývá převést %d zpráv.' ) ) . '</p>';
		ekc_maintenance_button( $id, 'import', 'Převést zprávy z Forminatoru', 'Převést zbývající zprávy z Forminatoru do Zpráv z webu?' );
	}
}

add_filter(
	'ekc_health_rows',
	static function ( array $rows ): array {
		$count = ekc_contact_unread();
		$rows['Obsah']['Nepřečtené zprávy z webu'] = '<a href="' . esc_url( admin_url( 'edit.php?post_type=ekc_message' ) ) . '">' . (int) $count . '</a> ' . ekc_status_dot( $count ? 'warn' : 'ok' );
		return $rows;
	}
);
