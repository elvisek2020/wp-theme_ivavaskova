<?php
/**
 * Odinstalace pluginu: smaže nastavení a data pluginu (options ekc_*, transienty, user meta ekc_*,
 * příspěvky typů ekc_event, ekc_testimonial a ekc_message – termíny, recenze a zprávy z webu –
 * včetně jejich meta, postmeta a comment meta _ekc_*, vlastní tabulky {prefix}ekc_*, např.
 * ekc_pageviews a ekc_reservations). Bez příspěvků by po nové instalaci zůstala poloviční data
 * (termíny bez začátku a kapacity, recenze bez kontextu, texty zpráv bez odesílatele).
 *
 * Karanténa se NEMAŽE: složka SLUG-karantena (mimo web nebo ve wp-content) a tabulky {prefix}ekq_*
 * zůstávají, aby o nic nepřišel ten, kdo plugin odinstaluje omylem. Smazat je jde předtím
 * v Nástroje → Údržba webu → Karanténa, nebo ručně.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$ekc_like = static fn( string $prefix ): string => $wpdb->esc_like( $prefix ) . '%';

// Options včetně transientů (_transient_ekc_*, _transient_timeout_ekc_*) a site transientů.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
		$ekc_like( 'ekc_' ),
		$ekc_like( '_transient_ekc_' ),
		$ekc_like( '_transient_timeout_ekc_' ),
		$ekc_like( '_site_transient_ekc_' ),
		$ekc_like( '_site_transient_timeout_ekc_' )
	)
);
if ( is_multisite() ) {
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s OR meta_key LIKE %s",
			$ekc_like( '_site_transient_ekc_' ),
			$ekc_like( '_site_transient_timeout_ekc_' )
		)
	);
}

// Příspěvky vlastních typů obsahu (typy už nejsou registrované, wp_delete_post je smaže i tak, včetně meta a revizí).
foreach ( (array) $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ('ekc_event','ekc_testimonial','ekc_message')" ) as $ekc_id ) {
	wp_delete_post( (int) $ekc_id, true );
}

// User meta (ekc_last_login, …), postmeta (_ekc_webp_fail, …) a comment meta (_ekc_notify, …).
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", $ekc_like( 'ekc_' ) ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $ekc_like( '_ekc_' ) ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->commentmeta} WHERE meta_key LIKE %s", $ekc_like( '_ekc_' ) ) );

// Vlastní tabulky modulů ({prefix}ekc_*, např. ekc_pageviews). Karanténní {prefix}ekq_* zůstávají.
foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $ekc_like( $wpdb->prefix . 'ekc_' ) ) ) as $ekc_table ) {
	$wpdb->query( 'DROP TABLE IF EXISTS `' . str_replace( '`', '', (string) $ekc_table ) . '`' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- název z SHOW TABLES
}

wp_cache_flush();
