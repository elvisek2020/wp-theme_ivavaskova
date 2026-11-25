<?php
/**
 * EKC module: reservations
 * Version: 1.0.0
 * Requires: common
 * Replaces: amelia, bookly-responsive-appointment-booking-tool, events-manager, simply-schedule-appointments
 * Summary: Přihlášky na skupinové termíny s kapacitou: formulář bez cizích služeb, potvrzení e-mailem, zrušení odkazem, přehled přihlášených s CSV a mazání údajů po termínu.
 */

defined( 'ABSPATH' ) || exit;

/*
 * - Termíny: typ obsahu ekc_event (menu Rezervace). Metadata _ekc_service (nabídka ze seznamu
 *   reservation_services), _ekc_start (místní čas Y-m-d H:i), _ekc_duration (min), _ekc_place,
 *   _ekc_capacity, _ekc_price, _ekc_note (veřejná poznámka), _ekc_closed (přihlašování uzavřené).
 *   Název termínu: prázdný = nabídka a datum; automatický název se po změně data nebo nabídky
 *   vygeneruje znovu, ručně zadaný zůstane. U termínu bez nabídky je název i veřejný popisek
 *   (formulář, e-maily). Termíny spravuje, kdo smí
 *   upravovat příspěvky (capability_type post); přihlášené (osobní údaje, CSV, rušení) vidí jen
 *   manage_options (filtr ekc_reservation_capability).
 * - Přihlášky: tabulka {prefix}ekc_reservations (id, event_id, name, email, phone, message, status
 *   confirmed|cancelled, token 32 hex, consent JSON se zněním souhlasů, created_at a cancelled_at
 *   v UTC). Vytvoří ji krok migrace reservations/table (a pojistka při prvním zápisu). IP adresa
 *   se neukládá; limit odeslání drží jen krátkodobý hash v transientu.
 * - Formulář: ekc_reservation_form( [service, limit, button] ), shortcode
 *   [ekc_rezervace sluzba="…" pocet="…" tlacitko="…"], předvýběr ?termin=ID. Budoucí termíny po
 *   nabídkách s volnými místy, plné a uzavřené jsou vidět, ale nejdou vybrat. Funguje bez JS:
 *   POST na stejnou adresu, chyby u polí s vyplněnými hodnotami, po úspěchu 303 na
 *   ?ekc_res_sent=N#ekc-res-N. Bez nonce (stránky v cache hostingu): podepsané časové razítko,
 *   skryté pole, nejméně 3 s na vyplnění, nejvýš 5 přihlášek za hodinu z jedné IP.
 * - Kapacita: MySQL GET_LOCK na termín, uvnitř zámku nové ověření stavu (uzavřeno, uzávěrka
 *   reservation_cutoff hodin před začátkem), spočítání potvrzených a vložení. Stejný e-mail na
 *   stejný termín podruhé = odpověď jako úspěch, nic nového se neuloží ani neodešle. U plného
 *   nebo uzavřeného termínu jen do 10 minut od první přihlášky (dvojí odeslání), aby odpověď
 *   neprozradila, kdo je na termín přihlášený.
 * - E-maily (wp_mail, prostý text): potvrzení klientovi (předmět a tělo z nastavení, zástupné
 *   {jmeno} {lekce} {datum} {cas} {misto} {cena} {poznamka} {zruseni} {web}; řádek, ve kterém
 *   jsou všechny zástupné prázdné, se vynechá), upozornění správci (reservation_to, prázdné =
 *   e-mail správce webu), upozornění na zrušení klientem.
 * - Zrušení odkazem z e-mailu (?ekc_res_cancel=ID&ekc_res_token=…): GET ukáže potvrzení, zruší až
 *   POST (náhled odkazu v poště nic nezruší), správci odejde e-mail. Jen do začátku termínu.
 * - Administrace: na termínu seznam přihlášených (zrušit, CSV, napsat všem), v přehledu sloupce
 *   Nabídka, Začátek, Obsazenost, Stav a pohledy Nadcházející / Proběhlé; řádky ve Zdraví webu;
 *   sekce v Údržbě webu.
 * - Osobní údaje: přihlášky se mažou reservation_retention dní po termínu (denní cron
 *   ekc_reservations_cleanup a stejná úloha v Údržbě webu) a s trvale smazaným termínem.
 *   Mažou se natrvalo, ne do karantény: jde o výmaz osobních údajů.
 *
 * Markup (výchozí, filtr ekc_reservation_form_html( $html, $args, $events )):
 *   div.ekc-res.ekc-form#ekc-res-N
 *     p.ekc-form__notice.ekc-form__notice--ok | --error (role=status / alert)
 *     p.ekc-res__summary, p.ekc-res__again > a          (po odeslání místo formuláře)
 *     p.ekc-res__empty                                   (žádné termíny / žádný volný)
 *     form.ekc-form__form.ekc-res__form[method=post]
 *       fieldset.ekc-res__fieldset > legend.ekc-form__label.ekc-res__legend
 *         div.ekc-res__group (jedna nabídka)
 *           h3.ekc-res__service                          (jen bez filtru service)
 *           ul.ekc-res__events > li.ekc-res__event.ekc-res__event--open|full|closed|cutoff (+ --selected)
 *             input.ekc-res__radio[type=radio][name=ekc_res_event] + label.ekc-res__label
 *               span.ekc-res__date, span.ekc-res__title (bez nabídky), span.ekc-res__place,
 *               span.ekc-res__price, span.ekc-res__free, span.ekc-res__note
 *         span.ekc-form__error
 *       div.ekc-form__grid > p.ekc-form__field.ekc-form__field--name|email|phone
 *         label.ekc-form__label (+ span.ekc-form__req) + input.ekc-form__input + span.ekc-form__error
 *       p.ekc-form__field.ekc-form__field--message > label + textarea.ekc-form__input
 *       p.ekc-form__field.ekc-form__check.ekc-form__field--privacy|informed
 *         input.ekc-form__checkbox + label.ekc-form__label (+ a) + span.ekc-form__error
 *       p.ekc-form__hp (skryté pole, inline styl)
 *       p.ekc-form__actions > button.ekc-form__submit.wp-element-button
 *   Vybraný termín bez JS: .ekc-res__radio:checked + .ekc-res__label (nebo :has()).
 *
 * Veřejné funkce: ekc_reservation_form( array $args ): string, ekc_reservation_events( array $args ): array,
 * ekc_reservation_event( int $id ): ?array, ekc_reservation_free( int $event_id ): int,
 * ekc_reservation_booked( int $event_id ): int, ekc_reservation_get( int $id ): ?array,
 * ekc_reservation_cancel( int $id, string $by ): bool, ekc_reservation_cancel_url( array $row ): string,
 * ekc_reservations_cleanup(): int.
 * Filtry: ekc_reservation_form_html, ekc_reservation_texts( $texts ), ekc_reservation_mail( $mail, $kind, $data )
 * (kind client|admin|cancel), ekc_reservation_is_spam( $spam, $values ), ekc_reservation_capability( 'manage_options' ).
 * Akce: ekc_reservation_created( $id, $data ), ekc_reservation_cancelled( $id, $by ) (by client|admin).
 */

/* -------------------------------------------------------------------------
 * Nastavení (sekce Rezervace)
 * ---------------------------------------------------------------------- */

ekc_settings_register(
	'reservation_services',
	array(
		'type'    => 'list',
		'default' => array(),
		'label'   => 'Nabídky',
		'help'    => 'Názvy lekcí a akcí, na které se dá přihlásit. U termínu se vybírají ze seznamu, formulář podle nich termíny seskupí (v tomto pořadí) a shortcode [ekc_rezervace sluzba="…"] ukáže jen jednu nabídku.',
		'section' => 'Rezervace',
		'group'   => 'Nabídky a upozornění',
	)
);
ekc_settings_register(
	'reservation_to',
	array(
		'type'    => 'text',
		'default' => '',
		'label'   => 'Upozornění na přihlášky',
		'help'    => 'E-maily oddělené čárkou. Prázdné = e-mail správce webu. Na první adresu může klient odpovědět na potvrzení.',
		'section' => 'Rezervace',
		'group'   => 'Nabídky a upozornění',
	)
);
ekc_settings_register(
	'reservation_phone',
	array(
		'type'    => 'select',
		'default' => 'optional',
		'label'   => 'Telefon',
		'options' => array(
			'optional' => 'Nepovinný',
			'required' => 'Povinný',
			'off'      => 'Nezobrazovat',
		),
		'section' => 'Rezervace',
		'group'   => 'Formulář',
	)
);
ekc_settings_register(
	'reservation_cutoff',
	array(
		'type'    => 'int',
		'default' => 2,
		'label'   => 'Uzávěrka přihlášek',
		'help'    => '0 = přihlásit se jde až do začátku termínu.',
		'min'     => 0,
		'max'     => 336,
		'unit'    => 'h před začátkem',
		'section' => 'Rezervace',
		'group'   => 'Formulář',
	)
);
ekc_settings_register(
	'reservation_success',
	array(
		'type'    => 'text',
		'default' => 'Přihláška je přijatá. Potvrzení s podrobnostmi přijde e-mailem.',
		'label'   => 'Text po odeslání',
		'section' => 'Rezervace',
		'group'   => 'Formulář',
	)
);
ekc_settings_register(
	'reservation_mail_subject',
	array(
		'type'    => 'text',
		'default' => 'Potvrzení přihlášky: {lekce}, {datum}',
		'label'   => 'Předmět',
		'section' => 'Rezervace',
		'group'   => 'E-mail klientovi',
	)
);
ekc_settings_register(
	'reservation_mail_body',
	array(
		'type'    => 'textarea',
		'default' => "Dobrý den, {jmeno},\n\npřihláška na {lekce} je přijatá.\n\nTermín: {datum}, {cas}\nMísto: {misto}\nCena: {cena}\nPoznámka: {poznamka}\n\nKdyž se termín nebude hodit, zrušte prosím přihlášku tímto odkazem, ať se místo uvolní dalším zájemcům:\n{zruseni}\n\n{web}",
		'label'   => 'Text',
		'help'    => 'Zástupné: {jmeno}, {lekce} (nabídka), {datum}, {cas} (od–do), {misto}, {cena}, {poznamka} (veřejná poznámka termínu), {zruseni} (odkaz pro zrušení), {web} (název a adresa webu). Řádek, ve kterém zůstanou jen prázdné zástupné, se vynechá.',
		'section' => 'Rezervace',
		'group'   => 'E-mail klientovi',
	)
);
ekc_settings_register(
	'reservation_privacy_url',
	array(
		'type'     => 'text',
		'default'  => '',
		'label'    => 'Zásady ochrany osobních údajů',
		'help'     => 'Adresa stránky v souhlasu se zpracováním údajů. Prázdné = zveřejněná stránka zásad z Nastavení → Soukromí.',
		'sanitize' => static fn( $value ): string => esc_url_raw( trim( (string) $value ) ),
		'section'  => 'Rezervace',
		'group'    => 'Souhlasy a osobní údaje',
	)
);
ekc_settings_register(
	'reservation_consent_text',
	array(
		'type'    => 'textarea',
		'default' => '',
		'label'   => 'Informovaný souhlas',
		'help'    => 'Text druhého povinného zaškrtávátka (např. o zdravotním stavu a průběhu lekce). Prázdné = ve formuláři není.',
		'section' => 'Rezervace',
		'group'   => 'Souhlasy a osobní údaje',
	)
);
ekc_settings_register(
	'reservation_consent_url',
	array(
		'type'     => 'text',
		'default'  => '',
		'label'    => 'Informovaný souhlas: odkaz',
		'help'     => 'Stránka s celým zněním (nepovinné).',
		'sanitize' => static fn( $value ): string => esc_url_raw( trim( (string) $value ) ),
		'section'  => 'Rezervace',
		'group'    => 'Souhlasy a osobní údaje',
	)
);
ekc_settings_register(
	'reservation_retention',
	array(
		'type'    => 'int',
		'default' => 365,
		'label'   => 'Mazání přihlášek',
		'help'    => 'Přihlášky (jméno, e-mail, telefon, poznámka, souhlasy) se tolik dní po termínu natrvalo smažou, automaticky jednou denně.',
		'min'     => 7,
		'max'     => 3650,
		'unit'    => 'dní po termínu',
		'section' => 'Rezervace',
		'group'   => 'Souhlasy a osobní údaje',
	)
);

