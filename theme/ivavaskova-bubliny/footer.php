<?php
/**
 * Patička: konec <main>, kontakt a sítě, odkazy, odběr novinek, řádek copyright · autor · cookies ·
 * administrace, plovoucí tlačítko Rezervovat a SVG sprite (sluníčko, hvězdy).
 */

defined( 'ABSPATH' ) || exit;

$ivb_email   = (string) ekt_opt( 'site_email' );
$ivb_phone   = (string) ekt_opt( 'site_phone' );
$ivb_news    = (string) ekt_opt( 'site_news_url' );
$ivb_booking = ivb_booking_url();
?>
</main>

<footer class="foot">
	<div class="wrap">
		<div class="foot-grid<?php echo $ivb_news ? '' : ' foot-grid--2'; ?>">
			<div>
				<a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">
					<?php echo ivb_sun(); // phpcs:ignore WordPress.Security.EscapeOutput -- statické SVG ?>
					<b><?php bloginfo( 'name' ); ?></b>
				</a>
				<?php if ( $ivb_email || $ivb_phone ) : ?>
					<ul class="foot-contact">
						<?php if ( $ivb_email ) : ?>
							<li><a href="mailto:<?php echo esc_attr( antispambot( $ivb_email ) ); ?>"><?php echo esc_html( antispambot( $ivb_email ) ); ?></a></li>
						<?php endif; ?>
						<?php if ( $ivb_phone ) : ?>
							<li><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $ivb_phone ) ); ?>"><?php echo esc_html( $ivb_phone ); ?></a></li>
						<?php endif; ?>
					</ul>
				<?php endif; ?>
				<?php
				if ( function_exists( 'ekt_social_links' ) ) {
					echo ekt_social_links( 'foot-social' ); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML z modulu
				}
				?>
			</div>
			<div>
				<h3>Odkazy</h3>
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'footer',
						'container'      => false,
						'menu_class'     => 'foot-menu',
						'depth'          => 1,
						'fallback_cb'    => 'ivb_footer_links_fallback',
					)
				);
				?>
			</div>
			<?php if ( $ivb_news ) : ?>
				<div>
					<div class="bub news">
						<h3>Novinky e-mailem</h3>
						<p><?php echo esc_html( (string) ekt_opt( 'site_news_text' ) ); ?></p>
						<a class="btn" href="<?php echo esc_url( $ivb_news ); ?>">Odebírat novinky</a>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<div class="foot-bottom">
			<?php
			if ( function_exists( 'ekt_site_info' ) ) {
				echo ekt_site_info( 'ekt-site-info' ); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML z common
			}
			?>
		</div>
	</div>
</footer>

<?php if ( $ivb_booking && ! is_page_template( 'page-rezervace.php' ) ) : ?>
	<a class="fab" href="<?php echo esc_url( $ivb_booking ); ?>" data-ivb-fab aria-label="Rezervovat lekci">
		<span class="lab">Rezervovat</span>
		<span class="dot"><?php echo ivb_sun(); // phpcs:ignore WordPress.Security.EscapeOutput -- statické SVG ?></span>
	</a>
<?php endif; ?>

<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">
	<defs>
		<?php // Zástupné logo – sluníčko; finální logo do hlavičky nahraje Iva v Přizpůsobit → Obecné → Logo. ?>
		<symbol id="ivb-sun" viewBox="0 0 100 100">
			<circle cx="50" cy="50" r="17" fill="none" stroke="currentColor" stroke-width="3"/>
			<circle cx="50" cy="50" r="7" fill="currentColor" opacity=".55"/>
			<circle cx="50" cy="50" r="31" fill="none" stroke="currentColor" stroke-width="14" stroke-dasharray="2.2 14.03"/>
			<circle cx="50" cy="50" r="28" fill="none" stroke="currentColor" stroke-width="8" stroke-dasharray="2 15.59" stroke-dashoffset="-8.8"/>
			<circle cx="50" cy="50" r="45" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-dasharray="0 11.78"/>
		</symbol>
		<symbol id="ivb-star" viewBox="0 0 24 24"><path d="M12 0C13 8 16 11 24 12 16 13 13 16 12 24 11 16 8 13 0 12 8 11 11 8 12 0Z" fill="currentColor"/></symbol>
		<symbol id="ivb-dot" viewBox="0 0 10 10"><circle cx="5" cy="5" r="4" fill="currentColor"/></symbol>
	</defs>
</svg>
<?php wp_footer(); ?>
</body>
</html>
