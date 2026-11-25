<?php
/**
 * Bublina „Raději se nejdřív zeptat?“ s e-mailem a telefonem (Přizpůsobit → Patička) a avatarem,
 * v panelu rezervace nad rozhovorem.
 * Bez kontaktů se nevypíše nic.
 */

defined( 'ABSPATH' ) || exit;

$ivb_email = (string) ekt_opt( 'site_email' );
$ivb_phone = (string) ekt_opt( 'site_phone' );
if ( '' === $ivb_email && '' === $ivb_phone ) {
	return;
}
?>
<aside class="ask-row">
	<div class="ask-ava">
		<?php echo ivb_avatar(); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML z WordPressu ?>
		<span class="hand"><?php bloginfo( 'name' ); ?></span>
	</div>
	<div class="bub ask tail t-left">
		<div class="ask__text">
			<h3>Raději se nejdřív zeptat?</h3>
			<p>Na cokoli k lekcím se dá zeptat e-mailem nebo telefonem.</p>
		</div>
		<ul>
			<?php if ( $ivb_email ) : ?>
				<li><?php echo ivb_icon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput -- statické SVG ?><a href="mailto:<?php echo esc_attr( antispambot( $ivb_email ) ); ?>"><?php echo esc_html( antispambot( $ivb_email ) ); ?></a></li>
			<?php endif; ?>
			<?php if ( $ivb_phone ) : ?>
				<li><?php echo ivb_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput -- statické SVG ?><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $ivb_phone ) ); ?>"><?php echo esc_html( $ivb_phone ); ?></a></li>
			<?php endif; ?>
		</ul>
	</div>
</aside>