const EKC_RESERVATION_MIN_SECONDS = 3;
const EKC_RESERVATION_RATE        = 5;  // přihlášek za hodinu z jedné IP
const EKC_RESERVATION_MAX_EVENTS  = 200; // nejvýš budoucích termínů v jednom výpisu

/* -------------------------------------------------------------------------
 * Texty a formátování
 * ---------------------------------------------------------------------- */

/**
 * Pevná mezera za jednopísmennými předložkami a spojkami (k, s, v, z, o, u, a, i).
 *
 * @param string $text Text.
 * @return string
 */
function ekc_reservation_typo( string $text ): string {
	return (string) preg_replace( '/(?<![\p{L}\p{N}])([ksvzouaiKSVZOUAI]) (?=\S)/u', "\$1\u{00A0}", $text );
}

/**
 * Texty formuláře a stránky zrušení (filtr ekc_reservation_texts).
 *
 * @return array<string,string>
 */
function ekc_reservation_texts(): array {
	$texts = array(
		'legend'         => 'Termín',
		'name'           => 'Jméno a příjmení',
		'email'          => 'E-mail',
		'phone'          => 'Telefon',
		'message'        => 'Poznámka',
		'button'         => 'Odeslat přihlášku',
		'free'           => 'Volná místa: %1$d z %2$d',
		'full'           => 'Obsazeno',
		'closed'         => 'Přihlašování uzavřeno',
		'cutoff'         => 'Přihlašování skončilo',
		'empty'          => 'Momentálně nejsou vypsané žádné termíny.',
		'none_open'      => 'Na vypsané termíny se teď přihlásit nejde.',
		'privacy'        => 'Souhlasím se zpracováním uvedených osobních údajů pro vyřízení přihlášky.',
		'privacy_link'   => 'Zásady ochrany osobních údajů',
		'informed_link'  => 'Celé znění',
		'again'          => 'Přihlásit se na další termín',
		'mail_failed'    => 'Potvrzovací e-mail se nepodařilo odeslat, přihláška je ale uložená.',
		'err_event'      => 'Vyberte prosím termín.',
		'err_full'       => 'Termín se mezitím zaplnil, vyberte prosím jiný.',
		'err_closed'     => 'Na tento termín se už přihlásit nejde, vyberte prosím jiný.',
		'err_name'       => 'Vyplňte prosím jméno a příjmení.',
		'err_email'      => 'Zadejte prosím platný e-mail.',
		'err_phone'      => 'Zadejte prosím platné telefonní číslo.',
		'err_privacy'    => 'Bez souhlasu se zpracováním údajů nejde přihlášku přijmout.',
		'err_informed'   => 'Potvrďte prosím informovaný souhlas.',
		'err_general'    => 'Přihlášku se nepodařilo odeslat, zkontrolujte prosím označená pole.',
		'err_rate'       => 'Z tohoto připojení přišlo příliš mnoho přihlášek, zkuste to prosím později.',
		'err_busy'       => 'Přihlášku se nepodařilo uložit, zkuste to prosím znovu.',
		'cancel_title'   => 'Zrušení přihlášky',
		'cancel_ask'     => 'Opravdu zrušit přihlášku na %s?',
		'cancel_button'  => 'Zrušit přihlášku',
		'cancel_done'    => 'Přihláška je zrušená, místo se uvolnilo pro další zájemce.',
		'cancel_past'    => 'Termín už začal nebo proběhl, přihlášku nejde zrušit.',
		'cancel_invalid' => 'Odkaz je neplatný nebo přihláška už neexistuje.',
		'back'           => 'Zpět na web',
	);
	$texts = array_map( 'strval', array_merge( $texts, (array) apply_filters( 'ekc_reservation_texts', $texts ) ) );
	return array_map( 'ekc_reservation_typo', $texts );
}

/**
 * Den česky s pevnými mezerami: „čt 15. 1. 2026“.
 *
 * @param int $ts Unix čas.
 * @return string
 */
function ekc_reservation_day( int $ts ): string {
	$days = array( 'ne', 'po', 'út', 'st', 'čt', 'pá', 'so' );
	$nb   = "\u{00A0}";
	return $days[ (int) wp_date( 'w', $ts ) ] . $nb . wp_date( 'j.', $ts ) . $nb . wp_date( 'n.', $ts ) . $nb . wp_date( 'Y', $ts );
}

/**
 * Datum a čas česky v pásmu webu: „čt 15. 1. 2026, 18:00“.
 *
 * @param int $ts Unix čas.
 * @return string
 */
function ekc_reservation_date( int $ts ): string {
	return ekc_reservation_day( $ts ) . ', ' . wp_date( 'G:i', $ts );
}

/**
 * Unix čas z uloženého začátku (místní čas webu Y-m-d H:i).
 *
 * @param string $start Začátek.
 * @return int 0 = neplatný.
 */
function ekc_reservation_ts( string $start ): int {
	$dt = '' !== $start ? DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $start, wp_timezone() ) : false;
	return ( $dt && $dt->format( 'Y-m-d H:i' ) === $start ) ? $dt->getTimestamp() : 0;
}

/**
 * Začátek z formuláře (datetime-local, Y-m-d H:i nebo 15. 1. 2026 18:00) → Y-m-d H:i.
 *
 * @param string $value Zadaná hodnota.
 * @return string '' = neplatné.
 */
function ekc_reservation_parse_start( string $value ): string {
	$value = trim( (string) preg_replace( '/\s+/', ' ', str_replace( 'T', ' ', $value ) ) );
	if ( preg_match( '/^(\d{4})-(\d{1,2})-(\d{1,2}) (\d{1,2}):(\d{2})(?::\d{2})?$/', $value, $m ) ) {
		list( , $y, $mo, $d, $h, $i ) = array_map( 'intval', $m );
	} elseif ( preg_match( '/^(\d{1,2})\. ?(\d{1,2})\. ?(\d{4}),? (\d{1,2}):(\d{2})$/', $value, $m ) ) {
		list( , $d, $mo, $y, $h, $i ) = array_map( 'intval', $m );
	} else {
		return '';
	}
	if ( ! checkdate( $mo, $d, $y ) || $h > 23 || $i > 59 ) {
		return '';
	}
	return sprintf( '%04d-%02d-%02d %02d:%02d', $y, $mo, $d, $h, $i );
}

/**
 * Oprávnění k osobním údajům přihlášených (seznam, CSV, rušení).
 *
 * @return string
 */
function ekc_reservation_cap(): string {
	return (string) apply_filters( 'ekc_reservation_capability', 'manage_options' );
}

/* -------------------------------------------------------------------------
 * Tabulka přihlášek
 * ---------------------------------------------------------------------- */

/**
 * Název tabulky.
 *
 * @return string
 */
function ekc_reservations_table(): string {
	global $wpdb;
	return $wpdb->prefix . 'ekc_reservations';
}

/**
 * Vytvoří tabulku (idempotentní).
 *
 * @return bool
 */
function ekc_reservations_install(): bool {
	global $wpdb;
	$table   = ekc_reservations_table();
	$charset = $wpdb->get_charset_collate();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- název tabulky z prefixu
	$wpdb->query(
		"CREATE TABLE IF NOT EXISTS {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			event_id BIGINT UNSIGNED NOT NULL,
			name VARCHAR(190) NOT NULL DEFAULT '',
			email VARCHAR(190) NOT NULL DEFAULT '',
			phone VARCHAR(40) NOT NULL DEFAULT '',
			message TEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'confirmed',
			token CHAR(32) NOT NULL DEFAULT '',
			consent TEXT NULL,
			created_at DATETIME NOT NULL,
			cancelled_at DATETIME NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY event_status (event_id, status),
			KEY created_at (created_at)
		) {$charset}"
	);
	$ok = $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
	if ( $ok ) {
		update_option( 'ekc_reservations_db', 1, false );
	}
	return $ok;
}

add_filter(
	'ekc_migrations',
	static function ( array $steps ): array {
		$steps['reservations/table'] = 'ekc_reservations_install';
		return $steps;
	}
);

/**
 * Existuje tabulka?
 *
 * @return bool
 */
function ekc_reservations_ready(): bool {
	return (bool) get_option( 'ekc_reservations_db' );
}

/**
 * Jedna přihláška.
 *
 * @param int $id ID.
 * @return array|null Řádek tabulky.
 */
function ekc_reservation_get( int $id ): ?array {
	if ( $id < 1 || ! ekc_reservations_ready() ) {
		return null;
	}
	global $wpdb;
	$table = ekc_reservations_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- název tabulky z prefixu
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
	return is_array( $row ) ? $row : null;
}

/**
 * Přihlášky termínu (potvrzené napřed, pak podle času přihlášení).
 *
 * @param int $event_id ID termínu.
 * @return array[]
 */
function ekc_reservation_rows( int $event_id ): array {
	if ( ! ekc_reservations_ready() ) {
		return array();
	}
	global $wpdb;
	$table = ekc_reservations_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- název tabulky z prefixu
	return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE event_id = %d ORDER BY status = 'confirmed' DESC, created_at ASC, id ASC", $event_id ), ARRAY_A );
}

/**
 * Počet potvrzených přihlášek termínu (vždy z databáze, bez cache).
 *
 * @param int $event_id ID termínu.
 * @return int
 */
function ekc_reservation_booked( int $event_id ): int {
	$map = ekc_reservation_booked_map( array( $event_id ) );
	return $map[ $event_id ] ?? 0;
}

/**
 * Potvrzené přihlášky pro více termínů jedním dotazem.
 *
 * @param int[] $ids ID termínů.
 * @return array<int,int> event_id => počet
 */
function ekc_reservation_booked_map( array $ids ): array {
	$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
	if ( ! $ids || ! ekc_reservations_ready() ) {
		return array();
	}
	global $wpdb;
	$table = ekc_reservations_table();
	$in    = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- tabulka z prefixu, placeholdery výše
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT event_id, COUNT(*) AS n FROM {$table} WHERE status = 'confirmed' AND event_id IN ({$in}) GROUP BY event_id", $ids ) );
	$out  = array();
	foreach ( (array) $rows as $row ) {
		$out[ (int) $row->event_id ] = (int) $row->n;
	}
	return $out;
}

/* -------------------------------------------------------------------------
 * Termíny
 * ---------------------------------------------------------------------- */

