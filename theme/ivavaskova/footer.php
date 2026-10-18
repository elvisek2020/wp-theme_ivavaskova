<?php
/**
 * Patička: sociální sítě, menu v patičce, copyright, nastavení cookies.
 */

defined( 'ABSPATH' ) || exit;

$iv_first = (int) get_the_date( 'Y', get_posts( array( 'numberposts' => 1, 'order' => 'ASC', 'fields' => 'ids' ) )[0] ?? 0 );
$iv_years = $iv_first && $iv_first < (int) gmdate( 'Y' ) ? $iv_first . '–' . gmdate( 'Y' ) : gmdate( 'Y' );
$iv_text  = trim( (string) get_theme_mod( 'iv_footer_text' ) );
?>
</main>

<footer class="iv-footer">
	<div class="iv-wrap">
		<?php iv_social_links( 'iv-social iv-social-footer' ); ?>
		<?php
		wp_nav_menu( array(
			'theme_location' => 'footer',
			'container'      => false,
			'menu_class'     => 'iv-footer-menu',
			'depth'          => 1,
			'fallback_cb'    => false,
		) );
		?>
		<p class="iv-copy">
			<?php echo esc_html( $iv_text ? $iv_text : '© ' . $iv_years . ' ' . get_bloginfo( 'name' ) ); ?>
			<?php
			$iv_extra = array_filter( array( iv_consent_link(), is_user_logged_in() ? '' : '<a href="' . esc_url( wp_login_url() ) . '" rel="nofollow">Administrace</a>' ) );
			if ( $iv_extra ) {
				echo ' · ' . implode( ' · ', $iv_extra ); // phpcs:ignore WordPress.Security.EscapeOutput -- escapováno výše
			}
			?>
		</p>
	</div>
</footer>

<div class="iv-lightbox" id="iv-lightbox" hidden role="dialog" aria-modal="true" aria-label="Fotografie">
	<button type="button" class="iv-lb-close" aria-label="Zavřít"><?php echo iv_icon( 'close', 28 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
	<button type="button" class="iv-lb-prev" aria-label="Předchozí"><?php echo iv_icon( 'arrow-l', 28 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
	<figure><img alt=""><figcaption></figcaption></figure>
	<button type="button" class="iv-lb-next" aria-label="Další"><?php echo iv_icon( 'arrow', 28 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
</div>

<?php wp_footer(); ?>
</body>
</html>
