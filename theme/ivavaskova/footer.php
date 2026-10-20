<?php
/**
 * Patička: sociální sítě, menu v patičce, copyright, nastavení cookies.
 */

defined( 'ABSPATH' ) || exit;

$iv_first = (int) get_the_date( 'Y', get_posts( array( 'numberposts' => 1, 'order' => 'ASC', 'fields' => 'ids' ) )[0] ?? 0 );
$iv_years = $iv_first && $iv_first < (int) gmdate( 'Y' ) ? $iv_first . '–' . gmdate( 'Y' ) : gmdate( 'Y' );
$iv_text  = trim( (string) get_theme_mod( 'iv_footer_text' ) );
$iv_owner = trim( (string) get_theme_mod( 'iv_copyright_name' ) );
$iv_cname = trim( (string) get_theme_mod( 'iv_credit_name' ) );
$iv_curl  = trim( (string) get_theme_mod( 'iv_credit_url' ) );
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
			<?php
			// Jeden řádek: copyright · autor webu · cookies · administrace.
			$iv_parts   = array();
			$iv_parts[] = $iv_text
				? esc_html( $iv_text )
				: sprintf( 'Copyright ©&nbsp;%s <strong>%s</strong>', esc_html( $iv_years ), esc_html( $iv_owner ? $iv_owner : get_bloginfo( 'name' ) ) );
			if ( $iv_cname ) {
				$iv_parts[] = 'Design &amp; code by ' . ( $iv_curl
					? sprintf( '<a href="%s" target="_blank" rel="noopener">%s</a>', esc_url( $iv_curl ), esc_html( $iv_cname ) )
					: esc_html( $iv_cname ) );
			}
			$iv_parts[] = iv_consent_link();
			$iv_parts[] = is_user_logged_in() ? '' : '<a href="' . esc_url( admin_url() ) . '" rel="nofollow">Administrace</a>';
			echo '<span>' . implode( '</span> · <span>', array_filter( $iv_parts ) ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escapováno výše
			?>
		</p>
		<div class="iv-theme-switch" role="group" aria-label="Vzhled webu">
			<button type="button" data-iv-theme-set="auto" aria-pressed="true">Systém</button>
			<button type="button" data-iv-theme-set="light" aria-pressed="false"><?php echo iv_icon( 'sun', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?> Světlý</button>
			<button type="button" data-iv-theme-set="dark" aria-pressed="false"><?php echo iv_icon( 'moon', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?> Tmavý</button>
		</div>
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