add_action(
	'init',
	static function () {
		register_post_type(
			'ekc_event',
			array(
				'labels'          => array(
					'name'               => 'Termíny',
					'singular_name'      => 'Termín',
					'menu_name'          => 'Rezervace',
					'all_items'          => 'Termíny',
					'add_new'            => 'Přidat termín',
					'add_new_item'       => 'Nový termín',
					'new_item'           => 'Nový termín',
					'edit_item'          => 'Upravit termín',
					'search_items'       => 'Hledat termíny',
					'not_found'          => 'Žádné termíny.',
					'not_found_in_trash' => 'V koši nejsou žádné termíny.',
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'show_in_rest'    => false,
				'menu_position'   => 27,
				'menu_icon'       => 'dashicons-calendar-alt',
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
				'rewrite'         => false,
				'query_var'       => false,
			)
		);
	}
);

/**
 * Data termínu.
 *
 * @param int      $event_id ID termínu.
 * @param int|null $booked   Počet potvrzených (když už je spočítaný), jinak se zjistí.
 * @return array|null id, title, service, label (nabídka, jinak název), status (post_status),
 *                    start (Y-m-d H:i), timestamp, end, day („čt 15. 1. 2026“), time („18:00–19:30“),
 *                    date („čt 15. 1. 2026, 18:00“), duration, place, capacity, booked,
 *                    free (volná místa, jen když jde přihlásit, jinak 0), price, note, closed,
 *                    state (open|full|closed|cutoff|past|draft).
 */
function ekc_reservation_event( int $event_id, ?int $booked = null ): ?array {
	$post = $event_id > 0 ? get_post( $event_id ) : null;
	if ( ! $post instanceof WP_Post || 'ekc_event' !== $post->post_type ) {
		return null;
	}
	$meta     = static fn( string $key ): string => (string) get_post_meta( $post->ID, $key, true );
	$start    = $meta( '_ekc_start' );
	$ts       = ekc_reservation_ts( $start );
	$duration = max( 0, (int) $meta( '_ekc_duration' ) );
	$capacity = max( 0, (int) $meta( '_ekc_capacity' ) );
	$booked   = null === $booked ? ekc_reservation_booked( $post->ID ) : max( 0, $booked );
	$service  = $meta( '_ekc_service' );
	$closed   = '' !== $meta( '_ekc_closed' ) && '0' !== $meta( '_ekc_closed' );
	$now      = time();
	$cutoff   = max( 0, (int) ekc_opt( 'reservation_cutoff' ) ) * HOUR_IN_SECONDS;

	if ( 'publish' !== $post->post_status || ! $ts ) {
		$state = 'draft';
	} elseif ( $ts <= $now ) {
		$state = 'past';
	} elseif ( $closed ) {
		$state = 'closed';
	} elseif ( $booked >= $capacity ) {
		$state = 'full';
	} elseif ( $ts - $cutoff <= $now ) {
		$state = 'cutoff';
	} else {
		$state = 'open';
	}
	$end = $ts && $duration ? $ts + $duration * MINUTE_IN_SECONDS : 0;

	return array(
		'id'        => (int) $post->ID,
		'title'     => (string) $post->post_title,
		'service'   => $service,
		'label'     => '' !== $service ? $service : (string) $post->post_title,
		'status'    => (string) $post->post_status,
		'start'     => $ts ? $start : '',
		'timestamp' => $ts,
		'end'       => $end,
		'day'       => $ts ? ekc_reservation_day( $ts ) : '',
		'time'      => $ts ? wp_date( 'G:i', $ts ) . ( $end ? '–' . wp_date( 'G:i', $end ) : '' ) : '',
		'date'      => $ts ? ekc_reservation_date( $ts ) : '',
		'duration'  => $duration,
		'place'     => $meta( '_ekc_place' ),
		'capacity'  => $capacity,
		'booked'    => $booked,
		'free'      => 'open' === $state ? max( 0, $capacity - $booked ) : 0,
		'price'     => $meta( '_ekc_price' ),
		'note'      => $meta( '_ekc_note' ),
		'closed'    => $closed,
		'state'     => $state,
	);
}

/**
 * Volná místa termínu (0, když je plný, uzavřený, po uzávěrce nebo proběhl).
 *
 * @param int $event_id ID termínu.
 * @return int
 */
function ekc_reservation_free( int $event_id ): int {
	$event = ekc_reservation_event( $event_id );
	return $event ? (int) $event['free'] : 0;
}

/**
 * Budoucí zveřejněné termíny (začátek po teď), od nejbližšího.
 *
 * @param array $args service (jen jedna nabídka), limit (výchozí 20), available (true = jen ty,
 *                    na které se dá přihlásit).
 * @return array[] Data termínů jako ekc_reservation_event().
 */
function ekc_reservation_events( array $args = array() ): array {
	$args  = wp_parse_args(
		$args,
		array(
			'service'   => '',
			'limit'     => 20,
			'available' => false,
		)
	);
	$limit = max( 1, min( EKC_RESERVATION_MAX_EVENTS, (int) $args['limit'] ) );
	global $wpdb;
	$service = trim( (string) $args['service'] );
	$sql     = "SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = '_ekc_start'";
	$params  = array();
	if ( '' !== $service ) {
		$sql     .= " JOIN {$wpdb->postmeta} v ON v.post_id = p.ID AND v.meta_key = '_ekc_service' AND v.meta_value = %s";
		$params[] = $service;
	}
	$sql     .= " WHERE p.post_type = 'ekc_event' AND p.post_status = 'publish' AND s.meta_value > %s ORDER BY s.meta_value ASC, p.ID ASC LIMIT %d";
	$params[] = wp_date( 'Y-m-d H:i' );
	$params[] = $args['available'] ? EKC_RESERVATION_MAX_EVENTS : $limit;
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- sestaveno výše s placeholdery
	$ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( $sql, $params ) ) );
	if ( ! $ids ) {
		return array();
	}
	_prime_post_caches( $ids, false, true );
	$booked = ekc_reservation_booked_map( $ids );
	$out    = array();
	foreach ( $ids as $id ) {
		$event = ekc_reservation_event( $id, $booked[ $id ] ?? 0 );
		if ( ! $event || ( $args['available'] && 'open' !== $event['state'] ) ) {
			continue;
		}
		$out[] = $event;
		if ( count( $out ) >= $limit ) {
			break;
		}
	}
	return $out;
}

/* -------------------------------------------------------------------------
 * Termín v administraci: box, uložení, přihlášení, přehled
 * ---------------------------------------------------------------------- */

add_filter(
	'enter_title_here',
	static fn( $text, $post ) => ( $post instanceof WP_Post && 'ekc_event' === $post->post_type ) ? 'Název pro přehled (nepovinný)' : $text,
	10,
	2
);

add_action(
	'add_meta_boxes_ekc_event',
	static function ( WP_Post $post ) {
		add_meta_box( 'ekc_event_details', 'Termín', 'ekc_reservation_box_details', 'ekc_event', 'normal', 'high' );
		if ( 'auto-draft' !== $post->post_status ) {
			$event = ekc_reservation_event( $post->ID );
			$title = $event ? sprintf( 'Přihlášení (%1$d z %2$d)', $event['booked'], $event['capacity'] ) : 'Přihlášení';
			add_meta_box( 'ekc_event_people', $title, 'ekc_reservation_box_people', 'ekc_event', 'normal', 'default' );
		}
	}
);

/**
 * Box s údaji termínu.
 *
 * @param WP_Post $post Termín.
 */
function ekc_reservation_box_details( WP_Post $post ): void {
	$v        = static fn( string $key ): string => (string) get_post_meta( $post->ID, $key, true );
	$services = array_map( 'strval', (array) ekc_opt( 'reservation_services' ) );
	$service  = $v( '_ekc_service' );
	$capacity = '' !== $v( '_ekc_capacity' ) ? (int) $v( '_ekc_capacity' ) : 10;
	$row      = static function ( string $key, string $label, string $field, string $help = '' ): void {
		printf(
			'<tr><th scope="row"><label for="ekc-ev-%1$s">%2$s</label></th><td>%3$s%4$s</td></tr>',
			esc_attr( $key ),
			esc_html( $label ),
			$field, // phpcs:ignore WordPress.Security.EscapeOutput -- sestaveno s escapováním níže
			'' !== $help ? '<p class="description">' . $help . '</p>' : '' // phpcs:ignore WordPress.Security.EscapeOutput
		);
	};
	wp_nonce_field( 'ekc_event_save', 'ekc_event_nonce' );
	echo '<table class="form-table" role="presentation"><tbody>';

	if ( $services ) {
		$list = $services;
		if ( '' !== $service && ! in_array( $service, $list, true ) ) {
			$list[] = $service;
		}
		$field = '<select id="ekc-ev-service" name="ekc_ev[service]"><option value="">— vyberte —</option>';
		foreach ( $list as $item ) {
			$field .= '<option value="' . esc_attr( $item ) . '"' . selected( $service, $item, false ) . '>' . esc_html( $item . ( in_array( $item, $services, true ) ? '' : ' (není v nastavení)' ) ) . '</option>';
		}
		$field .= '</select>';
	} else {
		$field = '<input type="text" id="ekc-ev-service" name="ekc_ev[service]" value="' . esc_attr( $service ) . '" class="regular-text">';
	}
	$help = current_user_can( 'manage_options' )
		? 'Seznam nabídek je v <a href="' . esc_url( ekc_page_url( 'ekc-nastaveni' ) . '#ekc-sec-rezervace' ) . '">nastavení rezervací</a>.'
		: '';
	$row( 'service', 'Nabídka', $field, $help );

	$row( 'start', 'Začátek', '<input type="datetime-local" id="ekc-ev-start" name="ekc_ev[start]" value="' . esc_attr( str_replace( ' ', 'T', $v( '_ekc_start' ) ) ) . '" required>', esc_html( 'Místní čas webu (' . wp_timezone_string() . ').' ) );
	$row( 'duration', 'Délka', '<input type="number" id="ekc-ev-duration" name="ekc_ev[duration]" value="' . esc_attr( $v( '_ekc_duration' ) ) . '" min="0" max="1440" step="5" class="small-text"> min' );
	$row( 'place', 'Místo', '<input type="text" id="ekc-ev-place" name="ekc_ev[place]" value="' . esc_attr( $v( '_ekc_place' ) ) . '" class="regular-text">' );
	$row( 'capacity', 'Kapacita', '<input type="number" id="ekc-ev-capacity" name="ekc_ev[capacity]" value="' . (int) $capacity . '" min="1" max="10000" step="1" class="small-text" required> míst' );
	$row( 'price', 'Cena', '<input type="text" id="ekc-ev-price" name="ekc_ev[price]" value="' . esc_attr( $v( '_ekc_price' ) ) . '" class="regular-text" placeholder="např. 450 Kč">' );
	$row( 'note', 'Veřejná poznámka', '<textarea id="ekc-ev-note" name="ekc_ev[note]" rows="2" class="large-text">' . esc_textarea( $v( '_ekc_note' ) ) . '</textarea>', 'Ukáže se u termínu ve formuláři a v potvrzovacím e-mailu (např. co si vzít s sebou).' );
	$row( 'closed', 'Přihlašování', '<label><input type="checkbox" id="ekc-ev-closed" name="ekc_ev[closed]" value="1"' . checked( '' !== $v( '_ekc_closed' ), true, false ) . '> Uzavřené</label>', 'Termín zůstane ve formuláři vidět, ale přihlásit se na něj nepůjde.' );
	echo '</tbody></table>';
	echo '<p class="description">Prázdný název = nabídka a datum, po změně data nebo nabídky se obnoví sám; ručně zadaný název zůstane. U termínu bez nabídky se název ukáže i ve formuláři a v e-mailech. Když se termín změní nebo zruší, dejte vědět přihlášeným (odkaz Napsat všem níže).</p>';
}

/**
 * Automatický název termínu z nabídky a začátku: „Nabídka – čt 15. 1. 2026, 18:00“.
 *
 * @param string $service Nabídka.
 * @param string $start   Začátek (Y-m-d H:i).
 * @return string '' = není z čeho sestavit.
 */
function ekc_reservation_auto_title( string $service, string $start ): string {
	$ts = ekc_reservation_ts( $start );
	return implode( ' – ', array_filter( array( $service, $ts ? ekc_reservation_date( $ts ) : '' ) ) );
}

add_filter(
	'wp_insert_post_data',
	static function ( array $data, array $postarr ): array {
		// Jen při uložení z editoru termínu (box s údaji).
		if ( 'ekc_event' !== ( $data['post_type'] ?? '' ) || empty( $postarr['ekc_ev'] ) || ! is_array( $postarr['ekc_ev'] ) ) {
			return $data;
		}
		// Prázdný název, nebo název shodný s automatickým z dosud uložených údajů (filtr běží před
		// save_post, meta jsou ještě stará) = sestavit znovu, aby po přesunu termínu nebo změně
		// nabídky neukazoval staré datum. Ručně zadaný název zůstane.
		$id    = (int) ( $postarr['ID'] ?? 0 );
		$norm  = static fn( string $s ): string => trim( (string) preg_replace( '/[\s\x{00A0}]+/u', ' ', html_entity_decode( $s, ENT_QUOTES, 'UTF-8' ) ) );
		$title = trim( (string) wp_unslash( (string) $data['post_title'] ) );
		$old   = $id ? (string) get_post_meta( $id, '_ekc_start', true ) : '';
		if ( '' !== $title ) {
			$auto = $id ? ekc_reservation_auto_title( (string) get_post_meta( $id, '_ekc_service', true ), $old ) : '';
			if ( '' === $auto || $norm( $title ) !== $norm( $auto ) ) {
				return $data;
			}
		}
		$in    = wp_unslash( $postarr['ekc_ev'] );
		$raw   = sanitize_text_field( is_scalar( $in['start'] ?? null ) ? (string) $in['start'] : '' );
		$start = ekc_reservation_parse_start( $raw );
		if ( '' === $start && '' !== $raw ) {
			$start = $old; // neplatný zápis nepřepíše platný začátek (stejně jako ekc_reservation_save_event)
		}
		$new = ekc_reservation_auto_title( sanitize_text_field( is_scalar( $in['service'] ?? null ) ? (string) $in['service'] : '' ), $start );
		if ( '' !== $new ) {
			$data['post_title'] = wp_slash( $new );
		}
		return $data;
	},
	10,
	2
);

