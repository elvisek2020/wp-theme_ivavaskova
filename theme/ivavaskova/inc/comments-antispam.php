<?php
/**
 * Antispam komentářů bez externích služeb (nahrazuje reCAPTCHA v komentářích):
 *  - skryté pole (honeypot), které člověk nevyplní
 *  - podepsané časové razítko: odeslání dřív než za 3 s nebo po 2 dnech = robot / starý formulář
 */

defined( 'ABSPATH' ) || exit;

const IV_AS_MIN_SECONDS = 3;
const IV_AS_MAX_SECONDS = 2 * DAY_IN_SECONDS;

add_action( 'comment_form_after_fields', 'iv_antispam_fields' );
add_action( 'comment_form_logged_in_after', 'iv_antispam_fields' );

function iv_antispam_fields(): void {
	$t = time();
	printf(
		'<p class="iv-hp" aria-hidden="true"><label>Nevyplňujte <input type="text" name="iv_website" value="" tabindex="-1" autocomplete="off"></label></p><input type="hidden" name="iv_ts" value="%1$d"><input type="hidden" name="iv_sig" value="%2$s">',
		$t,
		esc_attr( wp_hash( 'iv_comment_' . $t ) )
	);
}

add_filter( 'preprocess_comment', function ( $data ) {
	// Přihlášení uživatelé, pingbacky a trackbacky se nekontrolují.
	if ( is_user_logged_in() || ( isset( $data['comment_type'] ) && in_array( $data['comment_type'], array( 'pingback', 'trackback' ), true ) ) ) {
		return $data;
	}
	$ts  = (int) ( $_POST['iv_ts'] ?? 0 );
	$sig = (string) ( $_POST['iv_sig'] ?? '' );
	$age = time() - $ts;
	$bad = ! empty( $_POST['iv_website'] )
		|| ! $ts
		|| ! hash_equals( wp_hash( 'iv_comment_' . $ts ), $sig )
		|| $age < IV_AS_MIN_SECONDS
		|| $age > IV_AS_MAX_SECONDS;
	if ( $bad ) {
		wp_die( 'Komentář se nepodařilo odeslat. Zkuste to prosím znovu, případně obnovte stránku.', 'Komentář neodeslán', array( 'response' => 403, 'back_link' => true ) );
	}
	return $data;
} );
