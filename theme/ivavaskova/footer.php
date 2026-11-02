<?php
/**
 * Patička: sociální sítě (modul social), menu v patičce, řádek copyright · autor webu · Nastavení cookies
 * · Administrace (common + analytics) a přepínač Systém / Světlý / Tmavý (modul color-scheme).
 * Texty a odkazy v Přizpůsobit → Iva Vašková → Patička a Sociální sítě.
 */

defined( 'ABSPATH' ) || exit;
?>
</main>

<footer class="iv-footer">
	<div class="iv-wrap">
		<?php
		if ( function_exists( 'ekt_social_links' ) ) {
			echo ekt_social_links( 'iv-social' ); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML z modulu
		}

		wp_nav_menu(
			array(
				'theme_location' => 'footer',
				'container'      => false,
				'menu_class'     => 'iv-footer-menu',
				'depth'          => 1,
				'fallback_cb'    => false,
			)
		);

		echo ekt_site_info( 'iv-copy' ); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML z common

		if ( function_exists( 'ekt_color_scheme_switch' ) ) {
			echo ekt_color_scheme_switch(); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML z modulu
		}
		?>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
