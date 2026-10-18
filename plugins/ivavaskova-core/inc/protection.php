<?php
/**
 * Ochrana obsahu proti kopírování (nahrazuje rcd-right-click-disabler).
 * Pro nepřihlášené návštěvníky: bez kontextového menu, kopírování, výběru textu a přetahování obrázků.
 * Přihlášení (správa webu) a formulářová pole nejsou omezeni. Zapíná se v Nastavení → Obecné.
 */

defined( 'ABSPATH' ) || exit;

const IV_PROTECT_OPTION = 'iv_protect_content';

function iv_protect_enabled(): bool {
	return '1' === (string) get_option( IV_PROTECT_OPTION, '1' ) && ! is_user_logged_in();
}

add_action( 'admin_init', function () {
	register_setting( 'general', IV_PROTECT_OPTION, array(
		'type'              => 'string',
		'default'           => '1',
		'sanitize_callback' => function ( $v ) {
			return $v ? '1' : '0';
		},
	) );
	add_settings_field( IV_PROTECT_OPTION, 'Ochrana obsahu', function () {
		printf(
			'<label><input type="checkbox" name="%1$s" value="1" %2$s> Zakázat návštěvníkům pravé tlačítko, kopírování, výběr textu a stahování obrázků přetažením</label><p class="description">Přihlášených uživatelů se netýká. Odradí běžné kopírování, ne zkušeného uživatele.</p>',
			esc_attr( IV_PROTECT_OPTION ),
			checked( '1', (string) get_option( IV_PROTECT_OPTION, '1' ), false )
		);
	}, 'general' );
} );

add_action( 'wp_head', function () {
	if ( ! iv_protect_enabled() ) {
		return;
	}
	echo '<style id="iv-protect">body{-webkit-user-select:none;user-select:none;-webkit-touch-callout:none}input,textarea,select,[contenteditable]{-webkit-user-select:text;user-select:text}img{-webkit-user-drag:none}</style>' . "\n";
} );

add_action( 'wp_footer', function () {
	if ( ! iv_protect_enabled() ) {
		return;
	}
	?>
<script id="iv-protect-js">
(function () {
	var editable = function (el) { return el && el.closest && el.closest('input,textarea,select,[contenteditable]'); };
	['contextmenu', 'copy', 'cut', 'selectstart'].forEach(function (type) {
		document.addEventListener(type, function (e) { if (!editable(e.target)) { e.preventDefault(); } });
	});
	document.addEventListener('dragstart', function (e) { if (e.target && e.target.tagName === 'IMG') { e.preventDefault(); } });
})();
</script>
	<?php
}, 99 );