add_action( 'save_post_ekc_event', 'ekc_reservation_save_event', 10, 2 );

/**
 * Uložení údajů termínu z boxu.
 *
 * @param int     $post_id ID termínu.
 * @param WP_Post $post    Termín.
 */
function ekc_reservation_save_event( int $post_id, WP_Post $post ): void {
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	$nonce = isset( $_POST['ekc_event_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ekc_event_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'ekc_event_save' ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$in   = isset( $_POST['ekc_ev'] ) && is_array( $_POST['ekc_ev'] ) ? wp_unslash( $_POST['ekc_ev'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- čistí se po položkách
	$text = static fn( string $key ): string => isset( $in[ $key ] ) && is_scalar( $in[ $key ] ) ? sanitize_text_field( (string) $in[ $key ] ) : '';
	$set  = static fn( string $key, $value ) => update_post_meta( $post_id, $key, is_string( $value ) ? wp_slash( $value ) : $value );

	$set( '_ekc_service', $text( 'service' ) );
	$start = ekc_reservation_parse_start( $text( 'start' ) );
	if ( '' !== $start || '' === $text( 'start' ) ) { // neplatný zápis nepřepíše platný začátek
		$set( '_ekc_start', $start );
	}
	$set( '_ekc_duration', max( 0, min( 1440, (int) $text( 'duration' ) ) ) );
	$set( '_ekc_place', $text( 'place' ) );
	$set( '_ekc_capacity', max( 1, min( 10000, (int) $text( 'capacity' ) ) ) );
	$set( '_ekc_price', $text( 'price' ) );
	$set( '_ekc_note', isset( $in['note'] ) && is_scalar( $in['note'] ) ? sanitize_textarea_field( (string) $in['note'] ) : '' );
	if ( ! empty( $in['closed'] ) ) {
		$set( '_ekc_closed', 1 );
	} else {
		delete_post_meta( $post_id, '_ekc_closed' );
	}
}

add_filter(
	'post_updated_messages',
	static function ( array $messages ): array {
		$messages['ekc_event'] = array(
			0  => '',
			1  => 'Termín je uložený.',
			4  => 'Termín je uložený.',
			6  => 'Termín je zveřejněný, přihlásit se na něj jde ve formuláři.',
			7  => 'Termín je uložený.',
			8  => 'Termín je odeslaný ke schválení.',
			9  => 'Termín je naplánovaný.',
			10 => 'Koncept termínu je uložený.',
		);
		return $messages;
	}
);

/**
 * Box s přihlášenými (jména a kontakty jen s oprávněním ekc_reservation_cap()).
 *
 * @param WP_Post $post Termín.
 */
function ekc_reservation_box_people( WP_Post $post ): void {
	$event = ekc_reservation_event( $post->ID );
	if ( ! $event ) {
		return;
	}
	if ( $event['booked'] > $event['capacity'] ) {
		echo '<p>' . ekc_status_dot( 'warn' ) . ' Kapacita je nižší než počet přihlášených.</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	if ( ! current_user_can( ekc_reservation_cap() ) ) {
		echo '<p>' . esc_html( sprintf( 'Přihlášeno %1$d z %2$d. Jména a kontakty vidí jen správce webu.', $event['booked'], $event['capacity'] ) ) . '</p>';
		return;
	}
	$rows = ekc_reservation_rows( $post->ID );
	if ( ! $rows ) {
		echo '<p>Zatím se nikdo nepřihlásil.</p>';
		return;
	}
	$emails = array();
	foreach ( $rows as $r ) {
		if ( 'confirmed' === $r['status'] && is_email( $r['email'] ) ) {
			$emails[] = rawurlencode( (string) $r['email'] );
		}
	}
	$csv = wp_nonce_url( admin_url( 'admin-post.php?action=ekc_reservations_csv&event=' . (int) $post->ID ), 'ekc_res_csv_' . (int) $post->ID );
	echo '<p><a class="button" href="' . esc_url( $csv ) . '">Stáhnout CSV</a>';
	if ( $emails ) {
		echo ' <a class="button" href="' . esc_url( 'mailto:?bcc=' . implode( ',', $emails ) ) . '">Napsat všem přihlášeným</a>';
	}
	echo '</p>';

	echo '<table class="widefat striped"><thead><tr><th>Jméno</th><th>Kontakt</th><th>Poznámka</th><th>Souhlasy</th><th>Přihlášeno</th><th></th></tr></thead><tbody>';
	foreach ( $rows as $r ) {
		$cancelled = 'cancelled' === $r['status'];
		$consent   = json_decode( (string) $r['consent'], true );
		$consent   = is_array( $consent ) ? $consent : array();
		$labels    = array();
		foreach ( array( 'privacy' => 'OÚ', 'informed' => 'informovaný' ) as $key => $label ) {
			if ( isset( $consent[ $key ]['text'] ) ) {
				$labels[] = '<span title="' . esc_attr( (string) $consent[ $key ]['text'] ) . '">' . esc_html( $label ) . '</span>';
			}
		}
		$phone = (string) $r['phone'];
		echo '<tr' . ( $cancelled ? ' style="opacity:.55"' : '' ) . '>';
		echo '<td>' . ( $cancelled ? '<s>' : '<strong>' ) . esc_html( (string) $r['name'] ) . ( $cancelled ? '</s>' : '</strong>' ) . '</td>';
		echo '<td><a href="mailto:' . esc_attr( (string) $r['email'] ) . '">' . esc_html( (string) $r['email'] ) . '</a>'
			. ( '' !== $phone ? '<br><a href="tel:' . esc_attr( (string) preg_replace( '/[^0-9+]/', '', $phone ) ) . '">' . esc_html( $phone ) . '</a>' : '' ) . '</td>';
		echo '<td>' . nl2br( esc_html( (string) $r['message'] ) ) . '</td>';
		echo '<td>' . implode( ', ', $labels ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escapováno výše
		echo '<td>' . esc_html( ekc_datetime( (int) strtotime( $r['created_at'] . ' UTC' ) ) ) . '</td>';
		echo '<td>';
		if ( $cancelled ) {
			echo esc_html( 'zrušeno ' . ( $r['cancelled_at'] ? ekc_datetime( (int) strtotime( $r['cancelled_at'] . ' UTC' ) ) : '' ) );
		} else {
			printf(
				'<button type="submit" form="ekc-res-admin-form" name="reservation" value="%1$d" class="button-link button-link-delete" onclick="return window.confirm(%2$s);">Zrušit</button>',
				(int) $r['id'],
				esc_attr( (string) wp_json_encode( 'Zrušit přihlášku: ' . $r['name'] . '? Klientovi se e-mail neposílá.' ) )
			);
		}
		echo '</td></tr>';
	}
	echo '</tbody></table>';

	// Formulář zrušení leží mimo formulář příspěvku (vnořené formuláře nejdou), tlačítka na něj míří atributem form.
	$event_id = (int) $post->ID;
	add_action(
		'admin_footer',
		static function () use ( $event_id ) {
			echo '<form id="ekc-res-admin-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" hidden>';
			echo '<input type="hidden" name="action" value="ekc_reservation_cancel"><input type="hidden" name="event" value="' . (int) $event_id . '">';
			wp_nonce_field( 'ekc_res_admin_' . $event_id, '_ekc_nonce' );
			echo '</form>';
		}
	);
}

add_action( 'admin_post_ekc_reservation_cancel', 'ekc_reservation_admin_cancel' );

/**
 * Zrušení přihlášky správcem (POST, nonce na termín).
 */
function ekc_reservation_admin_cancel(): void {
	$event_id = isset( $_POST['event'] ) ? absint( $_POST['event'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- ověřuje se hned níže
	check_admin_referer( 'ekc_res_admin_' . $event_id, '_ekc_nonce' );
	if ( ! current_user_can( ekc_reservation_cap() ) ) {
		wp_die( 'Na tuto akci nemáte oprávnění.', 403 );
	}
	$id  = isset( $_POST['reservation'] ) ? absint( $_POST['reservation'] ) : 0;
	$row = ekc_reservation_get( $id );
	$ok  = $row && (int) $row['event_id'] === $event_id && ekc_reservation_cancel( $id, 'admin' );
	wp_safe_redirect( add_query_arg( 'ekc_res_msg', $ok ? 'cancelled' : 'failed', admin_url( 'post.php?post=' . $event_id . '&action=edit' ) ) . '#ekc_event_people' );
	exit;
}

add_action(
	'admin_notices',
	static function () {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- jen zpráva po přesměrování
		$msg = isset( $_GET['ekc_res_msg'] ) ? sanitize_key( wp_unslash( $_GET['ekc_res_msg'] ) ) : '';
		if ( ! $screen || 'ekc_event' !== $screen->post_type || '' === $msg ) {
			return;
		}
		$notices = array(
			'cancelled' => array( 'success', 'Přihláška je zrušená (klientovi se e-mail neposílá).' ),
			'failed'    => array( 'error', 'Přihlášku se nepodařilo zrušit: už je zrušená nebo neexistuje.' ),
		);
		if ( isset( $notices[ $msg ] ) ) {
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $notices[ $msg ][0] ), esc_html( $notices[ $msg ][1] ) );
		}
	}
);
add_filter( 'removable_query_args', static fn( array $args ): array => array_merge( $args, array( 'ekc_res_msg' ) ) );

add_action( 'admin_post_ekc_reservations_csv', 'ekc_reservation_csv' );

/**
 * Přihlášení termínu jako CSV (středník, UTF-8 s BOM pro Excel).
 */
function ekc_reservation_csv(): void {
	$event_id = isset( $_GET['event'] ) ? absint( $_GET['event'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- ověřuje se hned níže
	check_admin_referer( 'ekc_res_csv_' . $event_id );
	if ( ! current_user_can( ekc_reservation_cap() ) ) {
		wp_die( 'Na tuto akci nemáte oprávnění.', 403 );
	}
	$event = ekc_reservation_event( $event_id );
	if ( ! $event ) {
		wp_die( 'Termín neexistuje.', 404 );
	}
	// Ochrana proti vzorcům v tabulkovém procesoru (CSV injection).
	$cell  = static fn( $value ): string => preg_match( '/^[=+\-@\t\r]/', (string) $value ) ? "'" . $value : (string) $value;
	$local = static fn( ?string $utc ): string => $utc ? (string) wp_date( 'Y-m-d H:i', (int) strtotime( $utc . ' UTC' ) ) : '';
	$name  = 'prihlasky-' . $event_id . ( $event['start'] ? '-' . substr( $event['start'], 0, 10 ) : '' ) . '.csv';

	nocache_headers();
	header( 'Content-Type: text/csv; charset=UTF-8' );
	header( 'Content-Disposition: attachment; filename="' . $name . '"' );
	header( 'X-Content-Type-Options: nosniff' );
	$out = fopen( 'php://output', 'w' );
	fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	fputcsv( $out, array( 'Termín', 'Jméno', 'E-mail', 'Telefon', 'Poznámka', 'Stav', 'Přihlášeno', 'Zrušeno', 'Souhlasy' ), ';', '"', '' );
	foreach ( ekc_reservation_rows( $event_id ) as $r ) {
		$consent = json_decode( (string) $r['consent'], true );
		fputcsv(
			$out,
			array_map(
				$cell,
				array(
					$event['label'] . ', ' . ( $event['start'] ? $event['start'] : '' ),
					$r['name'],
					$r['email'],
					$r['phone'],
					$r['message'],
					'cancelled' === $r['status'] ? 'zrušená' : 'potvrzená',
					$local( $r['created_at'] ),
					$local( $r['cancelled_at'] ),
					implode( ' | ', array_map( static fn( $c ) => (string) ( $c['text'] ?? '' ), is_array( $consent ) ? array_filter( $consent, 'is_array' ) : array() ) ),
				)
			),
			';',
			'"',
			''
		);
	}
	fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit;
}

// Trvale smazaný termín = smazat i jeho přihlášky (osobní údaje). Koš je zachová.
add_action(
	'before_delete_post',
	static function ( $post_id ) {
		if ( 'ekc_event' !== get_post_type( (int) $post_id ) || ! ekc_reservations_ready() ) {
			return;
		}
		global $wpdb;
		$wpdb->delete( ekc_reservations_table(), array( 'event_id' => (int) $post_id ), array( '%d' ) );
	}
);

// Přehled termínů: sloupce, řazení podle začátku, pohledy Nadcházející / Proběhlé.
add_filter(
	'manage_ekc_event_posts_columns',
	static fn( array $cols ): array => array(
		'cb'          => $cols['cb'] ?? '<input type="checkbox">',
		'title'       => 'Název',
		'ekc_service' => 'Nabídka',
		'ekc_start'   => 'Začátek',
		'ekc_booked'  => 'Obsazenost',
		'ekc_state'   => 'Stav',
	)
);
add_filter( 'manage_edit-ekc_event_sortable_columns', static fn( array $cols ): array => array_merge( $cols, array( 'ekc_start' => 'ekc_start' ) ) );

add_action(
	'manage_ekc_event_posts_custom_column',
	static function ( $column, $post_id ) {
		static $cache = array();
		$event        = $cache[ $post_id ] ??= ekc_reservation_event( (int) $post_id );
		if ( ! $event ) {
			return;
		}
		$states = array(
			'open'   => array( 'ok', 'Otevřeno' ),
			'full'   => array( 'warn', 'Obsazeno' ),
			'closed' => array( 'bad', 'Uzavřeno' ),
			'cutoff' => array( 'warn', 'Po uzávěrce' ),
			'past'   => array( '', 'Proběhlo' ),
			'draft'  => array( '', 'Neveřejný' ),
		);
		switch ( $column ) {
			case 'ekc_service':
				echo esc_html( $event['service'] );
				break;
			case 'ekc_start':
				echo esc_html( $event['day'] . ( $event['time'] ? ', ' . $event['time'] : '' ) );
				break;
			case 'ekc_booked':
				printf( '<a href="%1$s">%2$d / %3$d</a>', esc_url( (string) get_edit_post_link( (int) $post_id ) . '#ekc_event_people' ), (int) $event['booked'], (int) $event['capacity'] );
				break;
			case 'ekc_state':
				$state = $states[ $event['state'] ] ?? array( '', $event['state'] );
				echo ( '' !== $state[0] ? ekc_status_dot( $state[0] ) . ' ' : '' ) . esc_html( $state[1] ); // phpcs:ignore WordPress.Security.EscapeOutput
				break;
		}
	},
	10,
	2
);

add_action(
	'pre_get_posts',
	static function ( WP_Query $q ) {
		if ( ! is_admin() || ! $q->is_main_query() || 'ekc_event' !== $q->get( 'post_type' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- jen filtr výpisu
		$when    = isset( $_GET['ekc_when'] ) ? sanitize_key( wp_unslash( $_GET['ekc_when'] ) ) : '';
		$orderby = (string) $q->get( 'orderby' );
		if ( in_array( $when, array( 'upcoming', 'past' ), true ) ) {
			$q->set( 'ekc_when', $when );
		}
		if ( 'ekc_start' === $orderby ) {
			$q->set( 'ekc_sort', 'ASC' === strtoupper( (string) $q->get( 'order' ) ) ? 'ASC' : 'DESC' );
		} elseif ( '' === $orderby ) {
			$q->set( 'ekc_sort', 'upcoming' === $when ? 'ASC' : 'DESC' );
		}
	}
);

add_filter(
	'posts_clauses',
	static function ( array $clauses, WP_Query $q ): array {
		$sort = (string) $q->get( 'ekc_sort' );
		$when = (string) $q->get( 'ekc_when' );
		if ( '' === $sort && '' === $when ) {
			return $clauses;
		}
		global $wpdb;
		$now               = wp_date( 'Y-m-d H:i' );
		$clauses['join']  .= " LEFT JOIN {$wpdb->postmeta} ekc_s ON ( ekc_s.post_id = {$wpdb->posts}.ID AND ekc_s.meta_key = '_ekc_start' )";
		if ( 'upcoming' === $when ) {
			$clauses['where'] .= $wpdb->prepare( ' AND ekc_s.meta_value > %s', $now );
		} elseif ( 'past' === $when ) {
			$clauses['where'] .= $wpdb->prepare( " AND ekc_s.meta_value <> '' AND ekc_s.meta_value <= %s", $now );
		}
		if ( '' !== $sort ) {
			$clauses['orderby'] = 'ekc_s.meta_value ' . ( 'ASC' === $sort ? 'ASC' : 'DESC' ) . ", {$wpdb->posts}.ID DESC";
		}
		return $clauses;
	},
	10,
	2
);

add_filter(
	'views_edit-ekc_event',
	static function ( array $views ): array {
		global $wpdb;
		$now  = wp_date( 'Y-m-d H:i' );
		$base = "SELECT COUNT(*) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_ekc_start' WHERE p.post_type = 'ekc_event' AND p.post_status NOT IN ('trash', 'auto-draft')";
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- pevný základ dotazu
		$counts = array(
			'upcoming' => (int) $wpdb->get_var( $wpdb->prepare( $base . ' AND m.meta_value > %s', $now ) ),
			'past'     => (int) $wpdb->get_var( $wpdb->prepare( $base . " AND m.meta_value <> '' AND m.meta_value <= %s", $now ) ),
		);
		// phpcs:enable
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- jen zvýraznění pohledu
		$current = isset( $_GET['ekc_when'] ) ? sanitize_key( wp_unslash( $_GET['ekc_when'] ) ) : '';
		$labels  = array(
			'upcoming' => 'Nadcházející',
			'past'     => 'Proběhlé',
		);
		$ours    = array();
		foreach ( $labels as $key => $label ) {
			$ours[ 'ekc_' . $key ] = sprintf(
				'<a href="%1$s"%2$s>%3$s <span class="count">(%4$d)</span></a>',
				esc_url( admin_url( 'edit.php?post_type=ekc_event&ekc_when=' . $key ) ),
				$current === $key ? ' class="current" aria-current="page"' : '',
				esc_html( $label ),
				$counts[ $key ]
			);
		}
		if ( isset( $labels[ $current ] ) && isset( $views['all'] ) ) {
			$views['all'] = str_replace( array( ' class="current"', ' aria-current="page"' ), '', $views['all'] );
		}
		return array_merge( array_slice( $views, 0, 1, true ), $ours, array_slice( $views, 1, null, true ) );
	}
);

/* -------------------------------------------------------------------------
 * Formulář
 * ---------------------------------------------------------------------- */

/**
 * Podpis časového razítka formuláře.
 *
 * @param int $ts Čas vykreslení.
 * @return string
 */
function ekc_reservation_sign( int $ts ): string {
	return substr( wp_hash( 'ekc_res|' . $ts, 'nonce' ), 0, 20 );
}

/**
 * Adresa, na kterou se formulář odesílá (aktuální stránka bez parametrů formuláře).
 *
 * @return string
 */
function ekc_reservation_action_url(): string {
	$url = is_singular() ? (string) get_permalink() : home_url( add_query_arg( array() ) );
	return remove_query_arg( array( 'ekc_res_sent', 'ekc_res_event', 'ekc_res_nomail', 'termin' ), $url );
}

/**
 * Stav posledního odeslání v tomto požadavku (chyby a hodnoty).
 *
 * @param array|null $set Nový stav.
 * @return array{errors:array<string,string>,values:array,general:string}
 */
function ekc_reservation_request( ?array $set = null ): array {
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
 * Adresa zásad ochrany osobních údajů: nastavení, jinak zveřejněná stránka zásad.
 *
 * @return string
 */
function ekc_reservation_privacy_url(): string {
	$url = trim( (string) ekc_opt( 'reservation_privacy_url' ) );
	if ( '' !== $url ) {
		return $url;
	}
	$page = (int) get_option( 'wp_page_for_privacy_policy' );
	return ( $page && 'publish' === get_post_status( $page ) ) ? (string) get_permalink( $page ) : '';
}

/**
 * HTML formuláře s výběrem termínu.
 *
 * @param array $args service (jen jedna nabídka), limit (počet termínů, výchozí 20), button (text tlačítka).
 * @return string
 */
function ekc_reservation_form( array $args = array() ): string {
	static $n = 0;
	++$n;
	$t       = ekc_reservation_texts();
	$args    = wp_parse_args(
		$args,
		array(
			'service' => '',
			'limit'   => 20,
			'button'  => $t['button'],
		)
	);
	$service = sanitize_text_field( (string) $args['service'] );
	$anchor  = 'ekc-res-' . $n;
	$req     = ekc_reservation_request();
	// phpcs:disable WordPress.Security.NonceVerification -- veřejný formulář, jen zobrazení
	$sent   = isset( $_GET['ekc_res_sent'] ) && $n === (int) $_GET['ekc_res_sent'];
	$posted = isset( $_POST['ekc_res'] ) && $n === (int) $_POST['ekc_res'];
	$preset = isset( $_GET['termin'] ) ? absint( $_GET['termin'] ) : 0;
	$done   = $sent && isset( $_GET['ekc_res_event'] ) ? absint( $_GET['ekc_res_event'] ) : 0;
	$nomail = $sent && ! empty( $_GET['ekc_res_nomail'] );
	// phpcs:enable
	$errors = $posted ? $req['errors'] : array();
	$values = $posted ? $req['values'] : array();
	$html   = '<div class="ekc-res ekc-form" id="' . esc_attr( $anchor ) . '">';

	if ( $sent ) {
		$html .= '<p class="ekc-form__notice ekc-form__notice--ok" role="status">' . esc_html( ekc_reservation_typo( (string) ekc_opt( 'reservation_success' ) ) ) . '</p>';
		$event = $done ? ekc_reservation_event( $done ) : null;
		if ( $event && 'draft' !== $event['state'] ) {
			$html .= '<p class="ekc-res__summary">' . esc_html( $event['label'] . ', ' . $event['day'] . ', ' . $event['time'] ) . '</p>';
		}
		if ( $nomail ) {
			$html .= '<p class="ekc-form__notice ekc-form__notice--error" role="alert">' . esc_html( $t['mail_failed'] ) . '</p>';
		}
		$html .= '<p class="ekc-res__again"><a href="' . esc_url( ekc_reservation_action_url() . '#' . $anchor ) . '">' . esc_html( $t['again'] ) . '</a></p></div>';
		return (string) apply_filters( 'ekc_reservation_form_html', $html, $args, array() );
	}

	if ( $posted && ( $errors || $req['general'] ) ) {
		$html .= '<p class="ekc-form__notice ekc-form__notice--error" role="alert">' . esc_html( $req['general'] ?: $t['err_general'] ) . '</p>';
	}

	$events = ekc_reservation_events(
		array(
			'service' => $service,
			'limit'   => (int) $args['limit'],
		)
	);
	if ( ! $events ) {
		$html .= '<p class="ekc-res__empty">' . esc_html( $t['empty'] ) . '</p></div>';
		return (string) apply_filters( 'ekc_reservation_form_html', $html, $args, $events );
	}

	// Vybraný termín: odeslaný, z ?termin=ID, jinak jediný volný.
	$open     = array_values( array_filter( $events, static fn( $e ) => 'open' === $e['state'] ) );
	$selected = $posted ? (int) ( $values['event'] ?? 0 ) : $preset;
	if ( ! $selected && 1 === count( $open ) ) {
		$selected = $open[0]['id'];
	}

	// Skupiny podle nabídek v pořadí z nastavení, ostatní za nimi, bez nabídky nakonec.
	$order  = array_flip( array_map( 'strval', (array) ekc_opt( 'reservation_services' ) ) );
	$groups = array();
	foreach ( $events as $e ) {
		$groups[ $e['service'] ][] = $e;
	}
	uksort(
		$groups,
		static function ( $a, $b ) use ( $order ) {
			$rank = static fn( $k ) => '' === (string) $k ? PHP_INT_MAX : ( $order[ (string) $k ] ?? PHP_INT_MAX - 1 );
			return ( $rank( $a ) <=> $rank( $b ) ) ?: strcmp( (string) $a, (string) $b );
		}
	);

	$err_event = $errors['event'] ?? '';
	$html     .= '<form class="ekc-form__form ekc-res__form" method="post" action="' . esc_url( ekc_reservation_action_url() . '#' . $anchor ) . '">';
	$html     .= '<fieldset class="ekc-res__fieldset"' . ( $err_event ? ' aria-describedby="' . esc_attr( $anchor . '-event-err' ) . '"' : '' ) . '>';
	$html     .= '<legend class="ekc-form__label ekc-res__legend">' . esc_html( $t['legend'] ) . ( $open ? ' <span class="ekc-form__req" aria-hidden="true">*</span>' : '' ) . '</legend>';
	foreach ( $groups as $name => $items ) {
		$html .= '<div class="ekc-res__group">';
		if ( '' === $service && '' !== (string) $name ) {
			$html .= '<h3 class="ekc-res__service">' . esc_html( (string) $name ) . '</h3>';
		}
		$html .= '<ul class="ekc-res__events">';
		foreach ( $items as $e ) {
			$rid     = $anchor . '-ev-' . $e['id'];
			$is_open = 'open' === $e['state'];
			$check   = $is_open && $selected === $e['id'];
			$status  = match ( $e['state'] ) {
				'open'   => sprintf( $t['free'], $e['free'], $e['capacity'] ),
				'full'   => $t['full'],
				'cutoff' => $t['cutoff'],
				default  => $t['closed'],
			};
			$html .= '<li class="ekc-res__event ekc-res__event--' . esc_attr( $e['state'] ) . ( $check ? ' ekc-res__event--selected' : '' ) . '">';
			$html .= '<input type="radio" class="ekc-res__radio" id="' . esc_attr( $rid ) . '" name="ekc_res_event" value="' . (int) $e['id'] . '"'
				. ( $is_open ? ' required' : ' disabled' ) . ( $check ? ' checked' : '' ) . '>';
			$html .= '<label class="ekc-res__label" for="' . esc_attr( $rid ) . '">';
			$html .= '<span class="ekc-res__date">' . esc_html( $e['day'] . ', ' . $e['time'] ) . '</span>';
			if ( '' === $e['service'] && '' !== $e['title'] ) {
				$html .= ' <span class="ekc-res__title">' . esc_html( $e['title'] ) . '</span>';
			}
			foreach ( array( 'place', 'price' ) as $key ) {
				if ( '' !== $e[ $key ] ) {
					$html .= ' <span class="ekc-res__' . $key . '">' . esc_html( $e[ $key ] ) . '</span>';
				}
			}
			$html .= ' <span class="ekc-res__free">' . esc_html( $status ) . '</span>';
			if ( '' !== $e['note'] ) {
				$html .= ' <span class="ekc-res__note">' . esc_html( $e['note'] ) . '</span>';
			}
			$html .= '</label></li>';
		}
		$html .= '</ul></div>';
	}
	if ( $err_event ) {
		$html .= '<span class="ekc-form__error" id="' . esc_attr( $anchor . '-event-err' ) . '">' . esc_html( $err_event ) . '</span>';
	}
	$html .= '</fieldset>';

	if ( ! $open ) {
		$html .= '<p class="ekc-res__empty">' . esc_html( $t['none_open'] ) . '</p></form></div>';
		return (string) apply_filters( 'ekc_reservation_form_html', $html, $args, $events );
	}

	$field = static function ( string $key, string $type, bool $required, string $autocomplete ) use ( $t, $errors, $values, $anchor ): string {
		$id   = $anchor . '-' . $key;
		$err  = $errors[ $key ] ?? '';
		$attr = ' id="' . esc_attr( $id ) . '" name="ekc_res_' . esc_attr( $key ) . '" class="ekc-form__input" autocomplete="' . esc_attr( $autocomplete ) . '"'
			. ( $required ? ' required' : '' ) . ( $err ? ' aria-invalid="true" aria-describedby="' . esc_attr( $id ) . '-err"' : '' );
		$val  = (string) ( $values[ $key ] ?? '' );
		$out  = '<p class="ekc-form__field ekc-form__field--' . esc_attr( $key ) . '"><label class="ekc-form__label" for="' . esc_attr( $id ) . '">' . esc_html( $t[ $key ] )
			. ( $required ? ' <span class="ekc-form__req" aria-hidden="true">*</span>' : '' ) . '</label>';
		if ( 'textarea' === $type ) {
			$out .= '<textarea' . $attr . ' rows="3" maxlength="2000">' . esc_textarea( $val ) . '</textarea>';
		} else {
			$out .= '<input type="' . esc_attr( $type ) . '"' . $attr . ' value="' . esc_attr( $val ) . '" maxlength="' . ( 'tel' === $type ? 30 : 120 ) . '">';
		}
		if ( $err ) {
			$out .= '<span class="ekc-form__error" id="' . esc_attr( $id ) . '-err">' . esc_html( $err ) . '</span>';
		}
		return $out . '</p>';
	};
	$check = static function ( string $key, string $label_html ) use ( $errors, $values, $anchor ): string {
		$id  = $anchor . '-' . $key;
		$err = $errors[ $key ] ?? '';
		return '<p class="ekc-form__field ekc-form__check ekc-form__field--' . esc_attr( $key ) . '">'
			. '<input type="checkbox" id="' . esc_attr( $id ) . '" name="ekc_res_' . esc_attr( $key ) . '" value="1" class="ekc-form__checkbox" required'
			. ( ! empty( $values[ $key ] ) ? ' checked' : '' ) . ( $err ? ' aria-invalid="true" aria-describedby="' . esc_attr( $id ) . '-err"' : '' ) . '> '
			. '<label class="ekc-form__label" for="' . esc_attr( $id ) . '">' . $label_html . ' <span class="ekc-form__req" aria-hidden="true">*</span></label>'
			. ( $err ? '<span class="ekc-form__error" id="' . esc_attr( $id ) . '-err">' . esc_html( $err ) . '</span>' : '' )
			. '</p>';
	};
	$link = static fn( string $url, string $text ): string => '' !== $url ? ' <a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $text ) . '</a>' : '';

	$phone = (string) ekc_opt( 'reservation_phone' );
	$ts    = time();
	$html .= '<div class="ekc-form__grid">';
	$html .= $field( 'name', 'text', true, 'name' );
	$html .= $field( 'email', 'email', true, 'email' );
	if ( 'off' !== $phone ) {
		$html .= $field( 'phone', 'tel', 'required' === $phone, 'tel' );
	}
	$html .= '</div>';
	$html .= $field( 'message', 'textarea', false, 'off' );
	$html .= $check( 'privacy', esc_html( $t['privacy'] ) . $link( ekc_reservation_privacy_url(), $t['privacy_link'] ) );
	$informed = trim( (string) ekc_opt( 'reservation_consent_text' ) );
	if ( '' !== $informed ) {
		$html .= $check( 'informed', nl2br( esc_html( ekc_reservation_typo( $informed ) ) ) . $link( (string) ekc_opt( 'reservation_consent_url' ), $t['informed_link'] ) );
	}
	$html .= '<p class="ekc-form__hp" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden"><label>Web <input type="text" name="ekc_res_web" tabindex="-1" autocomplete="off" value=""></label></p>';
	$html .= '<input type="hidden" name="ekc_res" value="' . (int) $n . '">';
	$html .= '<input type="hidden" name="ekc_res_ts" value="' . (int) $ts . '">';
	$html .= '<input type="hidden" name="ekc_res_sig" value="' . esc_attr( ekc_reservation_sign( $ts ) ) . '">';
	$html .= '<p class="ekc-form__actions"><button type="submit" class="ekc-form__submit wp-element-button">' . esc_html( (string) $args['button'] ) . '</button></p>';
	$html .= '</form></div>';

	return (string) apply_filters( 'ekc_reservation_form_html', $html, $args, $events );
}

add_shortcode(
	'ekc_rezervace',
	static function ( $atts ): string {
		$atts = is_array( $atts ) ? $atts : array();
		$args = array();
		foreach ( array( 'sluzba' => 'service', 'service' => 'service', 'pocet' => 'limit', 'limit' => 'limit', 'tlacitko' => 'button', 'button' => 'button' ) as $from => $to ) {
			if ( isset( $atts[ $from ] ) && '' !== (string) $atts[ $from ] ) {
				$args[ $to ] = 'limit' === $to ? (int) $atts[ $from ] : sanitize_text_field( (string) $atts[ $from ] );
			}
		}
		return ekc_reservation_form( $args );
	}
);

/* -------------------------------------------------------------------------
 * Zpracování přihlášky
 * ---------------------------------------------------------------------- */

add_action( 'template_redirect', 'ekc_reservation_handle', 5 );

/**
 * Zpracuje odeslaný formulář.
 */
function ekc_reservation_handle(): void {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! isset( $_POST['ekc_res'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	// phpcs:disable WordPress.Security.NonceVerification -- veřejný formulář v cache, ochrana podpisem razítka
	$t      = ekc_reservation_texts();
	$n      = (int) $_POST['ekc_res'];
	$get    = static fn( string $k ): string => isset( $_POST[ 'ekc_res_' . $k ] ) && is_scalar( $_POST[ 'ekc_res_' . $k ] ) ? trim( (string) wp_unslash( $_POST[ 'ekc_res_' . $k ] ) ) : '';
	$cut    = static fn( string $s, int $max ): string => function_exists( 'mb_substr' ) ? mb_substr( $s, 0, $max ) : substr( $s, 0, $max );
	$values = array(
		'event'    => absint( $get( 'event' ) ),
		'name'     => $cut( sanitize_text_field( $get( 'name' ) ), 190 ),
		'email'    => $cut( sanitize_text_field( $get( 'email' ) ), 190 ), // po ověření sanitize_email; chybný zůstane ve formuláři k opravě
		'phone'    => $cut( sanitize_text_field( $get( 'phone' ) ), 40 ),
		'message'  => $cut( sanitize_textarea_field( $get( 'message' ) ), 2000 ),
		'privacy'  => '' !== $get( 'privacy' ),
		'informed' => '' !== $get( 'informed' ),
	);
	$ts     = (int) $get( 'ts' );
	// phpcs:enable

	$url  = ekc_reservation_action_url();
	$done = static function ( int $event_id, bool $mailed = true ) use ( $url, $n ): void {
		$query = array(
			'ekc_res_sent'   => $n,
			'ekc_res_event'  => $event_id ? $event_id : false,
			'ekc_res_nomail' => $mailed ? false : 1,
		);
		wp_safe_redirect( add_query_arg( $query, $url ) . '#ekc-res-' . $n, 303 );
		exit;
	};
	$fail = static function ( array $errors, string $general = '' ) use ( $values ): void {
		ekc_reservation_request(
			array(
				'errors'  => $errors,
				'values'  => $values,
				'general' => $general,
			)
		);
	};

	// Spam: tváří se jako úspěch, nic se neuloží ani neodešle.
	$spam = '' !== $get( 'web' )
		|| ! hash_equals( ekc_reservation_sign( $ts ), $get( 'sig' ) )
		|| time() - $ts < EKC_RESERVATION_MIN_SECONDS
		|| preg_match_all( '#https?://|www\.#i', $values['message'] ) > 3;
	if ( apply_filters( 'ekc_reservation_is_spam', $spam, $values ) ) {
		$done( 0 );
	}

	$errors = array();
	$event  = $values['event'] ? ekc_reservation_event( $values['event'] ) : null;
	if ( ! $event || in_array( $event['state'], array( 'draft', 'past' ), true ) ) {
		$errors['event'] = $t['err_event'];
	} elseif ( 'open' !== $event['state'] && ! ( is_email( $values['email'] ) && ekc_reservation_has_email( (int) $event['id'], sanitize_email( $values['email'] ), 10 * MINUTE_IN_SECONDS ) ) ) {
		// Dvojí odeslání na poslední volné místo: kdo se na termín přihlásil před chvílí (10 min), dojde
		// až k ekc_reservation_insert() a dostane odpověď jako při úspěchu. Starší přihlášky se
		// nezohledňují, aby z odpovědi u plného termínu nešlo zjistit, kdo je na lekci přihlášený.
		$errors['event'] = 'full' === $event['state'] ? $t['err_full'] : $t['err_closed'];
	}
	$length = static fn( string $s ): int => function_exists( 'mb_strlen' ) ? mb_strlen( $s ) : strlen( $s );
	if ( $length( $values['name'] ) < 2 ) {
		$errors['name'] = $t['err_name'];
	}
	if ( ! is_email( $values['email'] ) ) {
		$errors['email'] = $t['err_email'];
	}
	$phone_mode = (string) ekc_opt( 'reservation_phone' );
	if ( 'off' === $phone_mode ) {
		$values['phone'] = '';
	} elseif ( ( '' !== $values['phone'] || 'required' === $phone_mode ) && ! preg_match( '/^[0-9 +()\/.-]{6,30}$/', $values['phone'] ) ) {
		$errors['phone'] = $t['err_phone'];
	}
	if ( ! $values['privacy'] ) {
		$errors['privacy'] = $t['err_privacy'];
	}
	$informed = trim( (string) ekc_opt( 'reservation_consent_text' ) );
	if ( '' !== $informed && ! $values['informed'] ) {
		$errors['informed'] = $t['err_informed'];
	}
	if ( $errors ) {
		$fail( $errors );
		return; // stránka se vykreslí s chybami a vyplněnými hodnotami
	}
	$values['email'] = sanitize_email( $values['email'] );

	$rate = 'ekc_res_rate_' . md5( ekc_client_ip() . '|' . wp_salt( 'nonce' ) );
	$sent = (int) get_transient( $rate );
	if ( $sent >= EKC_RESERVATION_RATE ) {
		$fail( array(), $t['err_rate'] );
		return;
	}
	set_transient( $rate, $sent + 1, HOUR_IN_SECONDS );

	// Znění souhlasů v okamžiku přihlášení.
	$consent = array(
		'privacy' => array(
			'text' => $t['privacy'],
			'url'  => ekc_reservation_privacy_url(),
		),
	);
	if ( '' !== $informed ) {
		$consent['informed'] = array(
			'text' => $informed,
			'url'  => (string) ekc_opt( 'reservation_consent_url' ),
		);
	}
	$values['consent'] = (string) wp_json_encode( $consent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

	$result = ekc_reservation_insert( (int) $event['id'], $values );
	if ( 'duplicate' === $result ) {
		$done( (int) $event['id'] );
	}
	if ( ! is_int( $result ) ) {
		$map = array(
			'full'   => array( array( 'event' => $t['err_full'] ), '' ),
			'closed' => array( array( 'event' => $t['err_closed'] ), '' ),
		);
		$fail( ...( $map[ $result ] ?? array( array(), $t['err_busy'] ) ) );
		return;
	}

	$row   = ekc_reservation_get( $result );
	$event = ekc_reservation_event( (int) $event['id'] ); // obsazenost po vložení
	if ( ! $row || ! $event ) {
		$done( 0 );
	}
	$data   = array_merge( $row, array( 'event' => $event ) );
	$mailed = ekc_reservation_mail_client( $row, $event );
	ekc_reservation_mail_admin( $row, $event, 'admin' );
	do_action( 'ekc_reservation_created', $result, $data );
	$done( (int) $event['id'], $mailed );
}

/**
 * Název zámku termínu (zámky MySQL platí pro celý server, proto i databáze a prefix).
 *
 * @param int $event_id ID termínu.
 * @return string Nejvýš 64 znaků.
 */
function ekc_reservation_lock( int $event_id ): string {
	global $wpdb;
	return 'ekc_res_' . substr( md5( DB_NAME . '|' . $wpdb->prefix ), 0, 12 ) . '_' . $event_id;
}

/**
 * Má e-mail na termín potvrzenou přihlášku?
 *
 * @param int    $event_id ID termínu.
 * @param string $email    E-mail (po sanitize_email).
 * @param int    $within   Jen přihláška vytvořená za posledních N sekund (0 = kdykoli).
 * @return bool
 */
function ekc_reservation_has_email( int $event_id, string $email, int $within = 0 ): bool {
	global $wpdb;
	if ( $event_id <= 0 || '' === $email || ! ekc_reservations_ready() ) {
		return false;
	}
	$table = ekc_reservations_table();
	if ( $within > 0 ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- název tabulky z prefixu
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE event_id = %d AND email = %s AND status = 'confirmed' AND created_at >= %s LIMIT 1", $event_id, $email, gmdate( 'Y-m-d H:i:s', time() - $within ) ) );
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- název tabulky z prefixu
	return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE event_id = %d AND email = %s AND status = 'confirmed' LIMIT 1", $event_id, $email ) );
}

/**
 * Atomické vložení přihlášky: zámek na termín, nové ověření stavu a obsazenosti, vložení.
 *
 * @param int   $event_id ID termínu.
 * @param array $data     name, email, phone, message, consent (JSON).
 * @return int|string ID přihlášky, nebo kód: full, closed, duplicate, busy, db.
 */
function ekc_reservation_insert( int $event_id, array $data ): int|string {
	global $wpdb;
	if ( ! ekc_reservations_ready() && ! ekc_reservations_install() ) {
		return 'db';
	}
	$table = ekc_reservations_table();
	$lock  = ekc_reservation_lock( $event_id );
	if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $lock, 10 ) ) ) {
		return 'busy';
	}
	try {
		$event = ekc_reservation_event( $event_id ); // obsazenost se počítá znovu uvnitř zámku
		if ( ! $event ) {
			return 'closed';
		}
		// Duplicita před stavem: když první odeslání obsadilo poslední místo, druhé není „plno“.
		if ( ekc_reservation_has_email( $event_id, (string) $data['email'] ) ) {
			return 'duplicate';
		}
		if ( 'open' !== $event['state'] ) {
			return 'full' === $event['state'] ? 'full' : 'closed';
		}
		$ok = $wpdb->insert(
			$table,
			array(
				'event_id'   => $event_id,
				'name'       => (string) $data['name'],
				'email'      => (string) $data['email'],
				'phone'      => (string) ( $data['phone'] ?? '' ),
				'message'    => (string) ( $data['message'] ?? '' ),
				'status'     => 'confirmed',
				'token'      => bin2hex( random_bytes( 16 ) ),
				'consent'    => (string) ( $data['consent'] ?? '' ),
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		return $ok ? (int) $wpdb->insert_id : 'db';
	} finally {
		$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
	}
}

/**
 * Zruší potvrzenou přihlášku.
 *
 * @param int    $id ID přihlášky.
 * @param string $by client|admin.
 * @return bool Zrušeno teď (false = neexistuje nebo už byla zrušená).
 */
function ekc_reservation_cancel( int $id, string $by = 'admin' ): bool {
	if ( ! ekc_reservations_ready() ) {
		return false;
	}
	global $wpdb;
	$table = ekc_reservations_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- název tabulky z prefixu
	$changed = (int) $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = 'cancelled', cancelled_at = %s WHERE id = %d AND status = 'confirmed'", gmdate( 'Y-m-d H:i:s' ), $id ) );
	if ( $changed ) {
		do_action( 'ekc_reservation_cancelled', $id, $by );
	}
	return $changed > 0;
}

/* -------------------------------------------------------------------------
 * E-maily
 * ---------------------------------------------------------------------- */

/**
 * Příjemci upozornění z nastavení, jinak e-mail správce.
 *
 * @return string[]
 */
function ekc_reservation_recipients(): array {
	$list = array_filter( array_map( 'trim', explode( ',', (string) ekc_opt( 'reservation_to' ) ) ), 'is_email' );
	return $list ? array_values( $list ) : array( (string) get_option( 'admin_email' ) );
}

/**
 * Adresa pro zrušení přihlášky (odkaz v e-mailu).
 *
 * @param array $row Řádek přihlášky.
 * @return string
 */
function ekc_reservation_cancel_url( array $row ): string {
	return add_query_arg(
		array(
			'ekc_res_cancel' => (int) $row['id'],
			'ekc_res_token'  => (string) $row['token'],
		),
		home_url( '/' )
	);
}

/**
 * Hodnoty zástupných v e-mailu klientovi.
 *
 * @param array $row   Přihláška.
 * @param array $event Termín.
 * @return array<string,string>
 */
function ekc_reservation_vars( array $row, array $event ): array {
	return array(
		'{jmeno}'    => (string) $row['name'],
		'{lekce}'    => (string) $event['label'],
		'{datum}'    => (string) $event['day'],
		'{cas}'      => (string) $event['time'],
		'{misto}'    => (string) $event['place'],
		'{cena}'     => (string) $event['price'],
		'{poznamka}' => (string) $event['note'],
		'{zruseni}'  => ekc_reservation_cancel_url( $row ),
		'{web}'      => wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ) . "\n" . home_url( '/' ),
	);
}

/**
 * Doplní zástupné; řádek, ve kterém jsou všechny zástupné prázdné, vynechá.
 *
 * @param string $template Šablona z nastavení.
 * @param array  $vars     Zástupné => hodnota.
 * @return string
 */
function ekc_reservation_fill( string $template, array $vars ): string {
	$out = array();
	foreach ( explode( "\n", str_replace( "\r", '', ekc_reservation_typo( $template ) ) ) as $line ) {
		if ( preg_match_all( '/\{[a-z]+\}/', $line, $m ) ) {
			$known = array_values( array_intersect( $m[0], array_keys( $vars ) ) );
			if ( $known && '' === trim( implode( '', array_map( static fn( $k ) => (string) $vars[ $k ], $known ) ) ) ) {
				continue;
			}
		}
		$out[] = strtr( $line, $vars );
	}
	return trim( (string) preg_replace( "/\n{3,}/", "\n\n", implode( "\n", $out ) ) );
}

/**
 * Odešle e-mail přes filtr ekc_reservation_mail (prázdné „to“ = neposílat).
 *
 * @param array  $mail to, subject, body, headers.
 * @param string $kind client|admin|cancel.
 * @param array  $data Přihláška a termín.
 * @return bool
 */
function ekc_reservation_send( array $mail, string $kind, array $data ): bool {
	$mail = (array) apply_filters( 'ekc_reservation_mail', $mail, $kind, $data );
	if ( empty( $mail['to'] ) ) {
		return false;
	}
	return (bool) wp_mail( $mail['to'], (string) ( $mail['subject'] ?? '' ), (string) ( $mail['body'] ?? '' ), $mail['headers'] ?? array() );
}

/**
 * Potvrzení klientovi (předmět a text z nastavení).
 *
 * @param array $row   Přihláška.
 * @param array $event Termín.
 * @return bool
 */
function ekc_reservation_mail_client( array $row, array $event ): bool {
	$vars    = ekc_reservation_vars( $row, $event );
	$subject = str_replace( array( "\r", "\n" ), ' ', ekc_reservation_fill( (string) ekc_opt( 'reservation_mail_subject' ), $vars ) );
	$mail    = array(
		'to'      => (string) $row['email'],
		'subject' => $subject,
		'body'    => ekc_reservation_fill( (string) ekc_opt( 'reservation_mail_body' ), $vars ) . "\n",
		'headers' => array(
			'Content-Type: text/plain; charset=UTF-8',
			'Reply-To: ' . ekc_reservation_recipients()[0],
		),
	);
	return ekc_reservation_send( $mail, 'client', array_merge( $row, array( 'event' => $event ) ) );
}

/**
 * Upozornění správci na novou (kind admin) nebo zrušenou (kind cancel) přihlášku.
 *
 * @param array  $row   Přihláška.
 * @param array  $event Termín.
 * @param string $kind  admin|cancel.
 * @return bool
 */
function ekc_reservation_mail_admin( array $row, array $event, string $kind = 'admin' ): bool {
	$site   = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
	$cancel = 'cancel' === $kind;
	$when   = $event['day'] . ( $event['time'] ? ', ' . $event['time'] : '' );
	$body   = ( $cancel ? 'Přihláška byla zrušena odkazem z potvrzovacího e-mailu.' : 'Nová přihláška z webu ' . $site . '.' ) . "\n\n"
		. 'Termín: ' . $event['label'] . ', ' . $when . "\n"
		. 'Obsazeno: ' . $event['booked'] . ' z ' . $event['capacity'] . "\n\n"
		. 'Jméno: ' . $row['name'] . "\n"
		. 'E-mail: ' . $row['email'] . "\n"
		. ( '' !== (string) $row['phone'] ? 'Telefon: ' . $row['phone'] . "\n" : '' )
		. ( '' !== (string) $row['message'] ? "\nPoznámka:\n" . $row['message'] . "\n" : '' )
		. "\n-- \n"
		. 'Přihlášení u termínu: ' . admin_url( 'post.php?post=' . (int) $event['id'] . '&action=edit' ) . "#ekc_event_people\n";
	$reply  = trim( (string) preg_replace( '/\s+/', ' ', str_replace( array( "\r", "\n", '"', '<', '>', ',', ';' ), ' ', (string) $row['name'] ) ) ); // wp_mail dělí Reply-To podle čárek, čárka ve jménu by přidala další adresu
	$mail   = array(
		'to'      => ekc_reservation_recipients(),
		'subject' => '[' . $site . '] ' . ( $cancel ? 'Zrušená přihláška' : 'Nová přihláška' ) . ': ' . $event['label'] . ', ' . $event['day'] . ' – ' . $row['name'],
		'body'    => $body,
		'headers' => array(
			'Content-Type: text/plain; charset=UTF-8',
			'Reply-To: ' . ( '' !== $reply ? $reply . ' ' : '' ) . '<' . $row['email'] . '>',
		),
	);
	return ekc_reservation_send( $mail, $kind, array_merge( $row, array( 'event' => $event ) ) );
}

/* -------------------------------------------------------------------------
 * Zrušení odkazem z e-mailu
 * ---------------------------------------------------------------------- */

add_action( 'template_redirect', 'ekc_reservation_cancel_page', 1 );

/**
 * Stránka zrušení: GET ukáže otázku, POST zruší a přesměruje zpět (303).
 */
function ekc_reservation_cancel_page(): void {
	// phpcs:disable WordPress.Security.NonceVerification -- odkaz z e-mailu, ověřený tokenem
	if ( ! isset( $_GET['ekc_res_cancel'] ) ) {
		return;
	}
	$id    = absint( $_GET['ekc_res_cancel'] );
	$token = isset( $_GET['ekc_res_token'] ) ? strtolower( (string) preg_replace( '/[^a-fA-F0-9]/', '', (string) wp_unslash( $_GET['ekc_res_token'] ) ) ) : '';
	$post  = 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_POST['ekc_res_confirm'] );
	// phpcs:enable
	$t     = ekc_reservation_texts();
	$row   = $id ? ekc_reservation_get( $id ) : null;
	$valid = $row && 32 === strlen( $token ) && 32 === strlen( (string) $row['token'] ) && hash_equals( (string) $row['token'], $token );

	nocache_headers();
	header( 'X-Robots-Tag: noindex, nofollow' );
	header( 'Referrer-Policy: no-referrer' );
	$page = static function ( string $body, int $code = 200 ) use ( $t ): void {
		$back = '<p><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html( $t['back'] ) . '</a></p>';
		wp_die( '<h1>' . esc_html( $t['cancel_title'] ) . '</h1>' . $body . $back, esc_html( $t['cancel_title'] ), array( 'response' => $code ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escapováno při sestavení
	};

	if ( ! $valid ) {
		$page( '<p>' . esc_html( $t['cancel_invalid'] ) . '</p>', 404 );
		return;
	}
	$event = ekc_reservation_event( (int) $row['event_id'] );
	$past  = ! $event || ! $event['timestamp'] || $event['timestamp'] <= time();

	if ( $post ) {
		if ( 'confirmed' === $row['status'] && ! $past && ekc_reservation_cancel( $id, 'client' ) ) {
			$fresh = ekc_reservation_event( (int) $row['event_id'] );
			ekc_reservation_mail_admin( ekc_reservation_get( $id ) ?? $row, $fresh ? $fresh : $event, 'cancel' );
		}
		wp_safe_redirect( ekc_reservation_cancel_url( $row ), 303 );
		exit;
	}

	if ( 'cancelled' === $row['status'] ) {
		$page( '<p>' . esc_html( $t['cancel_done'] ) . '</p>' );
	} elseif ( $past ) {
		$page( '<p>' . esc_html( $t['cancel_past'] ) . '</p>' );
	} else {
		$label = $event['label'] . ', ' . $event['day'] . ', ' . $event['time'];
		$page(
			'<p>' . esc_html( sprintf( $t['cancel_ask'], $label ) ) . '</p>'
			. '<form method="post" action="' . esc_url( ekc_reservation_cancel_url( $row ) ) . '"><p><button type="submit" class="button" name="ekc_res_confirm" value="1">' . esc_html( $t['cancel_button'] ) . '</button></p></form>'
		);
	}
}

/* -------------------------------------------------------------------------
 * Mazání osobních údajů po termínu (cron a Údržba webu)
 * ---------------------------------------------------------------------- */

/**
 * Část SQL pro přihlášky starší než reservation_retention dní po termínu (a bez termínu).
 *
 * @return array{0:string,1:string,2:string} FROM, WHERE, hranice (Y-m-d H:i)
 */
function ekc_reservations_expired_sql(): array {
	global $wpdb;
	$table = ekc_reservations_table();
	$limit = wp_date( 'Y-m-d H:i', time() - max( 1, (int) ekc_opt( 'reservation_retention' ) ) * DAY_IN_SECONDS );
	return array(
		"FROM {$table} r LEFT JOIN {$wpdb->posts} p ON p.ID = r.event_id LEFT JOIN {$wpdb->postmeta} m ON m.post_id = r.event_id AND m.meta_key = '_ekc_start'",
		"( p.ID IS NULL OR ( m.meta_value <> '' AND m.meta_value < %s ) )",
		(string) $limit,
	);
}

/**
 * Počet přihlášek ke smazání.
 *
 * @return int
 */
function ekc_reservations_expired(): int {
	if ( ! ekc_reservations_ready() ) {
		return 0;
	}
	global $wpdb;
	list( $from, $where, $limit ) = ekc_reservations_expired_sql();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- sestaveno z pevných částí
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT r.id) {$from} WHERE {$where}", $limit ) );
}

/**
 * Smaže přihlášky starší než reservation_retention dní po termínu a přihlášky smazaných termínů.
 *
 * @return int Počet smazaných.
 */
function ekc_reservations_cleanup(): int {
	if ( ! ekc_reservations_ready() ) {
		return 0;
	}
	global $wpdb;
	list( $from, $where, $limit ) = ekc_reservations_expired_sql();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- sestaveno z pevných částí
	return max( 0, (int) $wpdb->query( $wpdb->prepare( "DELETE r {$from} WHERE {$where}", $limit ) ) );
}

add_action( 'ekc_reservations_cleanup', 'ekc_reservations_cleanup' );
add_action(
	'init',
	static function () {
		if ( ! wp_next_scheduled( 'ekc_reservations_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'ekc_reservations_cleanup' );
		}
	}
);

/**
 * Deaktivace pluginu: zrušit denní úlohu.
 */
function ekc_reservations_deactivate(): void {
	wp_clear_scheduled_hook( 'ekc_reservations_cleanup' );
}
register_deactivation_hook( EKC_FILE, 'ekc_reservations_deactivate' );

/* -------------------------------------------------------------------------
 * Údržba webu a Zdraví webu
 * ---------------------------------------------------------------------- */

ekc_maintenance_section(
	'reservations',
	array(
		'title'  => 'Rezervace',
		'order'  => 46,
		'render' => 'ekc_reservation_maint_render',
		'tasks'  => array(
			'cleanup' => static function (): array {
				$deleted = ekc_reservations_cleanup();
				return array(
					'message' => $deleted
						? ekc_plural( $deleted, 'Smazána %d stará přihláška.', 'Smazány %d staré přihlášky.', 'Smazáno %d starých přihlášek.' )
						: 'Žádné staré přihlášky ke smazání.',
					'type'    => 'success',
				);
			},
		),
	)
);

/**
 * Sekce v Údržbě webu: počty a mazání starých přihlášek.
 *
 * @param string $id ID sekce.
 */
function ekc_reservation_maint_render( string $id ): void {
	if ( ! ekc_reservations_ready() ) {
		echo '<p>' . ekc_status_dot( 'warn' ) . ' Tabulka přihlášek zatím neexistuje, vytvoří se při příštím načtení administrace.</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
		return;
	}
	global $wpdb;
	$table    = ekc_reservations_table();
	$counts   = $wpdb->get_results( "SELECT status, COUNT(*) AS n FROM {$table} GROUP BY status", OBJECT_K ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$ok       = (int) ( $counts['confirmed']->n ?? 0 );
	$no       = (int) ( $counts['cancelled']->n ?? 0 );
	$upcoming = count( ekc_reservation_events( array( 'limit' => EKC_RESERVATION_MAX_EVENTS ) ) );
	$expired  = ekc_reservations_expired();
	$days     = (int) ekc_opt( 'reservation_retention' );
	$next     = wp_next_scheduled( 'ekc_reservations_cleanup' );

	echo '<p>Nadcházející termíny: <a href="' . esc_url( admin_url( 'edit.php?post_type=ekc_event&ekc_when=upcoming' ) ) . '">' . (int) $upcoming . '</a>. '
		. 'Přihlášky v databázi: ' . (int) ( $ok + $no ) . ' (potvrzené ' . (int) $ok . ', zrušené ' . (int) $no . ').</p>';
	echo '<p>' . esc_html(
		'Přihlášky se mažou ' . ekc_plural( $days, '%d den', '%d dny', '%d dní' ) . ' po termínu, automaticky jednou denně'
		. ( $next ? ' (příště ' . ekc_datetime( (int) $next ) . ')' : '' ) . '. Teď ke smazání: ' . $expired . '.'
	) . '</p>';
	if ( $expired ) {
		ekc_maintenance_button(
			$id,
			'cleanup',
			'Smazat staré přihlášky',
			ekc_plural( $expired, 'Smazat natrvalo %d přihlášku', 'Smazat natrvalo %d přihlášky', 'Smazat natrvalo %d přihlášek' ) . ' starší než ' . $days . ' dní po termínu? Tuto akci nejde vrátit.'
		);
	}
}

add_filter(
	'ekc_health_rows',
	static function ( array $rows ): array {
		if ( ! ekc_reservations_ready() ) {
			return $rows;
		}
		$events = ekc_reservation_events( array( 'limit' => EKC_RESERVATION_MAX_EVENTS ) );
		if ( $events ) {
			$e = $events[0];
			$rows['Rezervace']['Nejbližší termín'] = '<a href="' . esc_url( admin_url( 'post.php?post=' . (int) $e['id'] . '&action=edit' ) ) . '">' . esc_html( $e['label'] . ', ' . $e['date'] ) . '</a> · ' . esc_html( $e['booked'] . ' / ' . $e['capacity'] );
			$rows['Rezervace']['Obsazenost nadcházejících'] = '<a href="' . esc_url( admin_url( 'edit.php?post_type=ekc_event&ekc_when=upcoming' ) ) . '">' . esc_html( ekc_plural( count( $events ), '%d termín', '%d termíny', '%d termínů' ) ) . '</a>: '
				. esc_html( array_sum( array_column( $events, 'booked' ) ) . ' z ' . array_sum( array_column( $events, 'capacity' ) ) . ' míst' );
		} else {
			$rows['Rezervace']['Nejbližší termín'] = '<a href="' . esc_url( admin_url( 'post-new.php?post_type=ekc_event' ) ) . '">žádný vypsaný</a>';
		}
		global $wpdb;
		$table = ekc_reservations_table();
		$since = gmdate( 'Y-m-d H:i:s', time() - WEEK_IN_SECONDS );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- název tabulky z prefixu
		$new       = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = 'confirmed' AND created_at >= %s", $since ) );
		$cancelled = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = 'cancelled' AND cancelled_at >= %s", $since ) );
		// phpcs:enable
		$rows['Rezervace']['Nové přihlášky za 7 dní'] = esc_html( (string) $new . ( $cancelled ? ' (zrušené ' . $cancelled . ')' : '' ) );
		return $rows;
	}
);
